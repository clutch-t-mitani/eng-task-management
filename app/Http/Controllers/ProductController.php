<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(): JsonResponse
    {
        return response()->json($this->products->list());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        return response()->json($this->products->create($request->validated()), 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        return response()->json($this->products->update($product, $request->validated()));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(['message' => 'プロダクトを削除しました。']);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        return response()->json($this->products->reorder($request->validated('ordered_ids')));
    }
}
