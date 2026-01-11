<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StorePlacementsRequest;
use App\Http\Requests\UpdatePlacementRequest;
use App\Services\PlacementService;

class PlacementController extends Controller
{
    protected $placementService;

    public function __construct(PlacementService $placementService)
    {
        $this->placementService = $placementService;
    }

    /**
     * Save signature placement for a signer
     * POST /api/documents/{documentId}/placements
     */
    public function store(StorePlacementsRequest $request, $documentId)
    {
        $signerUserId = (int) $request->input('signerUserId');
        $result = $this->placementService->storePlacements(
            (int) $documentId,
            $signerUserId,
            $request->input('placements')
        );

        return ApiResponse::fromService($result);
    }

    /**
     * Update placement position
     * PUT /api/documents/{documentId}/placements/{placementId}
     */
    public function update(UpdatePlacementRequest $request, $documentId, $placementId)
    {
        $result = $this->placementService->updatePlacement(
            (int) $documentId,
            (int) $placementId,
            $request->only(['x', 'y', 'w', 'h'])
        );

        return ApiResponse::fromService($result);
    }

    /**
     * Get all placements for a document
     * GET /api/documents/{documentId}/placements
     */
    public function index($documentId)
    {
        $result = $this->placementService->getPlacements((int) $documentId);
        return ApiResponse::fromService($result);
    }
}
