<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\UpdatePortalSettingsRequest;
use App\Http\Requests\UploadPortalBannerRequest;
use App\Http\Requests\UploadPortalLogoRequest;
use App\Http\Resources\PortalSettingsResource;
use App\Services\PortalSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PortalSettingsController extends Controller
{
    public function __construct(private readonly PortalSettingsService $portalSettingsService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = Auth::user();

        $result = $this->portalSettingsService->show($user);
        if (($result['status'] ?? 'success') !== 'success') {
            return ApiResponse::fromService($result);
        }

        return ApiResponse::success(new PortalSettingsResource($result['data']));
    }

    public function update(UpdatePortalSettingsRequest $request): JsonResponse
    {
        $user = Auth::user();

        $result = $this->portalSettingsService->update($user, $request->validated());
        if (($result['status'] ?? 'success') !== 'success') {
            return ApiResponse::fromService($result);
        }

        return ApiResponse::success(new PortalSettingsResource($result['data']), $result['message'] ?? 'OK');
    }

    public function uploadLogo(UploadPortalLogoRequest $request): JsonResponse
    {
        $user = Auth::user();

        $result = $this->portalSettingsService->uploadLogo($user, $request->file('logo'));
        return ApiResponse::fromService($result);
    }

    public function uploadBanner(UploadPortalBannerRequest $request): JsonResponse
    {
        $user = Auth::user();

        $result = $this->portalSettingsService->uploadBanner($user, $request->file('banner'));
        return ApiResponse::fromService($result);
    }
}
