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
     */
    $groups = [
        'Work' => [
            ['label' => 'Experience', 'href' => route('experience')],
            ['label' => 'Skills', 'href' => route('skills')],
            ['label' => 'Projects', 'href' => route('projects.index')],
            ['label' => 'Resume', 'href' => route('resume')],
        ],
        'Writing' => [
            ['label' => 'Blog', 'href' => route('blog.index')],
        ],
    ];

    $links = [
        ['label' => 'About', 'href' => route('about')],
        ['label' => 'Connect', 'href' => route('connect')],
        ['label' => 'Contact', 'href' => route('contact')],
    ];

    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp

<div
    x-data="navMenu()"
    @keydown.escape.stop="closeAll()"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <ul
        role="menubar"
        aria-label="Primary"
        class="flex items-center gap-1 text-sm"
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
            <li role="none" class="relative" @mouseleave="close('{{ $gid }}')">
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

    <div class="mt-0 hidden md:block">
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
