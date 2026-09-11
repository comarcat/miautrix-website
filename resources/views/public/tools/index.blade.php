<x-layouts::app
    title="Tools — miautrix"
    description="Free tools and reports."
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Tools']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Tools</h1>
            <p class="text-body text-muted-foreground">Free tools and reports, mostly built by me.</p>
        </section>

        @if ($tools->isEmpty())
            <x-alert variant="info">No tools published yet.</x-alert>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($tools as $tool)
                    <x-card :title="$tool->title">
                        @if ($tool->version)
                            <x-badge class="mb-2">{{ $tool->version }}</x-badge>
                        @endif
                        <p>{{ $tool->summary }}</p>

                        <x-slot:footer>
                            <x-button :href="route('tools.show', $tool->slug)" variant="outline" size="sm">
                                View tool
                            </x-button>
                        </x-slot:footer>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
