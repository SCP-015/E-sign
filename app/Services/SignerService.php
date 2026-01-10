<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Support\Facades\DB;

class SignerService
{
    public function addSigners(int $documentId, int $ownerUserId, array $signersInput): array
    {
        $document = Document::where('id', $documentId)
            ->where('user_id', $ownerUserId)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $signers = [];
            foreach ($signersInput as $signerData) {
                $signer = DocumentSigner::create([
                    'document_id' => $document->id,
                    'user_id' => $signerData['userId'],
                    'name' => $signerData['name'],
                    'order' => $signerData['order'] ?? null,
                    'status' => 'PENDING',
                ]);
                $signers[] = $signer;
            }

            $document->update(['status' => 'IN_PROGRESS']);

            DB::commit();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    'documentId' => $document->id,
                    'status' => $document->status,
                    'signers' => collect($signers)->map(fn ($s) => [
                        'userId' => $s->user_id,
                        'name' => $s->name,
                        'order' => $s->order,
                    ])->toArray(),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to add signers: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function getSigners(int $documentId): array
    {
        $document = Document::findOrFail($documentId);
        $signers = $document->signers()->with('user')->get();

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'documentId' => $document->id,
                'status' => $document->status,
                'signers' => $signers->map(fn ($s) => [
                    'id' => $s->id,
                    'userId' => $s->user_id,
                    'name' => $s->name,
                    'order' => $s->order,
                    'signedAt' => $s->signed_at?->toIso8601String(),
                ])->toArray(),
            ],
        ];
    }
}
