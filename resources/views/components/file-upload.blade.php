@props([
    'name',
    'label' => null,
    'accept' => null,
    'multiple' => false,
    'hint' => null,
])

@php
    $inputId = $attributes->get('id') ?? 'file-upload-' . $name;
    // $errors is normally shared globally by Laravel's web middleware — defensively default
    // it here so this component also renders standalone (e.g. Blade::render() in tests).
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp

<div class="flex flex-col gap-2">
    @if ($label)
        <label for="{{ $inputId }}" class="text-body font-medium text-foreground">{{ $label }}</label>
    @endif

    <label
        for="{{ $inputId }}"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-card border border-dashed border-border bg-muted px-4 py-8 text-center text-body text-muted-foreground hover:border-accent hover:text-accent-text"
    >
        <span>Drop a file here or click to browse</span>
        @if ($accept)
            <span class="font-mono text-mono">{{ $accept }}</span>
        @endif
    </label>

    <input
        {{ $attributes->except(['id', 'class']) }}
        id="{{ $inputId }}"
        type="file"
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        @if ($accept) accept="{{ $accept }}" @endif
        @if ($multiple) multiple @endif
        class="sr-only"
    >

    @if ($hint)
        <p class="text-mono font-mono text-muted-foreground">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="text-body text-destructive">{{ $message }}</p>
    @enderror
</div>
