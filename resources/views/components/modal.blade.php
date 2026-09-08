@props(['title' => '', 'id'])

{{--
    Focus trap is hand-rolled in plain Alpine (no external plugin — Livewire's bundled Alpine
    core doesn't ship the focus plugin, so `x-trap` isn't available): `trigger()` remembers
    `document.activeElement` before opening and focuses the panel's first focusable element;
    `handleTab()` cycles Tab/Shift+Tab between the panel's first and last focusable elements so
    focus never escapes to the page behind it; `close()` restores focus to whatever triggered
    the modal (E4-T3 acceptance 3).
--}}
<div
    x-data="{
        open: false,
        previouslyFocused: null,
        focusables() {
            return [...this.$refs.panel.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex=\'-1\'])'
            )].filter((el) => !el.disabled && el.offsetParent !== null);
        },
        trigger() {
            this.previouslyFocused = document.activeElement;
            this.open = true;
            this.$nextTick(() => this.focusables()[0]?.focus());
        },
        close() {
            this.open = false;
            this.previouslyFocused?.focus();
        },
        handleTab(event) {
            const items = this.focusables();
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    {{ $attributes }}
>
    @isset($trigger)
        <span x-on:click="trigger()">{{ $trigger }}</span>
    @endisset

    <div
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="close()"
        x-on:keydown.tab="handleTab($event)"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4"
    >
        <div
            x-ref="panel"
            x-show="open"
            x-on:click.outside="close()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $id }}-title"
            class="w-full max-w-lg rounded-card border border-border bg-card p-6 shadow-elevation-2"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2 id="{{ $id }}-title" class="text-heading-3 text-card-foreground">{{ $title }}</h2>
                <button type="button" x-on:click="close()" aria-label="Close" class="text-muted-foreground hover:text-foreground">
                    &times;
                </button>
            </div>

            <div class="text-body text-card-foreground">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="mt-6 flex justify-end gap-3">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
