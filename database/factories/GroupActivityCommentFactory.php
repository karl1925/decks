<?php

namespace Database\Factories;

use App\Models\Attendee;
use App\Models\GroupActivityComment;
use App\Models\GroupActivitySubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupActivityComment>
 */
class GroupActivityCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => GroupActivitySubmission::factory(),
            'attendee_id' => Attendee::factory(),
            'body' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
