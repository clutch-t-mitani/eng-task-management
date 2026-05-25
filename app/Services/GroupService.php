<?php

namespace App\Services;

use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupService
{
    /**
     * @return Collection<int, Group>
     */
    public function list(?int $productId = null): Collection
    {
        return Group::query()
            ->when($productId, fn ($query) => $query->where('product_id', $productId))
            ->orderBy('product_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Group
    {
        return Group::query()->create([
            ...$attributes,
            'display_order' => $this->nextDisplayOrder((int) $attributes['product_id']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Group $group, array $attributes): Group
    {
        $group->update($attributes);

        return $group->refresh();
    }

    public function delete(Group $group): void
    {
        $group->delete();
    }

    /**
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, Group>
     */
    public function reorder(array $orderedIds): Collection
    {
        $groups = Group::query()->whereIn('id', $orderedIds)->get();
        $productIds = $groups->pluck('product_id')->unique()->values();

        if ($groups->count() !== count($orderedIds) || $productIds->count() !== 1) {
            throw ValidationException::withMessages([
                'ordered_ids' => ['同一プロダクトの登録済みグループIDをすべて指定してください。'],
            ]);
        }

        $productId = (int) $productIds->first();
        $expectedIds = Group::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $receivedIds = collect($orderedIds)->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

        if ($receivedIds !== $expectedIds) {
            throw ValidationException::withMessages([
                'ordered_ids' => ['同一プロダクトの登録済みグループIDをすべて指定してください。'],
            ]);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                Group::query()->whereKey($id)->update(['display_order' => $index + 1]);
            }
        });

        return $this->list($productId);
    }

    private function nextDisplayOrder(int $productId): int
    {
        return (int) Group::query()->where('product_id', $productId)->max('display_order') + 1;
    }
}
