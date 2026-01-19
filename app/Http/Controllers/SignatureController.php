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
        return ApiResponse::fromService($this->signatureService->index((int) $request->user()->id));
    }

    /**
     * Upload/create a new signature
     */
    public function store(SignatureStoreRequest $request)
    {
        $user = $request->user();

        $result = $this->signatureService->store(
            (int) $user->id,
            (string) $user->email,
            $request->file('image'),
            $request->input('name'),
            $request->boolean('is_default')
        );

        return ApiResponse::fromService($result);
    }

    /**
     * Get signature image
     */
    public function getImage(Request $request, $id)
    {
        $user = $request->user();
        $result = $this->signatureService->getImage((int) $user->id, (int) $id);
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
        return ApiResponse::fromService($this->signatureService->setDefault((int) $user->id, (int) $id));
    }

    /**
     * Delete a signature
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        return ApiResponse::fromService($this->signatureService->destroy((int) $user->id, (int) $id));
    }

    /**
     * Get current tenant ID from session or user
     */
    private function getCurrentTenantId(Request $request): ?string
    {
        return session('current_tenant_id') ?? $request->user()->current_tenant_id;
    }
}
