<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignaturePlacement;
use Illuminate\Support\Facades\DB;

class PlacementService
{
    public function storePlacements(int $documentId, int $signerUserId, array $placementsInput): array
    {
        $document = Document::findOrFail($documentId);

        $signer = DocumentSigner::where('document_id', $documentId)
            ->where('user_id', $signerUserId)
            ->first();

        if (!$signer) {
            $signerUser = \App\Models\User::findOrFail($signerUserId);
            $signer = DocumentSigner::create([
                'document_id' => $documentId,
                'user_id' => $signerUserId,
                'name' => $signerUser->name,
                'status' => 'PENDING',
            ]);

            $document->update(['status' => 'IN_PROGRESS']);
        }

        DB::beginTransaction();
        try {
            SignaturePlacement::where('document_id', $documentId)
                ->where('signer_id', $signer->id)
                ->delete();

            $placements = [];
            foreach ($placementsInput as $placementData) {
                $signature = Signature::where('id', $placementData['signatureId'])
                    ->where('user_id', $signerUserId)
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

            $signer->update([
                'status' => 'SIGNED',
                'signed_at' => now(),
            ]);

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

            DB::commit();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    'documentId' => $document->id,
                    'signerUserId' => $signerUserId,
                    'placements' => collect($placements)->map(fn ($p) => [
                        'placementId' => $p->id,
                        'page' => $p->page,
                        'x' => $p->x,
                        'y' => $p->y,
                        'w' => $p->w,
                        'h' => $p->h,
                        'signatureId' => $p->signature_id,
                    ])->toArray(),
                    'signerStatus' => 'SIGNED',
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to save placement: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function updatePlacement(int $documentId, int $placementId, array $updates): array
    {
        $placement = SignaturePlacement::where('id', $placementId)
            ->where('document_id', $documentId)
            ->firstOrFail();

        $placement->update($updates);

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'placementId' => $placement->id,
                'page' => $placement->page,
                'x' => $placement->x,
                'y' => $placement->y,
                'w' => $placement->w,
                'h' => $placement->h,
            ],
        ];
    }

    public function getPlacements(int $documentId): array
    {
        $document = Document::findOrFail($documentId);

        $placements = $document->placements()
            ->with(['signer', 'signature'])
            ->get();

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'documentId' => $document->id,
                'placements' => $placements->map(fn ($p) => [
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
            ],
        ];
    }
}
