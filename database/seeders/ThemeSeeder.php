<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * Phase 2 (E3-T1) — the two baseline theme rows. Deliberately standalone and admin-user-free
 * so it can run on any node with `php artisan db:seed --class=ThemeSeeder` without the
 * `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD` that DatabaseSeeder::seedAdminUser() throws
 * without.
 *
 * `tokens` is `{}` for both: E3 renders `technical`/`matrix` byte-identically to Phase 1;
 * only new special-event theme rows carry token overrides.
 */
class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        Theme::updateOrCreate(
            ['key' => 'technical'],
            [
                'name' => 'Technical',
                'tokens' => [],
                'is_default' => true,
                'enabled' => true,
                'shows_life_blog' => false,
                'sort_order' => 0,
            ],
        );

        Theme::updateOrCreate(
            ['key' => 'matrix'],
            [
                'name' => 'Matrix',
                'tokens' => [],
                'is_default' => false,
                'enabled' => true,
                'shows_life_blog' => true,
                'sort_order' => 1,
            ],
        );
    }
}
