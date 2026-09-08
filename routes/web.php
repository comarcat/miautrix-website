<?php

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

require __DIR__ . '/settings.php';
