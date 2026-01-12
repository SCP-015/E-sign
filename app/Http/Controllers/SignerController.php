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
        $result = $this->signerService->addSigners(
            (int) $documentId,
            (int) $request->user()->id,
            $request->input('signers')
        );

        return ApiResponse::fromService($result);
    }

    /**
     * Get document signers
     * GET /api/documents/{documentId}/signers
     */
    public function index($documentId)
    {
        $result = $this->signerService->getSigners((int) $documentId);
        return ApiResponse::fromService($result);
    }
}
