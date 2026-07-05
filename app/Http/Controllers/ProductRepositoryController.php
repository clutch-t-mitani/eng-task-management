<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProductRepositoryRequest;
use App\Http\Resources\ProductRepositoryResource;
use App\Models\Product;
use App\Services\ProductRepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductRepositoryController extends Controller
{
    public function __construct(private readonly ProductRepositoryService $service) {}

    public function show(Product $product): JsonResponse
    {
        $repo = $this->service->findByProduct($product);

        if ($repo === null) {
            return response()->json(['message' => 'リポジトリ設定が見つかりません。'], 404);
        }

        return response()->json(ProductRepositoryResource::make($repo)->resolve());
    }

    public function update(UpdateProductRepositoryRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $repo = $this->service->upsert($product, $data['owner'], $data['repo']);

        return response()->json(ProductRepositoryResource::make($repo)->resolve());
    }

    public function destroy(Product $product): Response
    {
        $this->service->deactivate($product);

        return response()->noContent();
    }
}
