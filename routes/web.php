<?php

use App\Http\Middleware\EnsureMfaConfirmed;
use Illuminate\Support\Facades\Route;

// Minimal hello-world for step 5 (blueprint §9) — proves the real domain is live over HTTPS,
// served by PHP-FPM, before any feature work exists. Replaced by the real design system's home
// page in epic 04.
Route::view('/', 'public.hello')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Placeholder — the real admin panel is Filament, epic 03. This exists now purely so
// EnsureMfaConfirmed has something real to guard for step 10's acceptance criteria; every
// route under /admin inherits the mandatory-MFA gate regardless of what epic 03 adds here.
Route::middleware(['auth', EnsureMfaConfirmed::class])->prefix('admin')->group(function () {
    Route::get('/', fn () => 'Admin — placeholder until epic 03 (Filament).')->name('admin.dashboard');
});

require __DIR__ . '/settings.php';
