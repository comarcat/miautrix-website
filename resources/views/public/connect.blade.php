<x-layouts::app
    title="Connect — miautrix"
    description="Find me on every platform I'm active on."
    :canonical="route('connect')"
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Connect']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Connect</h1>
            <p class="text-body text-muted-foreground">Find me on every platform I'm active on.</p>
        </section>

        @if ($socialProfiles->isEmpty())
            <x-alert variant="info">No social profiles published yet.</x-alert>
        @else
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($socialProfiles as $socialProfile)
                    <a
                        href="{{ $socialProfile->url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-3 rounded-card border border-border bg-card p-4 text-foreground hover:border-accent"
                    >
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full border border-border bg-background">
                            @if ($socialProfile->icon)
                                <img
                                    src="{{ route('media.show', [$socialProfile->icon, $socialProfile->icon->file_name]) }}"
                                    alt=""
                                    class="size-6 object-contain"
                                >
                            @else
                                <span class="font-mono text-mono text-muted-foreground">{{ mb_substr($socialProfile->platform, 0, 1) }}</span>
                            @endif
                        </div>

                        <div class="flex flex-col">
                            <span class="font-medium">{{ $socialProfile->platform }}</span>
                            <span class="text-mono font-mono text-muted-foreground">{{ $socialProfile->url }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
