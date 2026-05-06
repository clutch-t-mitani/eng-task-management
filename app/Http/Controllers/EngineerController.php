<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreEngineerRequest;
use App\Http\Requests\UpdateEngineerRequest;
use App\Models\Engineer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EngineerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Engineer::query()
                ->orderBy('display_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreEngineerRequest $request): JsonResponse
    {
        $nextOrder = (int) Engineer::query()->max('display_order') + 1;

        $engineer = Engineer::query()->create([
            'name' => $request->validated('name'),
            'display_order' => $nextOrder,
        ]);

        return response()->json($engineer, 201);
    }

    public function update(UpdateEngineerRequest $request, Engineer $engineer): JsonResponse
    {
        $engineer->update($request->validated());

        return response()->json($engineer->refresh());
    }

    public function destroy(Engineer $engineer): JsonResponse
    {
        $engineer->delete();

        return response()->json(['message' => 'エンジニアを削除しました。']);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $orderedIds = $request->validated('ordered_ids');
        $this->validateCompleteOrder($orderedIds);

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                Engineer::query()
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
        $expectedIds = Engineer::query()
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
                'ordered_ids' => ['並び順には登録済みエンジニアのIDをすべて指定してください。'],
            ]);
        }
    }
}
