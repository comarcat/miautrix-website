<x-layouts::app
    :title="$article->seo_title ?: ($article->title . ' — miautrix')"
    :description="$article->meta_description ?: $article->excerpt"
    :image="\App\Support\Seo\OgImage::resolve($article->og_image_id)"
    :canonical="$article->canonical_url ?: route('blog.show', $article->slug)"
>
    <x-slot:jsonLd>
        <x-json-ld :data="[
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $article->title,
            'description' => $article->excerpt,
            'url' => route('blog.show', $article->slug),
            'datePublished' => optional($article->published_at)->toIso8601String(),
        ]" />
    </x-slot:jsonLd>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[
            ['label' => 'Home', 'href' => route('home')],
            ['label' => 'Blog', 'href' => route('blog.index')],
            ['label' => $article->title],
        ]" />

        <article class="flex flex-col gap-4">
            <p class="font-mono text-mono text-muted-foreground">
                {{ $article->published_at->format('F j, Y') }}
            </p>
            <h1 class="text-display text-foreground">{{ $article->title }}</h1>

            {{-- The RichEditor body is already-sanitized HTML produced by Filament's own
                 Tiptap editor (E3-T6) — no raw user input ever reaches this page.
                 [&>*+*]:mt-4 spaces block elements without needing the (uninstalled)
                 @tailwindcss/typography plugin. --}}
            <div class="max-w-(--breakpoint-xs) text-body text-foreground [&>*+*]:mt-4">
                {!! $article->body !!}
            </div>
        </article>
    </div>
</x-layouts::app>
