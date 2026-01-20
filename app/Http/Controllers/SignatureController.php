<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\SignatureStoreRequest;
use App\Services\SignatureService;
use Illuminate\Http\Request;

class SignatureController extends Controller
{
    public function __construct(private readonly SignatureService $signatureService)
    {
    }

    /**
     * Get all signatures for authenticated user
     */
    public function index(Request $request)
    {
        return ApiResponse::fromService($this->signatureService->index($request->user()->id));
    }

    /**
     * Upload/create a new signature
     */
    public function store(SignatureStoreRequest $request)
    {
        $user = $request->user();
        $tenantId = $this->getCurrentTenantId($request);

        $result = $this->signatureService->store(
            $user->id,
            (string) $user->email,
            $request->file('image'),
            $request->input('name'),
            $request->boolean('is_default'),
            $tenantId
        );

        return ApiResponse::fromService($result);
    }

    /**
     * Get signature image
     */
    public function getImage(Request $request, $id)
    {
        $user = $request->user();
        $result = $this->signatureService->getImage($user->id, $id);
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

    /**
     * Set signature as default
     */
    public function setDefault(Request $request, $id)
    {
        $user = $request->user();
        return ApiResponse::fromService($this->signatureService->setDefault($user->id, $id));
    }

    /**
     * Delete a signature
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        return ApiResponse::fromService($this->signatureService->destroy($user->id, $id));
    }

    /**
     * Get current tenant ID from session or user
     */
    private function getCurrentTenantId(Request $request): ?string
    {
        return session('current_tenant_id') ?? $request->user()->current_tenant_id;
    }
}
