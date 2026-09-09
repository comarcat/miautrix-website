<?php

use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\ConnectController;
use App\Http\Controllers\Public\DocumentDownloadController;
use App\Http\Controllers\Public\DocumentPreviewController;
use App\Http\Controllers\Public\ExperienceController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MediaController;
use App\Http\Controllers\Public\ProjectController;
use App\Http\Controllers\Public\ResumeController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\SkillsController;
use App\Http\Controllers\Public\ThemeController;
use Illuminate\Support\Facades\Route;

// The real design system's home page (E4-T4, §9 step 22) — replaces the step-5 hello-world
// placeholder that lived here (public.hello) from before any feature work existed.
//
// 'cache.public' (E5-T2, §9 step 26) wraps every content page below EXCEPT the two that must
// always reflect the current instant (feed.xml/sitemap.xml are already cheap, and a stale
// sitemap could hide a newly-published page from crawlers longer than necessary) and /contact
// (its own content never changes; the Livewire form underneath still hydrates and posts to a
// separate, never-cached endpoint regardless of whether this shell was served from cache).
Route::middleware('cache.public')->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/experience', [ExperienceController::class, 'index'])->name('experience');
    Route::get('/skills', [SkillsController::class, 'index'])->name('skills');
    // Found in review: blueprint §4/§9 step 23 scoped a public /resume page (route table:
    // "documents where kind=resume | public") — DocumentResource, the seeded placeholder
    // Document row, and documents.download all shipped, but no route/view ever read them.
    // An admin who uploaded a real resume via the admin panel had no way to see where a
    // visitor would find or download it, because there was nowhere.
    Route::get('/resume', [ResumeController::class, 'index'])->name('resume');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');
    // Found in review: "a new section to publish all social profiles there with their
    // logos near to the links" — every SocialProfile, not just the smaller subset
    // show_in_footer puts in the footer.
    Route::get('/connect', [ConnectController::class, 'index'])->name('connect');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
});
Route::get('/feed.xml', [BlogController::class, 'feed'])->name('feed');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// BUG FIXED (found investigating a secscanner.app report): /contact used to sit inside the
// cache.public group above, directly contradicting that group's own comment ("EXCEPT ... and
// /contact") — a real, severe bug, since this page mounts a live Livewire component
// (ContactForm). Caching its rendered HTML freezes one visitor's CSRF token/wire:snapshot
// into the response and serves it to every subsequent visitor verbatim (their own submit
// would carry someone else's stale token), and — because Livewire's own script/style
// auto-injection (SupportAutoInjectedAssets) listens on the RequestHandled event, which fires
// only after the whole middleware stack (cache.public included) already returned — a page
// cached before that listener runs is captured with NEITHER Livewire's <script> nor its
// <style> tag at all, leaving the form entirely inert client-side. Moved out here, its own
// route, never cached.
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

// Inline viewer for the /resume page's embedded PDF (Content-Disposition: inline, no
// download_count increment) — see DocumentPreviewController's own docblock.
Route::get('/documents/{document}/preview', DocumentPreviewController::class)->name('documents.preview');

// Sets the miautrix_theme cookie and redirects back (§9 step 20) — a plain form POST, not
// a fetch call, so the browser's own navigation is what reloads the page with no flash.
Route::post('/theme', [ThemeController::class, 'update'])->name('theme.set');

require __DIR__ . '/settings.php';
