@props([
    'url',
    'title' => '',
    'summary' => '',
    // When both are set, each link points at the first-party /s/{network}/{type}/{id}
    // click-logging redirect (E2-T3) instead of the network's own share endpoint directly.
    'shareType' => null,
    'shareId' => null,
])

@php
    /**
     * Phase 2 (E2-T2 / E2-T3) — plain share-intent links, one per network. No SDK, no
     * tracking script, no iframe. Direct-mode hrefs are rawurlencode()'d; logged-mode hrefs
     * are `/s/{network}/{type}/{id}` and ShareRedirectController does the network-URL
     * construction after recording the click.
     *
     * The optional Web Share button is progressive enhancement: hidden by default, revealed
     * by the inline script only when navigator.share exists. That script carries the
     * per-request CSP nonce (Vite::cspNonce(), set by SecurityHeaders before the view renders).
     */
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    $s = rawurlencode($summary);

    $direct = [
        'Facebook' => "https://www.facebook.com/sharer/sharer.php?u={$u}",
        'X' => "https://twitter.com/intent/tweet?url={$u}&text={$t}",
        'LinkedIn' => "https://www.linkedin.com/sharing/share-offsite/?url={$u}",
        'WhatsApp' => "https://api.whatsapp.com/send?text={$t}%20{$u}",
        'Reddit' => "https://www.reddit.com/submit?url={$u}&title={$t}",
        'Email' => "mailto:?subject={$t}&body={$s}%0A%0A{$u}",
    ];

    $logged = $shareType !== null && $shareId !== null;

    $links = [];
    foreach ($direct as $network => $href) {
        $links[$network] = $logged
            ? url('/s/' . \Illuminate\Support\Str::lower($network) . '/' . $shareType . '/' . $shareId)
            : $href;
    }

    // Blog/project pages that mount this component are also cached by CachePublicPage for up
    // to an hour — a live Vite::cspNonce() baked in here would go stale on a cache hit, so
    // this bakes SecurityHeaders::NONCE_PLACEHOLDER instead, which that middleware substitutes
    // for the real, current-request nonce on the way out of every response, cached or not.
    $nonce = \App\Http\Middleware\SecurityHeaders::NONCE_PLACEHOLDER;
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
