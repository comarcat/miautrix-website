@props(['id'])

{{--
    E4-T9 (§9 step 34) — click-to-play YouTube facade: a thumbnail + a real <button>, NO
    <iframe> markup anywhere in the server-rendered HTML at all. A click builds the iframe
    element via document.createElement(...) and appends it — the `/life`-scoped CSP
    (SecurityHeaders) is what makes that request reachable in the first place, and this
    component is the only place on the whole site one can ever appear. No inline <script>
    here — play() is a plain Alpine x-data method, already covered by PUBLIC_CSP's
    script-src 'unsafe-eval' (Livewire needs that regardless — see SecurityHeaders' own
    docblock), so nothing here needs its own nonce.
--}}
<div
    x-data="{
        playing: false,
        play() {
            this.playing = true;
            const frame = document.createElement('iframe');
            frame.src = 'https://www.youtube-nocookie.com/embed/{{ $id }}?autoplay=1';
            frame.title = 'YouTube video player';
            frame.className = 'h-full w-full';
            frame.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
            frame.allowFullscreen = true;
            this.$refs.frame.appendChild(frame);
        },
    }"
    class="relative aspect-video w-full overflow-hidden rounded-card bg-muted"
    data-youtube-id="{{ $id }}"
>
    <button
        type="button"
        x-show="!playing"
        @click="play()"
        class="group relative block h-full w-full"
        aria-label="Play video"
    >
        <img
            src="https://i.ytimg.com/vi/{{ $id }}/hqdefault.jpg"
            alt=""
            class="h-full w-full object-cover"
            loading="lazy"
        >
        <span class="absolute inset-0 flex items-center justify-center bg-black/30 transition-opacity group-hover:bg-black/40">
            <span class="flex size-16 items-center justify-center rounded-full bg-white/90 text-black">
                &#9658;
            </span>
        </span>
    </button>

    <div x-show="playing" x-cloak x-ref="frame" class="h-full w-full"></div>
</div>
