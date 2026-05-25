<?php

namespace App\Services;

use App\Http\Resources\IssueResource;
use App\Models\Group;
use App\Models\Issue;

class TableService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function table(array $filters): array
    {
        $groups = Group::query()
            ->when($filters['product_id'] ?? null, function ($query, mixed $value) {
                return is_array($value)
                    ? $query->whereIn('product_id', $value)
                    : $query->where('product_id', $value);
            })
            ->orderBy('product_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(function (Group $group) use ($filters): array {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'release_date' => $group->release_date?->toDateString(),
                    'display_order' => $group->display_order,
                    'product_id' => $group->product_id,
                    'issues' => IssueResource::collection($this->groupIssues($group, $filters))->resolve(),
                ];
            })
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'ungrouped_issues' => IssueResource::collection($this->ungroupedIssues($filters))->resolve(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function groupIssues(Group $group, array $filters)
    {
        return Issue::query()
            ->with(['director', 'engineer', 'schedule', 'groupIssue.group'])
            ->managed()
            ->applyFilters($filters)
            ->whereHas('groupIssue', fn ($query) => $query->where('group_id', $group->id))
            ->join('group_issues', 'issues.id', '=', 'group_issues.issue_id')
            ->orderBy('group_issues.display_order')
            ->orderBy('issues.id')
            ->select('issues.*')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function ungroupedIssues(array $filters)
    {
        return Issue::query()
            ->with(['director', 'engineer', 'schedule', 'groupIssue.group'])
            ->managed()
            ->applyFilters($filters)
            ->doesntHave('groupIssue')
            ->orderBy('product_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }
}
