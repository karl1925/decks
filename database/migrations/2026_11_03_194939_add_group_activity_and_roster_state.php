<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendee_event', function (Blueprint $table): void {
            $table->boolean('is_group_leader')->default(false)->after('group_label');
        });

        Schema::table('deck_state', function (Blueprint $table): void {
            $table->string('presented_group_label', 8)->nullable()->after('current_topic_id');
        });

        Schema::create('group_activity_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deck_id')->constrained('presentation_decks')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('input_type', 16);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['deck_id', 'position']);
        });

        Schema::create('group_activity_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('group_activity_sections')->cascadeOnDelete();
            $table->string('group_label', 8);
            $table->text('text_content')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('attendees')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'section_id', 'group_label']);
        });

        Schema::create('group_activity_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('group_activity_submissions')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('attendees')->nullOnDelete();
            $table->timestamps();
            $table->index(['submission_id', 'status']);
        });

        Schema::create('group_activity_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deck_id')->constrained('presentation_decks')->cascadeOnDelete();
            $table->string('group_label', 8);
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'deck_id', 'group_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_activity_grades');
        Schema::dropIfExists('group_activity_comments');
        Schema::dropIfExists('group_activity_submissions');
        Schema::dropIfExists('group_activity_sections');

        Schema::table('deck_state', function (Blueprint $table): void {
            $table->dropColumn('presented_group_label');
        });
        Schema::table('attendee_event', function (Blueprint $table): void {
            $table->dropColumn('is_group_leader');
        });
    }
};
