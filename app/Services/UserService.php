<?php

namespace App\Services;

use App\Http\Resources\UserResource;

class UserService
{
    public function profile(string $userId): array
    {
        $user = \App\Models\User::with('certificate')->findOrFail($userId);

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'OK',
            'data' => (new UserResource($user))->resolve(),
        ];
    }
}
