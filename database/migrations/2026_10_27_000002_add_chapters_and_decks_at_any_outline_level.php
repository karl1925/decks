<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_chapters', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('presentation_sections', function (Blueprint $table) {
            $table->dropUnique(['title']);
            $table->foreignId('chapter_id')->nullable()->constrained('presentation_chapters')->nullOnDelete();
        });

        Schema::table('presentation_decks', function (Blueprint $table) {
            $table->foreignId('chapter_id')->nullable()->constrained('presentation_chapters')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('presentation_sections')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('presentation_topics')->cascadeOnDelete();
            $table->unsignedBigInteger('sub_topic_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('presentation_decks', function (Blueprint $table) {
            $table->dropForeign(['sub_topic_id']);
            $table->dropConstrainedForeignId('topic_id');
            $table->dropConstrainedForeignId('section_id');
            $table->dropConstrainedForeignId('chapter_id');
        });
        Schema::table('presentation_decks', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_topic_id')->nullable(false)->change();
        });
        Schema::table('presentation_decks', function (Blueprint $table) {
            $table->foreign('sub_topic_id')->references('id')->on('presentation_sub_topics')->cascadeOnDelete();
        });
        Schema::table('presentation_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chapter_id');
            $table->unique('title');
        });
        Schema::dropIfExists('presentation_chapters');
    }
};
