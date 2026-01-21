<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\Tenant\OrganizationController as TenantOrganizationController;
use Inertia\Inertia;

Route::get('/api-docs', [ApiDocumentationController::class, 'index'])->name('api.docs');
Route::get('/docs/api', [ApiDocumentationController::class, 'index'])->name('api.docs.ui');

Route::get('/', function () {
    return Inertia::render('RootRedirect');
})->name('root');

Route::get('/login', function () {
    $userAgent = (string) request()->header('User-Agent');
    $isMobile = preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $userAgent) === 1;

    if ($isMobile && (request()->has('code') || (request()->has('email') && request()->has('token')))) {
        $query = request()->getQueryString();
        return redirect('/invite' . ($query ? '?' . $query : ''));
    }

    return Inertia::render('Login');
})->name('login');

Route::get('/invite', function () {
    $query = request()->getQueryString();

    $userAgent = (string) request()->header('User-Agent');
    $isMobile = preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $userAgent) === 1;

    if (!$isMobile) {
        return redirect('/login' . ($query ? '?' . $query : ''));
    }

    $code = request()->query('code');
    $deepLink = $code ? ('ttd://invite?code=' . urlencode((string) $code)) : 'ttd://invite';
    $webFallback = '/login' . ($query ? '?' . $query : '');

    $autoOpenScript = $isMobile
        ? ('<script>' .
            'setTimeout(function(){ try{ window.location.href = ' . json_encode($deepLink) . '; } catch(e){} }, 250);' .
            '</script>')
        : '';

    return response()->make(
        '<!doctype html>' .
        '<html lang="en">' .
        '<head>' .
        '<meta charset="utf-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<title>Open App</title>' .
        '<style>' .
        'body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;display:flex;min-height:100vh;align-items:center;justify-content:center;background:#0b1220;color:#e5e7eb;margin:0;padding:24px}' .
        '.card{width:100%;max-width:420px;background:#0f172a;border:1px solid rgba(148,163,184,.2);border-radius:16px;padding:20px}' .
        'h1{font-size:18px;margin:0 0 6px}' .
        'p{margin:0 0 16px;color:rgba(226,232,240,.8);font-size:14px;line-height:1.4}' .
        'a.btn{display:block;text-align:center;padding:12px 14px;border-radius:12px;background:#4f46e5;color:#fff;text-decoration:none;font-weight:600;margin-bottom:10px}' .
        'a.link{display:block;text-align:center;color:#a5b4fc;text-decoration:none;font-weight:600}' .
        '</style>' .
        '</head>' .
        '<body>' .
        '<div class="card">' .
        '<h1>Buka undangan di aplikasi</h1>' .
        '<p>Klik tombol di bawah untuk membuka aplikasi. Jika tidak berhasil, lanjutkan lewat web.</p>' .
        '<a class="btn" href="' . e($deepLink) . '">Buka App</a>' .
        '<a class="link" href="' . e($webFallback) . '">Lanjut via Web</a>' .
        '</div>' .
        $autoOpenScript .
        '</body>' .
        '</html>',
        200,
        ['Content-Type' => 'text/html; charset=UTF-8']
    );
})->name('invite');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/{tenantSlug}/dashboard', function (string $tenantSlug) {
    return Inertia::render('Dashboard');
})->middleware('tenant.slug')->name('tenant.dashboard');

Route::get('/documents', function () {
    return Inertia::render('Documents');
})->name('documents');

Route::get('/{tenantSlug}/documents', function (string $tenantSlug) {
    return Inertia::render('Documents');
})->middleware('tenant.slug')->name('tenant.documents');

Route::get('/signature-setup', function () {
    return Inertia::render('SignatureSetup');
})->name('signature.setup');

Route::get('/{tenantSlug}/signature-setup', function (string $tenantSlug) {
    return Inertia::render('SignatureSetup');
})->middleware('tenant.slug')->name('tenant.signature.setup');

Route::get('/verify', function () {
    return Inertia::render('Verify');
})->name('verify');

Route::get('/{tenantSlug}/verify', function (string $tenantSlug) {
    return Inertia::render('Verify');
})->middleware('tenant.slug')->name('tenant.verify');

Route::get('/qr-positioner/{documentId}', function (int $documentId) {
    return Inertia::render('DocumentQrPositioner', [
        'documentId' => $documentId,
    ]);
})->name('qr.positioner');

Route::get('/organization/setup', function () {
    return Inertia::render('Organization/Setup');
})->name('organization.setup');

Route::get('/organization/members', function () {
    return Inertia::render('Organization/Members');
})->name('organization.members');

Route::get('/{tenantSlug}/organization/members', function (string $tenantSlug) {
    return Inertia::render('Organization/Members');
})->middleware('tenant.slug')->name('tenant.organization.members');

Route::get('/organization/invitations', function () {
    return Inertia::render('Organization/Invitations');
})->name('organization.invitations');

Route::get('/{tenantSlug}/organization/invitations', function (string $tenantSlug) {
    return Inertia::render('Organization/Invitations');
})->middleware('tenant.slug')->name('tenant.organization.invitations');

Route::get('/organization/billing', function () {
    return Inertia::render('Organization/Billing');
})->name('organization.billing');

Route::get('/{tenantSlug}/organization/billing', function (string $tenantSlug) {
    return Inertia::render('Organization/Billing');
})->middleware('tenant.slug')->name('tenant.organization.billing');

Route::post('/organization/switch', [TenantOrganizationController::class, 'switch'])->name('organization.switch');

Route::get('/profile', function () {
    return Inertia::render('Profile');
})->name('profile');

Route::get('/organization/settings', function () {
    return Inertia::render('Organization/Settings');
})->name('organization.settings');

Route::get('/{tenantSlug}/organization/settings', function (string $tenantSlug) {
    return Inertia::render('Organization/Settings');
})->middleware('tenant.slug')->name('tenant.organization.settings');

Route::get('/organization/quota', function () {
    return Inertia::render('Organization/Quota');
})->name('organization.quota');

Route::get('/{tenantSlug}/organization/quota', function (string $tenantSlug) {
    return Inertia::render('Organization/Quota');
})->middleware('tenant.slug')->name('tenant.organization.quota');
