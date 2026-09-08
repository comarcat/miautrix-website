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
--}}
<div class="flex items-center gap-3 text-sm">
    @if ($theme === 'matrix')
        <span class="font-mono text-xs uppercase tracking-wide text-accent-text">
            Matrix — always dark, overrides system theme
        </span>
        <form method="POST" action="{{ route('theme.set') }}">
            @csrf
            <input type="hidden" name="theme" value="technical">
            <button type="submit" class="hover:text-accent-text">
                Switch to Technical
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
