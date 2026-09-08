<div class="flex flex-col gap-6">
    @if ($submitted)
        <x-alert variant="success">
            Thanks — your message has been sent. I'll get back to you soon.
        </x-alert>
    @endif

    @if ($rateLimitMessage)
        <x-alert variant="error">{{ $rateLimitMessage }}</x-alert>
    @endif

    <form wire:submit="submit" class="flex flex-col gap-4" novalidate>
        {{-- Honeypot: sr-only (clipped via CSS, not display:none) so a screen-reader user
             never encounters it but a scraper filling every field in the DOM still trips it. --}}
        <div class="sr-only" aria-hidden="true">
            <label for="website_url_confirm">Leave this field blank</label>
            <input type="text" id="website_url_confirm" wire:model="website_url_confirm" tabindex="-1" autocomplete="off">
        </div>

        <div class="flex flex-col gap-1">
            <label for="name" class="text-body font-medium text-foreground">Name</label>
            <input
                type="text"
                id="name"
                wire:model="name"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            >
            {{-- Inline, next to its own field — never a top-of-page-only banner (a11y, §15). --}}
            @error('name')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="email" class="text-body font-medium text-foreground">Email</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            >
            @error('email')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="subject" class="text-body font-medium text-foreground">Subject</label>
            <input
                type="text"
                id="subject"
                wire:model="subject"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            >
            @error('subject')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="message" class="text-body font-medium text-foreground">Message</label>
            <textarea
                id="message"
                wire:model="message"
                rows="6"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            ></textarea>
            @error('message')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <x-button type="submit" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Send message</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </x-button>
        </div>
    </form>
</div>
