<x-layouts::app
    :title="'About — ' . $profile->full_name"
    :description="$profile->headline"
    :canonical="route('about')"
>
    <x-slot:jsonLd>
        <x-json-ld :data="[
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $profile->full_name,
            'jobTitle' => $profile->headline,
            'url' => route('about'),
        ]" />
    </x-slot:jsonLd>
    <div class="flex flex-col gap-12">
        <section class="flex flex-col gap-4">
            <span class="font-mono text-mono uppercase tracking-wide text-muted-foreground">About</span>
            <h1 class="text-display text-foreground">{{ $profile->full_name }}</h1>
            <p class="text-heading-3 text-muted-foreground">{{ $profile->headline }}</p>

            <div class="flex flex-wrap gap-3">
                @if ($profile->location)
                    <x-badge>{{ $profile->location }}</x-badge>
                @endif
                @if ($profile->availability_status)
                    <x-badge variant="accent">{{ $profile->availability_status }}</x-badge>
                @endif
            </div>

            {{-- --breakpoint-xs is a 375px MEDIA-QUERY breakpoint token (app.css), not a
                 content-width design token — using it here pinned this to phone width even
                 on desktop (found in review, same bug across every other page's body copy). --}}
            <p class="text-body text-foreground">{{ $profile->bio }}</p>
        </section>

        <section class="flex flex-col gap-6">
            <h2 class="text-heading-2 text-foreground">Experience</h2>

            @if ($experiences->isEmpty())
                <x-alert variant="info">No experience published yet.</x-alert>
            @else
                <x-timeline :items="$experiences->map(fn ($experience) => [
                    'title' => $experience->title,
                    'subtitle' => $experience->company->name ?? null,
                    'period' => $experience->started_at->format('Y') . '—' . ($experience->ended_at?->format('Y') ?? 'Present'),
                ])->all()" />
            @endif
        </section>

        <section class="flex flex-col gap-6">
            <h2 class="text-heading-2 text-foreground">Education</h2>

            @if ($education->isEmpty())
                <x-alert variant="info">No education published yet.</x-alert>
            @else
                <x-timeline :items="$education->map(fn ($item) => [
                    'title' => $item->degree . ', ' . $item->field_of_study,
                    'subtitle' => $item->institution,
                    'period' => $item->started_at->format('Y') . '—' . ($item->ended_at?->format('Y') ?? 'Present'),
                ])->all()" />
            @endif
        </section>
    </div>
</x-layouts::app>
