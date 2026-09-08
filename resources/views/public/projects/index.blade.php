<x-layouts::app
    title="Projects — miautrix"
    description="Published projects, filterable by category."
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Projects']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Projects</h1>

            @if ($categories->isNotEmpty())
                <nav aria-label="Filter by category" class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('projects.index') }}"
                        class="rounded-full px-3 py-1 font-mono text-mono {{ ! $activeCategory ? 'bg-primary text-on-primary' : 'bg-muted text-muted-foreground hover:text-foreground' }}"
                    >
                        All
                    </a>
                    @foreach ($categories as $category)
                        <a
                            href="{{ route('projects.index', ['category' => $category->slug]) }}"
                            class="rounded-full px-3 py-1 font-mono text-mono {{ $activeCategory?->id === $category->id ? 'bg-primary text-on-primary' : 'bg-muted text-muted-foreground hover:text-foreground' }}"
                        >
                            {{ $category->name }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </section>

        @if ($projects->isEmpty())
            <x-alert variant="info">No projects published yet.</x-alert>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    <x-card :title="$project->title">
                        @if ($project->projectCategory)
                            <x-badge variant="accent" class="mb-2">{{ $project->projectCategory->name }}</x-badge>
                        @endif
                        <p>{{ $project->summary }}</p>

                        <x-slot:footer>
                            <x-button :href="route('projects.show', $project->slug)" variant="outline" size="sm">
                                View project
                            </x-button>
                        </x-slot:footer>
                    </x-card>
                @endforeach
            </div>

            @if ($projects->hasPages())
                <nav aria-label="Pagination" class="flex justify-center gap-4">
                    @if ($projects->onFirstPage())
                        <span class="text-muted-foreground">Previous</span>
                    @else
                        <a href="{{ $projects->previousPageUrl() }}" class="text-accent-text hover:underline">Previous</a>
                    @endif

                    <span class="font-mono text-mono text-muted-foreground">
                        Page {{ $projects->currentPage() }} of {{ $projects->lastPage() }}
                    </span>

                    @if ($projects->hasMorePages())
                        <a href="{{ $projects->nextPageUrl() }}" class="text-accent-text hover:underline">Next</a>
                    @else
                        <span class="text-muted-foreground">Next</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</x-layouts::app>
