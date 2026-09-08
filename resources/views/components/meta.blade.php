@props([
    'title',
    'description',
    'image' => null,
    'canonical' => null,
    'type' => 'website',
])

@php
    $canonicalUrl = $canonical ?? url()->current();
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
