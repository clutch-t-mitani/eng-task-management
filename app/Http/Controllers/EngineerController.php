<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreEngineerRequest;
use App\Http\Requests\UpdateEngineerRequest;
use App\Models\Engineer;
use App\Services\EngineerService;
use Illuminate\Http\JsonResponse;

class EngineerController extends Controller
{
    public function __construct(private readonly EngineerService $engineers) {}

    public function index(): JsonResponse
    {
        return response()->json($this->engineers->list());
    }

    public function store(StoreEngineerRequest $request): JsonResponse
    {
        return response()->json($this->engineers->create($request->validated()), 201);
    }

    public function update(UpdateEngineerRequest $request, Engineer $engineer): JsonResponse
    {
        return response()->json($this->engineers->update($engineer, $request->validated()));
    }

    public function destroy(Engineer $engineer): JsonResponse
    {
        $this->engineers->delete($engineer);

        return response()->json(['message' => 'エンジニアを削除しました。']);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        return response()->json($this->engineers->reorder($request->validated('ordered_ids')));
    }
}
