<?php

namespace App\Livewire;

use App\Http\Middleware\ResolveTheme;
use App\Support\Theming\ThemeResolver;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

/**
 * Phase 2 (E5-T3, §9 step 37) — the "fun terminal-style command widget" (backlog item 4).
 * Mounted once on every public page (layouts/app.blade.php), never on the admin panel, which
 * builds its own separate Filament layout that never includes it. Output is a plain in-memory
 * transcript, never persisted.
 *
 * `whoami` is a stub here — its real IP-derived payload is wired in E5-T4's
 * WhoamiController; this just points at it so both code paths agree on one source of truth.
 */
class Terminal extends Component
{
    public string $input = '';

    /** @var list<string> */
    public array $history = [];

    public bool $open = false;

    /** @var array<string, string> page name => route name */
    private const PAGES = [
        'home' => 'home',
        'about' => 'about',
        'experience' => 'experience',
        'skills' => 'skills',
        'projects' => 'projects.index',
        'resume' => 'resume',
        'blog' => 'blog.index',
        'connect' => 'connect',
        'contact' => 'contact',
    ];

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function run(): void
    {
        $line = trim($this->input);
        $this->input = '';

        if ($line === '') {
            return;
        }

        $this->history[] = '$ ' . $line;

        [$command, $argument] = array_pad(preg_split('/\s+/', $line, 2), 2, null);
        $command = strtolower((string) $command);

        match ($command) {
            'help' => $this->print($this->helpText()),
            'dir', 'ls' => $this->print($this->listPages()),
            'cd' => $this->cd($argument),
            'whoami' => $this->print($this->whoamiText()),
            'clear' => $this->clearHistory(),
            'matrix' => $this->switchToMatrix(),
            default => $this->print("Unknown command: '{$command}'. Type 'help'."),
        };
    }

    private function print(string $text): void
    {
        $this->history[] = $text;
    }

    private function clearHistory(): void
    {
        $this->history = [];
    }

    private function helpText(): string
    {
        return implode("\n", [
            'help          - list commands',
            'dir, ls       - list public pages',
            'cd <page>     - navigate to a page',
            'whoami        - show what this site can tell about you',
            'clear         - clear this terminal',
            'matrix        - switch to the Matrix theme',
        ]);
    }

    /**
     * @return list<string>
     */
    private function pages(): array
    {
        $pages = array_keys(self::PAGES);

        if (app(ThemeResolver::class)->active(request())->shows_life_blog) {
            $pages[] = 'life';
        }

        return $pages;
    }

    private function listPages(): string
    {
        return implode('  ', $this->pages());
    }

    private function cd(?string $page): void
    {
        $page = strtolower((string) $page);

        if (! in_array($page, $this->pages(), true)) {
            $this->print("cd: '{$page}' is not a page. Try 'dir'.");

            return;
        }

        $routeName = $page === 'life' ? 'life.index' : (self::PAGES[$page] ?? 'home');

        $this->redirect(route($routeName));
    }

    private function whoamiText(): string
    {
        return 'IP: ' . request()->ip() . ' (full lookup: /whoami)';
    }

    /**
     * Same cookie ThemeController::update() would set for `theme=matrix` — a plain form POST
     * there, a terminal command here, identical effect (domain scoped to the canonical
     * host's registrable domain so www. and the apex share the choice — E3-T7).
     */
    private function switchToMatrix(): void
    {
        $registrable = preg_replace('/^www\./i', '', (string) config('site.canonical_host', 'miautrix.tech'));

        Cookie::queue(cookie(
            name: ResolveTheme::COOKIE_NAME,
            value: 'matrix',
            minutes: 60 * 24 * 365,
            domain: '.' . $registrable,
        ));

        $this->redirect(route('home'));
    }

    public function render()
    {
        return view('livewire.terminal');
    }
}
