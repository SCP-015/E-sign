<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;

class VerifyController extends Controller
{
    /**
     * Public verify endpoint (no auth required)
     * GET /api/v1/verify/{token}
     */
    public function verify($token)
    {
        $document = Document::where('verify_token', $token)
            ->with(['signers' => function($query) {
                $query->orderBy('order')->orderBy('signed_at');
            }])
            ->first();

        if (!$document) {
            return response()->json([
                'message' => 'Document not found or invalid token',
            ], 404);
        }

        return response()->json([
            'documentId' => $document->id,
            'status' => $document->status,
            'fileName' => $document->title ?? basename($document->file_path),
            'completedAt' => $document->completed_at?->toIso8601String(),
            'signers' => $document->signers->map(fn($s) => [
                'name' => $s->name,
                'status' => $s->status,
                'signedAt' => $s->signed_at?->toIso8601String(),
            ])->toArray(),
        ]);
    }
}
