<x-layouts::app
    title="miautrix — professional IT portfolio"
    description="A professional IT portfolio: featured projects, articles, and background."
>
    <div class="flex flex-col gap-16">
        {{-- Found in review: "I should be able to change the text before the blog post from
             the admin console" — this eyebrow/heading/subheading now come from the Settings
             resource (home_hero_eyebrow/home_hero_heading/home_hero_subheading), editable at
             /admin/settings; see HomeController for the fallback defaults. --breakpoint-xs
             (removed below) was a 375px MEDIA-QUERY breakpoint token (app.css), not a
             content-width design token — using it as max-w-* pinned this hero to phone width
             even on desktop. --}}
        <section class="flex flex-col gap-4">
            <span class="font-mono text-mono uppercase tracking-wide text-muted-foreground">{{ $heroEyebrow }}</span>
            <h1 class="text-display text-foreground">{{ $heroHeading }}</h1>
            <p class="text-body text-muted-foreground">
                {{ $heroSubheading }}
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
