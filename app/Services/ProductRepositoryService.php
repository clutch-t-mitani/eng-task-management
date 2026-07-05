<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Product;
use App\Models\ProductRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductRepositoryService
{
    public function findByProduct(Product $product): ?ProductRepository
    {
        return ProductRepository::query()
            ->where('product_id', $product->id)
            ->first();
    }

    public function upsert(Product $product, string $owner, string $repo): ProductRepository
    {
        $owner = strtolower($owner);
        $repo = strtolower($repo);

        $conflicting = ProductRepository::query()
            ->where('owner', $owner)
            ->where('repo', $repo)
            ->where('product_id', '!=', $product->id)
            ->exists();

        if ($conflicting) {
            throw ValidationException::withMessages([
                'repo' => ['このリポジトリは他のプロダクトに登録されています。'],
            ]);
        }

        $current = $this->findByProduct($product);

        if ($current !== null && ($current->owner !== $owner || $current->repo !== $repo)) {
            $hasImportedIssues = Issue::query()
                ->where('product_id', $product->id)
                ->whereNotNull('github_issue_number')
                ->exists();

            if ($hasImportedIssues) {
                throw ValidationException::withMessages([
                    'repo' => ['GitHubのISSUEが取り込まれているため、リポジトリは変更できません。'],
                ]);
            }
        }

        try {
            return DB::transaction(function () use ($product, $owner, $repo): ProductRepository {
                return ProductRepository::query()->updateOrCreate(
                    ['product_id' => $product->id],
                    ['owner' => $owner, 'repo' => $repo, 'is_active' => true],
                );
            });
        } catch (UniqueConstraintViolationException) {
            // 事前チェック後に他プロダクトが同一owner/repoを登録した競合
            throw ValidationException::withMessages([
                'repo' => ['このリポジトリは他のプロダクトに登録されています。'],
            ]);
        }
    }

    public function deactivate(Product $product): void
    {
        $repo = $this->findByProduct($product);

        if ($repo !== null) {
            $repo->update(['is_active' => false]);
        }
    }
}
