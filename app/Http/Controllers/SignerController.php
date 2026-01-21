<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreSignersRequest;
use App\Services\SignerService;

class SignerController extends Controller
{
    protected $signerService;

    public function __construct(SignerService $signerService)
    {
        $this->signerService = $signerService;
    }

    /**
     * Add signers to document
     * POST /api/documents/{documentId}/signers
     */
    public function store(StoreSignersRequest $request, $documentId)
    {
        $options = [
            'include_owner' => (bool) ($request->input('includeOwner') ?? $request->input('include_owner') ?? false),
            'owner_order' => $request->input('ownerOrder') ?? $request->input('owner_order'),
            'signing_mode' => $request->input('signingMode') ?? $request->input('signing_mode'),
        ];

        $result = $this->signerService->addSigners(
            $documentId,
            $request->user()->id,
            $request->input('signers'),
            $options
        );

        return ApiResponse::fromService($result);
    }

    /**
     * Get document signers
     * GET /api/documents/{documentId}/signers
     */
    public function index($documentId)
    {
        $result = $this->signerService->getSigners($documentId);
        return ApiResponse::fromService($result);
    }
}
