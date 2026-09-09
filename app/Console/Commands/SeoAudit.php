<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Media;
use App\Models\Project;
use Illuminate\Console\Command;

/**
 * E5-T1 (§9 step 25) — a real SEO audit, not a rubber stamp: every publishable entity
 * (Project/Article/Experience/Education/Certification) shares the same SEO column shape
 * (seo_title, meta_description, canonical_url, og_image_id), so this walks them generically
 * rather than one hand-written check per model.
 *
 * "Missing" — the derived title/description (the seo_title/meta_description override, or the
 * entity's own required title/summary field as fallback) is empty. Structurally can't happen
 * today (those fallback columns are NOT NULL), but this is the real guard against a future
 * migration making one nullable, not a vacuous check.
 * "Duplicate" — two published entities (of any type) sharing the same non-null canonical_url.
 * A canonical_url is supposed to be unique per page; two pages pointing at the same one is
 * accidental duplicate-content signaling, not a coincidence worth allowing.
 * "Mismatched" — a set canonical_url that isn't even a well-formed absolute URL, or (for the
 * two entities with a real public detail route, Project/Article) a canonical_url that doesn't
 * match the route the entity actually resolves to.
 * Also flags a broken og_image_id reference (set, but no matching Media row) — not one of the
 * three acceptance-named categories by itself, but folded into "missing" since that's exactly
 * what it is: a missing image.
 *
 * @phpstan-type PublishableEntity Project|Article|Experience|Education|Certification
 */
class SeoAudit extends Command
{
    protected $signature = 'seo:audit';

    protected $description = 'Audit every publishable entity for missing, duplicate, or mismatched SEO fields.';

    public function handle(): int
    {
        $issues = [];

        $canonicalUrls = [];

        foreach ($this->publishableEntities() as [$model, $routeName]) {
            $label = class_basename($model) . " #{$model->id}";
            $title = $model->seo_title ?: $this->fallbackTitle($model);
            $description = $model->meta_description ?: $this->fallbackDescription($model);

            if (trim((string) $title) === '') {
                $issues[] = "MISSING: {$label} has no usable title (seo_title and fallback both empty).";
            }

            if (trim((string) $description) === '') {
                $issues[] = "MISSING: {$label} has no usable description (meta_description and fallback both empty).";
            }

            if ($model->og_image_id && ! Media::find($model->og_image_id)) {
                $issues[] = "MISSING: {$label} og_image_id={$model->og_image_id} does not reference an existing Media row.";
            }

            if ($model->canonical_url) {
                if (filter_var($model->canonical_url, FILTER_VALIDATE_URL) === false) {
                    $issues[] = "MISMATCHED: {$label} canonical_url '{$model->canonical_url}' is not a well-formed absolute URL.";
                } elseif ($routeName && $model->canonical_url !== route($routeName, $model->slug)) {
                    $issues[] = "MISMATCHED: {$label} canonical_url '{$model->canonical_url}' does not match its own route (" . route($routeName, $model->slug) . ').';
                }

                if (isset($canonicalUrls[$model->canonical_url])) {
                    $issues[] = "DUPLICATE: {$label} shares canonical_url '{$model->canonical_url}' with {$canonicalUrls[$model->canonical_url]}.";
                } else {
                    $canonicalUrls[$model->canonical_url] = $label;
                }
            }
        }

        if ($issues === []) {
            $this->info('SEO audit passed — 0 missing/duplicate/mismatched fields.');

            return self::SUCCESS;
        }

        $this->error(count($issues) . ' SEO issue(s) found:');
        foreach ($issues as $issue) {
            $this->line(' - ' . $issue);
        }

        return self::FAILURE;
    }

    /**
     * @return list<array{0: PublishableEntity, 1: string|null}>
     */
    private function publishableEntities(): array
    {
        $entities = [];

        foreach (Project::where('published', true)->get() as $project) {
            $entities[] = [$project, 'projects.show'];
        }

        foreach (Article::published()->get() as $article) {
            $entities[] = [$article, 'blog.show'];
        }

        // No individual public detail route exists for these three (aggregate pages only,
        // E4-T4) — routeName is null, so the route-match half of the "mismatched" check is
        // skipped for them, but the other checks (missing title/description, broken image
        // reference, duplicate canonical_url) still apply.
        foreach (Experience::where('published', true)->get() as $experience) {
            $entities[] = [$experience, null];
        }

        foreach (Education::where('published', true)->get() as $education) {
            $entities[] = [$education, null];
        }

        foreach (Certification::where('published', true)->get() as $certification) {
            $entities[] = [$certification, null];
        }

        return $entities;
    }

    /**
     * @param  PublishableEntity  $model
     */
    private function fallbackTitle($model): ?string
    {
        return match ($model::class) {
            Project::class, Article::class, Experience::class => $model->title,
            Education::class => $model->degree,
            Certification::class => $model->name,
            default => null,
        };
    }

    /**
     * @param  PublishableEntity  $model
     */
    private function fallbackDescription($model): ?string
    {
        return match ($model::class) {
            Project::class => $model->summary,
            Article::class => $model->excerpt,
            Experience::class => $model->description,
            Education::class => $model->field_of_study,
            Certification::class => $model->issuer,
            default => null,
        };
    }
}
