<?php

namespace Tests\Feature;

use App\Models\PresentationDeck;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PresentedDeckDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_the_presented_deck_advances_or_returns_to_lobby_when_empty(): void
    {
        $this->actingAs(User::factory()->create());

        $currentDeck = PresentationDeck::create(['title' => 'Current', 'deck_type' => 'content', 'position' => 1]);
        $nextDeck = PresentationDeck::create(['title' => 'Next', 'deck_type' => 'content', 'position' => 2]);
        $currentSlide = Slide::create(['deck_id' => $currentDeck->id, 'title' => 'Current slide', 'type' => 'information', 'position' => 1]);
        $nextSlide = Slide::create(['deck_id' => $nextDeck->id, 'title' => 'Next slide', 'type' => 'information', 'position' => 2]);
        DB::table('deck_state')->where('id', 1)->update(['is_live' => true, 'current_slide_id' => $currentSlide->id]);

        $this->deleteJson(route('outline.delete', ['kind' => 'deck', 'id' => $currentDeck->id]))
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseHas('deck_state', ['id' => 1, 'is_live' => true, 'current_slide_id' => $nextSlide->id]);
        $this->assertDatabaseMissing('slides', ['id' => $currentSlide->id]);

        DB::table('deck_state')->where('id', 1)->update(['current_slide_id' => $nextSlide->id]);
        $this->deleteJson(route('outline.delete', ['kind' => 'deck', 'id' => $nextDeck->id]))
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseHas('deck_state', ['id' => 1, 'is_live' => false, 'current_slide_id' => null]);
    }
}
