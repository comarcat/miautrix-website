<x-layouts::app
    title="Skills — miautrix"
    description="Technical skills grouped by category."
>
    <div class="flex flex-col gap-8">
        <x-breadcrumb :items="[['label' => 'Home', 'href' => route('home')], ['label' => 'Skills']]" />

        <section class="flex flex-col gap-4">
            <h1 class="text-display text-foreground">Skills</h1>
        </section>

        @if ($skillCategories->isEmpty() || $skillCategories->every(fn ($category) => $category->skills->isEmpty()))
            <x-alert variant="info">No skills published yet.</x-alert>
        @else
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($skillCategories as $category)
                    @continue ($category->skills->isEmpty())

                    <x-card :title="$category->name">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($category->skills as $skill)
                                <x-badge>
                                    {{ $skill->name }}
                                    <span class="text-muted-foreground">· {{ $skill->proficiency }}</span>
                                </x-badge>
                            @endforeach
                        </div>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
