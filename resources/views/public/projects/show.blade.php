<x-layouts::app
    :title="$project->title . ' — miautrix'"
    :description="$project->summary"
>
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
            <p class="max-w-(--breakpoint-xs) text-body text-muted-foreground">{{ $project->summary }}</p>

            <div class="flex flex-wrap gap-3">
                @if ($project->repo_url)
                    <x-button :href="$project->repo_url" variant="outline" size="sm">Repository</x-button>
                @endif
                @if ($project->live_url)
                    <x-button :href="$project->live_url" variant="primary" size="sm">Live site</x-button>
                @endif
            </div>
        </section>

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
            <p class="whitespace-pre-line text-body text-foreground">{{ $project->description }}</p>

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
    </div>
</x-layouts::app>
