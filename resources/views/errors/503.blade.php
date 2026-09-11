@php
    /**
     * E1-T3 / E1-T4 (Phase 2, p2-step-03 / p2-step-04) — the styled maintenance page.
     *
     * Laravel renders this automatically while `php artisan down` is in effect, BEFORE the
     * app layout, the Vite manifest, or the CSP middleware are in play — so it is a
     * self-contained HTML document with its own inlined token CSS (the same
     * `--background` / `--foreground` / … palette and values as resources/css/app.css,
     * light + dark via prefers-color-scheme). No @vite, no external URL, no `style="…"`
     * attribute.
     *
     * ETA source (E1-T4): when the site is taken down with
     * `php artisan down --render="errors::503" --retry=N`, Laravel's maintenance middleware
     * throws an HttpException carrying a `Retry-After: N` header, which the exception handler
     * passes to this view as `$exception`. That is the ONLY reliable ETA signal here. When it
     * is present the countdown target is `now() + N seconds`, rendered as an ISO-8601 string
     * inside `data-countdown`. When it is absent (a plain `php artisan down`), there is no
     * countdown element at all — just a static "shortly" line — so the client never sees a
     * half-initialised timer (acceptance criterion 2).
     */
    use Illuminate\Support\Carbon;

    $retryAfter = null;

    if (isset($exception) && method_exists($exception, 'getHeaders')) {
        $header = $exception->getHeaders()['Retry-After'] ?? null;
        $retryAfter = is_numeric($header) ? (int) $header : null;
    }

    // Explicit override still wins (used by the direct-render test and any future caller).
    if (($eta ?? null) instanceof Carbon) {
        $retryAfter = (int) round(Carbon::now()->diffInSeconds($eta, false));
    }

    $eta = $retryAfter !== null && $retryAfter > 0
        ? Carbon::now()->addSeconds($retryAfter)
        : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>We&rsquo;ll be back soon &mdash; miautrix</title>
    <style>
        :root {
            --background: #FAFAFA;
            --foreground: #09090B;
            --card: #FFFFFF;
            --muted-foreground: #475569;
            --border: #E4E4E7;
            --accent-text: #2563EB;
            --radius-card: 12px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --background: #09090B;
                --foreground: #FAFAFA;
                --card: #111113;
                --muted-foreground: #A1A1AA;
                --border: #27272A;
                --accent-text: #60A5FA;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background-color: var(--background);
            color: var(--foreground);
            font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif;
            line-height: 1.5;
        }

        .panel {
            width: 100%;
            max-width: 32rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            background-color: var(--card);
            padding: 40px;
            text-align: center;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 28px;
            font-weight: 600;
        }

        p {
            margin: 8px 0;
            color: var(--muted-foreground);
        }

        .countdown {
            margin-top: 20px;
            font-family: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', monospace;
            font-size: 20px;
            font-weight: 600;
            color: var(--accent-text);
        }

        @media (prefers-reduced-motion: reduce) {
            .countdown { display: none; }
        }
    </style>
</head>
<body>
    <main class="panel">
        <h1>We&rsquo;ll be back soon</h1>
        <p>miautrix is down for a short, planned maintenance window.</p>
        @if ($eta)
            <p>We expect to be back around
                <strong>{{ $eta->format('H:i') }} UTC</strong>
                ({{ $eta->diffForHumans(['parts' => 2, 'short' => true]) }}).
            </p>
            <p class="countdown" data-countdown="{{ $eta->toIso8601String() }}" aria-hidden="true"></p>
        @else
            <p>We expect to be back shortly. Please check again in a few minutes.</p>
        @endif
    </main>

    @if ($eta)
        {{-- Countdown script emitted only when there is a target — with no ETA there is no
             element to tick, so no script ships either (acceptance criterion 2). --}}
        <script>
            (function () {
                var el = document.querySelector('.countdown[data-countdown]');
                if (!el) { return; }
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

                var target = new Date(el.getAttribute('data-countdown')).getTime();

                function pad(n) { return (n < 10 ? '0' : '') + n; }

                function tick() {
                    var remaining = target - Date.now();
                    if (remaining <= 0) {
                        el.textContent = 'Refreshing…';
                        setTimeout(function () { location.reload(); }, 3000);
                        return;
                    }
                    var mins = Math.floor(remaining / 60000);
                    var secs = Math.floor((remaining % 60000) / 1000);
                    el.textContent = 'Back in ' + pad(mins) + ':' + pad(secs);
                    setTimeout(tick, 1000);
                }

                tick();
            })();
        </script>
    @endif
</body>
</html>
