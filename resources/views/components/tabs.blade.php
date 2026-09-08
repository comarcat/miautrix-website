@props([
    // Each item: ['id' => string, 'label' => string]. Panel content is the default slot: the
    // caller wraps each panel in its own element carrying `x-show="tab === '<id>'"` — Alpine's
    // scope from this component's x-data reaches straight through the slotted HTML, so no
    // dynamic-variable-name trick is needed to route content to the right panel.
    'tabs' => [],
    'active' => null,
])

@php
    $activeId = $active ?? ($tabs[0]['id'] ?? null);
@endphp

<div x-data="{ tab: '{{ $activeId }}' }" {{ $attributes }}>
    <div role="tablist" class="flex gap-2 border-b border-border">
        @foreach ($tabs as $tab)
            <button
                type="button"
                role="tab"
                x-on:click="tab = '{{ $tab['id'] }}'"
                :aria-selected="(tab === '{{ $tab['id'] }}').toString()"
                :class="tab === '{{ $tab['id'] }}' ? 'border-accent text-accent-text' : 'border-transparent text-muted-foreground hover:text-foreground'"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium"
            >
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    <div class="pt-4">
        {{ $slot }}
    </div>
</div>
