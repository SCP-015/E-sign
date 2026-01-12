<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::fromService($this->userService->profile((int) $user->id));
    }
}
