<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\KycSubmitRequest;
use App\Services\KycService;
use Illuminate\Http\Request;

class KycController extends Controller
{
    protected $kycService;

    public function __construct(KycService $kycService)
    {
        $this->kycService = $kycService;
    }

    public function submit(KycSubmitRequest $request)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $result = $this->kycService->submitKycResult(
            $user->id,
            $request->validated(),
            $request->file('id_photo'),
            $request->file('selfie_photo')
        );

        return ApiResponse::fromService($result);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::fromService($this->kycService->getMyKycResult($user->id));
    }

    public function myFile(Request $request, string $type)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $result = $this->kycService->getMyKycFileResult($user->id, $type);
        if (($result['status'] ?? 'error') !== 'success') {
            return ApiResponse::fromService($result);
        }

        $content = $result['data']['content'] ?? '';
        $mimeType = $result['data']['mimeType'] ?? 'application/octet-stream';

        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
