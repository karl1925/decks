<?php

namespace Database\Factories;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\GroupActivitySection;
use App\Models\GroupActivitySubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupActivitySubmission>
 */
class GroupActivitySubmissionFactory extends Factory
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
            'section_id' => GroupActivitySection::factory(),
            'group_label' => fake()->randomElement(range('A', 'D')),
            'text_content' => fake()->paragraph(),
            'submitted_by' => Attendee::factory(),
        ];
    }
}
