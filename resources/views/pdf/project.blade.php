<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $project->title }} — miautrix</title>
    {{-- E4-T1 — Dompdf print layout. Single inline <style>, no @vite, no external link/script:
         Dompdf fetches nothing, so every rule must live here. --}}
    <style>
        @page { margin: 96px 64px; }
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #111827;
        }
        .doc-header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 24px; }
        .doc-kicker { font-size: 8pt; letter-spacing: 0.12em; text-transform: uppercase; color: #6b7280; }
        h1 { font-size: 22pt; margin: 6px 0 4px; }
        .summary { color: #374151; font-size: 12pt; margin: 0 0 4px; }
        .meta { font-size: 9pt; color: #6b7280; margin-top: 8px; }
        .meta span { margin-right: 14px; }
        h2 { font-size: 13pt; margin: 22px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        p { margin: 0 0 10px; }
        ul { margin: 0 0 10px 18px; padding: 0; }
        .tags span {
            display: inline-block; border: 1px solid #d1d5db; border-radius: 4px;
            padding: 1px 6px; margin: 0 4px 4px 0; font-size: 9pt; color: #374151;
        }
        .footer { margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 8pt; color: #9ca3af; }
        a { color: #1d4ed8; text-decoration: none; }
    </style>
</head>
<body>
    <div class="doc-header">
        <div class="doc-kicker">Project{{ $project->projectCategory ? ' · ' . $project->projectCategory->name : '' }}</div>
        <h1>{{ $project->title }}</h1>
        @if ($project->summary)
            <p class="summary">{{ $project->summary }}</p>
        @endif
        <div class="meta">
            @if ($project->started_at)
                <span>Started {{ $project->started_at->format('F Y') }}</span>
            @endif
            @if ($project->repo_url)
                <span>Repository: {{ $project->repo_url }}</span>
            @endif
            @if ($project->live_url)
                <span>Live: {{ $project->live_url }}</span>
            @endif
        </div>
    </div>

    @if ($project->technologies->isNotEmpty())
        <h2>Technologies</h2>
        <div class="tags">
            @foreach ($project->technologies as $technology)
                <span>{{ $technology->name }}</span>
            @endforeach
        </div>
    @endif

    @if ($project->description)
        <h2>About this project</h2>
        {!! $project->description !!}
    @endif

    @if ($project->softwareProject)
        <h2>Engineering notes</h2>
        @if ($project->softwareProject->language_primary)
            <p><strong>Primary language:</strong> {{ $project->softwareProject->language_primary }}</p>
        @endif
        @if ($project->softwareProject->architecture_notes)
            <p>{{ $project->softwareProject->architecture_notes }}</p>
        @endif
        @if ($project->softwareProject->deployment_notes)
            <p>{{ $project->softwareProject->deployment_notes }}</p>
        @endif
    @endif

    <div class="footer">
        Generated from miautrix.tech{{ ' · ' . now()->format('F j, Y') }} · {{ route('projects.show', $project->slug) }}
    </div>
</body>
</html>
