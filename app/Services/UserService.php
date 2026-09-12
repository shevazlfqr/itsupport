<?php

namespace App\Services;

use App\Models\User;
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
        $user->update(['active' => ! $user->active]);

        return $user->fresh();
    }
}
