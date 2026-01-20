<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\UpdateQuotaSettingsRequest;
use App\Http\Requests\UpdateQuotaUserOverrideRequest;
use App\Http\Resources\QuotaOverviewResource;
use App\Http\Resources\QuotaSettingsResource;
use App\Services\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class QuotaController extends Controller
{
    public function __construct(private readonly QuotaService $quotaService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->quotaService->overview($user);

        if (($result['status'] ?? 'success') !== 'success') {
            return ApiResponse::fromService($result);
        }

        $data = $result['data'] ?? [];
        return ApiResponse::success(new QuotaOverviewResource([
            'quotaSetting' => $data['quotaSetting'] ?? null,
            'usage' => $data['usage'] ?? [],
        ]));
    }

    public function update(UpdateQuotaSettingsRequest $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->quotaService->updateSettings($user, $request->validated());

        if (($result['status'] ?? 'success') !== 'success') {
            return ApiResponse::fromService($result);
        }

        return ApiResponse::success(
            new QuotaSettingsResource($result['data']),
            $result['message'] ?? 'OK'
        );
    }

    public function updateUserOverride(UpdateQuotaUserOverrideRequest $request, string $userId): JsonResponse
    {
        $user = Auth::user();
        $result = $this->quotaService->updateUserOverride($user, $userId, $request->validated());
        return ApiResponse::fromService($result);
    }
}
