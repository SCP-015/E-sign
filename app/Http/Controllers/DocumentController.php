<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentUploadRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Certificate;
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
        return DocumentResource::collection($request->user()->documents()->latest()->get());
    }

    public function upload(DocumentUploadRequest $request)
    {
        $user = $request->user();

        // Validate user has active certificate before allowing upload
        $cert = Certificate::where('user_id', $user->id)->where('status', 'active')->first();
        if (!$cert) {
            return response()->json([
                'message' => 'No active certificate found. Please complete KYC verification first.'
            ], 400);
        }

        $file = $request->file('file');
        $title = $request->input('title', $file->getClientOriginalName());
        
        $doc = $this->documentService->uploadWithMetadata($file, $user, $title);

        return response()->json([
            'documentId' => $doc->id,
            'fileName' => basename($doc->file_path),
            'fileType' => $doc->file_type,
            'fileSizeBytes' => $doc->file_size_bytes,
            'pageCount' => $doc->page_count,
            'status' => $doc->status,
            'createdAt' => $doc->created_at->toIso8601String(),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $document = Document::with(['signers'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        
        return response()->json([
            'documentId' => $document->id,
            'status' => $document->status,
            'pageCount' => $document->page_count,
            'verify_token' => $document->verify_token,
        ]);
    }

    public function viewUrl(Request $request, $id)
    {
        $document = Document::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // File is stored on the "private" disk, so resolve using Storage disk path
        $filePath = \Illuminate\Support\Facades\Storage::disk('private')->path($document->file_path);

        \Illuminate\Support\Facades\Log::info('ViewUrl - Document ID: ' . $id . ', File: ' . $document->file_path . ', Full path: ' . $filePath . ', Exists: ' . (file_exists($filePath) ? 'yes' : 'no'));

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($document->file_path) . '"',
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
        $document = Document::where('id', $id)->where('user_id', $user->id)->firstOrFail();

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
                
                $filePath = storage_path('app/' . $finalPdfPath);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
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
