<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueIndexRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\UpdateIssueScheduleRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    public function index(IssueIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $issues = Issue::query()
            ->with(['director', 'engineer', 'schedule'])
            ->applyFilters($filters)
            ->when(
                $request->boolean('unmanaged_imports'),
                fn ($query) => $query->unmanagedImports(),
                fn ($query) => $query->when($request->has('is_managed'), fn ($query) => $query->where('is_managed', $request->boolean('is_managed'))),
            )
            ->orderBy('product_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return response()->json(IssueResource::collection($issues)->resolve());
    }

    public function show(Issue $issue): JsonResponse
    {
        $issue->load(['director', 'engineer', 'schedule']);

        return response()->json(IssueResource::make($issue)->resolve());
    }

    public function update(UpdateIssueRequest $request, Issue $issue): JsonResponse
    {
        $issue->update($request->validated());
        $issue->load(['director', 'engineer', 'schedule']);

        return response()->json(IssueResource::make($issue)->resolve());
    }

    public function updateStatus(UpdateIssueStatusRequest $request, Issue $issue): JsonResponse
    {
        $issue->update($request->validated());
        $issue->load(['director', 'engineer', 'schedule']);

        return response()->json(IssueResource::make($issue)->resolve());
    }

    public function toggleManaged(Issue $issue): JsonResponse
    {
        $issue->update(['is_managed' => ! $issue->is_managed]);
        $issue->load(['director', 'engineer', 'schedule']);

        return response()->json(IssueResource::make($issue)->resolve());
    }

    public function destroy(Issue $issue): JsonResponse
    {
        $issue->update(['is_managed' => false]);
        $issue->load(['director', 'engineer', 'schedule']);

        return response()->json(IssueResource::make($issue)->resolve());
    }

    public function updateSchedule(UpdateIssueScheduleRequest $request, Issue $issue): JsonResponse
    {
        $issue->schedule()->updateOrCreate(
            ['issue_id' => $issue->id],
            $request->validated(),
        );

        $issue->load(['director', 'engineer', 'schedule']);

        return response()->json(IssueResource::make($issue)->resolve());
    }
}
