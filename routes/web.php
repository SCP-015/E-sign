<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiDocumentationController;
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

Route::get('/documents', function () {
    return Inertia::render('Documents');
})->name('documents');

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
