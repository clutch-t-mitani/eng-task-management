<?php

namespace App\Http\Controllers;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Http\Resources\SyncLogResource;
use App\Models\ProductRepository;
use App\Models\SyncLog;
use App\Services\GitHubIssueImportService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class GitHubWebhookController extends Controller
{
    private const SUPPORTED_ACTIONS = ['opened', 'edited', 'reopened', 'closed'];

    public function __construct(private readonly GitHubIssueImportService $importer) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Step 1: 署名検証（fail-closed）
        $rawBody = $request->getContent();
        $signatureHeader = $request->header('X-Hub-Signature-256', '');

        if (! $this->verifySignature($rawBody, $signatureHeader)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Step 2: ヘッダ・JSON検証
        $deliveryId = $request->header('X-GitHub-Delivery', '');
        $event = $request->header('X-GitHub-Event', '');

        if (empty($deliveryId) || mb_strlen($deliveryId) > 100) {
            return response()->json(['message' => 'X-GitHub-Delivery header is invalid.'], 422);
        }

        if (empty($event)) {
            return response()->json(['message' => 'X-GitHub-Event header is required.'], 422);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON payload.'], 422);
        }

        // Step 3: delivery重複排除
        $existingLog = SyncLog::query()
            ->where('github_delivery_id', $deliveryId)
            ->first();

        if ($existingLog !== null) {
            if (in_array($existingLog->status, [SyncStatus::Success, SyncStatus::Skipped], true)) {
                return response()->json(SyncLogResource::make($existingLog)->resolve());
            }

            // status=failed → 行ロックして再試行
            return $this->retryFailedDelivery($existingLog, $deliveryId, $event, $payload);
        }

        return $this->processNewDelivery($deliveryId, $event, $payload);
    }

    private function retryFailedDelivery(SyncLog $existingLog, string $deliveryId, string $event, array $payload): JsonResponse
    {
        $lockWait = (int) config('services.github.webhook_lock_ttl', 2);
        // TTLは待機時間ではなく処理全体（Issue反映＋SyncLog確定）を守る長さにする
        $lockTtl = (int) config('services.github.webhook_lock_hold_ttl', 30);
        $lock = Cache::lock('github-webhook:delivery:'.$deliveryId, $lockTtl);

        try {
            $lock->block($lockWait);
        } catch (LockTimeoutException) {
            $this->confirmFailedLog($existingLog);

            return response()->json(['message' => 'Processing conflict, please retry.'], 500);
        }

        try {
            $syncLog = null;
            DB::transaction(function () use ($existingLog, &$syncLog): void {
                $locked = SyncLog::query()->lockForUpdate()->find($existingLog->id);
                if ($locked === null) {
                    return;
                }
                if (! in_array($locked->status, [SyncStatus::Failed], true)) {
                    $syncLog = $locked;

                    return;
                }
                $locked->increment('attempt_count');
                $locked->update(['started_at' => now(), 'finished_at' => null]);
                $syncLog = $locked->refresh();
            });

            if ($syncLog === null) {
                return response()->json(['message' => 'Processing conflict, please retry.'], 500);
            }

            if ($syncLog->status !== SyncStatus::Failed) {
                return response()->json(SyncLogResource::make($syncLog->refresh())->resolve());
            }

            return $this->handleDelivery($syncLog, $event, $payload);
        } finally {
            $lock->release();
        }
    }

    private function processNewDelivery(string $deliveryId, string $event, array $payload): JsonResponse
    {
        try {
            $syncLog = SyncLog::query()->create([
                'trigger' => SyncTrigger::Webhook,
                'github_delivery_id' => $deliveryId,
                'attempt_count' => 1,
                'status' => SyncStatus::Running,
                'started_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // 同一deliveryの同時受信で先行処理が行を作成済み → 確定済みstatusで応答
            $existing = SyncLog::query()
                ->where('github_delivery_id', $deliveryId)
                ->first();

            if ($existing !== null && in_array($existing->status, [SyncStatus::Success, SyncStatus::Skipped], true)) {
                return response()->json(SyncLogResource::make($existing)->resolve());
            }

            return response()->json(['message' => 'Processing conflict, please retry.'], 500);
        }

        return $this->handleDelivery($syncLog, $event, $payload);
    }

    private function handleDelivery(SyncLog $syncLog, string $event, array $payload): JsonResponse
    {
        // Step 4: イベントフィルタ
        if ($event !== 'issues') {
            return $this->skip($syncLog, 'unsupported_event');
        }

        $action = $payload['action'] ?? '';

        // Step 5: アクションフィルタ（transferred含む）
        if (! in_array($action, self::SUPPORTED_ACTIONS, true)) {
            return $this->skip($syncLog, 'unsupported_action');
        }

        $issue = $payload['issue'] ?? [];

        // Step 6: PR除外
        if (isset($issue['pull_request'])) {
            return $this->skip($syncLog, 'pull_request', skippedCount: 1);
        }

        // Step 7: リポジトリ解決
        $owner = strtolower($payload['repository']['owner']['login'] ?? '');
        $repo = strtolower($payload['repository']['name'] ?? '');

        $productRepo = ProductRepository::query()
            ->where('owner', $owner)
            ->where('repo', $repo)
            ->where('is_active', true)
            ->first();

        if ($productRepo === null) {
            return $this->skipWithNullProduct($syncLog, 'repository_not_registered');
        }

        // Step 8: payload必須項目検証
        $number = $issue['number'] ?? null;
        $title = $issue['title'] ?? null;
        $htmlUrl = $issue['html_url'] ?? null;
        $state = $issue['state'] ?? null;

        if (! is_int($number) || empty($title) || empty($htmlUrl) || ! in_array($state, ['open', 'closed'], true)) {
            return $this->failWithLog($syncLog, $productRepo->product_id, 'invalid_issue_payload', 422);
        }

        // Step 9 & 10: Issue反映 → 成功確定
        try {
            $importResult = $this->importer->import($productRepo->product_id, [$issue], isWebhook: true);

            DB::transaction(function () use ($syncLog, $productRepo, $importResult): void {
                $status = $importResult->failed > 0 ? SyncStatus::Failed : SyncStatus::Success;

                if ($importResult->skipped > 0 && $importResult->created === 0 && $importResult->updated === 0 && $importResult->failed === 0) {
                    $status = SyncStatus::Skipped;
                }

                $syncLog->update([
                    'product_id' => $productRepo->product_id,
                    'status' => $status,
                    'created_count' => $importResult->created,
                    'updated_count' => $importResult->updated,
                    'skipped_count' => $importResult->skipped,
                    'failed_count' => $importResult->failed,
                    'error_message' => $importResult->firstError,
                    'finished_at' => now(),
                ]);

                if ($importResult->created > 0 || $importResult->updated > 0) {
                    $productRepo->update([
                        'last_synced_at' => now(),
                        'last_sync_status' => SyncStatus::Success,
                    ]);
                }
            });

            $syncLog->refresh();

            if ($syncLog->status === SyncStatus::Skipped) {
                return response()->json(SyncLogResource::make($syncLog)->resolve(), 202);
            }

            if ($syncLog->status === SyncStatus::Failed) {
                // 500を返してGitHubの再送に委ねる
                return response()->json(['message' => $syncLog->error_message ?? 'processing_failed'], 500);
            }

            return response()->json(SyncLogResource::make($syncLog)->resolve());
        } catch (Throwable) {
            // Step 11: 失敗確定（別transaction）
            $this->confirmFailedLog($syncLog, $productRepo->product_id);

            return response()->json(['message' => 'Server Error'], 500);
        }
    }

    private function skip(SyncLog $syncLog, string $reason, int $skippedCount = 0): JsonResponse
    {
        $syncLog->update([
            'status' => SyncStatus::Skipped,
            'skipped_count' => $skippedCount,
            'error_message' => $reason,
            'finished_at' => now(),
        ]);

        return response()->json(SyncLogResource::make($syncLog->refresh())->resolve(), 202);
    }

    private function skipWithNullProduct(SyncLog $syncLog, string $reason): JsonResponse
    {
        $syncLog->update([
            'product_id' => null,
            'status' => SyncStatus::Skipped,
            'error_message' => $reason,
            'finished_at' => now(),
        ]);

        return response()->json(SyncLogResource::make($syncLog->refresh())->resolve(), 202);
    }

    private function failWithLog(SyncLog $syncLog, int $productId, string $reason, int $httpStatus = 500): JsonResponse
    {
        $syncLog->update([
            'product_id' => $productId,
            'status' => SyncStatus::Failed,
            'error_message' => $reason,
            'finished_at' => now(),
        ]);

        return response()->json(['message' => $reason], $httpStatus);
    }

    private function confirmFailedLog(SyncLog $syncLog, ?int $productId = null): void
    {
        try {
            // attempt_countは行作成時(=1)または再試行transaction内で加算済みのため、ここでは加算しない
            DB::transaction(function () use ($syncLog, $productId): void {
                $syncLog->update([
                    'product_id' => $productId ?? $syncLog->product_id,
                    'status' => SyncStatus::Failed,
                    'error_message' => 'processing_failed',
                    'finished_at' => now(),
                ]);
            });
        } catch (Throwable) {
            // ログ確定の失敗はサイレントに記録のみ
        }
    }

    private function verifySignature(string $rawPayload, string $providedSignature): bool
    {
        $secret = config('services.github.webhook_secret', '');

        if (empty($secret)) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $providedSignature);
    }
}
