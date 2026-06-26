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
    public function list(): Collection
    {
        return Group::query()
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
            'display_order' => $this->nextDisplayOrder(),
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
        $orderedIds = collect($orderedIds)->map(fn (mixed $id): int => (int) $id)->all();
        $groups = Group::query()
            ->whereIn('id', $orderedIds)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        if ($groups->count() !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ordered_ids' => ['登録済みグループIDを指定してください。'],
            ]);
        }

        $displayOrders = $groups->pluck('display_order')->sort()->values()->all();

        DB::transaction(function () use ($orderedIds, $displayOrders): void {
            foreach ($orderedIds as $index => $id) {
                Group::query()->whereKey($id)->update(['display_order' => $displayOrders[$index]]);
            }
        });

        return $this->list();
    }

    private function nextDisplayOrder(): int
    {
        return (int) Group::query()->max('display_order') + 1;
    }
}
