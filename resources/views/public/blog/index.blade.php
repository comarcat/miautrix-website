<x-layouts::app
    title="Blog — miautrix"
    description="Articles and notes."
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Blog']]" />

        <section class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-display text-foreground">Blog</h1>
                <a href="{{ route('feed') }}" class="font-mono text-mono text-muted-foreground hover:text-accent-text">
                    RSS
                </a>
            </div>
        </section>

        @if ($articles->isEmpty())
            <x-alert variant="info">No articles published yet.</x-alert>
        @else
            <div class="grid gap-6 md:grid-cols-3">
                @foreach ($articles as $article)
                    <x-card :title="$article->title">
                        <p class="mb-2 font-mono text-mono text-muted-foreground">
                            {{ $article->published_at->format('M j, Y') }}
                        </p>
                        <p>{{ $article->excerpt }}</p>

                        <x-slot:footer>
                            <x-button :href="route('blog.show', $article->slug)" variant="outline" size="sm">
                                Read more
                            </x-button>
                        </x-slot:footer>
                    </x-card>
                @endforeach
            </div>

            @if ($articles->hasPages())
                <nav aria-label="Pagination" class="flex justify-center gap-4">
                    @if ($articles->onFirstPage())
                        <span class="text-muted-foreground">Previous</span>
                    @else
                        <a href="{{ $articles->previousPageUrl() }}" class="text-accent-text hover:underline">Previous</a>
                    @endif

                    <span class="font-mono text-mono text-muted-foreground">
                        Page {{ $articles->currentPage() }} of {{ $articles->lastPage() }}
                    </span>

                    @if ($articles->hasMorePages())
                        <a href="{{ $articles->nextPageUrl() }}" class="text-accent-text hover:underline">Next</a>
                    @else
                        <span class="text-muted-foreground">Next</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</x-layouts::app>
