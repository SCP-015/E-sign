<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
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
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/certificates/issue', [\App\Http\Controllers\CertificateController::class, 'issue']);
    
    Route::get('/documents', [\App\Http\Controllers\DocumentController::class, 'index']);
    Route::post('/documents', [\App\Http\Controllers\DocumentController::class, 'upload']);
    Route::post('/documents/{document}/sign', [\App\Http\Controllers\DocumentController::class, 'sign']);
    Route::get('/documents/{document}/download', [\App\Http\Controllers\DocumentController::class, 'download']);
    Route::post('/documents/verify', [\App\Http\Controllers\VerificationController::class, 'verify']);
    
    // QR Position Management (Modern drag & drop signature)
    Route::get('/documents/{document}/qr-position', [\App\Http\Controllers\DocumentController::class, 'getQrPosition']);
    Route::put('/documents/{document}/qr-position', [\App\Http\Controllers\DocumentController::class, 'updateQrPosition']);

    // Mobile KYC (Protected)
    Route::post('/kyc/submit', [\App\Http\Controllers\KycController::class, 'submit']);
});
