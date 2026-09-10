<?php

namespace Database\Factories;

use App\Models\ShareClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShareClick>
 */
class ShareClickFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'network' => fake()->randomElement(['facebook', 'x', 'linkedin', 'whatsapp', 'reddit', 'email']),
            'type' => 'article',
            'subject_id' => fake()->numberBetween(1, 50),
            'referrer' => fake()->boolean(70) ? fake()->url() : null,
            'ip' => fake()->boolean(80) ? fake()->ipv4() : null,
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
