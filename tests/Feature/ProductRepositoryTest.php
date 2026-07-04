<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->product = Product::query()->create(['name' => 'Test Product', 'display_order' => 0]);
    }

    // --- 未認証 ---

    public function test_guest_cannot_access_show(): void
    {
        $this->getJson("/api/v1/products/{$this->product->id}/repository")
            ->assertUnauthorized();
    }

    public function test_guest_cannot_access_update(): void
    {
        $this->putJson("/api/v1/products/{$this->product->id}/repository", ['owner' => 'foo', 'repo' => 'bar'])
            ->assertUnauthorized();
    }

    public function test_guest_cannot_access_destroy(): void
    {
        $this->deleteJson("/api/v1/products/{$this->product->id}/repository")
            ->assertUnauthorized();
    }

    // --- show ---

    public function test_show_returns_404_when_not_configured(): void
    {
        $this->actingAs($this->actor)
            ->getJson("/api/v1/products/{$this->product->id}/repository")
            ->assertNotFound();
    }

    public function test_show_returns_repository(): void
    {
        ProductRepository::query()->create([
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'myrepo',
            'is_active' => true,
        ]);

        $this->actingAs($this->actor)
            ->getJson("/api/v1/products/{$this->product->id}/repository")
            ->assertOk()
            ->assertJsonPath('owner', 'acme')
            ->assertJsonPath('repo', 'myrepo')
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('last_synced_at', null)
            ->assertJsonPath('last_sync_status', null);
    }

    public function test_show_returns_inactive_repository(): void
    {
        ProductRepository::query()->create([
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'myrepo',
            'is_active' => false,
        ]);

        $this->actingAs($this->actor)
            ->getJson("/api/v1/products/{$this->product->id}/repository")
            ->assertOk()
            ->assertJsonPath('is_active', false);
    }

    // --- update ---

    public function test_update_creates_repository_with_lowercase_normalization(): void
    {
        $this->actingAs($this->actor)
            ->putJson("/api/v1/products/{$this->product->id}/repository", [
                'owner' => 'ACME',
                'repo' => 'MyRepo',
            ])
            ->assertOk()
            ->assertJsonPath('owner', 'acme')
            ->assertJsonPath('repo', 'myrepo')
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('product_repositories', [
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'myrepo',
            'is_active' => true,
        ]);
    }

    public function test_update_validates_owner_repo_format(): void
    {
        $this->actingAs($this->actor)
            ->putJson("/api/v1/products/{$this->product->id}/repository", [
                'owner' => 'invalid owner!',
                'repo' => 'valid-repo',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner']);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->actingAs($this->actor)
            ->putJson("/api/v1/products/{$this->product->id}/repository", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner', 'repo']);
    }

    public function test_update_validates_max_length(): void
    {
        $this->actingAs($this->actor)
            ->putJson("/api/v1/products/{$this->product->id}/repository", [
                'owner' => str_repeat('a', 101),
                'repo' => 'valid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner']);
    }

    public function test_update_returns_422_when_repo_taken_by_another_product(): void
    {
        $other = Product::query()->create(['name' => 'Other', 'display_order' => 1]);
        ProductRepository::query()->create([
            'product_id' => $other->id,
            'owner' => 'acme',
            'repo' => 'shared',
            'is_active' => true,
        ]);

        $this->actingAs($this->actor)
            ->putJson("/api/v1/products/{$this->product->id}/repository", [
                'owner' => 'acme',
                'repo' => 'shared',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['repo']);
    }

    public function test_update_returns_422_when_changing_repo_with_existing_issues(): void
    {
        ProductRepository::query()->create([
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'original',
            'is_active' => true,
        ]);

        Issue::query()->create([
            'product_id' => $this->product->id,
            'title' => 'Imported Issue',
            'github_url' => 'https://github.com/acme/original/issues/1',
            'github_issue_number' => 1,
            'github_state' => 'open',
            'github_synced_at' => now(),
            'display_order' => 1,
            'status_id' => 1,
            'is_managed' => false,
        ]);

        $this->actingAs($this->actor)
            ->putJson("/api/v1/products/{$this->product->id}/repository", [
                'owner' => 'acme',
                'repo' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['repo']);
    }

    public function test_update_allows_same_repo_when_issues_exist(): void
    {
        ProductRepository::query()->create([
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'original',
            'is_active' => false,
        ]);

        Issue::query()->create([
            'product_id' => $this->product->id,
            'title' => 'Imported',
            'github_url' => 'https://github.com/acme/original/issues/1',
            'github_issue_number' => 1,
            'github_state' => 'open',
            'github_synced_at' => now(),
            'display_order' => 1,
            'status_id' => 1,
            'is_managed' => false,
        ]);

        $this->actingAs($this->actor)
            ->putJson("/api/v1/products/{$this->product->id}/repository", [
                'owner' => 'acme',
                'repo' => 'original',
            ])
            ->assertOk()
            ->assertJsonPath('is_active', true);
    }

    public function test_update_reactivates_own_deactivated_repository(): void
    {
        ProductRepository::query()->create([
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'myrepo',
            'is_active' => false,
        ]);

        $this->actingAs($this->actor)
            ->putJson("/api/v1/products/{$this->product->id}/repository", [
                'owner' => 'acme',
                'repo' => 'myrepo',
            ])
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('product_repositories', [
            'product_id' => $this->product->id,
            'is_active' => true,
        ]);
    }

    // --- destroy ---

    public function test_destroy_sets_is_active_to_false_and_returns_204(): void
    {
        ProductRepository::query()->create([
            'product_id' => $this->product->id,
            'owner' => 'acme',
            'repo' => 'myrepo',
            'is_active' => true,
        ]);

        $this->actingAs($this->actor)
            ->deleteJson("/api/v1/products/{$this->product->id}/repository")
            ->assertNoContent();

        $this->assertDatabaseHas('product_repositories', [
            'product_id' => $this->product->id,
            'is_active' => false,
        ]);
    }

    public function test_destroy_is_noop_when_no_repository_configured(): void
    {
        $this->actingAs($this->actor)
            ->deleteJson("/api/v1/products/{$this->product->id}/repository")
            ->assertNoContent();
    }
}
