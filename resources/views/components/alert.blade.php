@props([
    'variant' => 'info',
])

@php
    // 'info'/'success' both read as neutral/accent — the design system has no dedicated
    // success token (blueprint §7 lists no --color-success), so success reuses --color-accent
    // rather than inventing an unspecified color.
    $variants = [
        'info' => 'border-accent/40 bg-accent/10 text-foreground',
        'success' => 'border-accent/40 bg-accent/10 text-foreground',
        'warning' => 'border-border bg-muted text-foreground',
        'error' => 'border-destructive/40 bg-destructive/10 text-foreground',
    ];
@endphp

<div role="alert" {{ $attributes->merge(['class' => 'rounded-card border px-4 py-3 text-body ' . ($variants[$variant] ?? $variants['info'])]) }}>
    {{ $slot }}
</div>
