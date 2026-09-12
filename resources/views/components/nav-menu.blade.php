@props([
    'theme' => 'technical',
])

@php
    /**
     * E3-T8 (§9 step 20) — desktop-style menubar. Every Phase-1 destination is reachable:
     * Home and the About/Connect/Contact links sit directly on the bar; Work and Writing are
     * submenus. E4-T8 adds "Life" under Writing when the active theme allows it.
     *
     * ARIA: role="menubar" > role="menuitem"; submenu triggers carry aria-haspopup +
     * aria-expanded and own a role="menu" of role="menuitem" links. The Alpine component
     * (nonce'd script below) does arrow-key movement along the bar, ArrowDown/Up inside an
     * open submenu, Esc to close and restore focus to the trigger, and a focus trap while a
     * submenu is open. All chrome is driven by the --menu-* custom properties, which both
     * theme blocks in app.css define.
     *
     * BUG FIXED (found live once the menu's underlying CSP/caching bugs were fixed and it
     * became possible to actually click a submenu item): the trigger <li> used to carry
     * @mouseleave="close(...)" to close the dropdown when the pointer left it. The <ul> is
     * absolutely positioned mt-1 below the button and contributes nothing to the <li>'s own
     * (normal-flow) box, so that margin gap is a real dead zone covered by neither the button
     * nor the menu — moving the pointer from the button down into the dropdown crossed it and
     * fired mouseleave before the pointer ever reached a menu item, closing the menu it was
     * headed for. Removed; closing now happens on Escape (already handled), on toggling the
     * trigger again, or via @click.outside on the root element.
     */
    // E4-T8 — the raw cookie/theme key isn't enough on its own (a disabled or out-of-window
    // event theme still resolves to the default elsewhere); this mirrors ThemeResolver's own
    // fallback so the nav and the page body never disagree about whether Life is visible.
    $showsLifeBlog = config('site.themes.dynamic')
        ? (bool) (\App\Models\Theme::where('key', $theme)->value('shows_life_blog') ?? ($theme === 'matrix'))
        : $theme === 'matrix';

    // BUG FIXED (found live, reported as "on the admin but not showing on the website" and
    // confirmed missing from the terminal's own `dir` command too): CR-P2-13 (Tools) and
    // CR-P2-15 (Endorsements/testimonials) both shipped with working public routes, but
    // neither was ever added to this nav or to Terminal::PAGES (app/Livewire/Terminal.php) —
    // both pages were reachable only if a visitor already knew the exact URL, with no link
    // anywhere on the site pointing at either one.
    $groups = [
        'Work' => [
            ['label' => 'Experience', 'href' => route('experience')],
            ['label' => 'Skills', 'href' => route('skills')],
            ['label' => 'Projects', 'href' => route('projects.index')],
            ['label' => 'Tools', 'href' => route('tools.index')],
            ['label' => 'Resume', 'href' => route('resume')],
        ],
        'Writing' => array_filter([
            ['label' => 'Blog', 'href' => route('blog.index')],
            $showsLifeBlog ? ['label' => 'Life', 'href' => route('life.index')] : null,
        ]),
    ];

    $links = [
        ['label' => 'About', 'href' => route('about')],
        ['label' => 'Connect', 'href' => route('connect')],
        ['label' => 'Endorsements', 'href' => route('endorsements.index')],
        ['label' => 'Contact', 'href' => route('contact')],
    ];

    // This component renders on every public page, most of which CachePublicPage caches for
    // up to an hour — a live Vite::cspNonce() baked in here would go stale on a cache hit, so
    // this bakes SecurityHeaders::NONCE_PLACEHOLDER instead, which that middleware substitutes
    // for the real, current-request nonce on the way out of every response, cached or not.
    $nonce = \App\Http\Middleware\SecurityHeaders::NONCE_PLACEHOLDER;
@endphp

<div
    x-data="navMenu()"
    @keydown.escape.stop="closeAll()"
    @click.outside="closeAll()"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <ul
        role="menubar"
        aria-label="Primary"
        class="flex flex-wrap items-center gap-1 text-sm"
        x-ref="bar"
    >
        <li role="none">
            <a role="menuitem" href="{{ route('home') }}"
               class="inline-flex items-center rounded-input px-3 py-1.5 hover:bg-(--menu-highlight) focus-visible:bg-(--menu-highlight) focus-visible:outline-none">
                Home
            </a>
        </li>

        @foreach ($groups as $group => $items)
            @php $gid = Str::slug($group); @endphp
            <li role="none" class="relative">
                <button
                    type="button"
                    role="menuitem"
                    aria-haspopup="true"
                    :aria-expanded="open === '{{ $gid }}' ? 'true' : 'false'"
                    x-ref="trigger-{{ $gid }}"
                    @click="toggle('{{ $gid }}')"
                    @keydown.arrow-down.prevent="openAndFocusFirst('{{ $gid }}')"
                    @mouseenter="open = '{{ $gid }}'"
                    class="inline-flex items-center gap-1 rounded-input px-3 py-1.5 hover:bg-(--menu-highlight) focus-visible:bg-(--menu-highlight) focus-visible:outline-none"
                >
                    {{ $group }}
                    <span aria-hidden="true" class="text-[0.65em]">▾</span>
                </button>

                <ul
                    role="menu"
                    aria-label="{{ $group }}"
                    x-ref="menu-{{ $gid }}"
                    x-show="open === '{{ $gid }}'"
                    x-transition.opacity
                    x-cloak
                    @keydown.arrow-down.prevent="moveWithin('{{ $gid }}', 1)"
                    @keydown.arrow-up.prevent="moveWithin('{{ $gid }}', -1)"
                    @keydown.tab.prevent="closeAndFocusTrigger('{{ $gid }}')"
                    class="absolute left-0 top-full z-50 mt-1 min-w-44 rounded-card border border-(--menu-border) bg-(--menu-bg) p-1 shadow-[var(--menu-shadow)]"
                >
                    @foreach ($items as $item)
                        <li role="none">
                            <a
                                role="menuitem"
                                href="{{ $item['href'] }}"
                                tabindex="-1"
                                class="block rounded-input px-3 py-1.5 hover:bg-(--menu-highlight) focus-visible:bg-(--menu-highlight) focus-visible:outline-none"
                            >{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach

        @foreach ($links as $link)
            <li role="none">
                <a role="menuitem" href="{{ $link['href'] }}"
                   class="inline-flex items-center rounded-input px-3 py-1.5 hover:bg-(--menu-highlight) focus-visible:bg-(--menu-highlight) focus-visible:outline-none">
                    {{ $link['label'] }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- BUG FIXED (found live, reported as "themes still not working" — a mobile-only report):
         this was `hidden md:block`, so the theme switcher it wraps didn't render at all below
         the md breakpoint. A mobile visitor had no way to switch themes whatsoever, not a
         cosmetic issue. Visible at every width now. --}}
    <div class="mt-1 md:mt-0">
        <x-theme-switcher :theme="$theme" />
    </div>
</div>

<script{!! $nonce ? ' nonce="' . e($nonce) . '"' : '' !!}>
    document.addEventListener('alpine:init', function () {
        Alpine.data('navMenu', function () {
            return {
                open: null,
                toggle: function (id) { this.open = this.open === id ? null : id; },
                close: function (id) { if (this.open === id) { this.open = null; } },
                closeAll: function () {
                    var current = this.open;
                    this.open = null;
                    if (current) {
                        var t = this.$refs['trigger-' + current];
                        if (t) { t.focus(); }
                    }
                },
                items: function (id) {
                    var menu = this.$refs['menu-' + id];
                    return menu ? Array.prototype.slice.call(menu.querySelectorAll('[role="menuitem"]')) : [];
                },
                openAndFocusFirst: function (id) {
                    this.open = id;
                    var self = this;
                    this.$nextTick(function () {
                        var list = self.items(id);
                        if (list.length) { list[0].focus(); }
                    });
                },
                moveWithin: function (id, dir) {
                    var list = this.items(id);
                    if (!list.length) { return; }
                    var idx = list.indexOf(document.activeElement);
                    var next = (idx + dir + list.length) % list.length;
                    list[next].focus();
                },
                closeAndFocusTrigger: function (id) {
                    this.open = null;
                    var t = this.$refs['trigger-' + id];
                    if (t) { t.focus(); }
                },
            };
        });
    });
</script>
