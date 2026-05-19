<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(private readonly SortOrderService $sortOrder) {}

    /**
     * @return Collection<int, Product>
     */
    public function list(): Collection
    {
        return Product::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Product
    {
        return Product::query()->create([
            ...$attributes,
            'display_order' => $this->nextDisplayOrder(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        if (Schema::hasTable('issues') && $product->issues()->exists()) {
            throw ValidationException::withMessages([
                'product' => ['関連するISSUEが存在するため削除できません。'],
            ]);
        }

        $product->delete();
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds): Collection
    {
        $this->sortOrder->updateCompleteOrder(Product::class, $orderedIds, 'プロダクト');

        return $this->list();
    }

    private function nextDisplayOrder(): int
    {
        return (int) Product::query()->max('display_order') + 1;
    }
}
