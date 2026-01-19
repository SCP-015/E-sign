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
        $tenantId = $this->getCurrentTenantId($request);
        $result = $this->documentService->indexResult((int) $request->user()->id, $tenantId);
        return ApiResponse::fromService($result);
    }

    public function upload(DocumentUploadRequest $request)
    {
        $user = $request->user();
        $file = $request->file('file');
        $title = $request->input('title', $file->getClientOriginalName());
        $tenantId = $this->getCurrentTenantId($request);

        $result = $this->documentService->uploadWithMetadataResult(
            (int) $user->id,
            $file,
            $title,
            $tenantId
        );

        return ApiResponse::fromService($result);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getCurrentTenantId($request);
        $result = $this->documentService->showResult(
            (int) $id,
            (int) $request->user()->id,
            $tenantId
        );

        return ApiResponse::fromService($result);
    }

    public function viewUrl(Request $request, $id)
    {
        try {
            $user = $request->user();
            $tenantId = $this->getCurrentTenantId($request);
            
            // STRICT: filter by tenant context
            // Tenant members with documents.view_all can access all tenant docs
            if ($tenantId && $user->hasPermissionInTenant('documents.view_all', $tenantId)) {
                $document = Document::where('id', $id)
                    ->forCurrentContext($tenantId)
                    ->firstOrFail();
            } else {
                $document = Document::where('id', $id)
                    ->forCurrentContext($tenantId)
                    ->where(function($query) use ($user) {
                        $query->where('user_id', $user->id)
                              ->orWhereHas('signers', function($q) use ($user) {
                                  $q->where('user_id', $user->id)
                                    ->orWhere('email', $user->email);
                              });
                    })
                    ->firstOrFail();
            }

            $filePath = \Illuminate\Support\Facades\Storage::disk('private')->path($document->file_path);

            if (!file_exists($filePath)) {
                \Illuminate\Support\Facades\Log::error('File not found: ' . $filePath);
                return ApiResponse::error('File not found on server', 404);
            }

            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . basename($document->file_path) . '"',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Illuminate\Support\Facades\Log::error('Document not found or access denied: ' . $id);
            return ApiResponse::error('Document not found or access denied', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ViewUrl error: ' . $e->getMessage());
            return ApiResponse::error('Failed to load PDF: ' . $e->getMessage(), 500);
        }
    }

    public function getQrPosition(Request $request, $id)
    {
        $user = $request->user();
        $tenantId = $this->getCurrentTenantId($request);
        
        // Tenant members with documents.view_all can access all tenant docs
        if ($tenantId && $user->hasPermissionInTenant('documents.view_all', $tenantId)) {
            $document = Document::where('id', $id)
                ->forCurrentContext($tenantId)
                ->firstOrFail();
        } else {
            $document = Document::where('id', $id)
                ->forCurrentContext($tenantId)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhereHas('signers', function($q) use ($user) {
                              $q->where('user_id', $user->id)
                                ->orWhere('email', $user->email);
                          });
                })
                ->firstOrFail();
        }

        // Return default QR config that will be used during finalization
        $qrConfig = [
            'page' => 'LAST',
            'position' => 'BOTTOM_RIGHT',
            'marginBottom' => 15,
            'marginRight' => 15,
            'size' => 35,
        ];

        return ApiResponse::success([
            // Backward-compatible keys
            'qr_position' => $qrConfig,
            'document_id' => $document->id,
            // Preferred camelCase keys
            'qrPosition' => $qrConfig,
            'documentId' => $document->id,
        ]);
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
        $tenantId = $this->getCurrentTenantId($request);
        
        $document = Document::where('id', $id)
            ->forCurrentContext($tenantId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Check if all signers have signed
        if (!$document->isAllSigned()) {
            return ApiResponse::error('Cannot finalize: Some signers have not signed yet', 400);
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

            return ApiResponse::success([
                'document_id' => $document->id,
                'status' => 'COMPLETED',
                'verify_url' => $verifyUrl,
                'qr_value' => $verifyUrl,
                'final_pdf_url' => url('/api/documents/' . $document->id . '/download'),
                'completed_at' => $document->completed_at->toIso8601String(),
            ], 'OK', 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Finalize Failed: ' . $e->getMessage());
            return ApiResponse::error('Finalization failed: ' . $e->getMessage(), 500);
        }
    }

    public function sign(Request $request, $id)
    {
        // Legacy method for backward compatibility
        // In MVP flow, use PlacementController instead
        $user = $request->user();
        $tenantId = $this->getCurrentTenantId($request);
        
        $document = Document::where('id', $id)
            ->forCurrentContext($tenantId)
            ->where('user_id', $user->id)
            ->firstOrFail();

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
            return ApiResponse::error('No active certificate found', 400);
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

            return ApiResponse::success([
                'message' => 'Document signed successfully',
                'document' => (new DocumentResource($document->fresh()))->resolve(),
            ], 'OK', 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Signing Failed: ' . $e->getMessage());
            return ApiResponse::error('Signing failed: ' . $e->getMessage(), 500);
        }
    }

    public function download(Request $request, $id)
    {
        $user = $request->user();
        $tenantId = $this->getCurrentTenantId($request);
        
        // Tenant members with documents.view_all can access all tenant docs
        if ($tenantId && $user->hasPermissionInTenant('documents.view_all', $tenantId)) {
            $document = Document::where('id', $id)
                ->forCurrentContext($tenantId)
                ->firstOrFail();
        } else {
            $document = Document::where('id', $id)
                ->forCurrentContext($tenantId)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhereHas('signers', function($q) use ($user) {
                              $q->where('user_id', $user->id)
                                ->orWhere('email', $user->email);
                          });
                })
                ->firstOrFail();
        }

        $hasSigners = $document->signers()->exists();

        if ($hasSigners && $document->status !== 'COMPLETED') {
            return ApiResponse::error('Document must be finalized by owner before download', 400);
        }

        if (!in_array($document->status, ['signed', 'COMPLETED'], true)) {
            return ApiResponse::error('Document is not signed yet', 400);
        }

        // Check if final PDF exists (from finalize), otherwise use signed_path (legacy)
        $filePath = null;
        
        if ($document->final_pdf_path && \Illuminate\Support\Facades\Storage::disk('private')->exists($document->final_pdf_path)) {
            $filePath = \Illuminate\Support\Facades\Storage::disk('private')->path($document->final_pdf_path);
        } elseif ($document->signed_path) {
            $signedRelPath = str_replace('private/', '', $document->signed_path);
            if (\Illuminate\Support\Facades\Storage::disk('private')->exists($signedRelPath)) {
                $filePath = \Illuminate\Support\Facades\Storage::disk('private')->path($signedRelPath);
            }
        }

        if (!$filePath || !file_exists($filePath)) {
            if ($hasSigners) {
                return ApiResponse::error('Final PDF not found. Please finalize the document first.', 400);
            }
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
                
                $filePath = \Illuminate\Support\Facades\Storage::disk('private')->path($finalPdfPath);
            } catch (\Exception $e) {
                return ApiResponse::error('Failed to generate PDF: ' . $e->getMessage(), 500);
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

    /**
     * Get current tenant ID from session or user
     */
    private function getCurrentTenantId(Request $request): ?string
    {
        return session('current_tenant_id') ?? $request->user()->current_tenant_id;
    }
}
