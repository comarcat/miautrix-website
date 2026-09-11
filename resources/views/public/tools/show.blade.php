<x-layouts::app
    :title="$tool->title . ' — miautrix'"
    :description="$tool->summary"
    :canonical="route('tools.show', $tool->slug)"
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[
            ['label' => 'Home', 'href' => route('home')],
            ['label' => 'Tools', 'href' => route('tools.index')],
            ['label' => $tool->title],
        ]" />

        <section class="flex flex-col gap-4">
            @if ($tool->version)
                <x-badge>{{ $tool->version }}</x-badge>
            @endif
            <h1 class="text-display text-foreground">{{ $tool->title }}</h1>
            <p class="text-body text-muted-foreground">{{ $tool->summary }}</p>

            <div class="flex flex-wrap gap-3">
                @if ($tool->toolFile)
                    <x-button :href="route('tools.download', $tool->slug)" variant="primary" size="sm">
                        Download
                    </x-button>
                @endif
                @if ($tool->repo_url)
                    <x-button :href="$tool->repo_url" variant="outline" size="sm">Repository</x-button>
                @endif
            </div>
        </section>

        @if ($tool->description)
            <section class="flex flex-col gap-4">
                <h2 class="text-heading-2 text-foreground">About this tool</h2>
                <div class="text-body text-foreground [&>*+*]:mt-4">
                    {!! $tool->description !!}
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
