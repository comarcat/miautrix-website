<x-filament-widgets::widget>
    <x-filament::section heading="Top paths &amp; referrers">
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Top paths</h3>
                <ul class="space-y-1 text-sm">
                    @forelse ($topPaths as $path => $count)
                        <li class="flex justify-between gap-2">
                            <span class="truncate">/{{ $path }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">No data yet.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Referrer hosts</h3>
                <ul class="space-y-1 text-sm">
                    @forelse ($topReferrers as $host => $count)
                        <li class="flex justify-between gap-2">
                            <span class="truncate">{{ $host }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">No data yet.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Country breakdown</h3>
                <ul class="space-y-1 text-sm">
                    @forelse ($byCountry as $country => $count)
                        <li class="flex justify-between gap-2">
                            <span>{{ $country }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">No geo data (GeoLite2 .mmdb not installed, or no views yet).</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Professional vs. Life</h3>
                <ul class="space-y-1 text-sm">
                    @forelse ($byChannel as $channel => $count)
                        <li class="flex justify-between gap-2">
                            <span class="capitalize">{{ $channel }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">No data yet.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Downloads by tool</h3>
                <ul class="space-y-1 text-sm">
                    @forelse ($downloadsByTool as $title => $count)
                        <li class="flex justify-between gap-2">
                            <span class="truncate">{{ $title }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">No downloads yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
