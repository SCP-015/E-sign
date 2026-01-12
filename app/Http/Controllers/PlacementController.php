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
            'signerUserId' => 'nullable|exists:users,id',
            'email' => 'nullable|email',
            'placements' => 'required|array|min:1',
            'placements.*.page' => 'required|integer|min:1',
            'placements.*.x' => 'required|numeric',
            'placements.*.y' => 'required|numeric',
            'placements.*.w' => 'required|numeric',
            'placements.*.h' => 'required|numeric',
            'placements.*.signatureId' => 'nullable|exists:signatures,id',
        ]);

        $document = Document::findOrFail($documentId);
        $signerUserId = $request->input('signerUserId');
        $email = $request->input('email');
        
        // Find signer by user_id or email
        $signer = null;
        if ($signerUserId) {
            $signer = DocumentSigner::where('document_id', $documentId)
                ->where('user_id', $signerUserId)
                ->first();
        } elseif ($email) {
            $signer = DocumentSigner::where('document_id', $documentId)
                ->where('email', $email)
                ->first();
        }
        
        if (!$signer) {
            if ($signerUserId) {
                $signerUser = \App\Models\User::findOrFail($signerUserId);
                $signer = DocumentSigner::create([
                    'document_id' => $documentId,
                    'user_id' => $signerUserId,
                    'email' => $signerUser->email,
                    'name' => $signerUser->name,
                    'status' => 'PENDING',
                ]);
            } elseif ($email) {
                // This case usually handled in SignerController, but as fallback:
                $signer = DocumentSigner::create([
                    'document_id' => $documentId,
                    'email' => $email,
                    'name' => 'Signer', // Placeholder name
                    'status' => 'PENDING',
                ]);
            }
            
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
            $hasSignature = false;
            foreach ($request->input('placements') as $placementData) {
                $signatureId = $placementData['signatureId'] ?? null;
                
                if ($signatureId) {
                    // Verify signature belongs to user (only if signer is a registered user)
                    if ($signer->user_id) {
                        $signature = Signature::where('id', $signatureId)
                            ->where('user_id', $signer->user_id)
                            ->firstOrFail();
                        $hasSignature = true;
                    }
                }

                $placement = SignaturePlacement::create([
                    'document_id' => $documentId,
                    'signer_id' => $signer->id,
                    'signature_id' => $signatureId,
                    'page' => $placementData['page'],
                    'x' => $placementData['x'],
                    'y' => $placementData['y'],
                    'w' => $placementData['w'],
                    'h' => $placementData['h'],
                ]);
                $placements[] = $placement;
            }

            // Update signer status to SIGNED only if a signature was actually placed
            if ($hasSignature) {
                $signer->update([
                    'status' => 'SIGNED',
                    'signed_at' => now(),
                ]);

                // Check if all signers are signed, update document status
                $allSigned = $document->signers()
                    ->where('status', '!=', 'SIGNED')
                    ->count() === 0;
                
                if ($allSigned) {
                    $verifyToken = $document->verify_token ?? bin2hex(random_bytes(16));
                    $document->update([
                        'status' => 'signed',
                        'verify_token' => $verifyToken,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'documentId' => $document->id,
                'signerId' => $signer->id,
                'placements' => collect($placements)->map(fn($p) => [
                    'placementId' => $p->id,
                    'page' => $p->page,
                    'x' => $p->x,
                    'y' => $p->y,
                    'w' => $p->w,
                    'h' => $p->h,
                    'signatureId' => $p->signature_id,
                ])->toArray(),
                'signerStatus' => $signer->status,
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
