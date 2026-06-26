<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupIssue;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueService
{
    private const RESPONSE_RELATIONS = ['director', 'engineer', 'product', 'schedule', 'groupIssue.group'];

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Issue>
     */
    public function list(array $filters, bool $unmanagedImports, ?bool $isManaged): Collection
    {
        return Issue::query()
            ->with(self::RESPONSE_RELATIONS)
            ->applyFilters($filters)
            ->when(
                $unmanagedImports,
                fn ($query) => $query->unmanagedImports(),
                fn ($query) => $query->when($isManaged !== null, fn ($query) => $query->where('is_managed', $isManaged)),
            )
            ->orderBy('product_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Issue $issue, array $attributes): Issue
    {
        $updatesGroup = array_key_exists('group_id', $attributes);
        $groupId = $attributes['group_id'] ?? null;
        unset($attributes['group_id']);

        DB::transaction(function () use ($issue, $attributes, $updatesGroup, $groupId): void {
            $issue->update($attributes);
            $issue->refresh();

            if (! $issue->is_managed) {
                $issue->groupIssue()->delete();
            } elseif ($updatesGroup) {
                $this->updateGroup($issue, $groupId);
            }
        });

        return $this->loadForResponse($issue);
    }

    public function toggleManaged(Issue $issue): Issue
    {
        DB::transaction(function () use ($issue): void {
            $issue->update(['is_managed' => ! $issue->is_managed]);

            if (! $issue->is_managed) {
                $issue->groupIssue()->delete();
            }
        });

        return $this->loadForResponse($issue);
    }

    public function removeFromManaged(Issue $issue): Issue
    {
        DB::transaction(function () use ($issue): void {
            $issue->groupIssue()->delete();
            $issue->update(['is_managed' => false]);
        });

        return $this->loadForResponse($issue);
    }

    /**
     * @param  array<int, int>  $issueIds
     */
    public function bulkRemoveFromManaged(array $issueIds): void
    {
        DB::transaction(function () use ($issueIds): void {
            GroupIssue::query()->whereIn('issue_id', $issueIds)->delete();
            Issue::query()->whereIn('id', $issueIds)->update(['is_managed' => false]);
        });
    }

    /**
     * @param  array<int, int>  $issueIds
     */
    public function bulkUpdateGroup(array $issueIds, mixed $groupId): void
    {
        DB::transaction(function () use ($issueIds, $groupId): void {
            if ($groupId === null) {
                GroupIssue::query()->whereIn('issue_id', $issueIds)->delete();
                $nextOrder = (int) Issue::query()
                    ->managed()
                    ->doesntHave('groupIssue')
                    ->whereNotIn('id', $issueIds)
                    ->max('display_order') + 1;

                foreach ($issueIds as $issueId) {
                    Issue::query()->whereKey($issueId)->update([
                        'is_managed' => true,
                        'display_order' => $nextOrder++,
                    ]);
                }

                return;
            }

            $group = Group::query()->findOrFail($groupId);
            $nextOrder = (int) $group->groupIssues()->max('display_order') + 1;

            Issue::query()->whereIn('id', $issueIds)->update(['is_managed' => true]);

            foreach ($issueIds as $issueId) {
                GroupIssue::query()->updateOrCreate(
                    ['issue_id' => $issueId],
                    [
                        'group_id' => $group->id,
                        'display_order' => $nextOrder++,
                    ],
                );
            }
        });
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorderUngrouped(array $orderedIds): void
    {
        $orderedIds = collect($orderedIds)->map(fn (mixed $id): int => (int) $id)->all();
        $ungroupedIssues = Issue::query()
            ->managed()
            ->doesntHave('groupIssue')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $ungroupedIds = $ungroupedIssues->pluck('id')->map(fn (mixed $id): int => (int) $id);

        $hasDuplicateIds = count($orderedIds) !== count(array_unique($orderedIds));
        $hasInvalidIds = collect($orderedIds)->contains(fn (int $id): bool => ! $ungroupedIds->contains($id));

        if ($hasDuplicateIds || $hasInvalidIds) {
            throw ValidationException::withMessages([
                'ordered_ids' => ['並び順には管理対象かつ未グループのISSUE IDを指定してください。'],
            ]);
        }

        $orderedIdQueue = collect($orderedIds);
        $orderedIdSet = array_fill_keys($orderedIds, true);
        $newOrder = $ungroupedIds
            ->map(fn (int $id): int => isset($orderedIdSet[$id]) ? (int) $orderedIdQueue->shift() : $id)
            ->all();

        DB::transaction(function () use ($newOrder): void {
            foreach ($newOrder as $index => $issueId) {
                Issue::query()->whereKey($issueId)->update(['display_order' => $index + 1]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateSchedule(Issue $issue, array $attributes): Issue
    {
        DB::transaction(function () use ($issue, $attributes): void {
            $issue->schedule()->updateOrCreate(
                ['issue_id' => $issue->id],
                $attributes,
            );
        });

        return $this->loadForResponse($issue);
    }

    public function loadForResponse(Issue $issue): Issue
    {
        return $issue->load(self::RESPONSE_RELATIONS);
    }

    private function updateGroup(Issue $issue, mixed $groupId): void
    {
        if ($groupId === null) {
            $issue->groupIssue()->delete();

            return;
        }

        $currentGroupIssue = $issue->groupIssue()->first();

        if ((int) $currentGroupIssue?->group_id === (int) $groupId) {
            return;
        }

        $group = Group::query()->findOrFail($groupId);

        $nextOrder = (int) $group->groupIssues()->max('display_order') + 1;

        $issue->groupIssue()->updateOrCreate(
            ['issue_id' => $issue->id],
            [
                'group_id' => $group->id,
                'display_order' => $nextOrder,
            ],
        );
    }
}
