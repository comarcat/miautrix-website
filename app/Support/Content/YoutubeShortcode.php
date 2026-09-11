<?php

namespace App\Support\Content;

use Illuminate\Support\Facades\Blade;

/**
 * E4-T9 (§9 step 34) — expands a `[youtube:ID]` shortcode written into a Life article's
 * RichEditor body into the click-to-play facade (<x-youtube-embed>). `ID` is YouTube's own
 * 11-character video id alphabet (`A-Za-z0-9_-`); anything else is left untouched rather
 * than risk injecting an attacker-controlled id into the embed src.
 *
 * Body HTML is already-sanitized RichEditor output (no <script> node in its schema — see
 * ArticleForm's own comment), so this only ever substitutes a widget for a shortcode string;
 * it never introduces new attacker-controlled markup itself.
 */
class YoutubeShortcode
{
    public static function expand(string $html): string
    {
        return (string) preg_replace_callback(
            '/\[youtube:([A-Za-z0-9_-]{11})\]/',
            fn (array $matches) => Blade::render('<x-youtube-embed :id="$id" />', ['id' => $matches[1]]),
            $html,
        );
    }
}
