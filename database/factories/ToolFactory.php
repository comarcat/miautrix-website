<?php

namespace Database\Factories;

use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(12),
            'description' => '<p>' . implode('</p><p>', fake()->paragraphs(3)) . '</p>',
            'version' => 'v' . fake()->numberBetween(0, 3) . '.' . fake()->numberBetween(0, 9) . '.' . fake()->numberBetween(0, 9),
            'published' => true,
            'repo_url' => 'https://github.com/example/' . Str::slug($title),
            'sort_order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['published' => false]);
    }
}
