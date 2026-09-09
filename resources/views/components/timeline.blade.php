@props([
    // Each item: ['title' => string, 'subtitle' => ?string, 'period' => ?string,
    // 'description' => ?string, 'logo' => ?string (a company/institution logo URL)]
    'items' => [],
])

<ol {{ $attributes->merge(['class' => 'flex flex-col gap-8 border-l border-border pl-6']) }}>
    @forelse ($items as $item)
        <li class="relative">
            <span class="absolute -left-[27px] top-1.5 size-3 rounded-full bg-accent" aria-hidden="true"></span>

            <div class="flex items-start gap-4">
                {{-- Found in review: "add the logos of the companies / education
                     institutions" — shown at a fixed size regardless of the source image's
                     own dimensions, so a wide or tall logo never distorts the timeline's
                     layout. --}}
                @if (!empty($item['logo']))
                    <img
                        src="{{ $item['logo'] }}"
                        alt=""
                        class="size-12 shrink-0 rounded-card border border-border object-contain bg-card p-1"
                    >
                @endif

                <div class="flex flex-col gap-1">
                    @if (!empty($item['period']))
                        <span class="font-mono text-mono uppercase tracking-wide text-muted-foreground">{{ $item['period'] }}</span>
                    @endif
                    <h3 class="text-heading-3 text-foreground">{{ $item['title'] ?? '' }}</h3>
                    @if (!empty($item['subtitle']))
                        <p class="text-body text-muted-foreground">{{ $item['subtitle'] }}</p>
                    @endif
                    @if (!empty($item['description']))
                        <p class="text-body text-foreground">{{ $item['description'] }}</p>
                    @endif
                </div>
            </div>
        </li>
    @empty
        <li class="text-body text-muted-foreground">Nothing to show yet.</li>
    @endforelse
</ol>
