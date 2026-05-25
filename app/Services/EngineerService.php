<?php

namespace App\Services;

use App\Models\Engineer;
use Illuminate\Database\Eloquent\Collection;

class EngineerService
{
    public function __construct(private readonly SortOrderService $sortOrder) {}

    /**
     * @return Collection<int, Engineer>
     */
    public function list(): Collection
    {
        return Engineer::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Engineer
    {
        return Engineer::query()->create([
            'name' => $attributes['name'],
            'display_order' => $this->nextDisplayOrder(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Engineer $engineer, array $attributes): Engineer
    {
        $engineer->update($attributes);

        return $engineer->refresh();
    }

    public function delete(Engineer $engineer): void
    {
        $engineer->delete();
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds): Collection
    {
        $this->sortOrder->updateCompleteOrder(Engineer::class, $orderedIds, 'エンジニア');

        return $this->list();
    }

    private function nextDisplayOrder(): int
    {
        return (int) Engineer::query()->max('display_order') + 1;
    }
}
