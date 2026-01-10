<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignerController extends Controller
{
    /**
     * Add signers to document
     * POST /api/v1/documents/{documentId}/signers
     */
    public function store(Request $request, $documentId)
    {
        $request->validate([
            'signers' => 'required|array|min:1',
            'signers.*.userId' => 'required|exists:users,id',
            'signers.*.name' => 'required|string|max:255',
            'signers.*.order' => 'nullable|integer',
        ]);

        $document = Document::where('id', $documentId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $signers = [];
            foreach ($request->input('signers') as $signerData) {
                $signer = DocumentSigner::create([
                    'document_id' => $document->id,
                    'user_id' => $signerData['userId'],
                    'name' => $signerData['name'],
                    'order' => $signerData['order'] ?? null,
                    'status' => 'PENDING',
                ]);
                $signers[] = $signer;
            }

            // Update document status to IN_PROGRESS
            $document->update(['status' => 'IN_PROGRESS']);

            DB::commit();

            return response()->json([
                'documentId' => $document->id,
                'status' => $document->status,
                'signers' => collect($signers)->map(fn($s) => [
                    'userId' => $s->user_id,
                    'name' => $s->name,
                    'order' => $s->order,
                ])->toArray(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add signers: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get document signers
     * GET /api/v1/documents/{documentId}/signers
     */
    public function index($documentId)
    {
        $document = Document::findOrFail($documentId);
        
        $signers = $document->signers()->with('user')->get();

        return response()->json([
            'documentId' => $document->id,
            'status' => $document->status,
            'signers' => $signers->map(fn($s) => [
                'id' => $s->id,
                'userId' => $s->user_id,
                'name' => $s->name,
                'order' => $s->order,
                'signedAt' => $s->signed_at?->toIso8601String(),
            ])->toArray(),
        ]);
    }
}
