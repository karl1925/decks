<?php

namespace Database\Factories;

use App\Models\GroupActivitySection;
use App\Models\PresentationDeck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupActivitySection>
 */
class GroupActivitySectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deck_id' => PresentationDeck::factory(),
            'title' => fake()->sentence(3),
            'input_type' => fake()->randomElement(['text', 'image', 'file']),
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
