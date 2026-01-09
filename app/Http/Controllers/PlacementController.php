<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignaturePlacement;
use App\Models\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlacementController extends Controller
{
    /**
     * Save signature placement for a signer
     * POST /api/v1/documents/{documentId}/placements
     */
    public function store(Request $request, $documentId)
    {
        $request->validate([
            'signerUserId' => 'required|exists:users,id',
            'placements' => 'required|array|min:1',
            'placements.*.page' => 'required|integer|min:1',
            'placements.*.x' => 'required|numeric',
            'placements.*.y' => 'required|numeric',
            'placements.*.w' => 'required|numeric',
            'placements.*.h' => 'required|numeric',
            'placements.*.signatureId' => 'required|exists:signatures,id',
        ]);

        $document = Document::findOrFail($documentId);
        $signerUserId = $request->input('signerUserId');
        
        // Find or create signer
        $signer = DocumentSigner::where('document_id', $documentId)
            ->where('user_id', $signerUserId)
            ->first();
        
        if (!$signer) {
            // Auto-create signer if doesn't exist
            $signerUser = \App\Models\User::findOrFail($signerUserId);
            $signer = DocumentSigner::create([
                'document_id' => $documentId,
                'user_id' => $signerUserId,
                'name' => $signerUser->name,
                'status' => 'PENDING',
            ]);
            
            // Update document status to IN_PROGRESS
            $document->update(['status' => 'IN_PROGRESS']);
        }

        DB::beginTransaction();
        try {
            // Delete existing placements for this signer
            SignaturePlacement::where('document_id', $documentId)
                ->where('signer_id', $signer->id)
                ->delete();

            $placements = [];
            foreach ($request->input('placements') as $placementData) {
                // Verify signature belongs to user
                $signature = Signature::where('id', $placementData['signatureId'])
                    ->where('user_id', $request->input('signerUserId'))
                    ->firstOrFail();

                $placement = SignaturePlacement::create([
                    'document_id' => $documentId,
                    'signer_id' => $signer->id,
                    'signature_id' => $signature->id,
                    'page' => $placementData['page'],
                    'x' => $placementData['x'],
                    'y' => $placementData['y'],
                    'w' => $placementData['w'],
                    'h' => $placementData['h'],
                ]);
                $placements[] = $placement;
            }

            // Update signer status to SIGNED
            $signer->update([
                'status' => 'SIGNED',
                'signed_at' => now(),
            ]);

            // Check if all signers are signed, update document status
            $allSigned = $document->signers()
                ->where('status', '!=', 'SIGNED')
                ->count() === 0;
            
            if ($allSigned) {
                // Generate verify token if not exists
                $verifyToken = $document->verify_token ?? bin2hex(random_bytes(16));
                
                $document->update([
                    'status' => 'signed',
                    'verify_token' => $verifyToken,
                ]);
            }

            DB::commit();

            return response()->json([
                'documentId' => $document->id,
                'signerUserId' => $request->input('signerUserId'),
                'placements' => collect($placements)->map(fn($p) => [
                    'placementId' => $p->id,
                    'page' => $p->page,
                    'x' => $p->x,
                    'y' => $p->y,
                    'w' => $p->w,
                    'h' => $p->h,
                    'signatureId' => $p->signature_id,
                ])->toArray(),
                'signerStatus' => 'SIGNED',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save placement: ' . $e->getMessage()], 500);
        }
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

        $placement = SignaturePlacement::where('id', $placementId)
            ->where('document_id', $documentId)
            ->firstOrFail();

        $placement->update($request->only(['x', 'y', 'w', 'h']));

        return response()->json([
            'placementId' => $placement->id,
            'page' => $placement->page,
            'x' => $placement->x,
            'y' => $placement->y,
            'w' => $placement->w,
            'h' => $placement->h,
        ]);
    }

    /**
     * Get all placements for a document
     * GET /api/v1/documents/{documentId}/placements
     */
    public function index($documentId)
    {
        $document = Document::findOrFail($documentId);
        
        $placements = $document->placements()
            ->with(['signer', 'signature'])
            ->get();

        return response()->json([
            'documentId' => $document->id,
            'placements' => $placements->map(fn($p) => [
                'placementId' => $p->id,
                'signerUserId' => $p->signer->user_id,
                'signerName' => $p->signer->name,
                'page' => $p->page,
                'x' => $p->x,
                'y' => $p->y,
                'w' => $p->w,
                'h' => $p->h,
                'signatureId' => $p->signature_id,
            ])->toArray(),
        ]);
    }
}
