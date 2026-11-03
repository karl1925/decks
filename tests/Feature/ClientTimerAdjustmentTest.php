<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\PresentationDeck;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientTimerAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjusted_assessment_timer_is_available_to_both_client_screens(): void
    {
        $this->travelTo(now()->startOfSecond());

        $slide = Slide::create([
            'title' => 'Assessment',
            'type' => 'assessment',
            'duration_minutes' => 10,
            'position' => 1,
        ]);
        $attendee = Attendee::create([
            'year' => '1st year',
            'section' => 'A',
            'name' => 'Test learner',
        ]);
        DB::table('deck_state')->where('id', 1)->update([
            'is_live' => true,
            'current_slide_id' => $slide->id,
            'assessment_started_at' => now(),
            'assessment_ends_at' => now()->addMinutes(10),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('assessment.adjust-timer'), ['remaining_minutes' => 7])
            ->assertRedirect();

        $expectedEnd = (int) now()->addMinutes(7)->valueOf();
        $this->withSession(['attendee_id' => $attendee->id])
            ->getJson(route('client.status'))
            ->assertOk()
            ->assertJsonPath('assessment.ends_at_ms', $expectedEnd);

        $this->getJson(route('client.master.status'))
            ->assertOk()
            ->assertJsonPath('assessment.ends_at_ms', $expectedEnd);

        $this->withSession(['attendee_id' => $attendee->id])
            ->get(route('client.presentation'))
            ->assertOk()
            ->assertSee('id="quiz-timer" data-end="'.$expectedEnd.'"', false);

        $this->get(route('client.master'))
            ->assertOk()
            ->assertSee('id="master-clock" data-end="'.$expectedEnd.'"', false);
    }

    public function test_adjusted_special_deck_timer_is_available_to_both_client_screens(): void
    {
        $this->travelTo(now()->startOfSecond());

        $deck = PresentationDeck::create([
            'title' => 'Break',
            'deck_type' => 'break',
            'duration_minutes' => 20,
            'position' => 1,
        ]);
        $slide = Slide::create([
            'deck_id' => $deck->id,
            'title' => 'Break slide',
            'type' => 'information',
            'position' => 1,
        ]);
        $attendee = Attendee::create([
            'year' => '1st year',
            'section' => 'A',
            'name' => 'Test learner',
        ]);
        DB::table('deck_state')->where('id', 1)->update([
            'is_live' => true,
            'current_slide_id' => $slide->id,
            'section_timer_started_at' => now(),
            'section_timer_ends_at' => now()->addMinutes(20),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('special-deck.timer.adjust', $deck), ['remaining_minutes' => 8])
            ->assertRedirect();

        $expectedEnd = (int) now()->addMinutes(8)->valueOf();
        $this->withSession(['attendee_id' => $attendee->id])
            ->getJson(route('client.status'))
            ->assertOk()
            ->assertJsonPath('section_timer.ends_at_ms', $expectedEnd);

        $this->getJson(route('client.master.status'))
            ->assertOk()
            ->assertJsonPath('section_timer.ends_at_ms', $expectedEnd);

        $this->withSession(['attendee_id' => $attendee->id])
            ->get(route('client.presentation'))
            ->assertOk()
            ->assertSee('id="student-section-clock" data-end="'.$expectedEnd.'"', false);

        $this->get(route('client.master'))
            ->assertOk()
            ->assertSee('id="master-section-clock" data-end="'.$expectedEnd.'"', false);
    }
}
