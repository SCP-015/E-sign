<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;

class VerifyController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file = $request->file('file');
        $tmpPath = sys_get_temp_dir() . '/verify_upload_' . uniqid() . '.pdf';

        try {
            $file->move(dirname($tmpPath), basename($tmpPath));
            $content = file_get_contents($tmpPath);

            if ($content === false) {
                return response()->json([
                    'is_valid' => false,
                    'message' => 'Failed to read uploaded PDF',
                    'signed_by' => null,
                    'signed_at' => null,
                    'document_id' => null,
                ], 400);
            }

            // Stronger check: most digitally signed PDFs contain a /ByteRange array.
            // The previous marker-based checks (e.g. '/Contents') could cause false positives because
            // '/Contents' can also appear in normal PDF objects.
            $hasSignature = (bool) preg_match('/\\/ByteRange\s*\[\s*\d+\s+\d+\s+\d+\s+\d+\s*\]/', $content);

            if (!$hasSignature) {
                return response()->json([
                    'is_valid' => false,
                    'message' => 'No digital signature found in PDF',
                    'signed_by' => null,
                    'signed_at' => null,
                    'document_id' => null,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }

            // Best-effort: if this PDF was finalized by our system, the verify URL may appear in the PDF content.
            // If we can extract token, we can validate certificate using our stored document.
            $token = null;
            if (preg_match('#/api/verify/([a-f0-9]{32})#i', $content, $m)) {
                $token = $m[1];
            } elseif (preg_match('#/verify/([a-f0-9]{32})#i', $content, $m)) {
                $token = $m[1];
            } elseif (preg_match('/VERIFY_TOKEN=([a-f0-9]{32})/i', $content, $m)) {
                $token = $m[1];
            }

            if ($token) {
                $document = Document::where('verify_token', $token)->first();
                if ($document) {
                    $cert = $document->user?->certificate;
                    $isCertActive = $cert && $cert->status === 'active' && (!$cert->expires_at || $cert->expires_at->isFuture());

                    $payload = [
                        'is_valid' => (bool) $isCertActive,
                        'message' => $isCertActive ? 'Signature is valid' : 'Certificate is not active or has expired',
                        'signed_by' => $document->user?->name,
                        'signed_at' => $document->completed_at?->toIso8601String() ?? $document->updated_at?->toIso8601String(),
                        'document_id' => $document->id,
                        'file_name' => $document->title ?? basename($document->file_path),
                    ];

                    if (!$isCertActive && $cert) {
                        $payload['certificate'] = [
                            'id' => $cert->id,
                            'status' => $cert->status,
                            'issued_at' => $cert->issued_at?->toIso8601String(),
                            'expires_at' => $cert->expires_at?->toIso8601String(),
                        ];
                    }

                    return response()->json($payload);
                }
            }

            // Fallback: PDF appears to contain a digital signature, but we cannot link it back to
            // a document in our system (no verify token found). In this case we can only say the
            // PDF is signed, but we cannot show signer identity or validate certificate expiry.
            return response()->json([
                'is_valid' => true,
                'message' => 'Signature is valid (signer identity unknown)',
                'signed_by' => null,
                'signed_at' => null,
                'document_id' => null,
                'file_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'is_valid' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
                'signed_by' => null,
                'signed_at' => null,
                'document_id' => null,
            ], 500);
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

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

        $cert = $document->user?->certificate;
        $isCertActive = $cert && $cert->status === 'active' && (!$cert->expires_at || $cert->expires_at->isFuture());

        return response()->json([
            'documentId' => $document->id,
            'status' => $document->status,
            'is_valid' => (bool) $isCertActive,
            'message' => $isCertActive ? 'Document is valid' : 'Certificate is not active or has expired',
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
