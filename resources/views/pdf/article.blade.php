<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $article->title }} — miautrix</title>
    {{-- E4-T1 — Dompdf print layout. Single inline <style>, no @vite, no external link/script. --}}
    <style>
        @page { margin: 96px 64px; }
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #111827;
        }
        .doc-header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 24px; }
        .doc-kicker { font-size: 8pt; letter-spacing: 0.12em; text-transform: uppercase; color: #6b7280; }
        h1 { font-size: 22pt; margin: 6px 0 4px; }
        .meta { font-size: 9pt; color: #6b7280; margin-top: 8px; }
        h2 { font-size: 14pt; margin: 20px 0 6px; }
        h3 { font-size: 12pt; margin: 16px 0 5px; }
        p { margin: 0 0 12px; }
        ul, ol { margin: 0 0 12px 20px; padding: 0; }
        blockquote { margin: 0 0 12px; padding-left: 12px; border-left: 3px solid #d1d5db; color: #4b5563; }
        pre, code { font-family: "DejaVu Sans Mono", monospace; font-size: 9pt; }
        pre { background: #f3f4f6; padding: 10px; border-radius: 4px; white-space: pre-wrap; }
        .footer { margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 8pt; color: #9ca3af; }
        a { color: #1d4ed8; text-decoration: none; }
    </style>
</head>
<body>
    <div class="doc-header">
        <div class="doc-kicker">Article</div>
        <h1>{{ $article->title }}</h1>
        <div class="meta">
            @if ($article->published_at)
                {{ $article->published_at->format('F j, Y') }}
            @endif
        </div>
    </div>

    {!! $article->body !!}

    <div class="footer">
        Generated from miautrix.tech{{ ' · ' . now()->format('F j, Y') }} · {{ route('blog.show', $article->slug) }}
    </div>
</body>
</html>
