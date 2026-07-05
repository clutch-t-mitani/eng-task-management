<?php

namespace App\Services;

use App\Enums\GitHubIssueState;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\IssueSchedule;
use App\Services\Dto\ImportResult;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class GitHubIssueImportService
{
    /**
     * @param  array<int, array<string, mixed>>  $payloads
     */
    public function import(int $productId, array $payloads, bool $isWebhook = false): ImportResult
    {
        $result = new ImportResult;

        foreach ($payloads as $payload) {
            $this->importOne($productId, $payload, $result, $isWebhook);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function importOne(int $productId, array $payload, ImportResult $result, bool $isWebhook): void
    {
        // PR除外
        if (isset($payload['pull_request'])) {
            $result->skipped++;

            return;
        }

        $number = (int) $payload['number'];
        $state = $payload['state'];
        $title = $payload['title'];
        $htmlUrl = $payload['html_url'];

        try {
            $existing = Issue::query()
                ->where('product_id', $productId)
                ->where('github_issue_number', $number)
                ->first();

            if ($existing === null) {
                if ($state === GitHubIssueState::Closed->value) {
                    $result->skipped++;
                    if ($isWebhook) {
                        $result->firstError ??= 'issue_closed_not_imported';
                    }

                    return;
                }

                $this->createIssue($productId, $number, $title, $htmlUrl, $result);
            } else {
                $this->updateIssue($existing, $title, $htmlUrl, $state, $result);
            }
        } catch (Throwable $e) {
            $result->failed++;
            $result->firstError ??= $this->sanitizeError($e->getMessage());
        }
    }

    private function createIssue(
        int $productId,
        int $number,
        string $title,
        string $htmlUrl,
        ImportResult $result,
    ): void {
        $lockKey = 'github-import:display-order';
        $lockTtl = config('services.github.display_order_lock_ttl', 30);
        $lockWait = config('services.github.display_order_lock_wait', 5);

        $lock = Cache::lock($lockKey, $lockTtl);

        try {
            $lock->block($lockWait);
        } catch (LockTimeoutException) {
            $result->failed++;
            $result->firstError ??= 'display_order_lock_timeout';

            return;
        }

        try {
            DB::transaction(function () use ($productId, $number, $title, $htmlUrl, $result): void {
                $displayOrder = (int) Issue::query()->max('display_order') + 1;

                $issue = Issue::query()->create([
                    'product_id' => $productId,
                    'title' => $title,
                    'github_url' => $htmlUrl,
                    'github_issue_number' => $number,
                    'github_state' => GitHubIssueState::Open->value,
                    'github_synced_at' => now(),
                    'display_order' => $displayOrder,
                    'status_id' => IssueStatus::Todo->value,
                    'is_managed' => false,
                ]);

                IssueSchedule::query()->create(['issue_id' => $issue->id]);

                $result->created++;
            });
        } catch (UniqueConstraintViolationException) {
            $existing = Issue::query()
                ->where('product_id', $productId)
                ->where('github_issue_number', $number)
                ->first();

            if ($existing !== null) {
                $this->updateIssue($existing, $title, $htmlUrl, GitHubIssueState::Open->value, $result);
            } else {
                $result->failed++;
                $result->firstError ??= 'unique_conflict_unresolvable';
            }
        } finally {
            $lock->release();
        }
    }

    private function updateIssue(Issue $issue, string $title, string $htmlUrl, string $state, ImportResult $result): void
    {
        DB::transaction(function () use ($issue, $title, $htmlUrl, $state): void {
            $issue->update([
                'title' => $title,
                'github_url' => $htmlUrl,
                'github_state' => $state,
                'github_synced_at' => now(),
            ]);
        });

        $result->updated++;
    }

    private function sanitizeError(string $message): string
    {
        // 秘匿情報（トークン等）が含まれないよう先頭100文字のみ記録
        return mb_substr($message, 0, 100);
    }
}
