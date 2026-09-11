@props([
    'title',
    'description',
    'image' => null,
    'canonical' => null,
    'type' => 'website',
])

@php
    // E3-T7 — pin the canonical (and og:url) host to the one canonical host, so a page
    // served on `www.` and on the apex both advertise the same URL (paired with the
    // Cloudflare www->apex 301). Path + query are preserved: from the page's explicit
    // :canonical when given, otherwise from the current request URI.
    if ($canonical) {
        $canonicalPath = parse_url($canonical, PHP_URL_PATH) ?: '/';
        $canonicalQuery = parse_url($canonical, PHP_URL_QUERY);
        $canonicalTail = $canonicalPath . ($canonicalQuery ? '?' . $canonicalQuery : '');
    } else {
        $canonicalTail = request()->getRequestUri();
    }
    $canonicalUrl = 'https://' . config('site.canonical_host') . $canonicalTail;
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonicalUrl }}">

<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
@if ($image)
    <meta property="og:image" content="{{ $image }}">
@endif

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
