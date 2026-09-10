<?php

namespace App\Http\Controllers\Public;

use App\Models\Article;
use App\Models\ShareClick;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Phase 2 (E2-T3, §9 step 11) — first-party click-logging redirect for blog share links.
 *
 * `<x-share-links :share-type :share-id>` points every button here instead of straight at the
 * network. This records one append-only `share_clicks` row and then 302s to the network's own
 * share endpoint. Registered OUTSIDE `cache.public` (routes/web.php) so every hit runs.
 *
 * `network` is validated against the same six-value set the component emits; `type` is loose
 * ('article' only for now) because `subject_id` is not a foreign key — the log outlives its
 * subject. An unknown network or a missing/unpublished article is a 404 with no row written.
 */
class ShareRedirectController extends Controller
{
    /** Truncation cap for the stored `Referer` header. */
    private const REFERRER_MAX = 255;

    /** @var list<string> */
    private const NETWORKS = ['facebook', 'x', 'linkedin', 'whatsapp', 'reddit', 'email'];

    public function __invoke(Request $request, string $network, string $type, int $id): RedirectResponse
    {
        $network = Str::lower($network);

        if (! in_array($network, self::NETWORKS, true) || $type !== 'article') {
            throw new NotFoundHttpException;
        }

        $article = Article::query()->published()->find($id);

        if ($article === null) {
            throw new NotFoundHttpException;
        }

        $shareUrl = route('blog.show', $article->slug);
        $target = $this->networkUrl($network, $shareUrl, $article->title, (string) $article->excerpt);

        ShareClick::create([
            'network' => $network,
            'type' => $type,
            'subject_id' => $article->id,
            'referrer' => Str::limit((string) $request->headers->get('referer'), self::REFERRER_MAX, ''),
            'ip' => $request->ip(),
        ]);

        return redirect()->away($target, 302);
    }

    /**
     * Build the network's own share-intent URL. Mirrors the href table in
     * resources/views/components/share-links.blade.php (direct mode).
     */
    private function networkUrl(string $network, string $url, string $title, string $summary): string
    {
        $u = rawurlencode($url);
        $t = rawurlencode($title);
        $s = rawurlencode($summary);

        return match ($network) {
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$u}",
            'x' => "https://twitter.com/intent/tweet?url={$u}&text={$t}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$u}",
            'whatsapp' => "https://api.whatsapp.com/send?text={$t}%20{$u}",
            'reddit' => "https://www.reddit.com/submit?url={$u}&title={$t}",
            'email' => "mailto:?subject={$t}&body={$s}%0A%0A{$u}",
            default => throw new \LogicException("Unhandled share network: {$network}"),
        };
    }
}
