<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    
    // Web Google
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
    
    // Mobile Google
    Route::post('/google/mobile', [AuthController::class, 'googleMobileLogin']);
    Route::post('/google/mobile/code', [AuthController::class, 'googleMobileLoginCode']);
    
    // Logout (Protected)
    Route::middleware('auth:api')->post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [\App\Http\Controllers\UserController::class, 'show']);
    Route::post('/certificates/issue', [\App\Http\Controllers\CertificateController::class, 'issue']);
    
    // Documents
    Route::get('/documents', [\App\Http\Controllers\DocumentController::class, 'index']);
    Route::post('/documents', [\App\Http\Controllers\DocumentController::class, 'upload']);
    Route::get('/documents/{document}', [\App\Http\Controllers\DocumentController::class, 'show']);
    Route::get('/documents/{document}/view-url', [\App\Http\Controllers\DocumentController::class, 'viewUrl']);
    Route::post('/documents/{document}/sign', [\App\Http\Controllers\DocumentController::class, 'sign']);
    Route::post('/documents/{document}/finalize', [\App\Http\Controllers\DocumentController::class, 'finalize']);
    Route::get('/documents/{document}/download', [\App\Http\Controllers\DocumentController::class, 'download']);
    
    // Document Signers
    Route::post('/documents/{document}/signers', [\App\Http\Controllers\SignerController::class, 'store']);
    Route::get('/documents/{document}/signers', [\App\Http\Controllers\SignerController::class, 'index']);
    
    // Signature Placements
    Route::post('/documents/{document}/placements', [\App\Http\Controllers\PlacementController::class, 'store']);
    Route::get('/documents/{document}/placements', [\App\Http\Controllers\PlacementController::class, 'index']);
    Route::put('/documents/{document}/placements/{placement}', [\App\Http\Controllers\PlacementController::class, 'update']);
    
    // User Signatures
    Route::get('/signatures', [\App\Http\Controllers\SignatureController::class, 'index']);
    Route::post('/signatures', [\App\Http\Controllers\SignatureController::class, 'store']);
    Route::get('/signatures/{signature}/image', [\App\Http\Controllers\SignatureController::class, 'getImage']);
    Route::put('/signatures/{signature}/default', [\App\Http\Controllers\SignatureController::class, 'setDefault']);
    Route::delete('/signatures/{signature}', [\App\Http\Controllers\SignatureController::class, 'destroy']);
    
    // Verify
    Route::post('/documents/verify', [\App\Http\Controllers\VerificationController::class, 'verify']);

    // Mobile KYC
    Route::post('/kyc/submit', [\App\Http\Controllers\KycController::class, 'submit']);
});

// Public verify endpoint (no auth required)
Route::post('/verify/upload', [\App\Http\Controllers\VerifyController::class, 'upload']);
Route::get('/verify/{token}', [\App\Http\Controllers\VerifyController::class, 'verify']);
