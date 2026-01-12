<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\VerifyDocumentRequest;
use App\Services\VerificationService;

class VerificationController extends Controller
{
    protected $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    public function verify(VerifyDocumentRequest $request)
    {
        $result = $this->verificationService->verifyResult((int) $request->input('document_id'));
        return ApiResponse::fromService($result);
    }
}
