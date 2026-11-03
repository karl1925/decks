<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(10);
            $table->boolean('randomize_questions')->default(false);
        });

        Schema::table('deck_state', function (Blueprint $table) {
            $table->timestamp('assessment_started_at')->nullable();
            $table->timestamp('assessment_ends_at')->nullable();
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slide_id')->constrained('slides')->cascadeOnDelete();
            $table->unsignedInteger('order_index')->default(0);
            $table->string('type', 32);
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->foreignId('slide_id')->constrained('slides')->cascadeOnDelete();
            $table->json('question_order');
            $table->timestamp('started_at');
            $table->timestamps();
            $table->unique(['attendee_id', 'slide_id']);
        });

        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('assessment_questions');
        Schema::table('deck_state', function (Blueprint $table) {
            $table->dropColumn(['assessment_started_at', 'assessment_ends_at']);
        });
        Schema::table('slides', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'randomize_questions']);
        });
    }
};
