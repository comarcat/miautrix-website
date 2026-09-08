<?php

use App\Http\Controllers\Public\DocumentDownloadController;
use App\Http\Controllers\Public\MediaController;
use Illuminate\Support\Facades\Route;

// Minimal hello-world for step 5 (blueprint §9) — proves the real domain is live over HTTPS,
// served by PHP-FPM, before any feature work exists. Replaced by the real design system's home
// page in epic 04.
Route::view('/', 'public.hello')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// /admin itself is now Filament's (E3-T1, app/Providers/Filament/AdminPanelProvider.php) —
// the E2-T5 placeholder route that used to live here is gone; EnsureMfaConfirmed is wired
// onto the panel's own authMiddleware instead.

// The one path to a private-media file (§5's media-serving contract, §9 step 16) — 404s
// unless the owning entity is published.
Route::get('/media/{media}/{filename}', [MediaController::class, 'show'])->name('media.show');

// Streams the current version and increments download_count (§5, §9 step 17) — public only
// for a published document.
Route::get('/documents/{document}/download', DocumentDownloadController::class)->name('documents.download');

require __DIR__ . '/settings.php';
