<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // words() over catchPhrase(): the latter isn't in Faker\Generator's phpstan stubs
        // (a real, callable magic method — the Company provider — but not a statically
        // known one), so phpstan flags it as method.notFound despite working at runtime.
        $title = Str::title(fake()->unique()->words(3, true));
        $startedAt = fake()->dateTimeBetween('-4 years', '-1 year');

        return [
            'project_category_id' => null,
            'title' => $title,
            'summary' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'started_at' => $startedAt,
            'ended_at' => fake()->boolean(60) ? fake()->dateTimeBetween($startedAt, 'now') : null,
            'repo_url' => 'https://github.com/example/' . Str::slug($title),
            'live_url' => fake()->boolean(50) ? fake()->url() : null,
            'slug' => Str::slug($title),
            'published' => true,
            'featured' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Mark the project as featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['featured' => true]);
    }
}
