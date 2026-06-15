<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupIssue;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupIssueService
{
    public function add(Group $group, Issue $issue): Issue
    {
        DB::transaction(function () use ($group, $issue): void {
            $issue->update(['is_managed' => true]);
            $issue->groupIssue()->updateOrCreate(
                ['issue_id' => $issue->id],
                [
                    'group_id' => $group->id,
                    'display_order' => $this->nextDisplayOrder($group),
                ],
            );
        });

        return $issue->load(['product', 'director', 'engineer', 'schedule', 'groupIssue.group']);
    }

    public function remove(Group $group, Issue $issue): void
    {
        GroupIssue::query()
            ->where('group_id', $group->id)
            ->where('issue_id', $issue->id)
            ->delete();
    }

    /**
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, GroupIssue>
     */
    public function reorder(Group $group, array $orderedIds): Collection
    {
        $orderedIds = collect($orderedIds)->map(fn (mixed $id): int => (int) $id)->all();
        $groupIssues = $group->groupIssues()
            ->whereIn('issue_id', $orderedIds)
            ->orderBy('display_order')
            ->orderBy('issue_id')
            ->get();

        if ($groupIssues->count() !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ordered_ids' => ['並び順には対象グループに所属するISSUE IDを指定してください。'],
            ]);
        }

        $displayOrders = $groupIssues->pluck('display_order')->sort()->values()->all();

        DB::transaction(function () use ($group, $orderedIds, $displayOrders): void {
            foreach ($orderedIds as $index => $issueId) {
                GroupIssue::query()
                    ->where('group_id', $group->id)
                    ->where('issue_id', $issueId)
                    ->update(['display_order' => $displayOrders[$index]]);
            }
        });

        return $group->groupIssues()->orderBy('display_order')->get();
    }

    private function nextDisplayOrder(Group $group): int
    {
        return (int) $group->groupIssues()->max('display_order') + 1;
    }
}
