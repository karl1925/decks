<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deck_state', function (Blueprint $table) {
            $table->foreignId('current_topic_id')->nullable()->after('current_chapter_id')->constrained('presentation_topics')->nullOnDelete();
            $table->foreignId('current_sub_topic_id')->nullable()->after('current_topic_id')->constrained('presentation_sub_topics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deck_state', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_sub_topic_id');
            $table->dropConstrainedForeignId('current_topic_id');
        });
    }
};
