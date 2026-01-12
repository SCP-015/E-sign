<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StorePlacementsRequest;
use App\Services\PlacementService;
use Illuminate\Http\Request;

class PlacementController extends Controller
{
    protected $placementService;

    public function __construct(PlacementService $placementService)
    {
        $this->placementService = $placementService;
    }

    /**
     * Save signature placement for a signer
     * POST /api/v1/documents/{documentId}/placements
     */
    public function store(StorePlacementsRequest $request, $documentId)
    {
        $result = $this->placementService->storePlacements(
            (int) $documentId,
            $request->input('signerUserId') ? (int) $request->input('signerUserId') : null,
            $request->input('email'),
            $request->input('placements')
        );

        return ApiResponse::fromService($result);
    }

    /**
     * Update placement position
     * PUT /api/v1/documents/{documentId}/placements/{placementId}
     */
    public function update(Request $request, $documentId, $placementId)
    {
        $request->validate([
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
            'w' => 'nullable|numeric',
            'h' => 'nullable|numeric',
        ]);

        $result = $this->placementService->updatePlacement(
            (int) $documentId,
            (int) $placementId,
            $request->only(['x', 'y', 'w', 'h'])
        );

        return ApiResponse::fromService($result);
    }

    /**
     * Get all placements for a document
     * GET /api/v1/documents/{documentId}/placements
     */
    public function index($documentId)
    {
        $result = $this->placementService->getPlacements((int) $documentId);
        return ApiResponse::fromService($result);
    }
}
