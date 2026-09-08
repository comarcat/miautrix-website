<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

/**
 * E4-T3 — the 12 shared Blade components, rendered correctly in both themes (§9 step 21).
 *
 * One test per component (12 total, per this task's own acceptance criterion 4). Each test
 * covers acceptance criteria 1 and 2 together: render the component nested inside both a
 * `data-theme="technical"` and a `data-theme="matrix"` wrapper, assert no raw hex color ever
 * appears in a `style` attribute, and assert the component's own inner markup is byte-identical
 * between the two — components never branch on theme in Blade/PHP, only the CSS custom
 * properties their utility classes resolve to differ, so this is a real regression guard, not
 * a vacuous check. The Modal test additionally covers acceptance criterion 3 (focus trap).
 */
class ComponentLibraryTest extends TestCase
{
    /**
     * Renders $blade once inside a technical wrapper and once inside a matrix wrapper,
     * asserts neither contains a raw hex color in a style attribute, and asserts the
     * component's own markup (the wrapper stripped back off) is identical in both.
     */
    private function assertThemeAgnostic(string $blade, array $data = []): string
    {
        // $this->blade() (not Blade::render()) — it writes a real temp .blade.php file and
        // resolves it through the normal view() pipeline, which is what components using
        // named slots (<x-slot:trigger>, the Modal test) need: Blade::render()'s ad-hoc
        // string-component path left a dangling output buffer under nested named slots,
        // which PHPUnit's failOnRisky then correctly failed the build on.
        $technical = (string) $this->blade('<div data-theme="technical">' . $blade . '</div>', $data);
        $matrix = (string) $this->blade('<div data-theme="matrix">' . $blade . '</div>', $data);

        $this->assertDoesNotMatchRegularExpression('/style="[^"]*#[0-9a-fA-F]{3,8}/i', $technical);
        $this->assertDoesNotMatchRegularExpression('/style="[^"]*#[0-9a-fA-F]{3,8}/i', $matrix);

        $strip = fn (string $html, string $theme): string => preg_replace(
            '/^<div data-theme="' . $theme . '">|<\/div>$/',
            '',
            trim($html)
        );

        $technicalInner = $strip($technical, 'technical');
        $matrixInner = $strip($matrix, 'matrix');

        $this->assertSame($technicalInner, $matrixInner, 'Component markup must not vary by theme — only resolved CSS token values may differ.');

        return $technicalInner;
    }

    public function test_button_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-button variant="primary">Save</x-button>');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('bg-primary', $html);
        $this->assertStringContainsString('Save', $html);
    }

    public function test_card_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-card title="Heading">Body content</x-card>');

        $this->assertStringContainsString('bg-card', $html);
        $this->assertStringContainsString('Heading', $html);
        $this->assertStringContainsString('Body content', $html);
    }

    public function test_badge_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-badge variant="accent">New</x-badge>');

        $this->assertStringContainsString('<span', $html);
        $this->assertStringContainsString('bg-accent', $html);
    }

    public function test_timeline_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-timeline :items="$items" />', [
            'items' => [
                ['title' => 'Senior Engineer', 'subtitle' => 'Acme Corp', 'period' => '2022—Present'],
            ],
        ]);

        $this->assertStringContainsString('<ol', $html);
        $this->assertStringContainsString('Senior Engineer', $html);
    }

    public function test_table_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic(
            '<x-table :headers="[\'Name\', \'Role\']"><tr><td>Ada</td><td>Engineer</td></tr></x-table>'
        );

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Ada', $html);
    }

    public function test_breadcrumb_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-breadcrumb :items="$items" />', [
            'items' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Projects'],
            ],
        ]);

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Projects', $html);
    }

    public function test_alert_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-alert variant="error">Something went wrong.</x-alert>');

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('Something went wrong.', $html);
    }

    public function test_toast_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-toast />');

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('x-cloak', $html);
    }

    public function test_tabs_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic(
            '<x-tabs :tabs="$tabs"><div x-show="tab === \'overview\'">Overview panel</div></x-tabs>',
            ['tabs' => [['id' => 'overview', 'label' => 'Overview']]]
        );

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('Overview panel', $html);
    }

    public function test_file_upload_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-file-upload name="resume" label="Resume" accept=".pdf" />');

        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('name="resume"', $html);
    }

    public function test_image_gallery_component_renders_identically_across_themes_with_no_raw_hex(): void
    {
        $html = $this->assertThemeAgnostic('<x-image-gallery :images="$images" />', [
            'images' => [['src' => '/media/1/photo.jpg', 'alt' => 'A photo']],
        ]);

        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('/media/1/photo.jpg', $html);
    }

    public function test_modal_component_traps_focus_and_restores_it_to_the_trigger_on_close(): void
    {
        // Multi-line, not a one-liner: Blade's colon-shorthand slot tag
        // (<x-slot:trigger>...</x-slot:trigger>) needs its content on its own line to compile
        // correctly — crammed onto one line, the closing tag leaks past the compiler and the
        // literal "@endslot" text ends up in the rendered output.
        $html = $this->assertThemeAgnostic(<<<'BLADE'
            <x-modal id="confirm" title="Confirm">
                <x-slot:trigger>
                    <button>Open</button>
                </x-slot:trigger>
                Are you sure?
            </x-modal>
            BLADE
        );

        // Structural dialog semantics.
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="confirm-title"', $html);

        // The trap itself: remembers the triggering element, focuses the panel's first
        // focusable element on open, cycles Tab/Shift+Tab within the panel, and restores
        // focus to what was previously focused on close (acceptance criterion 3).
        $this->assertStringContainsString('previouslyFocused = document.activeElement', $html);
        $this->assertStringContainsString('previouslyFocused?.focus()', $html);
        $this->assertStringContainsString('handleTab(', $html);
        $this->assertStringContainsString('x-on:keydown.tab="handleTab($event)"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="close()"', $html);
    }
}
