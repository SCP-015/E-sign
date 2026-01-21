<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    
    // Web Google
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::get('/exchange', [AuthController::class, 'exchange']);
    
    // Mobile Google
    Route::post('/google/mobile', [AuthController::class, 'googleMobileLogin']);
    Route::post('/google/mobile/code', [AuthController::class, 'googleMobileLoginCode']);
    
    // Logout (Protected)
    Route::middleware('auth:api')->post('/logout', [AuthController::class, 'logout']);
});

// API Docs
Route::get('/docs', [ApiDocsController::class, 'index']);

// Invitation (Public validate + Auth accept)
Route::get('/invitations/validate', [InvitationController::class, 'validateInvitation']);
Route::middleware('auth:api')->post('/invitations/accept', [InvitationController::class, 'accept']);

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [\App\Http\Controllers\UserController::class, 'show']);
    Route::post('/certificates/issue', [\App\Http\Controllers\CertificateController::class, 'issue']);
    
    // Documents
    Route::middleware('kyc.verified')->group(function () {
        Route::post('/documents', [\App\Http\Controllers\DocumentController::class, 'upload']);
        Route::post('/documents/{document}/sign', [\App\Http\Controllers\DocumentController::class, 'sign']);
        Route::post('/documents/{document}/finalize', [\App\Http\Controllers\DocumentController::class, 'finalize']);
        Route::post('/documents/{document}/signers', [\App\Http\Controllers\SignerController::class, 'store']);
        Route::post('/documents/{document}/placements', [\App\Http\Controllers\PlacementController::class, 'store']);
        Route::put('/documents/{document}/placements/{placement}', [\App\Http\Controllers\PlacementController::class, 'update']);
        Route::post('/signatures', [\App\Http\Controllers\SignatureController::class, 'store']);
    });

    Route::get('/documents', [\App\Http\Controllers\DocumentController::class, 'index']);
    Route::post('/documents/sync', [\App\Http\Controllers\DocumentController::class, 'sync']);
    Route::get('/documents/{document}', [\App\Http\Controllers\DocumentController::class, 'show']);
    Route::get('/documents/{document}/view-url', [\App\Http\Controllers\DocumentController::class, 'viewUrl']);
    Route::get('/documents/{document}/qr-position', [\App\Http\Controllers\DocumentController::class, 'getQrPosition']);
    Route::get('/documents/{document}/download', [\App\Http\Controllers\DocumentController::class, 'download']);
    
    // Document Signers
    Route::get('/documents/{document}/signers', [\App\Http\Controllers\SignerController::class, 'index']);
    
    // Signature Placements
    Route::get('/documents/{document}/placements', [\App\Http\Controllers\PlacementController::class, 'index']);
    
    // User Signatures
    Route::get('/signatures', [\App\Http\Controllers\SignatureController::class, 'index']);
    Route::get('/signatures/{signature}/image', [\App\Http\Controllers\SignatureController::class, 'getImage']);
    Route::put('/signatures/{signature}/default', [\App\Http\Controllers\SignatureController::class, 'setDefault']);
    Route::delete('/signatures/{signature}', [\App\Http\Controllers\SignatureController::class, 'destroy']);
    
    // Verify
    Route::post('/documents/verify', [\App\Http\Controllers\VerificationController::class, 'verify']);

    // Mobile KYC
    Route::post('/kyc/submit', [\App\Http\Controllers\KycController::class, 'submit']);
    Route::get('/kyc/me', [\App\Http\Controllers\KycController::class, 'me']);
    Route::get('/kyc/me/file/{type}', [\App\Http\Controllers\KycController::class, 'myFile']);

    // Organization Routes
    Route::prefix('organizations')->group(function () {
        Route::get('/', [\App\Http\Controllers\Tenant\OrganizationController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Tenant\OrganizationController::class, 'store']);
        Route::get('/current', [\App\Http\Controllers\Tenant\OrganizationController::class, 'current']);
        Route::post('/join', [\App\Http\Controllers\Tenant\OrganizationController::class, 'join']);
        Route::post('/switch', [\App\Http\Controllers\Tenant\OrganizationController::class, 'switch']);
        
        Route::get('/{organization}', [\App\Http\Controllers\Tenant\OrganizationController::class, 'show']);
        Route::put('/{organization}', [\App\Http\Controllers\Tenant\OrganizationController::class, 'update']);
        Route::delete('/{organization}', [\App\Http\Controllers\Tenant\OrganizationController::class, 'destroy']);
        
        // Members management
        Route::get('/{organization}/members', [\App\Http\Controllers\Tenant\MemberController::class, 'index']);
        Route::put('/{organization}/members/{member}', [\App\Http\Controllers\Tenant\MemberController::class, 'update']);
        Route::delete('/{organization}/members/{member}', [\App\Http\Controllers\Tenant\MemberController::class, 'destroy']);
        
        // Invitations management
        Route::get('/{organization}/invitations', [\App\Http\Controllers\Tenant\InvitationController::class, 'index']);
        Route::post('/{organization}/invitations', [\App\Http\Controllers\Tenant\InvitationController::class, 'store']);
        Route::delete('/{organization}/invitations/{invitation}', [\App\Http\Controllers\Tenant\InvitationController::class, 'destroy']);
    });

    // Quota Management (Owner only)
    Route::prefix('quota')->group(function () {
        Route::get('/', [\App\Http\Controllers\QuotaController::class, 'index']);
        Route::put('/', [\App\Http\Controllers\QuotaController::class, 'update']);
        Route::put('/users/{userId}', [\App\Http\Controllers\QuotaController::class, 'updateUserOverride']);
    });

    // Portal Settings
    Route::prefix('portal-settings')->group(function () {
        Route::get('/', [\App\Http\Controllers\Tenant\PortalSettingsController::class, 'show']);
        Route::put('/', [\App\Http\Controllers\Tenant\PortalSettingsController::class, 'update']);
        Route::post('/logo', [\App\Http\Controllers\Tenant\PortalSettingsController::class, 'uploadLogo']);
        Route::post('/banner', [\App\Http\Controllers\Tenant\PortalSettingsController::class, 'uploadBanner']);
    });

    // User Profile
    Route::get('/profile', [\App\Http\Controllers\UserController::class, 'profile']);
});

// Public verify endpoint (no auth required)
Route::post('/verify/upload', [\App\Http\Controllers\VerifyController::class, 'upload']);
Route::get('/verify/{token}', [\App\Http\Controllers\VerifyController::class, 'verify']);
