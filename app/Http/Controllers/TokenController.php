<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\TokenService;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function __construct(private readonly TokenService $tokenService)
    {
    }

    public function getToken(Request $request)
    {
        return ApiResponse::fromService($this->tokenService->getToken());
    }
}
