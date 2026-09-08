@props([
    // Each item: ['src' => string, 'alt' => string]
    'images' => [],
])

<div
    x-data="{
        open: false,
        index: 0,
        previouslyFocused: null,
        images: {{ Illuminate\Support\Js::from(collect($images)->map(fn ($i) => ['src' => $i['src'] ?? '', 'alt' => $i['alt'] ?? ''])->values()) }},
        show(i) {
            this.index = i;
            this.previouslyFocused = document.activeElement;
            this.open = true;
            this.$nextTick(() => this.$refs.closeButton?.focus());
        },
        close() {
            this.open = false;
            this.previouslyFocused?.focus();
        },
        next() { this.index = (this.index + 1) % this.images.length },
        prev() { this.index = (this.index - 1 + this.images.length) % this.images.length },
    }"
    {{ $attributes }}
>
    @if (count($images))
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
            @foreach ($images as $i => $image)
                <button
                    type="button"
                    x-on:click="show({{ $i }})"
                    class="overflow-hidden rounded-card border border-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <img src="{{ $image['src'] ?? '' }}" alt="{{ $image['alt'] ?? '' }}" class="aspect-video w-full object-cover">
                </button>
            @endforeach
        </div>
    @else
        <p class="text-body text-muted-foreground">No images to show yet.</p>
    @endif

    <div
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="close()"
        role="dialog"
        aria-modal="true"
        aria-label="Image gallery"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/90 p-4"
    >
        <button type="button" x-ref="closeButton" x-on:click="close()" class="absolute right-4 top-4 text-foreground">
            Close
        </button>
        <button type="button" x-on:click="prev()" class="absolute left-4 text-foreground" x-show="images.length > 1">Prev</button>
        <img :src="images[index]?.src" :alt="images[index]?.alt" class="max-h-[80vh] max-w-full rounded-card">
        <button type="button" x-on:click="next()" class="absolute right-4 text-foreground" x-show="images.length > 1">Next</button>
    </div>
</div>
