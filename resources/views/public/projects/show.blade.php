<x-layouts::app
    :title="$project->seo_title ?: ($project->title . ' — miautrix')"
    :description="$project->meta_description ?: $project->summary"
    :image="\App\Support\Seo\OgImage::resolve($project->og_image_id)"
    :canonical="$project->canonical_url ?: route('projects.show', $project->slug)"
>
    <x-slot:jsonLd>
        <x-json-ld :data="[
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $project->title,
            'description' => $project->summary,
            'url' => route('projects.show', $project->slug),
            'dateCreated' => optional($project->started_at)->toDateString(),
        ]" />
    </x-slot:jsonLd>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[
            ['label' => 'Home', 'href' => route('home')],
            ['label' => 'Projects', 'href' => route('projects.index')],
            ['label' => $project->title],
        ]" />

        <section class="flex flex-col gap-4">
            @if ($project->projectCategory)
                <x-badge variant="accent">{{ $project->projectCategory->name }}</x-badge>
            @endif
            <h1 class="text-display text-foreground">{{ $project->title }}</h1>
            {{-- --breakpoint-xs is a 375px MEDIA-QUERY breakpoint token (app.css), not a
                 content-width design token — using it here pinned this to phone width even
                 on desktop (found in review, same bug across every other page's body copy). --}}
            <p class="text-body text-muted-foreground">{{ $project->summary }}</p>

            <div class="flex flex-wrap gap-3">
                @if ($project->repo_url)
                    <x-button :href="$project->repo_url" variant="outline" size="sm">Repository</x-button>
                @endif
                @if ($project->live_url)
                    <x-button :href="$project->live_url" variant="primary" size="sm">Live site</x-button>
                @endif
                {{-- E4-T2 — real PDF export (routes/web.php, outside cache.public). --}}
                <x-button :href="route('projects.pdf', $project->slug)" variant="outline" size="sm">Download PDF</x-button>
            </div>
        </section>

        {{-- E4-T6 — delivery-metrics stat strip. Every input is optional (E4-T5); rendered
             only when at least one of the nine raw columns is set — never a placeholder row
             of dashes when the project carries none of them. --}}
        @php
            $hasMetrics = collect([
                $project->budget_planned, $project->budget_actual,
                $project->planned_start, $project->planned_end,
                $project->actual_start, $project->actual_end,
                $project->team_size, $project->role, $project->outcome,
            ])->contains(fn ($value) => $value !== null);
        @endphp
        @if ($hasMetrics)
            <section class="flex flex-wrap gap-6 rounded-card border border-border bg-card p-4 font-mono text-mono text-muted-foreground">
                @if ($project->budget_planned !== null || $project->budget_actual !== null)
                    <div class="flex flex-col">
                        <span class="text-foreground">Budget</span>
                        <span>
                            {{ $project->budget_planned !== null ? 'Planned $' . number_format((float) $project->budget_planned, 2) : 'Planned —' }}
                            &middot;
                            {{ $project->budget_actual !== null ? 'Actual $' . number_format((float) $project->budget_actual, 2) : 'Actual —' }}
                        </span>
                    </div>
                @endif
                @if ($project->budgetPerformancePct !== null)
                    <div class="flex flex-col">
                        <span class="text-foreground">Budget performance</span>
                        <span>{{ $project->budgetPerformancePct }}%</span>
                    </div>
                @endif
                @if ($project->schedulePerformancePct !== null)
                    <div class="flex flex-col">
                        <span class="text-foreground">Schedule performance</span>
                        <span>{{ $project->schedulePerformancePct }}%</span>
                    </div>
                @endif
                @if ($project->team_size !== null)
                    <div class="flex flex-col">
                        <span class="text-foreground">Team size</span>
                        <span>{{ $project->team_size }}</span>
                    </div>
                @endif
                @if ($project->role)
                    <div class="flex flex-col">
                        <span class="text-foreground">Role</span>
                        <span>{{ $project->role }}</span>
                    </div>
                @endif
                @if ($project->outcome)
                    <div class="flex flex-col">
                        <span class="text-foreground">Outcome</span>
                        <span>{{ $project->outcome }}</span>
                    </div>
                @endif
            </section>
        @endif

        @if ($project->technologies->isNotEmpty())
            <section class="flex flex-wrap gap-2">
                @foreach ($project->technologies as $technology)
                    <x-badge>{{ $technology->name }}</x-badge>
                @endforeach
            </section>
        @endif

        @if ($project->media->isNotEmpty())
            <section class="flex flex-col gap-4">
                <h2 class="text-heading-2 text-foreground">Gallery</h2>
                <x-image-gallery :images="$project->media->map(fn ($media) => [
                    'src' => route('media.show', [$media, $media->file_name]),
                    'alt' => $project->title . ' screenshot',
                ])->all()" />
            </section>
        @endif

        <section class="flex flex-col gap-4">
            <h2 class="text-heading-2 text-foreground">About this project</h2>
            {{-- The RichEditor description is already-sanitized HTML produced by Filament's
                 own allowlisted node/mark schema (see ProjectForm's own comment) — same
                 pattern as the blog article body. --}}
            <div class="text-body text-foreground [&>*+*]:mt-4">
                {!! $project->description !!}
            </div>

            @if ($project->softwareProject)
                <div class="mt-2 flex flex-col gap-1 font-mono text-mono text-muted-foreground">
                    @if ($project->softwareProject->language_primary)
                        <span>Primary language: {{ $project->softwareProject->language_primary }}</span>
                    @endif
                </div>
            @endif
        </section>

        @if ($project->documents->isNotEmpty())
            <section class="flex flex-col gap-4">
                <h2 class="text-heading-2 text-foreground">Documents</h2>
                <ul class="flex flex-col gap-2">
                    @foreach ($project->documents as $document)
                        <li>
                            <x-button :href="route('documents.download', $document)" variant="outline" size="sm">
                                {{ $document->title }}
                            </x-button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- E4-T4 — supplementary files (PDFs, ZIP archives) distinct from the Documents
             section above and the image gallery; /projects/{slug}/files/{media} 404s unless
             the project is published. --}}
        @if ($project->projectFiles->isNotEmpty())
            <section class="flex flex-col gap-4">
                <h2 class="text-heading-2 text-foreground">Files</h2>
                <ul class="flex flex-col gap-2">
                    @foreach ($project->projectFiles as $file)
                        <li>
                            <x-button :href="route('projects.file', [$project->slug, $file])" variant="outline" size="sm">
                                {{ $file->pivot->label ?: $file->file_name }}
                            </x-button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts::app>
