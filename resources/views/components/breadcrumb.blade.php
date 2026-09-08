@props([
    // Each item: ['label' => string, 'href' => ?string] — the last item is the current page
    // (no href) and gets aria-current="page".
    'items' => [],
])

<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-2 font-mono text-mono text-muted-foreground">
        @foreach ($items as $index => $item)
            @if ($index > 0)
                <li aria-hidden="true">/</li>
            @endif

            <li>
                @if (!empty($item['href']) && !$loop->last)
                    <a href="{{ $item['href'] }}" class="hover:text-accent-text">{{ $item['label'] }}</a>
                @else
                    <span aria-current="page" class="text-foreground">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
