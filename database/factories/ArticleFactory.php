<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'title' => rtrim($title, '.'),
            'excerpt' => fake()->sentence(20),
            'body' => '<p>' . implode('</p><p>', fake()->paragraphs(5)) . '</p>',
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'featured' => false,
            'slug' => Str::slug($title),
            'published' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A draft article — published_at null, not yet public.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
            'published' => false,
        ]);
    }
}
