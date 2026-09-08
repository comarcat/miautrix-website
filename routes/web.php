<?php

use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\DocumentDownloadController;
use App\Http\Controllers\Public\ExperienceController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MediaController;
use App\Http\Controllers\Public\ProjectController;
use App\Http\Controllers\Public\SkillsController;
use App\Http\Controllers\Public\ThemeController;
use Illuminate\Support\Facades\Route;

// The real design system's home page (E4-T4, §9 step 22) — replaces the step-5 hello-world
// placeholder that lived here (public.hello) from before any feature work existed.
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [AboutController::class, 'index'])->name('about');
Route::get('/experience', [ExperienceController::class, 'index'])->name('experience');
Route::get('/skills', [SkillsController::class, 'index'])->name('skills');
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');
Route::view('/contact', 'public.contact')->name('contact');

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

// Sets the miautrix_theme cookie and redirects back (§9 step 20) — a plain form POST, not
// a fetch call, so the browser's own navigation is what reloads the page with no flash.
Route::post('/theme', [ThemeController::class, 'update'])->name('theme.set');

require __DIR__ . '/settings.php';
