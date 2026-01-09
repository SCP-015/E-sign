<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentUploadRequest;
use App\Http\Requests\UpdateQrPositionRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Certificate;
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

        $doc = $this->documentService->upload($request->file('file'), $user);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => new DocumentResource($doc)
        ]);
    }

    public function sign(Request $request, $id)
    {
        $user = $request->user();
        $document = Document::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        // Get user's active certificate
        $cert = Certificate::where('user_id', $user->id)->where('status', 'active')->latest()->first();

        if (!$cert) {
            return response()->json(['message' => 'No active certificate found. Please issue one first.'], 400);
        }

        try {
            // Sign PDF using qr_position from database
            $signedPath = $this->documentService->signPdf($document, $cert);
            
            // Update document status and signed_at timestamp
            $document->update([
                'status' => 'signed',
                'signed_at' => now(),
            ]);
            
            return response()->json([
                'message' => 'Document signed successfully',
                'signed_url' => url('storage/' . $signedPath),
                'path' => $signedPath,
                'qr_position_used' => $document->qr_position,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Signing Failed: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['message' => 'Signing failed: ' . $e->getMessage()], 500);
        }
    }

    // QR Position Management Endpoints
    
    public function getQrPosition(Request $request, $id)
    {
        $user = $request->user();
        $document = Document::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        return response()->json([
            'document_id' => $document->id,
            'qr_position' => $document->qr_position ?? [
                'x' => 0.5,
                'y' => 0.5,
                'width' => 0.15,
                'height' => 0.15,
                'page' => 1,
            ],
        ]);
    }

    public function updateQrPosition(UpdateQrPositionRequest $request, $id)
    {
        $user = $request->user();
        $document = Document::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        // Update QR position using accessor
        $document->qr_position = $request->validated();
        $document->save();

        return response()->json([
            'status' => 'success',
            'message' => 'QR position updated successfully',
            'qr_position' => $document->qr_position,
        ]);
    }

    public function download(Request $request, $id)
    {
        $user = $request->user();
        $document = Document::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if ($document->status !== 'signed' || !$document->signed_path) {
            return response()->json(['message' => 'Document is not signed yet'], 400);
        }

        // Remove 'private/' prefix if present
        $relativePath = str_replace('private/', '', $document->signed_path);
        $filePath = \Illuminate\Support\Facades\Storage::disk('private')->path($relativePath);

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'Signed document file not found'], 404);
        }

        // Get original filename or generate one
        $filename = $document->original_filename 
            ? 'signed_' . $document->original_filename 
            : 'signed_document_' . $document->id . '.pdf';

        // Return file as download with proper headers
        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
