<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\GroupActivityGrade;
use App\Models\PresentationDeck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupActivityGrade>
 */
class GroupActivityGradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'deck_id' => PresentationDeck::factory(),
            'group_label' => fake()->randomElement(range('A', 'D')),
            'score' => fake()->numberBetween(0, 100),
            'max_score' => 100,
            'feedback' => fake()->optional()->sentence(),
        ];
    }
}
