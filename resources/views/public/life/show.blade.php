{{-- E4-T8 — reuses public.blog.show's own structure, minus the PDF export / share-links
     buttons (backlog item 5/6 scoped those to the professional blog and projects only). --}}
<x-layouts::app
    :title="$article->seo_title ?: ($article->title . ' — miautrix')"
    :description="$article->meta_description ?: $article->excerpt"
    :image="\App\Support\Seo\OgImage::resolve($article->og_image_id)"
    :canonical="$article->canonical_url ?: route('life.show', $article->slug)"
>
    <x-slot:jsonLd>
        <x-json-ld :data="[
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $article->title,
            'description' => $article->excerpt,
            'url' => route('life.show', $article->slug),
            'datePublished' => optional($article->published_at)->toIso8601String(),
        ]" />
    </x-slot:jsonLd>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[
            ['label' => 'Home', 'href' => route('home')],
            ['label' => 'Life', 'href' => route('life.index')],
            ['label' => $article->title],
        ]" />

        <article class="flex flex-col gap-4">
            <p class="font-mono text-mono text-muted-foreground">
                {{ $article->published_at->format('F j, Y') }}
            </p>
            <h1 class="text-display text-foreground">{{ $article->title }}</h1>

            {{-- E4-T9 — [youtube:ID] shortcodes in the body expand to the click-to-play
                 facade; the RichEditor schema has no <script> node, so expand() only ever
                 substitutes a widget for a shortcode string. --}}
            <div class="text-body text-foreground [&>*+*]:mt-4">
                {!! \App\Support\Content\YoutubeShortcode::expand($article->body) !!}
            </div>
        </article>
    </div>
</x-layouts::app>
