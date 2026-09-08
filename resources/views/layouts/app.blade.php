@props([
    'title' => 'miautrix',
    'description' => 'Professional IT portfolio and blog.',
    'image' => null,
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

    <x-meta :title="$title" :description="$description" :image="$image" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground font-sans antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-input focus:bg-primary focus:px-4 focus:py-2 focus:text-on-primary">
        Skip to content
    </a>

    <header class="border-b border-border">
        <div class="mx-auto flex max-w-(--container-content) items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="font-mono text-sm font-medium tracking-wide text-foreground">
                miautrix
            </a>

            <nav aria-label="Primary" class="flex items-center gap-6 text-sm">
                <a href="{{ route('home') }}" class="hover:text-accent-text">Home</a>
                <a href="{{ route('about') }}" class="hover:text-accent-text">About</a>
                <a href="{{ route('experience') }}" class="hover:text-accent-text">Experience</a>
                <a href="{{ route('skills') }}" class="hover:text-accent-text">Skills</a>
                <a href="{{ route('projects.index') }}" class="hover:text-accent-text">Projects</a>
                <a href="{{ route('contact') }}" class="hover:text-accent-text">Contact</a>
                {{-- Blog lands once /blog exists (E4-T6) — a link to a route that doesn't
                     exist yet would itself be a broken-by-construction bug, not a placeholder
                     worth shipping. --}}
                <x-theme-switcher :theme="$theme ?? 'technical'" />
            </nav>
        </div>
    </header>

    <main id="main-content" class="mx-auto max-w-(--container-content) px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-border">
        <div class="mx-auto max-w-(--container-content) px-4 py-6 text-sm text-muted-foreground">
            &copy; {{ now()->year }} miautrix.
        </div>
    </footer>
</body>
</html>
