@props([
    'title' => 'miautrix',
    'description' => 'Professional IT portfolio and blog.',
    'image' => null,
    'canonical' => null,
])

<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme ?? 'technical' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Above-the-fold weights only — the rest of the family loads on demand via the
         @font-face declarations in app.css, per §7. --}}
    <link rel="preload" href="{{ asset('fonts/ibm-plex-sans-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/ibm-plex-sans-600.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- Found in review: "the favicon.ico is not updated still showing the old one" — the
         public layout never declared an explicit <link rel="icon"> at all, so browsers fell
         back to their own default /favicon.ico probe, and Cloudflare's edge had a 4-hour-old
         cached copy of that bare URL. An explicit tag with a content-hash-free but
         file-mtime-based version query string makes this a distinct URL every time the file
         actually changes, so both the browser and Cloudflare treat it as new (a fresh URL is
         never a cache HIT) — no manual Cloudflare purge needed, now or for any future swap. --}}
    <link rel="icon" href="/favicon.ico?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
    <link rel="icon" href="/favicon.svg?v={{ filemtime(public_path('favicon.svg')) }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ filemtime(public_path('apple-touch-icon.png')) }}">

    <x-meta :title="$title" :description="$description" :image="$image" :canonical="$canonical" />

    {{-- Organization JSON-LD is site-wide (every page is part of the same site); a page's own
         Person/CreativeWork/BlogPosting schema layers on top via the $jsonLd slot. --}}
    <x-json-ld :data="[
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'miautrix',
        'url' => route('home'),
    ]" />
    @isset($jsonLd)
        {{ $jsonLd }}
    @endisset

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- E3-T4 (§9 step 20) — special-event theme token overrides. Only when the dynamic
         flag is on AND the active theme is not one of the two seed themes (technical/matrix
         render entirely from app.css, unchanged). Values are hard-sanitised (no <>{};) and
         keys are constrained to a CSS custom-property shape, so {!! !!} is safe here; the
         block carries the per-request CSP nonce SecurityHeaders sets. --}}
    @if (config('site.themes.dynamic') && ! in_array($theme ?? 'technical', ['technical', 'matrix'], true))
        @php
            $activeTheme = \App\Models\Theme::query()->where('key', $theme)->first();
            $tokenCss = collect($activeTheme?->tokens ?? [])
                ->filter(fn ($value, $key) => is_string($key)
                    && preg_match('/^--[A-Za-z0-9-]+$/', $key) === 1
                    && (is_string($value) || is_numeric($value)))
                ->map(fn ($value, $key) => $key . ':' . preg_replace('/[<>{};]/', '', (string) $value))
                ->implode(';');
        @endphp
        @if ($activeTheme)
            <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">:root{ {!! $tokenCss !!} }</style>
        @endif
    @endif
</head>
<body class="min-h-screen bg-background text-foreground font-sans antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-input focus:bg-primary focus:px-4 focus:py-2 focus:text-on-primary">
        Skip to content
    </a>

    <header class="border-b border-border">
        <div class="mx-auto flex max-w-(--container-content) items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-mono text-sm font-medium tracking-wide text-foreground">
                <img src="{{ asset('images/brand/miautrix-logo.png') }}" alt="" class="h-8 w-8 object-contain">
                <span>miautrix</span>
            </a>

            <nav aria-label="Primary" class="flex items-center gap-6 text-sm">
                <a href="{{ route('home') }}" class="hover:text-accent-text">Home</a>
                <a href="{{ route('about') }}" class="hover:text-accent-text">About</a>
                <a href="{{ route('experience') }}" class="hover:text-accent-text">Experience</a>
                <a href="{{ route('skills') }}" class="hover:text-accent-text">Skills</a>
                <a href="{{ route('projects.index') }}" class="hover:text-accent-text">Projects</a>
                <a href="{{ route('resume') }}" class="hover:text-accent-text">Resume</a>
                <a href="{{ route('blog.index') }}" class="hover:text-accent-text">Blog</a>
                <a href="{{ route('connect') }}" class="hover:text-accent-text">Connect</a>
                <a href="{{ route('contact') }}" class="hover:text-accent-text">Contact</a>
                <x-theme-switcher :theme="$theme ?? 'technical'" />
            </nav>
        </div>
    </header>

    <main id="main-content" class="mx-auto max-w-(--container-content) px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-border">
        <div class="mx-auto flex max-w-(--container-content) flex-wrap items-center justify-between gap-4 px-4 py-6 text-sm text-muted-foreground">
            <span>&copy; {{ now()->year }} miautrix.</span>

            {{-- Found in review: "add to the social profiles, a field to check if it
                 should appear on the footer of the website" — only the ones marked
                 show_in_footer, unlike the full list on /connect. --}}
            <x-footer-social-profiles />
        </div>
    </footer>
</body>
</html>
