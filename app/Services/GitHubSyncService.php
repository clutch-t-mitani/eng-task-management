<?php

namespace App\Services;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Models\Product;
use App\Models\SyncLog;
use App\Services\Dto\ImportResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class GitHubSyncService
{
    public function __construct(private readonly GitHubIssueImportService $importer) {}

    public function sync(Product $product, int $userId): SyncLog
    {
        $repo = $product->repository;

        if ($repo === null || ! $repo->is_active) {
            throw new UnprocessableEntityHttpException('有効なリポジトリ連携がありません。');
        }

        $lockKey = 'github-sync:product:'.$product->id;
        $lockTtl = (int) config('services.github.sync_lock_ttl', 3600);
        $lock = Cache::lock($lockKey, $lockTtl);

        if (! $lock->get()) {
            throw new ConflictHttpException('この製品の同期が既に実行中です。');
        }

        $syncLog = SyncLog::query()->create([
            'product_id' => $product->id,
            'trigger' => SyncTrigger::ManualResync,
            'triggered_by' => $userId,
            'status' => SyncStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $token = config('services.github.token', '');

            if (empty($token)) {
                $syncLog->update([
                    'status' => SyncStatus::Failed,
                    'error_message' => 'GITHUB_TOKEN 未設定',
                    'finished_at' => now(),
                ]);

                return $syncLog->refresh();
            }

            $total = new ImportResult;
            $owner = $repo->owner;
            $repoName = $repo->repo;
            $maxPages = (int) config('services.github.sync_max_pages', 100);
            $maxSeconds = (int) config('services.github.sync_max_seconds', 120);
            $startedAt = microtime(true);
            $errorMessage = null;
            $limitReached = false;

            for ($page = 1; $page <= $maxPages; $page++) {
                // 残り時間確認
                if ((microtime(true) - $startedAt) >= $maxSeconds) {
                    $elapsed = round(microtime(true) - $startedAt, 1);
                    $errorMessage = "sync_limit_exceeded:page={$page},elapsed={$elapsed}s";
                    $limitReached = true;
                    break;
                }

                $response = Http::withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer '.$token,
                    'X-GitHub-Api-Version' => config('services.github.api_version', '2022-11-28'),
                ])
                    ->timeout((int) config('services.github.http_timeout', 30))
                    ->retry(3, 200, function (Throwable $exception): bool {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }
                        if ($exception instanceof RequestException) {
                            return in_array($exception->response->status(), [500, 502, 503, 504], true);
                        }

                        return false;
                    }, throw: false)
                    ->get("https://api.github.com/repos/{$owner}/{$repoName}/issues", [
                        'state' => 'all',
                        'per_page' => 100,
                        'page' => $page,
                    ]);

                if ($response->failed()) {
                    $errorMessage = $this->buildErrorMessage($response);
                    break;
                }

                $items = $response->json();

                if (! is_array($items)) {
                    $errorMessage = 'invalid_json_response';
                    break;
                }

                if (empty($items)) {
                    break;
                }

                $pageResult = $this->importer->import($product->id, $items);
                $total->merge($pageResult);

                // Linkヘッダに next がなければ最終ページ
                $linkHeader = $response->header('Link', '');
                if (! str_contains($linkHeader, 'rel="next"')) {
                    break;
                }
            }

            // 終了判定
            $hasChanges = $total->created + $total->updated >= 1;
            $firstError = $errorMessage ?? $total->firstError;
            $hasError = $errorMessage !== null || $total->failed > 0 || $limitReached;

            if ($hasChanges && $hasError) {
                $status = SyncStatus::Partial;
            } elseif ($hasChanges) {
                $status = SyncStatus::Success;
            } else {
                $status = SyncStatus::Failed;
            }

            DB::transaction(function () use ($syncLog, $repo, $total, $status, $firstError): void {
                $syncLog->update([
                    'status' => $status,
                    'created_count' => $total->created,
                    'updated_count' => $total->updated,
                    'skipped_count' => $total->skipped,
                    'failed_count' => $total->failed,
                    'error_message' => $firstError,
                    'finished_at' => now(),
                ]);

                if (in_array($status, [SyncStatus::Success, SyncStatus::Partial], true)) {
                    $repo->update([
                        'last_synced_at' => now(),
                        'last_sync_status' => $status,
                    ]);
                }
            });

            return $syncLog->refresh();
        } catch (Throwable $e) {
            try {
                $syncLog->update([
                    'status' => SyncStatus::Failed,
                    'error_message' => mb_substr($e->getMessage(), 0, 100),
                    'finished_at' => now(),
                ]);
            } catch (Throwable) {
                // サイレント
            }

            return $syncLog->refresh();
        } finally {
            $lock->release();
        }
    }

    private function buildErrorMessage(Response $response): string
    {
        $status = $response->status();

        if (in_array($status, [403, 429], true)) {
            $remaining = $response->header('X-RateLimit-Remaining');
            $retryAfter = $response->header('Retry-After');
            $resetAt = $response->header('X-RateLimit-Reset');

            if ($remaining === '0' || $retryAfter !== null) {
                $resetTime = $retryAfter
                    ? date('Y-m-d H:i', time() + (int) $retryAfter)
                    : ($resetAt ? date('Y-m-d H:i', (int) $resetAt) : 'unknown');

                return "rate_limit_exceeded:next_available={$resetTime}";
            }

            return 'github_auth_error:'.$status;
        }

        return match (true) {
            $status === 401 => 'github_auth_error:401',
            $status === 404 => 'repository_not_found',
            $status === 422 => 'github_validation_error',
            default => 'github_api_error:'.$status,
        };
    }
}
