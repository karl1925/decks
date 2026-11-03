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
        Schema::table('presentation_chapters', function (Blueprint $table): void {
            $table->dropUnique('presentation_chapters_title_unique');
            $table->unique(['lecture_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presentation_chapters', function (Blueprint $table): void {
            $table->dropUnique(['lecture_id', 'title']);
            $table->unique('title');
        });
    }
};
