<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>miautrix</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #0b0f14;
            --fg: #e8edf3;
            --muted: #8a97a8;
            --accent: #5eead4;
            --glitch-red: #ff2a6d;
            --glitch-cyan: #05d9e8;
        }
        * { box-sizing: border-box; }
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            color: var(--fg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            text-align: center;
            padding: 2rem;
            overflow: hidden;
        }

        /* CRT scanlines + flicker, applied over the whole viewport */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 2;
            background: repeating-linear-gradient(
                to bottom,
                rgba(255, 255, 255, 0.035) 0px,
                rgba(255, 255, 255, 0.035) 1px,
                transparent 1px,
                transparent 3px
            );
            mix-blend-mode: overlay;
            animation: scan-flicker 6s infinite steps(60);
        }

        /* Occasional horizontal tear, like a dropped video frame */
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 3;
            opacity: 0;
            background: linear-gradient(
                to bottom,
                transparent 0%,
                rgba(94, 234, 212, 0.06) 49%,
                rgba(255, 42, 109, 0.06) 50%,
                transparent 51%,
                transparent 100%
            );
            animation: frame-tear 7s infinite;
        }

        main {
            position: relative;
            z-index: 1;
            max-width: 32rem;
        }
        .mark {
            display: inline-block;
            font-size: 0.875rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 1rem;
        }
        h1 {
            position: relative;
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            margin: 0 0 0.75rem;
        }

        /* RGB-split glitch text: two color-shifted copies clipped to shifting horizontal
           bands, layered over the real (readable) heading text. */
        .glitch {
            display: inline-block;
        }
        .glitch::before,
        .glitch::after {
            content: attr(data-text);
            position: absolute;
            inset: 0;
            background: var(--bg);
            overflow: hidden;
        }
        .glitch::before {
            left: 2px;
            color: var(--glitch-cyan);
            clip-path: inset(0 0 0 0);
            animation: glitch-slice-1 4.5s infinite steps(1);
        }
        .glitch::after {
            left: -2px;
            color: var(--glitch-red);
            clip-path: inset(0 0 0 0);
            animation: glitch-slice-2 4.5s infinite steps(1);
        }

        p {
            color: var(--muted);
            line-height: 1.6;
            margin: 0;
        }

        @keyframes scan-flicker {
            0%, 92%, 100% { opacity: 1; }
            93% { opacity: 0.55; }
            94% { opacity: 1; }
            95% { opacity: 0.7; }
            96% { opacity: 1; }
        }

        @keyframes frame-tear {
            0%, 96%, 100% { opacity: 0; transform: translateY(0); }
            97% { opacity: 1; transform: translateY(-6px); }
            98% { opacity: 0; transform: translateY(4px); }
        }

        @keyframes glitch-slice-1 {
            0%, 88%, 100% { clip-path: inset(0 0 0 0); transform: translate(0, 0); }
            89% { clip-path: inset(10% 0 65% 0); transform: translate(-3px, 0); }
            90% { clip-path: inset(55% 0 15% 0); transform: translate(3px, 0); }
            91% { clip-path: inset(30% 0 40% 0); transform: translate(-2px, 0); }
            92% { clip-path: inset(0 0 0 0); transform: translate(0, 0); }
        }

        @keyframes glitch-slice-2 {
            0%, 88%, 100% { clip-path: inset(0 0 0 0); transform: translate(0, 0); }
            89% { clip-path: inset(60% 0 5% 0); transform: translate(3px, 0); }
            90% { clip-path: inset(5% 0 70% 0); transform: translate(-3px, 0); }
            91% { clip-path: inset(45% 0 25% 0); transform: translate(2px, 0); }
            92% { clip-path: inset(0 0 0 0); transform: translate(0, 0); }
        }

        @media (prefers-reduced-motion: reduce) {
            body::before,
            body::after,
            .glitch::before,
            .glitch::after {
                animation: none;
                content: none;
            }
        }
    </style>
</head>
<body>
    <main>
        <span class="mark">miautrix</span>
        <h1><span class="glitch" data-text="Something's being built here.">Something's being built here.</span></h1>
        <p>A professional IT portfolio and blog is on its way. Check back soon.</p>
    </main>
</body>
</html>
