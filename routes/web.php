<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiDocumentationController;
use Inertia\Inertia;

Route::get('/api-docs', [ApiDocumentationController::class, 'index'])->name('api.docs');

Route::get('/', function () {
    return Inertia::render('RootRedirect');
})->name('root');

Route::get('/login', function () {
    return Inertia::render('Login');
})->name('login');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/signature-setup', function () {
    return Inertia::render('SignatureSetup');
})->name('signature.setup');

Route::get('/verify', function () {
    return Inertia::render('Verify');
})->name('verify');

Route::get('/qr-positioner/{documentId}', function (int $documentId) {
    return Inertia::render('DocumentQrPositioner', [
        'documentId' => $documentId,
    ]);
})->name('qr.positioner');
