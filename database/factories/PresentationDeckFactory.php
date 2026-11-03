<?php

namespace Database\Factories;

use App\Models\Lecture;
use App\Models\PresentationDeck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PresentationDeck>
 */
class PresentationDeckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lecture_id' => Lecture::factory(),
            'title' => fake()->sentence(3),
            'position' => fake()->numberBetween(0, 20),
            'deck_type' => 'group_activity',
            'content_type' => null,
            'duration_minutes' => 30,
        ];
    }
}
