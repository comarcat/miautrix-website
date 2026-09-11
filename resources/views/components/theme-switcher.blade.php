@props([
    'theme' => 'technical',
])

{{--
    E4-T2 (§9 step 20) — a plain form POST per theme option, not a fetch call: submitting
    it is itself the "reload" the acceptance criteria ask for, and the cookie is already
    set by ThemeController before the browser's own navigation re-requests the page, so
    there is no client-side re-render step where a flash could sneak in.

    When the current theme is matrix, the "overrides system theme" label makes the
    override visible rather than silently ignoring prefers-color-scheme (acceptance 4).

    "Console" is this theme's DISPLAY name only — found in review, the old "Technical"
    label read as a generic settings toggle rather than the terminal-styled theme it
    actually is (see app.css's Courier New override). The cookie value, data-theme
    attribute, and every internal identifier stay 'technical' (ResolveTheme,
    CachePublicPage's cache key, ThemeController's validation) — renaming those would
    touch cache keys and stored cookies for zero user-visible benefit; only the label
    users actually read has changed.

    E3-T4 (§9 step 20): with `site.themes.dynamic` ON, the option list is driven by the
    `themes` table — only rows that are `enabled` and currently inside their active window.
    With it OFF (the default and every pre-Phase-2 environment) the markup below is
    untouched.
--}}
@php
    // Dynamic list only when the flag is ON *and* real theme rows exist — otherwise (a
    // fresh DB before ThemeSeeder) fall through to the literal two-option switcher, matching
    // ThemeResolver's own degradation.
    $dynamicSwitcher = config('site.themes.dynamic') && \App\Models\Theme::query()->exists();
@endphp
@if ($dynamicSwitcher)
    @php
        $available = \App\Models\Theme::query()
            ->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('active_from')->orWhereDate('active_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('active_until')->orWhereDate('active_until', '>=', now()))
            ->orderBy('sort_order')
            ->get(['key', 'name']);
    @endphp

    <div class="flex items-center gap-3 text-sm" data-theme-switcher>
        @if ($theme === 'matrix')
            <span class="font-mono text-xs uppercase tracking-wide text-accent-text">
                Matrix — always dark, overrides system theme
            </span>
        @endif

        @foreach ($available as $option)
            @continue ($option->key === $theme)
            <form method="POST" action="{{ route('theme.set') }}">
                @csrf
                <input type="hidden" name="theme" value="{{ $option->key }}">
                <button type="submit" class="hover:text-accent-text">
                    Switch to {{ $option->name }}
                </button>
            </form>
        @endforeach
    </div>
@else
    <div class="flex items-center gap-3 text-sm">
        @if ($theme === 'matrix')
            <span class="font-mono text-xs uppercase tracking-wide text-accent-text">
                Matrix — always dark, overrides system theme
            </span>
            <form method="POST" action="{{ route('theme.set') }}">
                @csrf
                <input type="hidden" name="theme" value="technical">
                <button type="submit" class="hover:text-accent-text">
                    Switch to Console
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('theme.set') }}">
                @csrf
                <input type="hidden" name="theme" value="matrix">
                <button type="submit" class="hover:text-accent-text">
                    Switch to Matrix
                </button>
            </form>
        @endif
    </div>
@endif
