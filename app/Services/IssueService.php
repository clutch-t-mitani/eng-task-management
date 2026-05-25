<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueService
{
    private const RESPONSE_RELATIONS = ['director', 'engineer', 'schedule', 'groupIssue.group'];

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

            if ($updatesGroup) {
                $this->updateGroup($issue->refresh(), $groupId);
            }
        });

        return $this->loadForResponse($issue);
    }

    public function toggleManaged(Issue $issue): Issue
    {
        $issue->update(['is_managed' => ! $issue->is_managed]);

        return $this->loadForResponse($issue);
    }

    public function removeFromManaged(Issue $issue): Issue
    {
        $issue->update(['is_managed' => false]);

        return $this->loadForResponse($issue);
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

        $group = Group::query()->findOrFail($groupId);

        if ((int) $group->product_id !== (int) $issue->product_id) {
            throw ValidationException::withMessages([
                'group_id' => ['ISSUEと同じプロダクトのグループを指定してください。'],
            ]);
        }

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
