<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $subTopics = DB::table('presentation_sub_topics')->orderBy('topic_id')->orderBy('position')->orderBy('id')->get();
            foreach ($subTopics as $subTopic) {
                $maxPosition = (int) DB::table('presentation_decks')->where('topic_id', $subTopic->topic_id)->max('position');
                DB::table('presentation_decks')->where('sub_topic_id', $subTopic->id)->orderBy('position')->orderBy('id')->get()
                    ->each(function ($deck) use ($subTopic, &$maxPosition): void {
                        DB::table('presentation_decks')->where('id', $deck->id)->update([
                            'chapter_id' => null,
                            'section_id' => null,
                            'topic_id' => $subTopic->topic_id,
                            'position' => ++$maxPosition,
                            'updated_at' => now(),
                        ]);
                    });
            }

            if (Schema::hasColumn('deck_state', 'current_sub_topic_id')) {
                $state = DB::table('deck_state')->where('id', 1)->first();
                if ($state?->current_sub_topic_id) {
                    $subTopic = DB::table('presentation_sub_topics')->where('id', $state->current_sub_topic_id)->first();
                    DB::table('deck_state')->where('id', 1)->update([
                        'current_sub_topic_id' => null,
                        'current_topic_id' => $subTopic?->topic_id,
                    ]);
                }
            }

            Schema::table('deck_state', function (Blueprint $table): void {
                if (Schema::hasColumn('deck_state', 'current_sub_topic_id')) {
                    $table->dropConstrainedForeignId('current_sub_topic_id');
                }
            });
            Schema::table('presentation_decks', function (Blueprint $table): void {
                $table->dropUnique('presentation_decks_sub_topic_id_title_unique');
                $table->dropConstrainedForeignId('sub_topic_id');
            });
            Schema::dropIfExists('presentation_sub_topics');
        });
    }

    public function down(): void
    {
        Schema::create('presentation_sub_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_id')->constrained('presentation_topics')->cascadeOnDelete();
            $table->string('title', 180);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::table('presentation_decks', function (Blueprint $table): void {
            $table->foreignId('sub_topic_id')->nullable()->constrained('presentation_sub_topics')->nullOnDelete();
        });
        Schema::table('deck_state', function (Blueprint $table): void {
            $table->foreignId('current_sub_topic_id')->nullable()->constrained('presentation_sub_topics')->nullOnDelete();
        });
    }
};
