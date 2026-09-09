<x-layouts::app
    title="Resume — miautrix"
    description="Download the current resume."
    :canonical="route('resume')"
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Resume']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Resume</h1>

            @if ($resume)
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-body text-muted-foreground">
                        {{ $resume->title }} &middot; version {{ $resume->version }}
                    </p>

                    <x-button :href="route('documents.download', $resume)" variant="primary">
                        Download résumé (PDF)
                    </x-button>
                </div>

                {{-- Found in review: "show the PDF in a section, not just a link" — embedded
                     via documents.preview (Content-Disposition: inline), not
                     documents.download (which forces a save dialog and would inflate
                     download_count on every page view). A visitor without inline PDF
                     support in their browser still has the Download button above. --}}
                <iframe
                    src="{{ route('documents.preview', $resume) }}"
                    title="{{ $resume->title }} preview"
                    class="h-[80vh] w-full rounded-card border border-border"
                ></iframe>
            @else
                <x-alert variant="info">No résumé published yet.</x-alert>
            @endif
        </section>
    </div>
</x-layouts::app>
