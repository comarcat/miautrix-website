<x-layouts::app
    title="miautrix — professional IT portfolio"
    description="A professional IT portfolio: featured projects, articles, and background."
>
    <div class="flex flex-col gap-16">
        <section class="flex flex-col gap-4">
            <span class="font-mono text-mono uppercase tracking-wide text-muted-foreground">Portfolio · Blog · CMS</span>
            {{-- Found in review: --breakpoint-xs is a 375px MEDIA-QUERY breakpoint token
                 (app.css), not a content-width design token — using it as max-w-* pinned
                 this hero (and, before this fix, every other page's body copy) to phone
                 width even on desktop. Removed everywhere; content now fills the same
                 --container-content width the header/nav already use. --}}
            <h1 class="text-display text-foreground">Building reliable systems, end to end.</h1>
            <p class="text-body text-muted-foreground">
                A professional IT portfolio covering backend architecture, infrastructure, and
                the projects behind it.
            </p>
        </section>

        {{-- Found in review: swapped with Projects below — blog posts are ready to show now,
             projects still need work, so blog goes first on the homepage. --}}
        <section class="flex flex-col gap-6">
            <h2 class="text-heading-2 text-foreground">Latest from the blog</h2>

            @if ($latestArticles->isEmpty())
                <x-alert variant="info">No articles published yet.</x-alert>
            @else
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($latestArticles as $article)
                        <x-card :title="$article->title">
                            <p>{{ $article->excerpt }}</p>

                            <x-slot:footer>
                                <x-button :href="route('blog.show', $article->slug)" variant="outline" size="sm">
                                    Read more
                                </x-button>
                            </x-slot:footer>
                        </x-card>
                    @endforeach
                </div>
            @endif
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
    </div>
</x-layouts::app>
