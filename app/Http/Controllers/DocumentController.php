<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\DocumentFinalizeRequest;
use App\Http\Requests\DocumentSignRequest;
use App\Http\Requests\DocumentUploadRequest;
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
        return ApiResponse::fromService($this->documentService->indexResult((int) $request->user()->id));
    }

    public function upload(DocumentUploadRequest $request)
    {
        $user = $request->user();
        $file = $request->file('file');
        $title = $request->input('title', $file->getClientOriginalName());

        return ApiResponse::fromService(
            $this->documentService->uploadWithMetadataResult((int) $user->id, $file, (string) $title)
        );
    }

    public function show(Request $request, $id)
    {
        return ApiResponse::fromService(
            $this->documentService->showResult((int) $id, (int) $request->user()->id)
        );
    }

    public function viewUrl(Request $request, $id)
    {
        $result = $this->documentService->resolveViewUrlResult((int) $id, (int) $request->user()->id);
        if (($result['status'] ?? 'error') !== 'success') {
            return ApiResponse::fromService($result);
        }

        $filePath = $result['data']['filePath'] ?? '';
        $fileName = $result['data']['fileName'] ?? 'document.pdf';

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    public function finalize(DocumentFinalizeRequest $request, $id)
    {
        $user = $request->user();
        $qrPlacement = (array) $request->input('qrPlacement', []);

        return ApiResponse::fromService(
            $this->documentService->finalizeResult((int) $id, (int) $user->id, $qrPlacement)
        );
    }

    public function sign(DocumentSignRequest $request, $id)
    {
        $user = $request->user();
        return ApiResponse::fromService(
            $this->documentService->signLegacyResult((int) $id, (int) $user->id, $request->validated())
        );
    }

    public function download(Request $request, $id)
    {
        $user = $request->user();
        $result = $this->documentService->resolveDownloadResult((int) $id, (int) $user->id);
        if (($result['status'] ?? 'error') !== 'success') {
            return ApiResponse::fromService($result);
        }

        $filePath = $result['data']['filePath'] ?? '';
        $filename = $result['data']['fileName'] ?? ('signed_document_' . $id . '.pdf');

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
