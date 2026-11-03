<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_decks', function (Blueprint $table) {
            $table->string('deck_type', 24)->default('content');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
        });

        foreach (DB::table('presentation_sections')->get() as $section) {
            $decks = DB::table('presentation_decks')->where('section_id', $section->id)->get();
            foreach ($decks as $deck) {
                DB::table('presentation_decks')->where('id', $deck->id)->update([
                    'section_id' => null,
                    'deck_type' => $section->section_type,
                    'duration_minutes' => $section->duration_minutes,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('presentation_decks', function (Blueprint $table) {
            $table->dropColumn(['deck_type', 'duration_minutes']);
        });
    }
};
