<?php

namespace App\Services;

use App\Http\Resources\IssueResource;
use App\Models\Group;
use App\Models\Issue;
use Illuminate\Support\Collection;

class TableService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function table(array $filters): array
    {
        $groups = Group::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $allGroupedIssues = Issue::query()
            ->with(['director', 'engineer', 'product', 'schedule', 'groupIssue.group'])
            ->managed()
            ->applyFilters($filters)
            ->whereHas('groupIssue')
            ->join('group_issues', 'issues.id', '=', 'group_issues.issue_id')
            ->orderBy('group_issues.display_order')
            ->orderBy('issues.id')
            ->select('issues.*')
            ->get()
            ->groupBy(fn (Issue $issue): int => $issue->groupIssue->group_id);

        $hasFilters = $this->hasActiveFilters($filters);

        $groupsData = $groups
            ->map(function (Group $group) use ($allGroupedIssues): array {
                $issues = IssueResource::collection($allGroupedIssues->get($group->id, collect()))->resolve();

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'release_date' => $group->release_date?->toDateString(),
                    'display_order' => $group->display_order,
                    'issues' => $issues,
                ];
            })
            ->filter(fn (array $group): bool => ! $hasFilters || $group['issues'] !== [])
            ->values()
            ->all();

        return [
            'groups' => $groupsData,
            'ungrouped_issues' => IssueResource::collection($this->ungroupedIssues($filters))->resolve(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function ungroupedIssues(array $filters): Collection
    {
        return Issue::query()
            ->with(['director', 'engineer', 'product', 'schedule', 'groupIssue.group'])
            ->managed()
            ->applyFilters($filters)
            ->doesntHave('groupIssue')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasActiveFilters(array $filters): bool
    {
        return collect($filters)->contains(
            fn (mixed $value): bool => is_array($value)
                ? $value !== []
                : $value !== null && $value !== ''
        );
    }
}
