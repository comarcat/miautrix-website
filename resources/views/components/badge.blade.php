@props([
    'variant' => 'default',
])

@php
    $variants = [
        'default' => 'bg-muted text-muted-foreground',
        'accent' => 'bg-accent text-on-accent',
        'destructive' => 'bg-destructive text-on-destructive',
        // Phase 2 (E4-T6) — the project card's "On time · On budget" badge.
        'success' => 'bg-primary text-on-primary',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 font-mono text-mono ' . ($variants[$variant] ?? $variants['default'])]) }}>
    {{ $slot }}
</span>
