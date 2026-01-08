<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentUploadRequest;
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

        // Validate request
        $validated = $request->validate([
            'x_coord' => 'nullable|numeric',
            'y_coord' => 'nullable|numeric',
        ]);

        // Update coordinates if provided
        $document->update([
            'x_coord' => $request->input('x_coord', 50),
            'y_coord' => $request->input('y_coord', 250),
        ]);

        // Get user's active certificate
        $cert = Certificate::where('user_id', $user->id)->where('status', 'active')->latest()->first();

        if (!$cert) {
            return response()->json(['message' => 'No active certificate found. Please issue one first.'], 400);
        }

        try {
            $signedPath = $this->documentService->signPdf($document, $cert);
            return response()->json([
                'message' => 'Document signed successfully',
                'signed_url' => url('storage/' . $signedPath),
                'path' => $signedPath
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Signing Failed: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['message' => 'Signing failed: ' . $e->getMessage()], 500);
        }
    }
}
