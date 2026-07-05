<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Models\Issue;
use App\Models\IssueSchedule;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Product $product;

    private ProductRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();
        $this->product = Product::query()->create(['name' => 'Test Product', 'display_order' => 0]);
        $this->repo = ProductRepository::query()->create([
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'myrepo',
            'is_active' => true,
        ]);

        config(['services.github.token' => 'test-token']);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    // --- 未認証 ---

    public function test_guest_cannot_trigger_sync(): void
    {
        $this->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertUnauthorized();
    }

    public function test_guest_cannot_list_sync_logs(): void
    {
        $this->getJson("/api/v1/products/{$this->product->id}/sync-logs")
            ->assertUnauthorized();
    }

    // --- sync store (POST /products/{id}/sync) ---

    public function test_returns_422_when_no_active_repository(): void
    {
        $this->repo->update(['is_active' => false]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertUnprocessable();
    }

    public function test_returns_422_when_no_repository_configured(): void
    {
        $product2 = Product::query()->create(['name' => 'No Repo Product', 'display_order' => 1]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$product2->id}/sync")
            ->assertUnprocessable();
    }

    public function test_returns_409_when_sync_already_running(): void
    {
        // キャッシュロックを先に取得してsync中を模擬
        $lock = Cache::lock('github-sync:product:'.$this->product->id, 3600);
        $lock->get();

        try {
            $this->actingAs($this->actor)
                ->postJson("/api/v1/products/{$this->product->id}/sync")
                ->assertStatus(409);
        } finally {
            $lock->release();
        }
    }

    public function test_returns_failed_synclog_when_token_not_configured(): void
    {
        config(['services.github.token' => '']);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error_message', 'GITHUB_TOKEN 未設定');

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'failed',
            'error_message' => 'GITHUB_TOKEN 未設定',
        ]);
    }

    public function test_successful_sync_creates_open_issues_and_skips_closed(): void
    {
        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push([
                    $this->githubIssue(1, 'open'),
                    $this->githubIssue(2, 'closed'),
                    $this->githubIssue(3, 'open', isPr: true),
                ], 200)
                ->push([], 200), // 2ページ目は空
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('created_count', 1)   // open issue #1 のみ
            ->assertJsonPath('skipped_count', 2);  // closed #2 + PR #3

        $this->assertDatabaseCount('issues', 1);
        $this->assertDatabaseHas('issues', ['github_issue_number' => 1, 'github_state' => 'open']);
        $this->assertDatabaseMissing('issues', ['github_issue_number' => 2]);
        $this->assertDatabaseMissing('issues', ['github_issue_number' => 3]);
    }

    public function test_sync_creates_issue_schedules(): void
    {
        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push([$this->githubIssue(1, 'open')], 200)
                ->push([], 200),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk();

        $issue = Issue::query()->where('github_issue_number', 1)->first();
        $this->assertNotNull($issue);
        $this->assertDatabaseHas('issue_schedules', ['issue_id' => $issue->id]);
    }

    public function test_sync_updates_existing_issue_github_fields_only(): void
    {
        $issue = Issue::query()->create([
            'product_id' => $this->product->id,
            'title' => 'Old Title',
            'github_url' => 'https://github.com/acme/myrepo/issues/1',
            'github_issue_number' => 1,
            'github_state' => 'open',
            'github_synced_at' => now()->subDay(),
            'display_order' => 1,
            'status_id' => 3,
            'is_managed' => true,
        ]);
        IssueSchedule::query()->create(['issue_id' => $issue->id]);

        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push([$this->githubIssue(1, 'closed', title: 'New Title')], 200)
                ->push([], 200),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk()
            ->assertJsonPath('updated_count', 1)
            ->assertJsonPath('created_count', 0);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'title' => 'New Title',
            'github_state' => 'closed',
            'status_id' => 3,      // 変更されていない
            'is_managed' => true,   // 変更されていない
        ]);
    }

    public function test_sync_updates_last_synced_at_on_success(): void
    {
        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push([$this->githubIssue(1, 'open')], 200)
                ->push([], 200),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk();

        $this->repo->refresh();
        $this->assertNotNull($this->repo->last_synced_at);
        $this->assertEquals('success', $this->repo->last_sync_status->value);
    }

    public function test_sync_does_not_update_last_synced_at_on_failure(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(null, 401),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->repo->refresh();
        $this->assertNull($this->repo->last_synced_at);
        $this->assertNull($this->repo->last_sync_status);
    }

    public function test_sync_returns_partial_when_some_issues_fail(): void
    {
        // 既存Issueに対してUNIQUEを故意に起こすのは難しいので
        // created+updated >= 1 の状態でエラーが混在する場合をテスト
        // ここでは1件成功後にAPIエラー（2ページ目で401）を模擬
        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push(
                    [$this->githubIssue(1, 'open')],
                    200,
                    ['Link' => '<https://api.github.com/repos/acme/myrepo/issues?page=2>; rel="next"']
                )
                ->push(null, 401),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'partial');

        $this->assertDatabaseCount('issues', 1);
    }

    public function test_sync_returns_success_when_repository_has_no_issues(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([], 200),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('created_count', 0)
            ->assertJsonPath('updated_count', 0);
    }

    public function test_sync_fails_when_github_returns_401(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(null, 401),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->assertDatabaseHas('sync_logs', [
            'status' => 'failed',
            'error_message' => 'github_auth_error:401',
        ]);
    }

    public function test_sync_log_has_running_status_while_syncing(): void
    {
        // 同期実行後のSyncLogがrunning→success/failedに更新されることを確認
        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push([$this->githubIssue(1, 'open')], 200)
                ->push([], 200),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // runningのログは残らない（成功時にsuccessへ更新される）
        $this->assertDatabaseMissing('sync_logs', ['status' => 'running']);
    }

    // --- sync-logs index (GET /products/{id}/sync-logs) ---

    public function test_sync_index_returns_logs_in_descending_order(): void
    {
        SyncLog::query()->create([
            'product_id' => $this->product->id,
            'trigger' => 'manual_resync',
            'triggered_by' => $this->actor->id,
            'status' => SyncStatus::Success,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addSeconds(10),
        ]);
        SyncLog::query()->create([
            'product_id' => $this->product->id,
            'trigger' => 'manual_resync',
            'triggered_by' => $this->actor->id,
            'status' => SyncStatus::Failed,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(30)->addSeconds(5),
        ]);

        $response = $this->actingAs($this->actor)
            ->getJson("/api/v1/products/{$this->product->id}/sync-logs")
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('failed', $data[0]['status']);
        $this->assertEquals('success', $data[1]['status']);
    }

    public function test_sync_index_respects_limit_parameter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            SyncLog::query()->create([
                'product_id' => $this->product->id,
                'trigger' => 'manual_resync',
                'status' => SyncStatus::Success,
                'started_at' => now()->subMinutes($i),
                'finished_at' => now()->subMinutes($i)->addSeconds(1),
            ]);
        }

        $this->actingAs($this->actor)
            ->getJson("/api/v1/products/{$this->product->id}/sync-logs?limit=3")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_sync_index_validates_limit_parameter(): void
    {
        $this->actingAs($this->actor)
            ->getJson("/api/v1/products/{$this->product->id}/sync-logs?limit=0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);

        $this->actingAs($this->actor)
            ->getJson("/api/v1/products/{$this->product->id}/sync-logs?limit=101")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_sync_log_resource_includes_required_fields(): void
    {
        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push([$this->githubIssue(1, 'open')], 200)
                ->push([], 200),
        ]);

        $this->actingAs($this->actor)
            ->postJson("/api/v1/products/{$this->product->id}/sync")
            ->assertOk();

        $this->actingAs($this->actor)
            ->getJson("/api/v1/products/{$this->product->id}/sync-logs")
            ->assertOk()
            ->assertJsonPath('data.0.trigger', 'manual_resync')
            ->assertJsonPath('data.0.github_delivery_id', null)
            ->assertJsonPath('data.0.attempt_count', 1)
            ->assertJsonPath('data.0.status', 'success')
            ->assertJsonPath('data.0.created_count', 1);
    }

    // --- ヘルパーメソッド ---

    /**
     * @return array<string, mixed>
     */
    private function githubIssue(
        int $number,
        string $state,
        string $title = '',
        bool $isPr = false,
    ): array {
        $issue = [
            'number' => $number,
            'title' => $title ?: "Issue #{$number}",
            'html_url' => "https://github.com/acme/myrepo/issues/{$number}",
            'state' => $state,
        ];

        if ($isPr) {
            $issue['pull_request'] = ['url' => "https://github.com/acme/myrepo/pulls/{$number}"];
        }

        return $issue;
    }
}
