<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['title']);
        });

        Schema::create('presentation_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('presentation_sections')->cascadeOnDelete();
            $table->string('title', 180);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['section_id', 'title']);
        });

        Schema::create('presentation_sub_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('presentation_topics')->cascadeOnDelete();
            $table->string('title', 180);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['topic_id', 'title']);
        });

        Schema::create('presentation_decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_topic_id')->constrained('presentation_sub_topics')->cascadeOnDelete();
            $table->string('title', 180);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['sub_topic_id', 'title']);
        });

        Schema::table('slides', function (Blueprint $table) {
            $table->foreignId('deck_id')->nullable()->after('id')->constrained('presentation_decks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deck_id');
        });
        Schema::dropIfExists('presentation_decks');
        Schema::dropIfExists('presentation_sub_topics');
        Schema::dropIfExists('presentation_topics');
        Schema::dropIfExists('presentation_sections');
    }
};
