<div
    x-data="{ open: @entangle('open') }"
    class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-4"
>
    <div class="w-full max-w-4xl">
        <div class="flex justify-center">
            <button
                type="button"
                @click="open = !open"
                class="rounded-t-card border border-b-0 border-border bg-card px-4 py-1 font-mono text-mono text-muted-foreground hover:text-foreground"
                aria-label="Toggle terminal"
            >
                {{ '>' }}_ terminal
            </button>
        </div>

        <div
            x-show="open"
            x-cloak
            x-transition
            class="flex h-[28rem] flex-col rounded-card border border-border bg-card p-3 font-mono text-mono text-foreground shadow-elevation-2"
        >
            {{-- BUG FIXED (found live: "should move to the last line created like a real
                 terminal"): new output used to just append below the fold with no auto-scroll,
                 so a real terminal user had to manually scroll down to see their own command's
                 result. $wire.history is Livewire's Alpine-reactive view of the history
                 property; touching it inside x-effect re-runs the effect on every change
                 (including this one loading, which is harmless — it just scrolls to whatever
                 is already at the bottom), then $nextTick waits for the new <div> lines to
                 actually be in the DOM before measuring scrollHeight. --}}
            <div
                x-ref="output"
                x-effect="$wire.history; $nextTick(() => $refs.output.scrollTop = $refs.output.scrollHeight)"
                class="flex-1 overflow-y-auto whitespace-pre-wrap" aria-live="polite"
            >
                @foreach ($history as $line)
                    <div>{{ $line }}</div>
                @endforeach
            </div>

            <form wire:submit="run" class="mt-2 flex items-center gap-2 border-t border-border pt-2">
                <label for="terminal-input" class="sr-only">Terminal command</label>
                <span aria-hidden="true">$</span>
                <input
                    id="terminal-input"
                    type="text"
                    wire:model="input"
                    autocomplete="off"
                    class="flex-1 border-0 bg-transparent p-0 font-mono text-mono text-foreground focus:outline-none focus:ring-0"
                    placeholder="help"
                >
            </form>
        </div>
    </div>
</div>
