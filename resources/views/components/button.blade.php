@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    // Every variant maps to --color-* tokens only (E4-T3 acceptance 1) — no component-local
    // hex, so the same markup renders correctly in both themes with zero conditional logic.
    $variants = [
        'primary' => 'bg-primary text-on-primary hover:opacity-90',
        'secondary' => 'bg-secondary text-on-secondary hover:opacity-90',
        'outline' => 'border border-border text-foreground hover:bg-muted',
        'destructive' => 'bg-destructive text-on-destructive hover:opacity-90',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-input font-medium shadow-elevation-1 transition-opacity focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50 disabled:pointer-events-none '
        . ($variants[$variant] ?? $variants['primary']) . ' '
        . ($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
