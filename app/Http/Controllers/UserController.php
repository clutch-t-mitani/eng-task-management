<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()
                ->orderBy('id')
                ->get()
                ->map(fn (User $user): array => $this->serialize($user))
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return response()->json($this->serialize($user), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($this->serialize($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            $data = Arr::except($data, ['password']);
        }

        $user->update($data);

        return response()->json($this->serialize($user->refresh()));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->is($user)) {
            return response()->json([
                'message' => 'ログイン中のユーザー自身は削除できません。',
            ], 422);
        }

        if (User::query()->count() <= 1) {
            return response()->json([
                'message' => '最後のユーザーは削除できません。',
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'ユーザーを削除しました。']);
    }

    /**
     * @return array{id:int,name:string,email:string}
     */
    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
