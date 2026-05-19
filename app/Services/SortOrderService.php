<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SortOrderService
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $orderedIds
     */
    public function updateCompleteOrder(string $modelClass, array $orderedIds, string $attributeLabel): void
    {
        $this->validateCompleteOrder($modelClass, $orderedIds, $attributeLabel);

        DB::transaction(function () use ($modelClass, $orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                $modelClass::query()
                    ->whereKey($id)
                    ->update(['display_order' => $index + 1]);
            }
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $orderedIds
     */
    private function validateCompleteOrder(string $modelClass, array $orderedIds, string $attributeLabel): void
    {
        $expectedIds = $modelClass::query()
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
                'ordered_ids' => ["並び順には登録済み{$attributeLabel}のIDをすべて指定してください。"],
            ]);
        }
    }
}
