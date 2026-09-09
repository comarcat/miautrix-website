<x-layouts::app
    title="Resume — miautrix"
    description="Download the current resume."
    :canonical="route('resume')"
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Resume']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Resume</h1>

            @if ($resume)
                <p class="text-body text-muted-foreground">
                    {{ $resume->title }} &middot; version {{ $resume->version }}
                </p>

                <div>
                    <x-button :href="route('documents.download', $resume)" variant="primary">
                        Download résumé (PDF)
                    </x-button>
                </div>
            @else
                <x-alert variant="info">No résumé published yet.</x-alert>
            @endif
        </section>
    </div>
</x-layouts::app>
