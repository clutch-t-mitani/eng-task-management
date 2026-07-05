<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncLogIndexRequest;
use App\Http\Resources\SyncLogResource;
use App\Models\Product;
use App\Services\GitHubSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SyncController extends Controller
{
    public function __construct(private readonly GitHubSyncService $sync) {}

    public function store(Request $request, Product $product): JsonResponse
    {
        try {
            $syncLog = $this->sync->sync($product, $request->user()->id);
        } catch (ConflictHttpException) {
            return response()->json(['message' => 'この製品の同期が既に実行中です。'], 409);
        }

        return response()->json(SyncLogResource::make($syncLog)->resolve());
    }

    public function index(SyncLogIndexRequest $request, Product $product): JsonResponse
    {
        $limit = (int) $request->validated('limit', 20);

        $logs = $product->syncLogs()
            ->with('triggeredByUser')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json(['data' => SyncLogResource::collection($logs)->resolve()]);
    }
}
