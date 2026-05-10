<?php

namespace Tests\Feature;

use App\Models\Engineer;
use App\Models\Issue;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IssueManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_access_issue_apis(): void
    {
        $issue = $this->createIssue();

        $this->getJson('/api/v1/issues')->assertUnauthorized();
        $this->getJson("/api/v1/issues/{$issue->id}")->assertUnauthorized();
        $this->putJson("/api/v1/issues/{$issue->id}", [])->assertUnauthorized();
        $this->patchJson("/api/v1/issues/{$issue->id}/status", ['status' => '作業中'])->assertUnauthorized();
        $this->putJson("/api/v1/issues/{$issue->id}/schedule", [])->assertUnauthorized();
    }

    public function test_can_list_show_and_filter_issues(): void
    {
        Carbon::setTestNow('2026-05-07 09:00:00');
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $otherProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $engineer = Engineer::query()->create(['name' => '山田 太郎', 'display_order' => 1]);
        $director = User::factory()->create(['name' => '田中 美咲']);

        $target = $this->createIssue([
            'product_id' => $product->id,
            'engineer_id' => $engineer->id,
            'director_id' => $director->id,
            'status' => '作業中',
            'display_order' => 2,
        ]);
        $target->schedule()->create([
            'planned_end' => '2026-05-08',
        ]);
        $this->createIssue([
            'product_id' => $otherProduct->id,
            'status' => '未着手',
            'github_issue_number' => 102,
        ]);

        $this->actingAs($actor)->getJson('/api/v1/issues?product_id='.$product->id.'&status=作業中')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $target->id)
            ->assertJsonPath('0.director.name', '田中 美咲')
            ->assertJsonPath('0.engineer.name', '山田 太郎')
            ->assertJsonPath('0.schedule.planned_end', '2026-05-08')
            ->assertJsonMissingPath('0.schedule.planned_hours')
            ->assertJsonMissingPath('0.schedule.actual_hours')
            ->assertJsonPath('0.is_overdue', false)
            ->assertJsonPath('0.is_due_soon', true);

        $this->actingAs($actor)->getJson("/api/v1/issues/{$target->id}")
            ->assertOk()
            ->assertJsonPath('id', $target->id)
            ->assertJsonPath('github_issue_number', 101);
    }

    public function test_can_filter_issues_by_multiple_values(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $otherProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $engineer = Engineer::query()->create(['name' => '山田 太郎', 'display_order' => 1]);
        $otherEngineer = Engineer::query()->create(['name' => '佐藤 花子', 'display_order' => 2]);
        $director = User::factory()->create(['name' => '田中 美咲']);
        $otherDirector = User::factory()->create(['name' => '鈴木 一郎']);

        $working = $this->createIssue([
            'product_id' => $product->id,
            'engineer_id' => $engineer->id,
            'director_id' => $director->id,
            'status' => '作業中',
            'github_issue_number' => 201,
        ]);
        $testing = $this->createIssue([
            'product_id' => $otherProduct->id,
            'engineer_id' => $otherEngineer->id,
            'director_id' => $otherDirector->id,
            'status' => 'テスト中',
            'github_issue_number' => 202,
        ]);
        $this->createIssue([
            'product_id' => $product->id,
            'engineer_id' => $engineer->id,
            'director_id' => $director->id,
            'status' => '完了',
            'github_issue_number' => 203,
        ]);

        $this->actingAs($actor)->getJson('/api/v1/issues?'.http_build_query([
            'product_id' => [$product->id, $otherProduct->id],
            'engineer_id' => [$engineer->id, $otherEngineer->id],
            'director_id' => [$director->id, $otherDirector->id],
            'status' => ['作業中', 'テスト中'],
        ]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $working->id)
            ->assertJsonPath('1.id', $testing->id);
    }

    public function test_single_issue_filter_values_remain_supported(): void
    {
        $actor = User::factory()->create();

        $working = $this->createIssue(['status' => '作業中', 'github_issue_number' => 201]);
        $this->createIssue(['status' => 'テスト中', 'github_issue_number' => 202]);

        $this->actingAs($actor)->getJson('/api/v1/issues?status=作業中')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $working->id);
    }

    public function test_issue_index_rejects_invalid_multi_filter_values(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->getJson('/api/v1/issues?'.http_build_query([
            'status' => ['作業中', 'レビュー中'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status.1']);
    }

    public function test_can_update_management_fields_without_overwriting_github_fields(): void
    {
        $actor = User::factory()->create();
        $director = User::factory()->create(['name' => '担当ディレクター']);
        $engineer = Engineer::query()->create(['name' => '担当エンジニア', 'display_order' => 1]);
        $issue = $this->createIssue([
            'title' => 'GitHub由来タイトル',
            'github_url' => 'https://github.com/example/repo/issues/101',
            'github_issue_number' => 101,
            'github_state' => 'open',
        ]);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}", [
            'title' => '手入力タイトル',
            'github_url' => 'https://example.com/changed',
            'github_issue_number' => 999,
            'github_state' => 'closed',
            'director_id' => $director->id,
            'engineer_id' => $engineer->id,
            'status' => 'テスト中',
            'is_managed' => true,
        ])->assertOk()
            ->assertJsonPath('director.id', $director->id)
            ->assertJsonPath('engineer.id', $engineer->id)
            ->assertJsonPath('status', 'テスト中')
            ->assertJsonPath('is_managed', true)
            ->assertJsonPath('title', 'GitHub由来タイトル')
            ->assertJsonPath('github_url', 'https://github.com/example/repo/issues/101')
            ->assertJsonPath('github_issue_number', 101)
            ->assertJsonPath('github_state', 'open');

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'title' => 'GitHub由来タイトル',
            'director_id' => $director->id,
            'engineer_id' => $engineer->id,
            'status' => 'テスト中',
            'is_managed' => true,
        ]);
    }

    public function test_can_update_status_toggle_managed_remove_from_managed_and_update_schedule(): void
    {
        $actor = User::factory()->create();
        $issue = $this->createIssue(['is_managed' => false]);

        $this->actingAs($actor)->patchJson("/api/v1/issues/{$issue->id}/status", [
            'status' => '作業中',
        ])->assertOk()
            ->assertJsonPath('status', '作業中');

        $this->actingAs($actor)->patchJson("/api/v1/issues/{$issue->id}/managed")
            ->assertOk()
            ->assertJsonPath('is_managed', true);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}/schedule", [
            'planned_start' => '2026-05-01',
            'planned_end' => '2026-05-10',
            'actual_start' => '2026-05-02',
            'actual_end' => null,
        ])->assertOk()
            ->assertJsonPath('schedule.planned_start', '2026-05-01')
            ->assertJsonMissingPath('schedule.planned_hours')
            ->assertJsonMissingPath('schedule.actual_hours');

        $this->actingAs($actor)->deleteJson("/api/v1/issues/{$issue->id}")
            ->assertOk()
            ->assertJsonPath('is_managed', false);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'is_managed' => false,
        ]);
    }

    public function test_unmanaged_imports_query_returns_only_unmanaged_github_issues(): void
    {
        $actor = User::factory()->create();

        $unmanaged = $this->createIssue(['is_managed' => false, 'github_issue_number' => 101]);
        $this->createIssue(['is_managed' => true, 'github_issue_number' => 102]);

        $this->actingAs($actor)->getJson('/api/v1/issues?unmanaged_imports=true')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $unmanaged->id);
    }

    public function test_default_issue_index_returns_all_issues(): void
    {
        $actor = User::factory()->create();

        $managed = $this->createIssue(['is_managed' => true, 'github_issue_number' => 101]);
        $unmanaged = $this->createIssue(['is_managed' => false, 'github_issue_number' => 102]);

        $this->actingAs($actor)->getJson('/api/v1/issues')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $managed->id)
            ->assertJsonPath('1.id', $unmanaged->id);
    }

    public function test_can_filter_managed_issues_explicitly(): void
    {
        $actor = User::factory()->create();

        $managed = $this->createIssue(['is_managed' => true, 'github_issue_number' => 101]);
        $this->createIssue(['is_managed' => false, 'github_issue_number' => 102]);

        $this->actingAs($actor)->getJson('/api/v1/issues?is_managed=true')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $managed->id);
    }

    public function test_issue_validation_returns_422(): void
    {
        $actor = User::factory()->create();
        $issue = $this->createIssue();

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}", [
            'status' => 'レビュー中',
            'director_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'director_id']);

        $this->actingAs($actor)->patchJson("/api/v1/issues/{$issue->id}/status", [
            'status' => 'レビュー中',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}/schedule", [
            'planned_end' => '2026/05/10',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_end']);
    }

    public function test_development_seeder_adds_github_imported_issues_with_schedules(): void
    {
        $this->seed(DevelopmentSeeder::class);

        $this->assertDatabaseCount('issues', 50);
        $this->assertDatabaseCount('issue_schedules', 50);
        $this->assertDatabaseHas('issues', [
            'title' => 'ログイン画面のバリデーション修正',
            'github_issue_number' => 101,
            'github_state' => 'closed',
            'is_managed' => true,
        ]);
        $this->assertDatabaseHas('issue_schedules', [
            'planned_start' => '2026-04-01',
        ]);
        $this->assertDatabaseHas('issues', [
            'title' => '請求履歴一覧のページング改善 #01',
            'github_issue_number' => 401,
            'is_managed' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createIssue(array $attributes = []): Issue
    {
        $productId = $attributes['product_id'] ?? Product::query()->create([
            'name' => 'Product '.fake()->unique()->word(),
            'display_order' => 1,
        ])->id;

        return Issue::query()->create([
            'title' => 'ログイン画面のバリデーション修正',
            'github_url' => 'https://github.com/example/repo/issues/'.($attributes['github_issue_number'] ?? 101),
            'director_id' => null,
            'engineer_id' => null,
            'product_id' => $productId,
            'status' => '未着手',
            'is_managed' => true,
            'display_order' => 1,
            'github_issue_number' => 101,
            'github_state' => 'open',
            'github_synced_at' => '2026-05-07 09:00:00',
            ...$attributes,
        ]);
    }
}
