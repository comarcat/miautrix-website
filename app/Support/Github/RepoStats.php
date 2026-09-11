<?php

namespace App\Support\Github;

use App\Models\Tool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Phase 2 (E5-T7, §9 step 41) — cached GitHub repo stats for a tool (backlog item 13.1).
 * `for()` is a read-triggered refresh, called from Tool::repoStats() — a page view decides
 * whether a fetch happens, nothing here runs on a schedule.
 *
 * Two independent staleness guards, both 1 hour: the `gh_fetched_at` column (cheap, no
 * cache lookup, and survives a cache flush) short-circuits most calls before they even
 * reach Cache::remember; the cache entry underneath protects against two requests racing
 * within the same hour before the first one's DB write has landed.
 *
 * On anything that isn't a clean 2xx JSON response — a non-2xx status, a timeout, a DNS
 * failure, a malformed body — this returns the Tool with its EXISTING gh_* values
 * untouched (gh_fetched_at included, so the next view retries rather than waiting out a
 * full hour on a transient failure).
 */
class RepoStats
{
    private const TTL_SECONDS = 3600;

    public function for(Tool $tool): Tool
    {
        $slug = $this->repoSlug($tool->repo_url);

        if ($slug === null) {
            return $tool;
        }

        if ($tool->gh_fetched_at !== null && $tool->gh_fetched_at->gt(now()->subSeconds(self::TTL_SECONDS))) {
            return $tool;
        }

        $data = Cache::remember("github-repo-stats:{$slug}", self::TTL_SECONDS, function () use ($slug) {
            return $this->fetch($slug);
        });

        if ($data === null) {
            return $tool;
        }

        $tool->update([
            'gh_stars' => $data['stargazers_count'] ?? null,
            'gh_forks' => $data['forks_count'] ?? null,
            'gh_language' => $data['language'] ?? null,
            'gh_license' => $data['license']['spdx_id'] ?? null,
            'gh_pushed_at' => $data['pushed_at'] ?? null,
            'gh_fetched_at' => now(),
        ]);

        return $tool;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $slug): ?array
    {
        try {
            $request = Http::timeout(5)->acceptJson();
            $token = config('services.github.token');

            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->get("https://api.github.com/repos/{$slug}");

            if (! $response->successful()) {
                return null;
            }

            return $response->json();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 'owner/repo' from a github.com URL, or null for anything else (no repo_url, a
     * non-GitHub host, a malformed URL).
     */
    private function repoSlug(?string $repoUrl): ?string
    {
        if (! $repoUrl) {
            return null;
        }

        $host = parse_url($repoUrl, PHP_URL_HOST);

        if (! in_array($host, ['github.com', 'www.github.com'], true)) {
            return null;
        }

        $path = trim((string) parse_url($repoUrl, PHP_URL_PATH), '/');
        $segments = array_values(array_filter(explode('/', $path)));

        if (count($segments) < 2) {
            return null;
        }

        return $segments[0] . '/' . preg_replace('/\.git$/', '', $segments[1]);
    }
}
