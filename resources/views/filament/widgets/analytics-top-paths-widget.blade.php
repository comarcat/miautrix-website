<x-filament-widgets::widget>
    {{-- BUG FIXED (found live, reported as "not saying what is being show and there is an
         error on the title"): the heading was written as "Top paths &amp; referrers" — a
         literal 5-character "&amp;" in the PHP string, not an ampersand. Filament's section
         component echoes its heading through Blade's auto-escaping {{ }}, which escaped that
         literal string a second time into the visible "&amp;amp;" the sponsor saw. Fixing it
         to a single literal "&" lets Blade escape it correctly, exactly once. --}}
    <x-filament::section heading="Top paths & referrers">
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
            Page views recorded over the selected date range (chosen above), most-visited first
            in each list below.
        </p>
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Top paths</h3>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Most-viewed URLs, by number of page views.</p>
                <ul class="space-y-1 text-sm">
                    @forelse ($topPaths as $path => $count)
                        <li class="flex justify-between gap-2">
                            {{-- BUG FIXED (found live): $path is already "/" for the home
                                 page (Laravel's own Request::path() convention) — unconditionally
                                 prepending another "/" rendered the home page's row as "// 127"
                                 instead of "/ 127". Every other path (e.g. "blog", "connect")
                                 has no leading slash at all, so it still needs one added. --}}
                            <span class="truncate">{{ str_starts_with($path, '/') ? $path : '/' . $path }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">No data yet.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Referrer hosts</h3>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Sites that sent visitors here (blank when a visitor arrived directly, e.g. by typing the URL or a bookmark).</p>
                <ul class="space-y-1 text-sm">
                    @forelse ($topReferrers as $host => $count)
                        <li class="flex justify-between gap-2">
                            <span class="truncate">{{ $host }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">No referrers recorded yet.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Country breakdown</h3>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Visitor country, resolved from IP address via MaxMind GeoLite2.</p>
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
                {{-- BUG FIXED (found live): titled "Professional vs. Life," a two-way framing,
                     but the underlying channel() logic (RecordPageView middleware) actually
                     buckets every page view into THREE groups — home+blog as "professional",
                     the theme-gated Life/Gaming blog as "life", and every other page (About,
                     Connect, Contact, Skills, Projects, Resume, Tools, Endorsements, etc.) as a
                     literal "other" — which rendered as the unexplained, unlabeled bucket that
                     actually held the second-most views. Retitled to describe what it really
                     shows, and each bucket now has a plain-language label instead of the raw
                     channel value. --}}
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Traffic by content channel</h3>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Every page view, grouped into the site's three content channels.</p>
                <ul class="space-y-1 text-sm">
                    @forelse ($byChannel as $channel => $count)
                        <li class="flex justify-between gap-2">
                            <span>{{ match ($channel) {
                                'professional' => 'Professional (Home + Blog)',
                                'life' => 'Life / Gaming blog',
                                default => 'Other pages (About, Connect, Contact, etc.)',
                            } }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500 dark:text-gray-400">No data yet.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Downloads by tool</h3>
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Download count per file in the public Tools section.</p>
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
