<x-filament-panels::page>
    {{-- E5-T9 — a plain query-string range selector (?days=). Both widgets below read the
         same request()->integer('days', 30), so there is nothing here to keep in sync. --}}
    <div class="flex flex-wrap gap-2">
        @foreach (\App\Filament\Pages\Analytics::RANGES as $range)
            <a
                href="{{ \App\Filament\Pages\Analytics::getUrl(['days' => $range['days']]) }}"
                class="rounded-full px-3 py-1 text-sm {{ $this->activeDays() === $range['days'] ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}"
            >
                {{ $range['label'] }}
            </a>
        @endforeach
    </div>

    {{-- Rendered directly (not via getHeaderWidgets()'s plain-page wiring, which the base
         Page Blade template doesn't auto-output for a non-Dashboard page) so both widgets
         re-read the current ?days= on every request. --}}
    <div class="mt-4">
        @livewire(\App\Filament\Widgets\AnalyticsOverviewWidget::class)
    </div>
    <div class="mt-4">
        @livewire(\App\Filament\Widgets\AnalyticsTopPathsWidget::class)
    </div>
</x-filament-panels::page>
