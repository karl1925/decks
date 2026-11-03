<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $lectureId = DB::table('lectures')->where('title', 'Cyber Tibay')->value('id');
        $titleDeck = $lectureId
            ? DB::table('presentation_decks')->where('lecture_id', $lectureId)->where('deck_type', 'title')->orderBy('position')->first()
            : null;

        if (! $titleDeck || DB::table('slides')->where('deck_id', $titleDeck->id)->where('title', 'Scan and Log In with Facebook')->exists()) {
            return;
        }

        $titleSlide = DB::table('slides')->where('deck_id', $titleDeck->id)->orderBy('position')->first();
        $position = $titleSlide ? (int) $titleSlide->position + 1 : 0;

        DB::table('slides')->where('lecture_id', $lectureId)->where('position', '>=', $position)->increment('position');
        DB::table('slides')->insert([
            'lecture_id' => $lectureId,
            'deck_id' => $titleDeck->id,
            'title' => 'Scan and Log In with Facebook',
            'type' => 'information',
            'content' => '<ol><li>Scan the QR Code</li><li>Enter your Facebook credentials</li><li>Click Login</li><li>Enjoy!</li></ol>',
            'instructions' => null,
            'media_url' => null,
            'options' => null,
            'position' => $position,
            'duration_minutes' => 10,
            'randomize_questions' => false,
            'notes' => null,
            'layout_key' => 'split',
            'layout_data' => json_encode([
                'image_url' => '/deck-images/facebook-login-qr.png',
                'image_caption' => 'Scan the QR code to get started',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $slide = DB::table('slides')->where('title', 'Scan and Log In with Facebook')->first();
        if (! $slide) {
            return;
        }

        DB::table('slides')->where('lecture_id', $slide->lecture_id)->where('position', '>', $slide->position)->decrement('position');
        DB::table('slides')->where('id', $slide->id)->delete();
    }
};
