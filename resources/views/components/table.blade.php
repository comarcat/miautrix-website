@props([
    // Column headers, e.g. ['Name', 'Role', 'Started']
    'headers' => [],
])

{{-- Wide tables scroll inside their own container rather than the page (a11y + layout rule,
     blueprint §15) — never let a table push the whole viewport into horizontal scroll. --}}
<div class="overflow-x-auto rounded-card border border-border">
    <table {{ $attributes->merge(['class' => 'w-full text-left text-body']) }}>
        @if (count($headers))
            <thead class="border-b border-border bg-muted">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-4 py-3 font-mono text-mono uppercase tracking-wide text-muted-foreground">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-border">
            {{ $slot }}
        </tbody>
    </table>
</div>
