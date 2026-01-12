<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\KycSubmitRequest;
use App\Services\KycService;

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
            (int) $user->id,
            $request->validated(),
            $request->file('id_photo'),
            $request->file('selfie_photo')
        );

        return ApiResponse::fromService($result);
    }
}
