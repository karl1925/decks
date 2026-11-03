<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_topics', function (Blueprint $table) {
            $table->foreignId('chapter_id')->nullable()->constrained('presentation_chapters')->cascadeOnDelete();
            $table->unsignedBigInteger('section_id')->nullable()->change();
        });

        DB::statement('UPDATE presentation_topics SET chapter_id = (SELECT chapter_id FROM presentation_sections WHERE presentation_sections.id = presentation_topics.section_id) WHERE chapter_id IS NULL AND section_id IN (SELECT id FROM presentation_sections WHERE chapter_id IS NOT NULL)');
        DB::table('presentation_topics')->whereNotNull('chapter_id')->update(['section_id' => null]);

        Schema::table('presentation_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chapter_id');
            $table->string('section_type', 24)->default('custom');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
        });

        Schema::table('deck_state', function (Blueprint $table) {
            $table->foreignId('current_chapter_id')->nullable()->constrained('presentation_chapters')->nullOnDelete();
            $table->timestamp('section_timer_started_at')->nullable();
            $table->timestamp('section_timer_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deck_state', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_chapter_id');
            $table->dropColumn(['section_timer_started_at', 'section_timer_ends_at']);
        });
        Schema::table('presentation_sections', function (Blueprint $table) {
            $table->dropColumn(['section_type', 'duration_minutes']);
            $table->foreignId('chapter_id')->nullable()->constrained('presentation_chapters')->nullOnDelete();
        });
        Schema::table('presentation_topics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chapter_id');
        });
    }
};
