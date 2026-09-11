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

            {{-- E4-T2 — real PDF export (routes/web.php, outside cache.public). --}}
            <div>
                <x-button :href="route('blog.pdf', $article->slug)" variant="outline" size="sm">Download PDF</x-button>
            </div>

            {{-- The RichEditor body is already-sanitized HTML produced by Filament's own
                 Tiptap editor (E3-T6) — no raw user input ever reaches this page.
                 [&>*+*]:mt-4 spaces block elements without needing the (uninstalled)
                 @tailwindcss/typography plugin.

                 Found in review: this used max-w-(--breakpoint-xs) — a 375px MEDIA-QUERY
                 breakpoint token (app.css), not a content-width design token — which pinned
                 every article's body to phone width even on desktop (reported: "the space
                 for the content is like the space on a phone"). Removed; content now fills
                 the same --container-content width the header/nav already use. --}}
            <div class="text-body text-foreground [&>*+*]:mt-4">
                {!! $article->body !!}
            </div>

            {{-- E2-T3 — every button routes through /s/{network}/article/{id} (ShareRedirectController)
                 which logs one share_clicks row and then 302s to the network's own share endpoint. --}}
            <x-share-links
                class="mt-8 border-t border-border pt-6"
                :url="route('blog.show', $article->slug)"
                :title="$article->title"
                :summary="$article->excerpt"
                share-type="article"
                :share-id="$article->id"
            />
        </article>
    </div>
</x-layouts::app>
