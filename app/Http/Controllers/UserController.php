<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(): JsonResponse
    {
        return response()->json(UserResource::collection($this->users->list())->resolve());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return response()->json(UserResource::make($this->users->create($request->validated()))->resolve(), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(UserResource::make($user)->resolve());
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return response()->json(UserResource::make($this->users->update($user, $request->validated()))->resolve());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->users->delete($actor, $user);

        return response()->json(['message' => 'ユーザーを削除しました。']);
    }
}
