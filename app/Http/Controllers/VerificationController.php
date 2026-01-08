<?php

namespace App\Http\Controllers;

use App\Services\VerificationService;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    protected $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    public function verify(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id'
        ]);

        $result = $this->verificationService->verify($request->document_id);

        return response()->json($result);
    }
}
