<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Product::query()
                ->orderBy('display_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $nextOrder = (int) Product::query()->max('display_order') + 1;

        $product = Product::query()->create([
            ...$request->validated(),
            'display_order' => $nextOrder,
        ]);

        return response()->json($product, 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json($product->refresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        if (Schema::hasTable('issues') && $product->issues()->exists()) {
            return response()->json([
                'message' => '関連するISSUEが存在するため削除できません。',
            ], 422);
        }

        $product->delete();

        return response()->json(['message' => 'プロダクトを削除しました。']);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $orderedIds = $request->validated('ordered_ids');
        $this->validateCompleteOrder($orderedIds);

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                Product::query()
                    ->whereKey($id)
                    ->update(['display_order' => $index + 1]);
            }
        });

        return $this->index();
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    private function validateCompleteOrder(array $orderedIds): void
    {
        $expectedIds = Product::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $receivedIds = collect($orderedIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($receivedIds !== $expectedIds) {
            throw ValidationException::withMessages([
                'ordered_ids' => ['並び順には登録済みプロダクトのIDをすべて指定してください。'],
            ]);
        }
    }
}
