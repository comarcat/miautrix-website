<?php

namespace App\Filament\Widgets;

use App\Models\PageView;
use App\Models\ShareClick;
use App\Models\ToolDownload;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 2 (E5-T9, §9 step 43) — totals over the Analytics Page's range (views, downloads,
 * share clicks), straight from page_views/tool_downloads/share_clicks — no third-party
 * analytics vendor, no JS chart library.
 */
class AnalyticsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $since = $this->since();

        return [
            Stat::make('Page views', $this->scoped(PageView::query(), $since)->count()),
            Stat::make('Tool downloads', $this->scoped(ToolDownload::query(), $since)->count()),
            Stat::make('Share clicks', $this->scoped(ShareClick::query(), $since)->count()),
        ];
    }

    private function since(): ?CarbonImmutable
    {
        $days = (int) request()->integer('days', 30);

        return $days > 0 ? now()->subDays($days) : null;
    }

    private function scoped(Builder $query, ?CarbonImmutable $since): Builder
    {
        return $since ? $query->where('created_at', '>=', $since) : $query;
    }
}
