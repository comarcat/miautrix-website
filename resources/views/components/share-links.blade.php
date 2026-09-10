@props([
    'url',
    'title' => '',
    'summary' => '',
])

@php
    /**
     * Phase 2 (E2-T2) — plain share-intent links, one per network. No SDK, no tracking
     * script, no iframe. Every parameter is URL-encoded with rawurlencode(). The href points
     * at the network's own share endpoint directly; the /s/{network}/{type}/{id} first-party
     * click-logging redirect (E2-T3) wraps these where a subject id is known.
     *
     * The optional Web Share button is progressive enhancement: it is hidden by default and
     * only revealed by the inline script when navigator.share exists. That script carries the
     * per-request CSP nonce (Vite::cspNonce(), set by SecurityHeaders before the view renders).
     */
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    $s = rawurlencode($summary);

    $links = [
        'Facebook' => "https://www.facebook.com/sharer/sharer.php?u={$u}",
        'X' => "https://twitter.com/intent/tweet?url={$u}&text={$t}",
        'LinkedIn' => "https://www.linkedin.com/sharing/share-offsite/?url={$u}",
        'WhatsApp' => "https://api.whatsapp.com/send?text={$t}%20{$u}",
        'Reddit' => "https://www.reddit.com/submit?url={$u}&title={$t}",
        'Email' => "mailto:?subject={$t}&body={$s}%0A%0A{$u}",
    ];

    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }} data-share-links>
    <span class="font-mono text-mono text-muted-foreground">Share:</span>

    @foreach ($links as $network => $href)
        <a
            href="{{ $href }}"
            @if ($network !== 'Email') target="_blank" rel="noopener noreferrer" @endif
            class="inline-flex items-center rounded-input border border-border px-3 py-1 font-mono text-mono text-foreground hover:border-accent hover:text-accent-text"
            data-share-network="{{ \Illuminate\Support\Str::lower($network) }}"
        >{{ $network }}</a>
    @endforeach

    <button type="button" data-web-share hidden
        class="inline-flex items-center rounded-input border border-border px-3 py-1 font-mono text-mono text-foreground hover:border-accent hover:text-accent-text">
        Share&hellip;
    </button>
</div>

<script{!! $nonce ? ' nonce="' . e($nonce) . '"' : '' !!}>
    (function () {
        var root = document.querySelector('[data-share-links]');
        if (!root || !navigator.share) { return; }
        var btn = root.querySelector('[data-web-share]');
        if (!btn) { return; }
        btn.hidden = false;
        btn.addEventListener('click', function () {
            navigator.share({
                title: @json($title),
                text: @json($summary),
                url: @json($url),
            }).catch(function () {});
        });
    })();
</script>
