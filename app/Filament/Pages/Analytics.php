<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Phase 2 (E5-T9, §9 step 43) — self-hosted analytics (backlog item 14), consolidating
 * page_views (E5-T8), tool_downloads (E5-T5) and share_clicks (E2-T1). A plain `?days=`
 * query-string range selector rather than Filament's page-filters machinery: the view
 * mounts AnalyticsOverviewWidget/AnalyticsTopPathsWidget directly (a custom Page's base
 * Blade template doesn't auto-render getHeaderWidgets() the way the built-in Dashboard
 * page does), and both widgets read the same `request()->integer('days', 30)` the Page
 * does — nothing here can disagree about which range is active, and the range survives a
 * page refresh/share exactly like any other URL.
 */
class Analytics extends Page
{
    protected string $view = 'filament.pages.analytics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Analytics';

    /**
     * @var list<array{label: string, days: int}>
     */
    public const RANGES = [
        ['label' => 'Last 24 hours', 'days' => 1],
        ['label' => 'Last 7 days', 'days' => 7],
        ['label' => 'Last 30 days', 'days' => 30],
        ['label' => 'All time', 'days' => 0],
    ];

    public function activeDays(): int
    {
        return (int) request()->integer('days', 30);
    }
}
