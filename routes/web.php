<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiDocumentationController;

Route::get('/api-docs', [ApiDocumentationController::class, 'index'])->name('api.docs');
Route::get('/docs/api', [ApiDocumentationController::class, 'index'])->name('api.docs.ui');

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
