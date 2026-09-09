<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

{{-- Versioned by file mtime — see layouts/app.blade.php's copy of this comment for why. --}}
<link rel="icon" href="/favicon.ico?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
<link rel="icon" href="/favicon.svg?v={{ filemtime(public_path('favicon.svg')) }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ filemtime(public_path('apple-touch-icon.png')) }}">

@fonts

{{-- resources/css/authenticated.css, not app.css — this partial is only ever included by
     the retained starter-kit's own layouts (dashboard sidebar/header, auth card/simple/split),
     which need Flux's own CSS to render at all. See authenticated.css's own docblock. --}}
@vite(['resources/css/authenticated.css', 'resources/js/app.js'])
@fluxAppearance
