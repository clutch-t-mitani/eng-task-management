<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\IssueSchedule;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-2: issues / issue_schedules のスキーマ制約を固定するテスト。
 * これらの制約はGitHub連携の冪等Upsertが依存する最終防衛線である。
 */
class SchemaConstraintTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::query()->create(['name' => 'Test Product', 'display_order' => 0]);
    }

    // --- issues.(product_id, github_issue_number) UNIQUE ---

    public function test_issues_product_github_issue_number_unique_constraint_exists(): void
    {
        Issue::query()->create($this->issueAttributes(number: 1));

        $this->expectException(UniqueConstraintViolationException::class);

        Issue::query()->create($this->issueAttributes(number: 1));
    }

    public function test_same_github_issue_number_in_different_products_is_allowed(): void
    {
        $other = Product::query()->create(['name' => 'Other Product', 'display_order' => 1]);

        Issue::query()->create($this->issueAttributes(number: 1, productId: $this->product->id));
        Issue::query()->create($this->issueAttributes(number: 1, productId: $other->id));

        $this->assertDatabaseCount('issues', 2);
    }

    public function test_different_github_issue_numbers_in_same_product_are_allowed(): void
    {
        Issue::query()->create($this->issueAttributes(number: 1));
        Issue::query()->create($this->issueAttributes(number: 2));

        $this->assertDatabaseCount('issues', 2);
    }

    // --- issue_schedules.issue_id UNIQUE（1 Issue : 1 Schedule） ---

    public function test_issue_schedules_issue_id_unique_constraint_exists(): void
    {
        $issue = Issue::query()->create($this->issueAttributes(number: 10));
        IssueSchedule::query()->create(['issue_id' => $issue->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        IssueSchedule::query()->create(['issue_id' => $issue->id]);
    }

    public function test_different_issues_can_each_have_one_schedule(): void
    {
        $issue1 = Issue::query()->create($this->issueAttributes(number: 1));
        $issue2 = Issue::query()->create($this->issueAttributes(number: 2));

        IssueSchedule::query()->create(['issue_id' => $issue1->id]);
        IssueSchedule::query()->create(['issue_id' => $issue2->id]);

        $this->assertDatabaseCount('issue_schedules', 2);
    }

    // --- ヘルパー ---

    /**
     * @return array<string, mixed>
     */
    private function issueAttributes(int $number, ?int $productId = null): array
    {
        return [
            'product_id'          => $productId ?? $this->product->id,
            'title'               => "Issue #{$number}",
            'github_url'          => "https://github.com/acme/repo/issues/{$number}",
            'github_issue_number' => $number,
            'github_state'        => 'open',
            'github_synced_at'    => now(),
            'display_order'       => $number,
            'status_id'           => 1,
            'is_managed'          => false,
        ];
    }
}
