<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\DocumentUploadRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\Signature;
use App\Services\DocumentService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    protected $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index(Request $request)
    {
        $result = $this->documentService->indexResult((int) $request->user()->id);
        return ApiResponse::fromService($result);
    }

    public function upload(DocumentUploadRequest $request)
    {
        $user = $request->user();
        $file = $request->file('file');
        $title = $request->input('title', $file->getClientOriginalName());

        $result = $this->documentService->uploadWithMetadataResult(
            (int) $user->id,
            $file,
            $title
        );

        return ApiResponse::fromService($result);
    }

    public function show(Request $request, $id)
    {
        $result = $this->documentService->showResult(
            (int) $id,
            (int) $request->user()->id
        );

        return ApiResponse::fromService($result);
    }

    public function viewUrl(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $document = Document::where('id', $id)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhereHas('signers', function($q) use ($user) {
                              $q->where('user_id', $user->id)
                                ->orWhere('email', $user->email);
                          });
                })
                ->firstOrFail();

            $filePath = \Illuminate\Support\Facades\Storage::disk('private')->path($document->file_path);

            if (!file_exists($filePath)) {
                \Illuminate\Support\Facades\Log::error('File not found: ' . $filePath);
                return response()->json(['message' => 'File not found on server'], 404);
            }

            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . basename($document->file_path) . '"',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Illuminate\Support\Facades\Log::error('Document not found or access denied: ' . $id);
            return response()->json(['message' => 'Document not found or access denied'], 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ViewUrl error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to load PDF: ' . $e->getMessage()], 500);
        }
    }

    public function finalize(Request $request, $id)
    {
        $request->validate([
            'qrPlacement.page' => 'nullable|string',
            'qrPlacement.position' => 'nullable|string',
            'qrPlacement.marginBottom' => 'nullable|integer',
            'qrPlacement.size' => 'nullable|integer',
        ]);

        $user = $request->user();
        $document = Document::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        // Check if all signers have signed
        if (!$document->isAllSigned()) {
            return response()->json([
                'message' => 'Cannot finalize: Some signers have not signed yet',
            ], 400);
        }

        // Generate verify token
        $verifyToken = bin2hex(random_bytes(16));
        $document->update(['verify_token' => $verifyToken]);

        // Generate final PDF with all placements and QR code
        $qrConfig = $request->input('qrPlacement', [
            'page' => 'LAST',
            'position' => 'BOTTOM_RIGHT',
            'marginBottom' => 15,
            'marginRight' => 15,
            'size' => 35,
        ]);

        try {
            $finalPdfPath = $this->documentService->finalizePdf($document, $verifyToken, $qrConfig);

            // Update document status
            $document->update([
                'status' => 'COMPLETED',
                'final_pdf_path' => $finalPdfPath,
                'completed_at' => now(),
            ]);

            $document = $document->fresh();

            // Create/refresh signing evidence (LTV payload) for public verification
            $cert = \App\Models\Certificate::where('user_id', (int) $document->user_id)
                ->where('status', 'active')
                ->orderByDesc('issued_at')
                ->orderByDesc('created_at')
                ->first();

            if ($cert) {
                $this->documentService->upsertSigningEvidence($document, $cert, $document->final_pdf_path, $document->completed_at);
            }

            $verifyUrl = url('/api/verify/' . $verifyToken);

            return response()->json([
                'documentId' => $document->id,
                'status' => 'COMPLETED',
                'verifyUrl' => $verifyUrl,
                'qrValue' => $verifyUrl,
                'finalPdfUrl' => url('/api/documents/' . $document->id . '/download'),
                'completedAt' => $document->completed_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Finalize Failed: ' . $e->getMessage());
            return response()->json(['message' => 'Finalization failed: ' . $e->getMessage()], 500);
        }
    }

    public function sign(Request $request, $id)
    {
        // Legacy method for backward compatibility
        // In MVP flow, use PlacementController instead
        $user = $request->user();
        $document = Document::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'signature_id' => 'required|integer|exists:signatures,id',
            'signature_position' => 'required|array',
            'signature_position.x' => 'required|numeric|min:0|max:1',
            'signature_position.y' => 'required|numeric|min:0|max:1',
            'signature_position.width' => 'required|numeric|min:0.01|max:0.5',
            'signature_position.height' => 'required|numeric|min:0.01|max:0.5',
            'signature_position.page' => 'required|integer|min:1',
        ]);

        $signature = Signature::where('id', $validated['signature_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $cert = Certificate::where('user_id', $user->id)->where('status', 'active')->latest()->first();

        if (!$cert) {
            return response()->json(['message' => 'No active certificate found'], 400);
        }

        try {
            $signedPath = $this->documentService->signPdf(
                $document, 
                $cert, 
                $signature, 
                $validated['signature_position']
            );
            
            $document->update([
                'status' => 'signed',
            ]);
            
            return response()->json([
                'message' => 'Document signed successfully',
                'document' => new DocumentResource($document->fresh()),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Signing Failed: ' . $e->getMessage());
            return response()->json(['message' => 'Signing failed: ' . $e->getMessage()], 500);
        }
    }

    public function download(Request $request, $id)
    {
        $user = $request->user();
        $document = Document::where('id', $id)
            ->where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhereHas('signers', function($q) use ($user) {
                          $q->where('user_id', $user->id)
                            ->orWhere('email', $user->email);
                      });
            })
            ->firstOrFail();

        if (!in_array($document->status, ['signed', 'COMPLETED'], true)) {
            return response()->json(['message' => 'Document is not signed yet'], 400);
        }

        // Check if final PDF exists (from finalize), otherwise use signed_path (legacy)
        $filePath = null;
        
        if ($document->final_pdf_path && file_exists(storage_path('app/' . $document->final_pdf_path))) {
            $filePath = storage_path('app/' . $document->final_pdf_path);
        } elseif ($document->signed_path) {
            $relativePath = str_replace('private/', '', $document->signed_path);
            $filePath = \Illuminate\Support\Facades\Storage::disk('private')->path($relativePath);
        }

        if (!$filePath || !file_exists($filePath)) {
            // Generate final PDF on-the-fly if doesn't exist
            try {
                $verifyToken = $document->verify_token ?? bin2hex(random_bytes(16));
                $qrConfig = [
                    'page' => 'LAST',
                    'position' => 'BOTTOM_RIGHT',
                    'marginBottom' => 15,
                    'marginRight' => 15,
                    'size' => 35,
                ];
                
                $finalPdfPath = $this->documentService->finalizePdf($document, $verifyToken, $qrConfig);
                $document->update([
                    'final_pdf_path' => $finalPdfPath,
                    'verify_token' => $verifyToken,
                ]);

                $document = $document->fresh();

                // Create/refresh signing evidence so verify/upload can validate
                $cert = \App\Models\Certificate::where('user_id', (int) $document->user_id)
                    ->where('status', 'active')
                    ->orderByDesc('issued_at')
                    ->orderByDesc('created_at')
                    ->first();

                if ($cert) {
                    $this->documentService->upsertSigningEvidence($document, $cert, $document->final_pdf_path, $document->completed_at ?? now());
                }
                
                $filePath = storage_path('app/' . $finalPdfPath);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
            }
        }

        // Ensure signing evidence exists even for previously generated final PDFs
        $document->load('signingEvidence');
        $evidence = $document->signingEvidence;
        $evidenceMissing = !$evidence
            || !$evidence->signed_at
            || !$evidence->certificate_not_before
            || !$evidence->certificate_not_after;

        if ($evidenceMissing) {
            $cert = Certificate::where('user_id', (int) $document->user_id)
                ->where('status', 'active')
                ->orderByDesc('issued_at')
                ->orderByDesc('created_at')
                ->first();

            if ($cert) {
                $this->documentService->upsertSigningEvidence(
                    $document,
                    $cert,
                    $document->final_pdf_path,
                    $document->completed_at ?? $document->updated_at ?? now()
                );
            }
        }

        // Get original filename or generate one
        $filename = $document->title 
            ? str_replace('.pdf', '', $document->title) . '_signed.pdf'
            : 'signed_document_' . $document->id . '.pdf';

        // Return file as download with proper headers
        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
