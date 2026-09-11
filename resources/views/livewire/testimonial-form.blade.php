<div class="flex flex-col gap-6">
    @if ($submitted)
        <x-alert variant="success">
            Thanks — your endorsement has been submitted and is pending review.
        </x-alert>
    @endif

    @if ($rateLimitMessage)
        <x-alert variant="error">{{ $rateLimitMessage }}</x-alert>
    @endif

    <form wire:submit="submit" class="flex flex-col gap-4" novalidate>
        {{-- Honeypot: same field name and sr-only (clipped, not display:none) technique as
             ContactForm's own. --}}
        <div class="sr-only" aria-hidden="true">
            <label for="testimonial_website_url_confirm">Leave this field blank</label>
            <input type="text" id="testimonial_website_url_confirm" wire:model="website_url_confirm" tabindex="-1" autocomplete="off">
        </div>

        <div class="flex flex-col gap-1">
            <label for="testimonial_name" class="text-body font-medium text-foreground">Name</label>
            <input
                type="text"
                id="testimonial_name"
                wire:model="name"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            >
            @error('name')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="testimonial_organization" class="text-body font-medium text-foreground">Organization (optional)</label>
            <input
                type="text"
                id="testimonial_organization"
                wire:model="organization"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            >
            @error('organization')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="testimonial_role" class="text-body font-medium text-foreground">Role (optional)</label>
            <input
                type="text"
                id="testimonial_role"
                wire:model="role"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            >
            @error('role')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="testimonial_contact_type" class="text-body font-medium text-foreground">How should I show your contact info?</label>
            <select
                id="testimonial_contact_type"
                wire:model="contact_type"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            >
                <option value="email">Email</option>
                <option value="linkedin">LinkedIn</option>
                <option value="url">Website</option>
            </select>
            @error('contact_type')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="testimonial_contact_value" class="text-body font-medium text-foreground">
                {{ $contact_type === 'email' ? 'Email address' : 'Link' }}
            </label>
            <input
                type="text"
                id="testimonial_contact_value"
                wire:model="contact_value"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            >
            @error('contact_value')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="testimonial_body" class="text-body font-medium text-foreground">Your endorsement</label>
            <textarea
                id="testimonial_body"
                wire:model="body"
                rows="6"
                class="rounded-input border border-border bg-card px-3 py-2 text-body text-foreground"
            ></textarea>
            @error('body')
                <p class="text-body text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <x-button type="submit" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Submit for review</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </x-button>
        </div>
    </form>
</div>
