<?php

namespace Database\Factories;

use App\Models\SocialProfileGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialProfileGroup>
 */
class SocialProfileGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Professional', 'Personal', 'Open Source', 'Speaking']);

        return [
            'name' => $name,
            'heading' => $name . ' profiles',
            'intro_text' => fake()->boolean(60) ? fake()->sentence(12) : null,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
