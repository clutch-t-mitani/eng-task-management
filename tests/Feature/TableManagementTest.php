<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupIssue;
use App\Models\Issue;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_table_and_group_apis(): void
    {
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'product_id' => $product->id, 'display_order' => 1]);
        $issue = $this->createIssue(['product_id' => $product->id]);

        $this->getJson('/api/v1/table')->assertUnauthorized();
        $this->getJson('/api/v1/groups')->assertUnauthorized();
        $this->postJson('/api/v1/groups', [])->assertUnauthorized();
        $this->patchJson('/api/v1/groups/reorder', ['ordered_ids' => [$group->id]])->assertUnauthorized();
        $this->postJson("/api/v1/groups/{$group->id}/issues/{$issue->id}")->assertUnauthorized();
    }

    public function test_table_returns_managed_grouped_and_ungrouped_issues_only(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $otherProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $group = Group::query()->create(['name' => 'v1', 'product_id' => $product->id, 'display_order' => 1]);

        $grouped = $this->createIssue(['product_id' => $product->id, 'title' => 'Grouped', 'status_id' => 2]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $grouped->id, 'display_order' => 1]);
        $ungrouped = $this->createIssue(['product_id' => $product->id, 'title' => 'Ungrouped', 'status_id' => 2]);
        $this->createIssue(['product_id' => $product->id, 'is_managed' => false, 'title' => 'Unmanaged']);
        $this->createIssue(['product_id' => $otherProduct->id, 'title' => 'Other']);

        $this->actingAs($actor)->getJson('/api/v1/table?product_id='.$product->id.'&status_id=2')
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonPath('groups.0.issues.0.id', $grouped->id)
            ->assertJsonPath('groups.0.issues.0.group.id', $group->id)
            ->assertJsonPath('ungrouped_issues.0.id', $ungrouped->id)
            ->assertJsonMissingPath('ungrouped_issues.1');
    }

    public function test_group_crud_and_reorder(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $first = Group::query()->create(['name' => 'v1', 'product_id' => $product->id, 'display_order' => 1]);
        $second = Group::query()->create(['name' => 'v2', 'product_id' => $product->id, 'display_order' => 2]);

        $created = $this->actingAs($actor)->postJson('/api/v1/groups', [
            'name' => 'v3',
            'release_date' => '2026-05-31',
            'product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('name', 'v3')
            ->assertJsonPath('release_date', '2026-05-31');

        $this->actingAs($actor)->putJson("/api/v1/groups/{$first->id}", [
            'name' => 'v1.1',
            'release_date' => null,
        ])->assertOk()
            ->assertJsonPath('name', 'v1.1');

        $this->actingAs($actor)->patchJson('/api/v1/groups/reorder', [
            'ordered_ids' => [$second->id, $first->id, $created->json('id')],
        ])->assertOk()
            ->assertJsonPath('0.id', $second->id);

        $this->assertDatabaseHas('groups', ['id' => $second->id, 'display_order' => 1]);

        $this->actingAs($actor)->deleteJson("/api/v1/groups/{$first->id}")->assertOk();
        $this->assertDatabaseMissing('groups', ['id' => $first->id]);
    }

    public function test_group_issue_add_remove_reorder_and_cross_product_rejection(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $otherProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $group = Group::query()->create(['name' => 'v1', 'product_id' => $product->id, 'display_order' => 1]);
        $first = $this->createIssue(['product_id' => $product->id, 'is_managed' => false, 'github_issue_number' => 201]);
        $second = $this->createIssue(['product_id' => $product->id, 'github_issue_number' => 202]);
        $otherIssue = $this->createIssue(['product_id' => $otherProduct->id, 'github_issue_number' => 301]);

        $this->actingAs($actor)->postJson("/api/v1/groups/{$group->id}/issues/{$first->id}")
            ->assertOk()
            ->assertJsonPath('id', $first->id)
            ->assertJsonPath('is_managed', true)
            ->assertJsonPath('group.id', $group->id);

        $this->actingAs($actor)->postJson("/api/v1/groups/{$group->id}/issues/{$second->id}")->assertOk();

        $this->actingAs($actor)->patchJson("/api/v1/groups/{$group->id}/issues/reorder", [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->assertDatabaseHas('group_issues', [
            'group_id' => $group->id,
            'issue_id' => $second->id,
            'display_order' => 1,
        ]);

        $this->actingAs($actor)->postJson("/api/v1/groups/{$group->id}/issues/{$otherIssue->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['issue_id']);

        $this->actingAs($actor)->deleteJson("/api/v1/groups/{$group->id}/issues/{$first->id}")->assertOk();
        $this->assertDatabaseMissing('group_issues', ['issue_id' => $first->id]);
    }

    public function test_issue_update_can_change_group_and_validates_product(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $otherProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $group = Group::query()->create(['name' => 'v1', 'product_id' => $product->id, 'display_order' => 1]);
        $otherGroup = Group::query()->create(['name' => 'v2', 'product_id' => $otherProduct->id, 'display_order' => 1]);
        $issue = $this->createIssue(['product_id' => $product->id]);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}", [
            'group_id' => $group->id,
        ])->assertOk()
            ->assertJsonPath('group.id', $group->id);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}", [
            'group_id' => $otherGroup->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['group_id']);
    }

    public function test_group_validation_returns_422(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->postJson('/api/v1/groups', [
            'name' => '',
            'product_id' => 999999,
            'release_date' => '2026/05/31',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'product_id', 'release_date']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createIssue(array $attributes = []): Issue
    {
        static $issueNumber = 1000;
        $githubIssueNumber = $attributes['github_issue_number'] ?? $issueNumber++;
        $productId = $attributes['product_id'] ?? Product::query()->create([
            'name' => 'Product '.fake()->unique()->word(),
            'display_order' => 1,
        ])->id;

        return Issue::query()->create([
            'title' => 'ログイン画面のバリデーション修正',
            'github_url' => 'https://github.com/example/repo/issues/'.$githubIssueNumber,
            'director_id' => null,
            'engineer_id' => null,
            'product_id' => $productId,
            'status_id' => 1,
            'is_managed' => true,
            'display_order' => 1,
            'github_issue_number' => $githubIssueNumber,
            'github_state' => 'open',
            'github_synced_at' => '2026-05-07 09:00:00',
            ...$attributes,
        ]);
    }
}
