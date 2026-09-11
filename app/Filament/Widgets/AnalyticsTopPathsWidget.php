<?php

namespace App\Filament\Widgets;

use App\Models\PageView;
use App\Models\ToolDownload;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Phase 2 (E5-T9, §9 step 43) — top paths, referrer hosts, country breakdown and
 * professional-vs-life split, all from page_views/tool_downloads directly. A plain Widget
 * with its own Blade view (not TableWidget, which is built around one single query) —
 * static HTML lists, no chart library, no third-party script.
 */
class AnalyticsTopPathsWidget extends Widget
{
    protected string $view = 'filament.widgets.analytics-top-paths-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $since = $this->since();
        $views = $since ? PageView::query()->where('created_at', '>=', $since) : PageView::query();

        return [
            'topPaths' => (clone $views)->selectRaw('path, count(*) as total')
                ->groupBy('path')->orderByDesc('total')->limit(10)->pluck('total', 'path'),
            'topReferrers' => (clone $views)->whereNotNull('referrer_host')
                ->selectRaw('referrer_host, count(*) as total')
                ->groupBy('referrer_host')->orderByDesc('total')->limit(10)->pluck('total', 'referrer_host'),
            'byCountry' => (clone $views)->whereNotNull('country')
                ->selectRaw('country, count(*) as total')
                ->groupBy('country')->orderByDesc('total')->limit(10)->pluck('total', 'country'),
            'byChannel' => (clone $views)->selectRaw('channel, count(*) as total')
                ->groupBy('channel')->orderByDesc('total')->pluck('total', 'channel'),
            'downloadsByTool' => $this->downloadsByTool($since),
        ];
    }

    private function downloadsByTool(?CarbonImmutable $since): Collection
    {
        $downloads = $since
            ? ToolDownload::query()->where('tool_downloads.created_at', '>=', $since)
            : ToolDownload::query();

        return $downloads->join('tools', 'tools.id', '=', 'tool_downloads.tool_id')
            ->selectRaw('tools.title, count(*) as total')
            ->groupBy('tools.title')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'title');
    }

    private function since(): ?CarbonImmutable
    {
        $days = (int) request()->integer('days', 30);

        return $days > 0 ? now()->subDays($days) : null;
    }
}
