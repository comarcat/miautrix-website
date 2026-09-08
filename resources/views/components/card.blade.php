@props([
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-card border border-border bg-card p-6 shadow-elevation-1']) }}>
    @if ($title)
        <h3 class="text-heading-3 text-card-foreground">{{ $title }}</h3>
    @elseif (isset($header))
        <div class="mb-2">{{ $header }}</div>
    @endif

    <div class="text-body text-card-foreground {{ $title || isset($header) ? 'mt-2' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="mt-4 border-t border-border pt-4">
            {{ $footer }}
        </div>
    @endisset
</div>
