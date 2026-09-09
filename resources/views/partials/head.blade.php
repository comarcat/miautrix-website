<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

{{-- resources/css/authenticated.css, not app.css — this partial is only ever included by
     the retained starter-kit's own layouts (dashboard sidebar/header, auth card/simple/split),
     which need Flux's own CSS to render at all. See authenticated.css's own docblock. --}}
@vite(['resources/css/authenticated.css', 'resources/js/app.js'])
@fluxAppearance
