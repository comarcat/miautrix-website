<x-layouts::app
    title="Connect — miautrix"
    description="Find me on every platform I'm active on."
    :canonical="route('connect')"
>
    @php
        // E2-T8 — one <section> per SocialProfileGroup (ordered by sort_order), then a
        // trailing "Other" section for profiles with no group_id (omitted when empty).
        // The footer's own list (<x-footer-social-profiles>) is a separate layout-level
        // component keyed on show_in_footer and is unaffected by this grouping.
        $sections = $groups
            ->filter(fn ($group) => $group->socialProfiles->isNotEmpty())
            ->map(fn ($group) => [
                'key' => (string) $group->id,
                'heading' => $group->heading,
                'intro' => $group->intro_text,
                'profiles' => $group->socialProfiles,
            ]);

        if ($ungrouped->isNotEmpty()) {
            $sections->push([
                'key' => 'other',
                'heading' => 'Other',
                'intro' => null,
                'profiles' => $ungrouped,
            ]);
        }
    @endphp

    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Connect']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Connect</h1>
            <p class="text-body text-muted-foreground">Find me on every platform I'm active on.</p>
        </section>

        @if ($sections->isEmpty())
            <x-alert variant="info">No social profiles published yet.</x-alert>
        @else
            @foreach ($sections as $section)
                <section class="flex flex-col gap-4" data-social-group="{{ $section['key'] }}">
                    <h2 class="text-heading-2 text-foreground">{{ $section['heading'] }}</h2>

                    @if ($section['intro'])
                        <p class="text-body text-muted-foreground">{{ $section['intro'] }}</p>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($section['profiles'] as $socialProfile)
                            <a
                                href="{{ $socialProfile->url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-3 rounded-card border border-border bg-card p-4 text-foreground hover:border-accent"
                            >
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-full border border-border bg-background">
                                    @if ($socialProfile->icon)
                                        <img
                                            src="{{ route('media.show', [$socialProfile->icon, $socialProfile->icon->file_name]) }}"
                                            alt=""
                                            class="size-6 object-contain"
                                        >
                                    @else
                                        <span class="font-mono text-mono text-muted-foreground">{{ mb_substr($socialProfile->platform, 0, 1) }}</span>
                                    @endif
                                </div>

                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $socialProfile->platform }}</span>
                                    <span class="text-mono font-mono text-muted-foreground">{{ $socialProfile->url }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</x-layouts::app>
