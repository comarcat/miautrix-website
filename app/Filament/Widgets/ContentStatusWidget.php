<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/**
 * Content-publish status counts (§9 step 17) — real published/total counts across every
 * publishable-entity content type, straight from the database.
 */
class ContentStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        return [
            $this->publishStat('Projects', Project::class),
            $this->publishStat('Experience', Experience::class),
            $this->publishStat('Education', Education::class),
            $this->publishStat('Certifications', Certification::class),
            $this->publishStat('Articles', Article::class),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function publishStat(string $label, string $model): Stat
    {
        $total = $model::count();
        $published = $model::where('published', true)->count();

        return Stat::make($label, "{$published} / {$total}")
            ->description('published / total');
    }
}
