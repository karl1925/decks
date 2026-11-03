<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Lecture;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('today', '+6 months');

        return [
            'creator_id' => User::factory(),
            'lecture_id' => Lecture::factory(),
            'title' => fake()->sentence(4),
            'location' => fake()->city(),
            'audience' => fake()->words(3, true),
            'date' => Carbon::instance($startsAt)->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => Carbon::instance($startsAt)->addHours(2),
            'lecturer' => fake()->name(),
            'lecturer_id' => User::factory(),
            'expected_participants' => 40,
            'group_count' => 0,
            'allow_pre_registration' => true,
        ];
    }
}
