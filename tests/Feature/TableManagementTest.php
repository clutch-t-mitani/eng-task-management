<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupIssue;
use App\Models\Issue;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_independent_group_migration_preserves_groups_and_normalizes_global_order(): void
    {
        $migration = require database_path('migrations/2026_05_11_000002_make_groups_product_independent.php');
        $migration->down();

        $firstProduct = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $secondProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);

        DB::table('groups')->insert([
            [
                'id' => 101,
                'name' => 'Product B First',
                'release_date' => null,
                'display_order' => 1,
                'product_id' => $secondProduct->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 102,
                'name' => 'Product A Second',
                'release_date' => null,
                'display_order' => 2,
                'product_id' => $firstProduct->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 103,
                'name' => 'Product A First',
                'release_date' => null,
                'display_order' => 1,
                'product_id' => $firstProduct->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $issue = $this->createIssue(['product_id' => $firstProduct->id]);
        GroupIssue::query()->create([
            'group_id' => 101,
            'issue_id' => $issue->id,
            'display_order' => 1,
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('groups', 'product_id'));
        $this->assertDatabaseCount('groups', 3);
        $this->assertDatabaseHas('group_issues', ['group_id' => 101, 'issue_id' => $issue->id]);
        $this->assertSame([103, 102, 101], DB::table('groups')->orderBy('display_order')->pluck('id')->all());
        $this->assertSame([1, 2, 3], DB::table('groups')->orderBy('display_order')->pluck('display_order')->all());
    }

    public function test_product_independent_group_migration_rolls_back_when_every_group_has_one_product(): void
    {
        $migration = require database_path('migrations/2026_05_11_000002_make_groups_product_independent.php');
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $issue = $this->createIssue(['product_id' => $product->id]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $issue->id, 'display_order' => 1]);

        $migration->down();

        $this->assertTrue(Schema::hasColumn('groups', 'product_id'));
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'product_id' => $product->id]);
        $productIdColumn = collect(Schema::getColumns('groups'))->firstWhere('name', 'product_id');
        $this->assertFalse($productIdColumn['nullable']);
        $productForeignKey = collect(Schema::getForeignKeys('groups'))
            ->first(fn (array $foreignKey): bool => $foreignKey['columns'] === ['product_id']);
        $this->assertNotNull($productForeignKey);
        $this->assertSame('products', $productForeignKey['foreign_table']);
        $this->assertSame(['id'], $productForeignKey['foreign_columns']);
        $this->assertSame('cascade', $productForeignKey['on_delete']);

        $migration->up();
    }

    public function test_product_independent_group_migration_rejects_rollback_with_empty_group_without_changing_schema(): void
    {
        $migration = require database_path('migrations/2026_05_11_000002_make_groups_product_independent.php');
        $group = Group::query()->create(['name' => 'Empty', 'display_order' => 1]);

        try {
            $migration->down();
            $this->fail('Expected rollback to reject an empty group.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString((string) $group->id, $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('groups', 'product_id'));
        $this->assertDatabaseHas('groups', ['id' => $group->id]);
    }

    public function test_product_independent_group_migration_rejects_rollback_with_mixed_product_group_without_changing_schema(): void
    {
        $migration = require database_path('migrations/2026_05_11_000002_make_groups_product_independent.php');
        $firstProduct = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $secondProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $group = Group::query()->create(['name' => 'Mixed', 'display_order' => 1]);
        $firstIssue = $this->createIssue(['product_id' => $firstProduct->id]);
        $secondIssue = $this->createIssue(['product_id' => $secondProduct->id]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $firstIssue->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $secondIssue->id, 'display_order' => 2]);

        try {
            $migration->down();
            $this->fail('Expected rollback to reject a mixed-product group.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString((string) $group->id, $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('groups', 'product_id'));
        $this->assertDatabaseHas('group_issues', ['group_id' => $group->id, 'issue_id' => $firstIssue->id]);
        $this->assertDatabaseHas('group_issues', ['group_id' => $group->id, 'issue_id' => $secondIssue->id]);
    }

    public function test_guest_cannot_access_table_and_group_apis(): void
    {
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $issue = $this->createIssue(['product_id' => $product->id]);

        $this->getJson('/api/v1/table')->assertUnauthorized();
        $this->getJson('/api/v1/groups')->assertUnauthorized();
        $this->postJson('/api/v1/groups', [])->assertUnauthorized();
        $this->patchJson('/api/v1/groups/reorder', ['ordered_ids' => [$group->id]])->assertUnauthorized();
        $this->postJson("/api/v1/groups/{$group->id}/issues/{$issue->id}")->assertUnauthorized();
        $this->patchJson('/api/v1/issues/bulk/remove-from-managed', ['issue_ids' => [$issue->id]])->assertUnauthorized();
        $this->patchJson('/api/v1/issues/bulk/group', ['issue_ids' => [$issue->id], 'group_id' => $group->id])->assertUnauthorized();
        $this->patchJson('/api/v1/issues/ungrouped/reorder', ['ordered_ids' => [$issue->id]])->assertUnauthorized();
    }

    public function test_table_returns_managed_grouped_and_ungrouped_issues_only(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $otherProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $otherGroup = Group::query()->create(['name' => 'v2', 'display_order' => 2]);

        $grouped = $this->createIssue(['product_id' => $product->id, 'title' => 'Grouped', 'status_id' => 2]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $grouped->id, 'display_order' => 1]);
        $ungrouped = $this->createIssue(['product_id' => $product->id, 'title' => 'Ungrouped', 'status_id' => 2]);
        $this->createIssue(['product_id' => $product->id, 'is_managed' => false, 'title' => 'Unmanaged']);
        $otherGrouped = $this->createIssue(['product_id' => $otherProduct->id, 'title' => 'Other', 'status_id' => 2]);
        GroupIssue::query()->create(['group_id' => $otherGroup->id, 'issue_id' => $otherGrouped->id, 'display_order' => 1]);

        $this->actingAs($actor)->getJson('/api/v1/table?product_id='.$product->id.'&status_id=2')
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonPath('groups.0.issues.0.id', $grouped->id)
            ->assertJsonPath('groups.0.issues.0.group.id', $group->id)
            ->assertJsonPath('ungrouped_issues.0.id', $ungrouped->id)
            ->assertJsonMissingPath('ungrouped_issues.1');
    }

    public function test_table_returns_empty_groups_without_filters(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $emptyGroup = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $issue = $this->createIssue(['product_id' => $product->id]);

        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $issue->id, 'display_order' => 1]);

        $this->actingAs($actor)->getJson('/api/v1/table')
            ->assertOk()
            ->assertJsonCount(2, 'groups')
            ->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonCount(1, 'groups.0.issues')
            ->assertJsonPath('groups.1.id', $emptyGroup->id)
            ->assertJsonCount(0, 'groups.1.issues');
    }

    public function test_table_hides_empty_groups_when_filters_are_applied(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $otherStatusGroup = Group::query()->create(['name' => 'v1.1', 'display_order' => 2]);
        $emptyGroup = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $issue = $this->createIssue(['product_id' => $product->id, 'status_id' => 2]);
        $otherStatusIssue = $this->createIssue(['product_id' => $product->id, 'status_id' => 4]);

        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $issue->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $otherStatusGroup->id, 'issue_id' => $otherStatusIssue->id, 'display_order' => 1]);

        $this->actingAs($actor)->getJson('/api/v1/table?product_id='.$product->id)
            ->assertOk()
            ->assertJsonCount(2, 'groups');

        $this->actingAs($actor)->getJson('/api/v1/table?status_id=2')
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.id', $group->id);

        $this->actingAs($actor)->getJson('/api/v1/groups')
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.id', $group->id)
            ->assertJsonPath('1.id', $otherStatusGroup->id)
            ->assertJsonPath('2.id', $emptyGroup->id);
    }

    public function test_group_crud_and_reorder(): void
    {
        $actor = User::factory()->create();
        $first = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $second = Group::query()->create(['name' => 'v2', 'display_order' => 2]);

        $created = $this->actingAs($actor)->postJson('/api/v1/groups', [
            'name' => 'v3',
            'release_date' => '2026-05-31',
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

    public function test_groups_are_reordered_with_global_order(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $first = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $second = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $third = Group::query()->create(['name' => 'v3', 'display_order' => 3]);

        foreach ([$first, $second, $third] as $index => $group) {
            $issue = $this->createIssue(['product_id' => $product->id, 'github_issue_number' => 300 + $index]);
            GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $issue->id, 'display_order' => 1]);
        }

        $this->actingAs($actor)->patchJson('/api/v1/groups/reorder', [
            'ordered_ids' => [$second->id, $third->id, $first->id],
        ])->assertOk()
            ->assertJsonPath('0.id', $second->id)
            ->assertJsonPath('1.id', $third->id)
            ->assertJsonPath('2.id', $first->id);

        $this->assertDatabaseHas('groups', ['id' => $second->id, 'display_order' => 1]);
        $this->assertDatabaseHas('groups', ['id' => $third->id, 'display_order' => 2]);
        $this->assertDatabaseHas('groups', ['id' => $first->id, 'display_order' => 3]);

        $this->actingAs($actor)->getJson('/api/v1/table')
            ->assertOk()
            ->assertJsonPath('groups.0.id', $second->id)
            ->assertJsonPath('groups.1.id', $third->id)
            ->assertJsonPath('groups.2.id', $first->id);
    }

    public function test_groups_are_reordered_with_empty_groups_visible_in_table(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $first = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $second = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $emptyGroup = Group::query()->create(['name' => 'v3', 'display_order' => 3]);
        $issue = $this->createIssue(['product_id' => $product->id]);

        GroupIssue::query()->create(['group_id' => $first->id, 'issue_id' => $issue->id, 'display_order' => 1]);

        $this->actingAs($actor)->patchJson('/api/v1/groups/reorder', [
            'ordered_ids' => [$emptyGroup->id, $second->id, $first->id],
        ])->assertOk();

        $this->assertDatabaseHas('groups', ['id' => $emptyGroup->id, 'display_order' => 1]);
        $this->assertDatabaseHas('groups', ['id' => $second->id, 'display_order' => 2]);
        $this->assertDatabaseHas('groups', ['id' => $first->id, 'display_order' => 3]);

        $this->actingAs($actor)->getJson('/api/v1/table')
            ->assertOk()
            ->assertJsonPath('groups.0.id', $emptyGroup->id)
            ->assertJsonCount(0, 'groups.0.issues')
            ->assertJsonPath('groups.1.id', $second->id)
            ->assertJsonCount(0, 'groups.1.issues')
            ->assertJsonPath('groups.2.id', $first->id)
            ->assertJsonCount(1, 'groups.2.issues');
    }

    public function test_groups_can_be_partially_reordered_without_moving_hidden_groups(): void
    {
        $actor = User::factory()->create();
        $first = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $hiddenFirst = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $second = Group::query()->create(['name' => 'v3', 'display_order' => 3]);
        $hiddenSecond = Group::query()->create(['name' => 'v4', 'display_order' => 4]);

        $this->actingAs($actor)->patchJson('/api/v1/groups/reorder', [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->assertDatabaseHas('groups', ['id' => $second->id, 'display_order' => 1]);
        $this->assertDatabaseHas('groups', ['id' => $hiddenFirst->id, 'display_order' => 2]);
        $this->assertDatabaseHas('groups', ['id' => $first->id, 'display_order' => 3]);
        $this->assertDatabaseHas('groups', ['id' => $hiddenSecond->id, 'display_order' => 4]);
    }

    public function test_group_issue_add_remove_reorder_and_cross_product_mixing(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $otherProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $first = $this->createIssue(['product_id' => $product->id, 'is_managed' => false, 'github_issue_number' => 201]);
        $second = $this->createIssue(['product_id' => $product->id, 'github_issue_number' => 202]);
        $otherIssue = $this->createIssue(['product_id' => $otherProduct->id, 'github_issue_number' => 301]);

        $this->actingAs($actor)->postJson("/api/v1/groups/{$group->id}/issues/{$first->id}")
            ->assertOk()
            ->assertJsonPath('id', $first->id)
            ->assertJsonPath('is_managed', true)
            ->assertJsonPath('product_name', 'Product A')
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
            ->assertOk()
            ->assertJsonPath('group.id', $group->id);

        $this->actingAs($actor)->deleteJson("/api/v1/groups/{$group->id}/issues/{$first->id}")->assertOk();
        $this->assertDatabaseMissing('group_issues', ['issue_id' => $first->id]);
    }

    public function test_group_issues_can_be_partially_reordered_without_moving_hidden_issues(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $first = $this->createIssue(['product_id' => $product->id]);
        $hiddenFirst = $this->createIssue(['product_id' => $product->id]);
        $second = $this->createIssue(['product_id' => $product->id]);
        $hiddenSecond = $this->createIssue(['product_id' => $product->id]);

        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $first->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $hiddenFirst->id, 'display_order' => 2]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $second->id, 'display_order' => 3]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $hiddenSecond->id, 'display_order' => 4]);

        $this->actingAs($actor)->patchJson("/api/v1/groups/{$group->id}/issues/reorder", [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->assertDatabaseHas('group_issues', ['issue_id' => $second->id, 'display_order' => 1]);
        $this->assertDatabaseHas('group_issues', ['issue_id' => $hiddenFirst->id, 'display_order' => 2]);
        $this->assertDatabaseHas('group_issues', ['issue_id' => $first->id, 'display_order' => 3]);
        $this->assertDatabaseHas('group_issues', ['issue_id' => $hiddenSecond->id, 'display_order' => 4]);
    }

    public function test_group_issues_can_be_moved_and_partially_reordered_without_moving_hidden_issues(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $sourceGroup = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $targetGroup = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $moved = $this->createIssue(['product_id' => $product->id]);
        $first = $this->createIssue(['product_id' => $product->id]);
        $hidden = $this->createIssue(['product_id' => $product->id]);
        $second = $this->createIssue(['product_id' => $product->id]);

        GroupIssue::query()->create(['group_id' => $sourceGroup->id, 'issue_id' => $moved->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $targetGroup->id, 'issue_id' => $first->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $targetGroup->id, 'issue_id' => $hidden->id, 'display_order' => 2]);
        GroupIssue::query()->create(['group_id' => $targetGroup->id, 'issue_id' => $second->id, 'display_order' => 3]);

        $this->actingAs($actor)->postJson("/api/v1/groups/{$targetGroup->id}/issues/{$moved->id}")
            ->assertOk();

        $this->actingAs($actor)->patchJson("/api/v1/groups/{$targetGroup->id}/issues/reorder", [
            'ordered_ids' => [$first->id, $moved->id, $second->id],
        ])->assertOk();

        $this->assertDatabaseHas('group_issues', ['issue_id' => $first->id, 'display_order' => 1]);
        $this->assertDatabaseHas('group_issues', ['issue_id' => $hidden->id, 'display_order' => 2]);
        $this->assertDatabaseHas('group_issues', ['issue_id' => $moved->id, 'display_order' => 3]);
        $this->assertDatabaseHas('group_issues', ['issue_id' => $second->id, 'display_order' => 4]);
        $this->assertDatabaseMissing('group_issues', [
            'group_id' => $sourceGroup->id,
            'issue_id' => $moved->id,
        ]);
    }

    public function test_group_issue_partial_reorder_rejects_invalid_foreign_and_duplicate_ids(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $otherGroup = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $issue = $this->createIssue(['product_id' => $product->id]);
        $otherIssue = $this->createIssue(['product_id' => $product->id]);

        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $issue->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $otherGroup->id, 'issue_id' => $otherIssue->id, 'display_order' => 1]);

        $this->actingAs($actor)->patchJson("/api/v1/groups/{$group->id}/issues/reorder", [
            'ordered_ids' => [999999],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ordered_ids']);

        $this->actingAs($actor)->patchJson("/api/v1/groups/{$group->id}/issues/reorder", [
            'ordered_ids' => [$otherIssue->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ordered_ids']);

        $this->actingAs($actor)->patchJson("/api/v1/groups/{$group->id}/issues/reorder", [
            'ordered_ids' => [$issue->id, $issue->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ordered_ids.1']);
    }

    public function test_issue_update_can_change_group_across_products(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $otherGroup = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $issue = $this->createIssue(['product_id' => $product->id]);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}", [
            'group_id' => $group->id,
        ])->assertOk()
            ->assertJsonPath('group.id', $group->id);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}", [
            'group_id' => $otherGroup->id,
        ])->assertOk()
            ->assertJsonPath('group.id', $otherGroup->id);
    }

    public function test_issue_update_keeps_group_order_and_returns_product_name(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $issue = $this->createIssue(['product_id' => $product->id, 'status_id' => 1]);

        GroupIssue::query()->create([
            'group_id' => $group->id,
            'issue_id' => $issue->id,
            'display_order' => 7,
        ]);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$issue->id}", [
            'status_id' => 2,
            'group_id' => $group->id,
        ])->assertOk()
            ->assertJsonPath('status_id', 2)
            ->assertJsonPath('product_name', 'Product A')
            ->assertJsonPath('group.id', $group->id);

        $this->assertDatabaseHas('group_issues', [
            'group_id' => $group->id,
            'issue_id' => $issue->id,
            'display_order' => 7,
        ]);
    }

    public function test_issue_update_and_toggle_clear_group_when_issue_becomes_unmanaged(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $updatedIssue = $this->createIssue(['product_id' => $product->id]);
        $toggledIssue = $this->createIssue(['product_id' => $product->id]);

        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $updatedIssue->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $toggledIssue->id, 'display_order' => 2]);

        $this->actingAs($actor)->putJson("/api/v1/issues/{$updatedIssue->id}", [
            'is_managed' => false,
            'group_id' => $group->id,
        ])->assertOk()
            ->assertJsonPath('is_managed', false)
            ->assertJsonPath('group', null);

        $this->actingAs($actor)->patchJson("/api/v1/issues/{$toggledIssue->id}/managed")
            ->assertOk()
            ->assertJsonPath('is_managed', false)
            ->assertJsonPath('group', null);

        $this->assertDatabaseMissing('group_issues', ['issue_id' => $updatedIssue->id]);
        $this->assertDatabaseMissing('group_issues', ['issue_id' => $toggledIssue->id]);
    }

    public function test_bulk_remove_from_managed_marks_issues_unmanaged_and_clears_groups(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $first = $this->createIssue(['product_id' => $product->id]);
        $second = $this->createIssue(['product_id' => $product->id]);

        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $first->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $second->id, 'display_order' => 2]);

        $this->actingAs($actor)->patchJson('/api/v1/issues/bulk/remove-from-managed', [
            'issue_ids' => [$first->id, $second->id],
        ])->assertOk()
            ->assertJsonPath('message', '選択したISSUEを管理表から外しました。');

        $this->assertDatabaseHas('issues', ['id' => $first->id, 'is_managed' => false]);
        $this->assertDatabaseHas('issues', ['id' => $second->id, 'is_managed' => false]);
        $this->assertDatabaseMissing('group_issues', ['issue_id' => $first->id]);
        $this->assertDatabaseMissing('group_issues', ['issue_id' => $second->id]);
    }

    public function test_bulk_group_moves_issues_to_group_in_selected_order(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $sourceGroup = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $targetGroup = Group::query()->create(['name' => 'v2', 'display_order' => 2]);
        $existing = $this->createIssue(['product_id' => $product->id]);
        $first = $this->createIssue(['product_id' => $product->id]);
        $second = $this->createIssue(['product_id' => $product->id, 'is_managed' => false]);

        GroupIssue::query()->create(['group_id' => $targetGroup->id, 'issue_id' => $existing->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $sourceGroup->id, 'issue_id' => $first->id, 'display_order' => 1]);

        $this->actingAs($actor)->patchJson('/api/v1/issues/bulk/group', [
            'issue_ids' => [$second->id, $first->id],
            'group_id' => $targetGroup->id,
        ])->assertOk()
            ->assertJsonPath('message', '選択したISSUEを移動しました。');

        $this->assertDatabaseHas('issues', ['id' => $first->id, 'is_managed' => true]);
        $this->assertDatabaseHas('issues', ['id' => $second->id, 'is_managed' => true]);
        $this->assertDatabaseHas('group_issues', [
            'group_id' => $targetGroup->id,
            'issue_id' => $second->id,
            'display_order' => 2,
        ]);
        $this->assertDatabaseHas('group_issues', [
            'group_id' => $targetGroup->id,
            'issue_id' => $first->id,
            'display_order' => 3,
        ]);
    }

    public function test_bulk_group_moves_issues_to_ungrouped_and_marks_them_managed(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $existingUngrouped = $this->createIssue(['product_id' => $product->id, 'display_order' => 10]);
        $first = $this->createIssue(['product_id' => $product->id, 'display_order' => 1]);
        $second = $this->createIssue(['product_id' => $product->id, 'display_order' => 2]);
        $unmanaged = $this->createIssue(['product_id' => $product->id, 'is_managed' => false, 'display_order' => 99]);

        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $first->id, 'display_order' => 1]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $second->id, 'display_order' => 2]);

        $this->actingAs($actor)->patchJson('/api/v1/issues/bulk/group', [
            'issue_ids' => [$second->id, $first->id, $unmanaged->id],
            'group_id' => null,
        ])->assertOk();

        $this->assertDatabaseHas('issues', ['id' => $existingUngrouped->id, 'display_order' => 10]);
        $this->assertDatabaseHas('issues', ['id' => $second->id, 'is_managed' => true, 'display_order' => 11]);
        $this->assertDatabaseHas('issues', ['id' => $first->id, 'is_managed' => true, 'display_order' => 12]);
        $this->assertDatabaseHas('issues', ['id' => $unmanaged->id, 'is_managed' => true, 'display_order' => 13]);
        $this->assertDatabaseMissing('group_issues', ['issue_id' => $first->id]);
        $this->assertDatabaseMissing('group_issues', ['issue_id' => $second->id]);
        $this->assertDatabaseMissing('group_issues', ['issue_id' => $unmanaged->id]);
    }

    public function test_ungrouped_issues_can_be_reordered_across_products(): void
    {
        $actor = User::factory()->create();
        $firstProduct = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $secondProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);
        $first = $this->createIssue(['product_id' => $firstProduct->id, 'display_order' => 1]);
        $second = $this->createIssue(['product_id' => $secondProduct->id, 'display_order' => 2]);

        $this->actingAs($actor)->patchJson('/api/v1/issues/ungrouped/reorder', [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertOk()
            ->assertJsonPath('message', '未グループISSUEの並び順を更新しました。');

        $this->assertDatabaseHas('issues', ['id' => $second->id, 'display_order' => 1]);
        $this->assertDatabaseHas('issues', ['id' => $first->id, 'display_order' => 2]);

        $this->actingAs($actor)->getJson('/api/v1/table')
            ->assertOk()
            ->assertJsonPath('ungrouped_issues.0.id', $second->id)
            ->assertJsonPath('ungrouped_issues.1.id', $first->id);
    }

    public function test_ungrouped_issues_can_be_partially_reordered_without_moving_hidden_issues(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $first = $this->createIssue(['product_id' => $product->id, 'display_order' => 1]);
        $hiddenFirst = $this->createIssue(['product_id' => $product->id, 'display_order' => 2]);
        $second = $this->createIssue(['product_id' => $product->id, 'display_order' => 3]);
        $hiddenSecond = $this->createIssue(['product_id' => $product->id, 'display_order' => 4]);

        $this->actingAs($actor)->patchJson('/api/v1/issues/ungrouped/reorder', [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->assertDatabaseHas('issues', ['id' => $second->id, 'display_order' => 1]);
        $this->assertDatabaseHas('issues', ['id' => $hiddenFirst->id, 'display_order' => 2]);
        $this->assertDatabaseHas('issues', ['id' => $first->id, 'display_order' => 3]);
        $this->assertDatabaseHas('issues', ['id' => $hiddenSecond->id, 'display_order' => 4]);
    }

    public function test_grouped_issue_can_be_moved_to_a_specific_ungrouped_position(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $first = $this->createIssue(['product_id' => $product->id, 'display_order' => 1]);
        $second = $this->createIssue(['product_id' => $product->id, 'display_order' => 2]);
        $moved = $this->createIssue(['product_id' => $product->id, 'display_order' => 99]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $moved->id, 'display_order' => 1]);

        $this->actingAs($actor)->deleteJson("/api/v1/groups/{$group->id}/issues/{$moved->id}")->assertOk();
        $this->actingAs($actor)->patchJson('/api/v1/issues/ungrouped/reorder', [
            'ordered_ids' => [$first->id, $moved->id, $second->id],
        ])->assertOk();

        $this->assertDatabaseHas('issues', ['id' => $first->id, 'display_order' => 1]);
        $this->assertDatabaseHas('issues', ['id' => $moved->id, 'display_order' => 2]);
        $this->assertDatabaseHas('issues', ['id' => $second->id, 'display_order' => 3]);
    }

    public function test_ungrouped_reorder_rejects_grouped_unmanaged_missing_and_duplicate_ids(): void
    {
        $actor = User::factory()->create();
        $product = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $group = Group::query()->create(['name' => 'v1', 'display_order' => 1]);
        $ungrouped = $this->createIssue(['product_id' => $product->id]);
        $grouped = $this->createIssue(['product_id' => $product->id]);
        $unmanaged = $this->createIssue(['product_id' => $product->id, 'is_managed' => false]);
        GroupIssue::query()->create(['group_id' => $group->id, 'issue_id' => $grouped->id, 'display_order' => 1]);

        foreach ([$grouped->id, $unmanaged->id, 999999] as $invalidId) {
            $this->actingAs($actor)->patchJson('/api/v1/issues/ungrouped/reorder', [
                'ordered_ids' => [$invalidId],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['ordered_ids']);
        }

        $this->actingAs($actor)->patchJson('/api/v1/issues/ungrouped/reorder', [
            'ordered_ids' => [$ungrouped->id, $ungrouped->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ordered_ids.1']);
    }

    public function test_bulk_issue_validation_returns_422(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->patchJson('/api/v1/issues/bulk/remove-from-managed', [
            'issue_ids' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['issue_ids']);

        $this->actingAs($actor)->patchJson('/api/v1/issues/bulk/remove-from-managed', [
            'issue_ids' => [999],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['issue_ids.0']);

        $this->actingAs($actor)->patchJson('/api/v1/issues/bulk/group', [
            'issue_ids' => [999],
            'group_id' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['issue_ids.0', 'group_id']);
    }

    public function test_group_validation_returns_422(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->postJson('/api/v1/groups', [
            'name' => '',
            'release_date' => '2026/05/31',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'release_date']);
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
