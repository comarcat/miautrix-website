<?php

namespace Database\Factories;

use App\Models\Tool;
use App\Models\ToolDownload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolDownload>
 */
class ToolDownloadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_id' => Tool::factory(),
            'ip' => fake()->ipv4(),
            'country' => fake()->boolean(70) ? fake()->countryCode() : null,
            'region' => null,
            'city' => fake()->boolean(50) ? fake()->city() : null,
            'isp' => null,
            'referrer' => fake()->boolean(40) ? fake()->url() : null,
            'user_agent' => fake()->userAgent(),
            'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];
    }
}
