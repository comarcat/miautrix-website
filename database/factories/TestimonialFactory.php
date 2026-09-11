<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'organization' => fake()->boolean(70) ? fake()->company() : null,
            'role' => fake()->boolean(70) ? fake()->jobTitle() : null,
            'contact_type' => Testimonial::CONTACT_TYPE_EMAIL,
            'contact_value' => fake()->safeEmail(),
            'body' => fake()->paragraph(4),
            'status' => Testimonial::STATUS_PENDING,
            'approved_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Testimonial::STATUS_PENDING,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Testimonial::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Testimonial::STATUS_REJECTED,
            'approved_at' => null,
        ]);
    }
}
