<?php

namespace App\Http\Controllers;

use App\Http\Requests\GroupIndexRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Http\JsonResponse;

class GroupController extends Controller
{
    public function __construct(private readonly GroupService $groups) {}

    public function index(GroupIndexRequest $request): JsonResponse
    {
        return response()->json(GroupResource::collection(
            $this->groups->list($request->integer('product_id') ?: null)
        )->resolve());
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        return response()->json(GroupResource::make($this->groups->create($request->validated()))->resolve(), 201);
    }

    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        return response()->json(GroupResource::make($this->groups->update($group, $request->validated()))->resolve());
    }

    public function destroy(Group $group): JsonResponse
    {
        $this->groups->delete($group);

        return response()->json(['message' => 'グループを削除しました。']);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        return response()->json(GroupResource::collection($this->groups->reorder($request->validated('ordered_ids')))->resolve());
    }
}
