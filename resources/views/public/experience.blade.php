<x-layouts::app
    title="Experience — miautrix"
    description="Professional experience, most recent first."
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Experience']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Experience</h1>
        </section>

        <section>
            @if ($experiences->isEmpty())
                <x-alert variant="info">No experience published yet.</x-alert>
            @else
                <x-timeline :items="$experiences->map(fn ($experience) => [
                    'title' => $experience->title,
                    'subtitle' => $experience->company->name ?? null,
                    'period' => $experience->started_at->format('Y') . '—' . ($experience->ended_at?->format('Y') ?? 'Present'),
                    'description' => $experience->description,
                ])->all()" />
            @endif
        </section>
    </div>
</x-layouts::app>
