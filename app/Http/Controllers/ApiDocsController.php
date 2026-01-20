<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\ApiDocsResource;
use App\Services\ApiDocsService;
use Illuminate\Http\JsonResponse;

class ApiDocsController extends Controller
{
    protected ApiDocsService $apiDocsService;

    public function __construct(ApiDocsService $apiDocsService)
    {
        $this->apiDocsService = $apiDocsService;
    }

    public function index(): JsonResponse
    {
        $spec = $this->apiDocsService->getSpec();
        return ApiResponse::success(new ApiDocsResource($spec), 'OK');
    }
}
