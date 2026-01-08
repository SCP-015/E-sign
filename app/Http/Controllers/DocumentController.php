<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Certificate;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    protected $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index(Request $request)
    {
        return $request->user()->documents()->latest()->get();
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf',
            'x_coord' => 'numeric',
            'y_coord' => 'numeric',
        ]);

        $user = $request->user();
        $doc = $this->documentService->upload($request->file('file'), $user);

        // Update coords if provided
        $doc->update([
            'x_coord' => $request->input('x_coord', 50),
            'y_coord' => $request->input('y_coord', 250),
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => $doc
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
            $signedPath = $this->documentService->signPdf($document, $cert);
            return response()->json([
                'message' => 'Document signed successfully',
                'signed_url' => url('storage/' . $signedPath), // Assuming storage link is set
                'path' => $signedPath
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Signing Failed: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['message' => 'Signing failed: ' . $e->getMessage()], 500);
        }
    }
}
