@props([
    'event' => 'toast', // window event name this instance listens for; dispatch with { detail: { message, variant } }
])

{{--
    Fully self-contained (no external Alpine plugin — Livewire's bundled Alpine core is enough):
    `window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Saved', variant: 'success' } }))`
    or, from a Livewire component, `$this->dispatch('toast', message: 'Saved', variant: 'success')`.
--}}
<div
    x-data="{
        visible: false,
        message: '',
        variant: 'info',
        timeout: null,
        show(detail) {
            this.message = detail.message ?? '';
            this.variant = detail.variant ?? 'info';
            this.visible = true;
            clearTimeout(this.timeout);
            this.timeout = setTimeout(() => { this.visible = false }, 5000);
        },
    }"
    x-on:{{ $event }}.window="show($event.detail)"
    x-cloak
    class="fixed bottom-4 right-4 z-50"
    role="status"
    aria-live="polite"
>
    <div
        x-show="visible"
        x-transition
        x-on:click="visible = false"
        class="cursor-pointer rounded-card border px-4 py-3 text-body shadow-elevation-2"
        :class="{
            'border-accent/40 bg-card text-foreground': variant === 'info' || variant === 'success',
            'border-destructive/40 bg-card text-foreground': variant === 'error',
        }"
        x-text="message"
    ></div>
</div>
