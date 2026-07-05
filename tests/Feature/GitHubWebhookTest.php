<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Models\Issue;
use App\Models\IssueSchedule;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private const DELIVERY_ID = 'abc123-delivery-001';

    private const WEBHOOK_URL = '/api/v1/github/webhook';

    private Product $product;

    private ProductRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.github.webhook_secret' => self::SECRET]);

        $this->product = Product::query()->create(['name' => 'Test Product', 'display_order' => 0]);
        $this->repo = ProductRepository::query()->create([
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'myrepo',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    // --- 署名検証 ---

    public function test_returns_401_when_secret_not_configured(): void
    {
        config(['services.github.webhook_secret' => '']);

        $this->webhookPost($this->issuePayload('opened'))
            ->assertStatus(401);

        $this->assertDatabaseCount('sync_logs', 0);
    }

    public function test_returns_401_on_invalid_signature(): void
    {
        $this->webhookPost($this->issuePayload('opened'), secret: 'wrong-secret')
            ->assertStatus(401);

        $this->assertDatabaseCount('sync_logs', 0);
    }

    // --- ヘッダ・JSON検証 ---

    public function test_returns_422_when_delivery_id_missing(): void
    {
        $this->webhookPost($this->issuePayload('opened'), deliveryId: '')
            ->assertStatus(422);

        $this->assertDatabaseCount('sync_logs', 0);
    }

    public function test_returns_422_when_delivery_id_too_long(): void
    {
        $this->webhookPost($this->issuePayload('opened'), deliveryId: str_repeat('x', 101))
            ->assertStatus(422);

        $this->assertDatabaseCount('sync_logs', 0);
    }

    public function test_returns_422_when_payload_is_not_json(): void
    {
        $this->webhookPostRaw('not-json-at-all', 'issues', self::DELIVERY_ID)
            ->assertStatus(422);

        $this->assertDatabaseCount('sync_logs', 0);
    }

    // --- イベント・アクションフィルタ ---

    public function test_skips_unsupported_event_with_202(): void
    {
        $this->webhookPost($this->issuePayload('opened'), event: 'push')
            ->assertStatus(202);

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'skipped',
            'error_message' => 'unsupported_event',
        ]);
    }

    public function test_skips_unsupported_action_with_202(): void
    {
        $this->webhookPost($this->issuePayload('labeled'))
            ->assertStatus(202);

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'skipped',
            'error_message' => 'unsupported_action',
        ]);
    }

    public function test_skips_transferred_action_with_202(): void
    {
        $this->webhookPost($this->issuePayload('transferred'))
            ->assertStatus(202);

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'skipped',
            'error_message' => 'unsupported_action',
        ]);
    }

    // --- PR除外 ---

    public function test_skips_pull_request_with_202(): void
    {
        $payload = $this->issuePayload('opened');
        $payload['issue']['pull_request'] = ['url' => 'https://github.com/acme/myrepo/pulls/1'];

        $this->webhookPost($payload)
            ->assertStatus(202);

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'skipped',
            'skipped_count' => 1,
            'error_message' => 'pull_request',
        ]);
        $this->assertDatabaseCount('issues', 0);
    }

    // --- リポジトリ解決 ---

    public function test_skips_unregistered_repository_with_202(): void
    {
        $payload = $this->issuePayload('opened');
        $payload['repository']['owner']['login'] = 'other-owner';

        $this->webhookPost($payload)
            ->assertStatus(202);

        $this->assertDatabaseHas('sync_logs', [
            'product_id' => null,
            'status' => 'skipped',
            'error_message' => 'repository_not_registered',
        ]);
    }

    public function test_skips_inactive_repository_with_202(): void
    {
        $this->repo->update(['is_active' => false]);

        $this->webhookPost($this->issuePayload('opened'))
            ->assertStatus(202);

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'skipped',
            'error_message' => 'repository_not_registered',
        ]);
    }

    // --- Issue反映 ---

    public function test_creates_new_issue_for_opened_action(): void
    {
        $this->webhookPost($this->issuePayload('opened'))
            ->assertOk();

        $this->assertDatabaseHas('issues', [
            'product_id' => $this->product->id,
            'github_issue_number' => 42,
            'title' => 'Test Issue',
            'github_state' => 'open',
            'is_managed' => false,
            'status_id' => 1,
        ]);
        $this->assertDatabaseHas('sync_logs', [
            'status' => 'success',
            'created_count' => 1,
            'updated_count' => 0,
        ]);

        // issue_schedules も作成されていること
        $issue = Issue::query()->where('github_issue_number', 42)->first();
        $this->assertNotNull($issue);
        $this->assertDatabaseHas('issue_schedules', ['issue_id' => $issue->id]);
    }

    public function test_skips_new_closed_issue_with_202(): void
    {
        $payload = $this->issuePayload('closed');
        $payload['issue']['state'] = 'closed';

        $this->webhookPost($payload)
            ->assertStatus(202);

        $this->assertDatabaseCount('issues', 0);
        $this->assertDatabaseHas('sync_logs', [
            'status' => 'skipped',
            'skipped_count' => 1,
        ]);
    }

    public function test_updates_existing_issue_github_fields_only(): void
    {
        $issue = Issue::query()->create([
            'product_id' => $this->product->id,
            'title' => 'Old Title',
            'github_url' => 'https://github.com/acme/myrepo/issues/42',
            'github_issue_number' => 42,
            'github_state' => 'open',
            'github_synced_at' => now()->subDay(),
            'display_order' => 1,
            'status_id' => 3,
            'is_managed' => true,
        ]);
        IssueSchedule::query()->create(['issue_id' => $issue->id]);

        $payload = $this->issuePayload('edited');
        $payload['issue']['title'] = 'Updated Title';

        $this->webhookPost($payload, deliveryId: 'delivery-002')
            ->assertOk();

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'title' => 'Updated Title',
            'status_id' => 3,        // 変更されていない
            'is_managed' => true,    // 変更されていない
        ]);
        $this->assertDatabaseHas('sync_logs', [
            'status' => 'success',
            'updated_count' => 1,
            'created_count' => 0,
        ]);
    }

    public function test_updates_github_state_without_changing_tool_status(): void
    {
        $issue = Issue::query()->create([
            'product_id' => $this->product->id,
            'title' => 'An Issue',
            'github_url' => 'https://github.com/acme/myrepo/issues/42',
            'github_issue_number' => 42,
            'github_state' => 'open',
            'github_synced_at' => now()->subDay(),
            'display_order' => 1,
            'status_id' => 2,
            'is_managed' => true,
        ]);
        IssueSchedule::query()->create(['issue_id' => $issue->id]);

        $payload = $this->issuePayload('closed');
        $payload['issue']['state'] = 'closed';

        $this->webhookPost($payload, deliveryId: 'delivery-close')
            ->assertOk();

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'github_state' => 'closed',
            'status_id' => 2,    // アプリ側ステータスは変わらない
        ]);
    }

    // --- delivery重複排除 ---

    public function test_idempotent_on_duplicate_delivery_with_success(): void
    {
        // 初回送信
        $this->webhookPost($this->issuePayload('opened'))
            ->assertOk();

        $this->assertDatabaseCount('issues', 1);

        // 同一deliveryを再送
        $this->webhookPost($this->issuePayload('opened'))
            ->assertOk();

        // Issueは重複作成されない
        $this->assertDatabaseCount('issues', 1);
        $this->assertDatabaseCount('sync_logs', 1);
    }

    public function test_idempotent_on_duplicate_delivery_with_skipped(): void
    {
        $this->webhookPost($this->issuePayload('labeled'))
            ->assertStatus(202);

        // skippedのdeliveryを再送 → 200で終了（処理済みとして返す）
        $this->webhookPost($this->issuePayload('labeled'))
            ->assertOk();

        $this->assertDatabaseCount('sync_logs', 1);
    }

    public function test_retries_failed_delivery_and_increments_attempt_count(): void
    {
        // 失敗ログを手動で作成（failed状態のdelivery）
        $failedLog = SyncLog::query()->create([
            'github_delivery_id' => self::DELIVERY_ID,
            'trigger' => 'webhook',
            'status' => SyncStatus::Failed,
            'attempt_count' => 1,
            'error_message' => 'some_error',
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subSecond(),
        ]);

        $this->webhookPost($this->issuePayload('opened'))
            ->assertOk();

        $failedLog->refresh();
        $this->assertEquals(2, $failedLog->attempt_count);
        $this->assertEquals(SyncStatus::Success, $failedLog->status);
        $this->assertDatabaseCount('issues', 1);
    }

    public function test_returns_500_with_failed_log_when_import_fails(): void
    {
        // display_orderロックを保持してIssue作成を失敗させる
        config(['services.github.display_order_lock_wait' => 0]);
        Cache::lock('github-import:display-order', 30)->get();

        $this->webhookPost($this->issuePayload('opened'))
            ->assertStatus(500);

        $this->assertDatabaseCount('issues', 0);
        $this->assertDatabaseHas('sync_logs', [
            'github_delivery_id' => self::DELIVERY_ID,
            'status' => 'failed',
            'attempt_count' => 1,
            'error_message' => 'display_order_lock_timeout',
        ]);
    }

    public function test_retry_failure_increments_attempt_count_once(): void
    {
        $failedLog = SyncLog::query()->create([
            'github_delivery_id' => self::DELIVERY_ID,
            'trigger' => 'webhook',
            'status' => SyncStatus::Failed,
            'attempt_count' => 1,
            'error_message' => 'some_error',
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subSecond(),
        ]);

        // 再試行も失敗させる（display_orderロック保持）
        config(['services.github.display_order_lock_wait' => 0]);
        Cache::lock('github-import:display-order', 30)->get();

        $this->webhookPost($this->issuePayload('opened'))
            ->assertStatus(500);

        $failedLog->refresh();
        $this->assertEquals(2, $failedLog->attempt_count);
        $this->assertEquals(SyncStatus::Failed, $failedLog->status);
    }

    // --- ルート確認 ---

    public function test_webhook_route_does_not_require_authentication(): void
    {
        // Sanctum認証なしでもwebhookは到達できる（署名で認証）
        $this->webhookPost($this->issuePayload('opened'))
            ->assertOk();
    }

    // --- ヘルパーメソッド ---

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhookPost(
        array $payload,
        string $event = 'issues',
        string $deliveryId = self::DELIVERY_ID,
        ?string $secret = null,
    ): TestResponse {
        $body = json_encode($payload);
        $usedSecret = $secret ?? self::SECRET;
        $signature = 'sha256='.hash_hmac('sha256', $body, $usedSecret);

        return $this->call(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'HTTP_X_GITHUB_DELIVERY' => $deliveryId,
                'HTTP_X_GITHUB_EVENT' => $event,
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );
    }

    private function webhookPostRaw(
        string $body,
        string $event,
        string $deliveryId,
    ): TestResponse {
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        return $this->call(
            'POST',
            self::WEBHOOK_URL,
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'HTTP_X_GITHUB_DELIVERY' => $deliveryId,
                'HTTP_X_GITHUB_EVENT' => $event,
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function issuePayload(string $action, int $number = 42): array
    {
        return [
            'action' => $action,
            'issue' => [
                'number' => $number,
                'title' => 'Test Issue',
                'html_url' => "https://github.com/acme/myrepo/issues/{$number}",
                'state' => 'open',
            ],
            'repository' => [
                'name' => 'myrepo',
                'owner' => ['login' => 'acme'],
            ],
        ];
    }
}
