<?php

namespace Tests\Feature\Layout;

use Tests\TestCase;

/**
 * E4-T1 — design tokens, base public layout, meta component (§9 step 19).
 *
 * These render the layout/component directly via Blade strings rather than through a
 * real route: the public pages that actually use `x-layouts::app` land in E4-T4/T5/T6,
 * and this task's acceptance criteria are about the layout and meta component in
 * isolation, not about any specific page's content.
 */
class MetaComponentTest extends TestCase
{
    public function test_base_layout_preloads_the_400_and_600_weight_ibm_plex_sans_fonts(): void
    {
        $html = (string) $this->blade(
            '<x-layouts::app title="Test" description="Test description">Body content</x-layouts::app>'
        );

        $this->assertStringContainsString('ibm-plex-sans-400.woff2', $html);
        $this->assertStringContainsString('ibm-plex-sans-600.woff2', $html);
        $this->assertMatchesRegularExpression(
            '/<link rel="preload" href="[^"]*ibm-plex-sans-400\.woff2" as="font" type="font\/woff2" crossorigin>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<link rel="preload" href="[^"]*ibm-plex-sans-600\.woff2" as="font" type="font\/woff2" crossorigin>/',
            $html
        );
    }

    public function test_meta_component_renders_matching_title_description_and_og_tags(): void
    {
        $html = (string) $this->blade(
            '<x-meta title="Hello World" description="A test description" image="https://example.com/og.png" />'
        );

        $this->assertStringContainsString('<title>Hello World</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="A test description">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Hello World">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="A test description">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://example.com/og.png">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
    }

    public function test_base_layout_defaults_to_the_technical_theme_and_uses_semantic_color_tokens(): void
    {
        $html = (string) $this->blade(
            '<x-layouts::app title="Test" description="Test description">Body content</x-layouts::app>'
        );

        $this->assertStringContainsString('data-theme="technical"', $html);
        $this->assertStringContainsString('bg-background', $html);
        $this->assertStringContainsString('text-foreground', $html);
    }
}
