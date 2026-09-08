<?xml version="1.0" encoding="UTF-8"?>
{{--
    E4-T6 (§9 step 24) — hand-rolled RSS 2.0, no package. {{ }} (not {!! !!}) throughout: XML's
    escaping needs are a strict subset of Blade's default htmlspecialchars() escaping, so plain
    interpolation is both correct and safe here — the one exception is each article's HTML body,
    wrapped in CDATA instead (a reader's whole point is showing the formatted content), with any
    literal "]]>" inside it neutralized so it can't prematurely close the CDATA section.
--}}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title>{{ config('app.name') }}</title>
        <link>{{ route('home') }}</link>
        <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml" />
        <description>Articles and notes from {{ config('app.name') }}.</description>
        <language>en-us</language>
        @if ($articles->isNotEmpty())
            <lastBuildDate>{{ $articles->first()->published_at->toRfc2822String() }}</lastBuildDate>
        @endif

        @foreach ($articles as $article)
            <item>
                <title>{{ $article->title }}</title>
                <link>{{ route('blog.show', $article->slug) }}</link>
                <guid isPermaLink="true">{{ route('blog.show', $article->slug) }}</guid>
                <pubDate>{{ $article->published_at->toRfc2822String() }}</pubDate>
                <description>{{ $article->excerpt }}</description>
                <content:encoded><![CDATA[{!! str_replace(']]>', ']]]]><![CDATA[>', $article->body) !!}]]></content:encoded>
            </item>
        @endforeach
    </channel>
</rss>
