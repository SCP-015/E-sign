<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\VerifyUploadRequest;
use App\Services\PublicVerifyService;

class VerifyController extends Controller
{
    protected $publicVerifyService;

    public function __construct(PublicVerifyService $publicVerifyService)
    {
        $this->publicVerifyService = $publicVerifyService;
    }

    public function upload(VerifyUploadRequest $request)
    {
        $file = $request->file('file');

        $result = $this->publicVerifyService->verifyUpload($file);
        return ApiResponse::fromService($result);
    }

    /**
     * Public verify endpoint (no auth required)
     * GET /api/verify/{token}
     */
    public function verify($token)
    {
        $result = $this->publicVerifyService->verifyToken((string) $token);
        return ApiResponse::fromService($result);
    }
}
