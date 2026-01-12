<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\CertificateService;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    public function issue(Request $request)
    {
        $user = $request->user();

        // Check if user already has an active certificate?
        // For MVP, allow multiple or just new one.

        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $result = $this->certificateService->issueCertificateResult((int) $user->id);
        return ApiResponse::fromService($result);
    }
}
