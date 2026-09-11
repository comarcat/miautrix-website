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

        {{-- E5-T7 — cached GitHub repo stats (backlog item 13.1). Reading $tool->repoStats
             triggers RepoStats::for($tool) (subject to its own staleness guards), so this
             section reflects whatever the last successful fetch stored even when the
             GitHub request itself fails right now. --}}
        @if ($tool->repo_url && str_contains($tool->repo_url, 'github.com'))
            @php $stats = $tool->repoStats; @endphp
            @if ($stats['fetched_at'])
                <section class="flex flex-wrap gap-6 rounded-card border border-border bg-card p-4 font-mono text-mono text-muted-foreground">
                    @if ($stats['stars'] !== null)
                        <div class="flex flex-col">
                            <span class="text-foreground">Stars</span>
                            <span>{{ $stats['stars'] }}</span>
                        </div>
                    @endif
                    @if ($stats['forks'] !== null)
                        <div class="flex flex-col">
                            <span class="text-foreground">Forks</span>
                            <span>{{ $stats['forks'] }}</span>
                        </div>
                    @endif
                    @if ($stats['language'])
                        <div class="flex flex-col">
                            <span class="text-foreground">Language</span>
                            <span>{{ $stats['language'] }}</span>
                        </div>
                    @endif
                    @if ($stats['license'])
                        <div class="flex flex-col">
                            <span class="text-foreground">License</span>
                            <span>{{ $stats['license'] }}</span>
                        </div>
                    @endif
                    <div class="flex flex-col">
                        <span class="text-foreground">Last updated</span>
                        <span>{{ $stats['fetched_at']->diffForHumans() }}</span>
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-layouts::app>
