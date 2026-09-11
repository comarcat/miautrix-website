<x-layouts::app
    title="Endorsements — miautrix"
    description="What people I've worked with have to say."
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Endorsements']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Endorsements</h1>
            <p class="text-body text-muted-foreground">What people I've worked with have to say.</p>
        </section>

        @if ($testimonials->isEmpty())
            <x-alert variant="info">No endorsements published yet.</x-alert>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    @php
                        $contactHref = match ($testimonial->contact_type) {
                            'email' => 'mailto:' . $testimonial->contact_value,
                            default => $testimonial->contact_value,
                        };
                        $contactLabel = match ($testimonial->contact_type) {
                            'email' => 'Email ' . $testimonial->name,
                            'linkedin' => 'View ' . $testimonial->name . "'s LinkedIn profile",
                            default => "Visit {$testimonial->name}'s website",
                        };
                    @endphp
                    <x-card :title="$testimonial->name">
                        <p class="mb-2 font-mono text-mono text-muted-foreground">
                            {{-- Company has no public page of its own in this app (it exists
                                 only as an Experience-entry FK target) — shown as plain text,
                                 same as the free-text `organization` field. --}}
                            {{ $testimonial->company?->name ?: $testimonial->organization }}
                            @if ($testimonial->role)
                                &middot; {{ $testimonial->role }}
                            @endif
                        </p>
                        <p>&ldquo;{{ $testimonial->body }}&rdquo;</p>

                        <x-slot:footer>
                            <a
                                href="{{ $contactHref }}"
                                @if ($testimonial->contact_type !== 'email') target="_blank" rel="noopener noreferrer" @endif
                                aria-label="{{ $contactLabel }}"
                                class="font-mono text-mono text-accent-text hover:underline"
                            >
                                {{ $testimonial->contact_type === 'email' ? 'Email' : ($testimonial->contact_type === 'linkedin' ? 'LinkedIn' : 'Website') }}
                            </a>
                        </x-slot:footer>
                    </x-card>
                @endforeach
            </div>
        @endif

        <section class="flex flex-col gap-4 border-t border-border pt-8">
            <h2 class="text-heading-2 text-foreground">Add your endorsement</h2>
            {{-- Privacy note (§9 step 46): approval is manual, never automatic, and this is the
                 only place any submitted endorsement — including its contact info — is ever
                 shown; a pending/rejected one is never rendered anywhere public. --}}
            <p class="text-body text-muted-foreground">
                Submissions are reviewed manually before publishing — nothing appears here automatically.
                Your name, organization, role and contact info are shown publicly only once approved, and
                only on this page. Submissions are retained indefinitely for review; email
                {{ config('services.contact.to_address') }} to request removal of an approved endorsement.
            </p>

            <livewire:testimonial-form />
        </section>
    </div>
</x-layouts::app>
