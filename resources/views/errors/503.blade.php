@php
    /**
     * E1-T3 (Phase 2, p2-step-03) — the styled maintenance page.
     *
     * Laravel renders this automatically while `php artisan down` is in effect, BEFORE the
     * app layout, the Vite manifest, or the CSP middleware are in play — so it is a
     * self-contained HTML document with its own inlined token CSS (the same
     * `--background` / `--foreground` / … palette and values as resources/css/app.css,
     * light + dark via prefers-color-scheme). No @vite, no external URL, no `style="…"`
     * attribute.
     *
     * The estimated return time is computed here as a 30-minute default; E1-T4 overrides
     * `$eta` with the real `Retry-After` / cached value the operator sets when taking the
     * site down. Both a machine-readable ISO timestamp (for the JS countdown) and a
     * plain-text sentence (which needs no JS at all — acceptance criterion 2) are rendered.
     */
    use Illuminate\Support\Carbon;

    $eta = ($eta ?? null) instanceof Carbon ? $eta : Carbon::now()->addMinutes(30);
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
        <p>We expect to be back around
            <strong>{{ $eta->format('H:i') }} UTC</strong>
            ({{ $eta->diffForHumans(['parts' => 2, 'short' => true]) }}).
        </p>
        <p class="countdown" data-countdown="{{ $eta->toIso8601String() }}" aria-hidden="true"></p>
    </main>

    <script>
        (function () {
            var el = document.querySelector('[data-countdown]');
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
</body>
</html>
