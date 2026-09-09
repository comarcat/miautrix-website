@if ($socialProfiles->isNotEmpty())
    <div class="flex items-center gap-4">
        @foreach ($socialProfiles as $socialProfile)
            <a
                href="{{ $socialProfile->url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center gap-1.5 hover:text-accent-text"
            >
                @if ($socialProfile->icon)
                    <img
                        src="{{ route('media.show', [$socialProfile->icon, $socialProfile->icon->file_name]) }}"
                        alt=""
                        class="size-4 object-contain"
                    >
                @endif
                {{ $socialProfile->platform }}
            </a>
        @endforeach
    </div>
@endif
