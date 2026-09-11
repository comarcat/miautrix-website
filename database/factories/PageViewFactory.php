<?php

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => fake()->randomElement(['/', 'about', 'projects', 'blog', 'blog/a-post', 'life', 'tools']),
            'referrer_host' => fake()->boolean(50) ? fake()->domainName() : null,
            'country' => fake()->boolean(70) ? fake()->countryCode() : null,
            'device' => fake()->randomElement(['desktop', 'mobile']),
            'channel' => fake()->randomElement(['professional', 'life', 'other']),
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
