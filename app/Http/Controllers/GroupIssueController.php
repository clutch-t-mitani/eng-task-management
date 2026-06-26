<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Resources\IssueResource;
use App\Models\Group;
use App\Models\Issue;
use App\Services\GroupIssueService;
use Illuminate\Http\JsonResponse;

class GroupIssueController extends Controller
{
    public function __construct(private readonly GroupIssueService $groupIssues) {}

    public function add(Group $group, Issue $issue): JsonResponse
    {
        return response()->json(IssueResource::make($this->groupIssues->add($group, $issue))->resolve());
    }

    public function remove(Group $group, Issue $issue): JsonResponse
    {
        $this->groupIssues->remove($group, $issue);

        return response()->json(['message' => 'ISSUEをグループから除外しました。']);
    }

    public function reorder(ReorderRequest $request, Group $group): JsonResponse
    {
        $this->groupIssues->reorder($group, $request->validated('ordered_ids'));

        return response()->json(['message' => 'ISSUEの並び順を更新しました。']);
    }
}
