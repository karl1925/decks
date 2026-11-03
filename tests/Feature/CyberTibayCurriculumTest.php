<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\Attendee;
use App\Models\Slide;
use Database\Seeders\CybersecurityChaptersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CyberTibayCurriculumTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_page_offers_course_entry_without_collecting_credentials(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Cyber Tibay')
            ->assertSee('Join the live course')
            ->assertSee(route('client.join'), false)
            ->assertDontSee('type="password"', false)
            ->assertDontSee('<form', false);
    }

    public function test_course_seeder_replaces_old_course_and_learner_data_with_the_guide(): void
    {
        $oldAttendee = Attendee::create([
            'year' => '2025',
            'section' => 'A',
            'name' => 'Previous course attendee',
        ]);

        $this->seed(CybersecurityChaptersSeeder::class);

        $this->assertDatabaseCount('presentation_chapters', 4);
        $this->assertDatabaseCount('presentation_topics', 10);
        $this->assertDatabaseCount('presentation_decks', 48);
        $this->assertDatabaseCount('slides', 48);
        $this->assertDatabaseCount('assessment_questions', 80);
        $this->assertDatabaseMissing('attendees', ['id' => $oldAttendee->id]);
        $this->assertDatabaseHas('slides', [
            'id' => 1,
            'title' => 'Cyber Tibay: Building Cyber Resilience',
            'layout_key' => 'cover',
        ]);
        $this->assertSame(44, Slide::where('type', 'information')->whereNotNull('notes')->count());

        foreach ([45, 46, 47, 48] as $assessmentSlideId) {
            $this->assertSame(20, AssessmentQuestion::where('slide_id', $assessmentSlideId)->count());
        }
    }
}
