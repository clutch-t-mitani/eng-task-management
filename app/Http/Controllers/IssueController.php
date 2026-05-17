<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueIndexRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\UpdateIssueScheduleRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use App\Services\IssueService;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    public function __construct(private readonly IssueService $issues) {}

    public function index(IssueIndexRequest $request): JsonResponse
    {
        $issues = $this->issues->list(
            $request->validated(),
            $request->boolean('unmanaged_imports'),
            $request->has('is_managed') ? $request->boolean('is_managed') : null,
        );

        return response()->json(IssueResource::collection($issues)->resolve());
    }

    public function show(Issue $issue): JsonResponse
    {
        return response()->json(IssueResource::make($this->issues->loadForResponse($issue))->resolve());
    }

    public function update(UpdateIssueRequest $request, Issue $issue): JsonResponse
    {
        return response()->json(IssueResource::make($this->issues->update($issue, $request->validated()))->resolve());
    }

    public function updateStatus(UpdateIssueStatusRequest $request, Issue $issue): JsonResponse
    {
        return response()->json(IssueResource::make($this->issues->update($issue, $request->validated()))->resolve());
    }

    public function toggleManaged(Issue $issue): JsonResponse
    {
        return response()->json(IssueResource::make($this->issues->toggleManaged($issue))->resolve());
    }

    public function destroy(Issue $issue): JsonResponse
    {
        return response()->json(IssueResource::make($this->issues->removeFromManaged($issue))->resolve());
    }

    public function updateSchedule(UpdateIssueScheduleRequest $request, Issue $issue): JsonResponse
    {
        return response()->json(IssueResource::make($this->issues->updateSchedule($issue, $request->validated()))->resolve());
    }
}
