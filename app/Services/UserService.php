<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(array $attributes): User
    {
        $attributes['email'] = strtolower(trim($attributes['email']));
        $attributes['password'] = Hash::make($attributes['password']);

        return User::create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $attributes['email'] = strtolower(trim($attributes['email']));
        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        } else {
            $attributes['password'] = Hash::make($attributes['password']);
        }

        $user->update($attributes);

        return $user->fresh();
    }

    public function toggle(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            $account->active = ! $account->active;
            $account->session_version = (int) $account->session_version + 1;
            $account->save();

            return $account;
        });
    }
}
