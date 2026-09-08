<x-layouts::app
    title="miautrix — professional IT portfolio"
    description="A professional IT portfolio: featured projects, articles, and background."
>
    <div class="flex flex-col gap-16">
        <section class="flex flex-col gap-4">
            <span class="font-mono text-mono uppercase tracking-wide text-muted-foreground">Portfolio · Blog · CMS</span>
            <h1 class="max-w-(--breakpoint-xs) text-display text-foreground">Building reliable systems, end to end.</h1>
            <p class="max-w-(--breakpoint-xs) text-body text-muted-foreground">
                A professional IT portfolio covering backend architecture, infrastructure, and
                the projects behind it.
            </p>
        </section>

        <section class="flex flex-col gap-6">
            <h2 class="text-heading-2 text-foreground">Featured projects</h2>

            @if ($featuredProjects->isEmpty())
                <x-alert variant="info">No projects published yet.</x-alert>
            @else
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($featuredProjects as $project)
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
            @endif
        </section>

        <section class="flex flex-col gap-6">
            <h2 class="text-heading-2 text-foreground">Latest from the blog</h2>

            @if ($latestArticles->isEmpty())
                <x-alert variant="info">No articles published yet.</x-alert>
            @else
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($latestArticles as $article)
                        <x-card :title="$article->title">
                            <p>{{ $article->excerpt }}</p>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts::app>
