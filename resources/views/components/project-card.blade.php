@props(['project'])

{{--
    Phase 2 (E4-T6, §9 step 31) — extracted from the inline card markup the projects index
    used to repeat per row, specifically so the "On time · On budget" badge has one place to
    live. Shown only when BOTH isOnTime and isOnBudget are true (neither is ever a string of
    its own opinion about a project missing the inputs those accessors need — both are null
    rather than false in that case, so the badge simply doesn't render).
--}}
<x-card :title="$project->title">
    <div class="mb-2 flex flex-wrap items-center gap-2">
        @if ($project->projectCategory)
            <x-badge variant="accent">{{ $project->projectCategory->name }}</x-badge>
        @endif
        @if ($project->isOnTime === true && $project->isOnBudget === true)
            <x-badge variant="success">On time &middot; On budget</x-badge>
        @endif
    </div>
    <p>{{ $project->summary }}</p>

    <x-slot:footer>
        <x-button :href="route('projects.show', $project->slug)" variant="outline" size="sm">
            View project
        </x-button>
    </x-slot:footer>
</x-card>
