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
        $this->assertSameProduct($group, $issue);

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

        return $issue->load(['director', 'engineer', 'schedule', 'groupIssue.group']);
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
        $expectedIds = $group->groupIssues()
            ->orderBy('issue_id')
            ->pluck('issue_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $receivedIds = collect($orderedIds)->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

        if ($receivedIds !== $expectedIds) {
            throw ValidationException::withMessages([
                'ordered_ids' => ['並び順にはグループ内のISSUE IDをすべて指定してください。'],
            ]);
        }

        DB::transaction(function () use ($group, $orderedIds): void {
            foreach ($orderedIds as $index => $issueId) {
                GroupIssue::query()
                    ->where('group_id', $group->id)
                    ->where('issue_id', $issueId)
                    ->update(['display_order' => $index + 1]);
            }
        });

        return $group->groupIssues()->orderBy('display_order')->get();
    }

    private function assertSameProduct(Group $group, Issue $issue): void
    {
        if ((int) $group->product_id !== (int) $issue->product_id) {
            throw ValidationException::withMessages([
                'issue_id' => ['グループと同じプロダクトのISSUEを指定してください。'],
            ]);
        }
    }

    private function nextDisplayOrder(Group $group): int
    {
        return (int) $group->groupIssues()->max('display_order') + 1;
    }
}
