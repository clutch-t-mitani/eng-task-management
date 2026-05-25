<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        return User::query()
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        if (blank($attributes['password'] ?? null)) {
            $attributes = Arr::except($attributes, ['password']);
        }

        $user->update($attributes);

        return $user->refresh();
    }

    public function delete(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['ログイン中のユーザー自身は削除できません。'],
            ]);
        }

        if (User::query()->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => ['最後のユーザーは削除できません。'],
            ]);
        }

        $user->delete();
    }
}
