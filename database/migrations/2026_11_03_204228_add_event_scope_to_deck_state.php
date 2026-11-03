<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deck_state', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->after('lecture_id')->constrained()->cascadeOnDelete();
            $table->dropUnique('deck_state_lecture_id_unique');
            $table->unique(['lecture_id', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('deck_state')
            ->whereNotIn('id', DB::table('deck_state')->selectRaw('MAX(id)')->groupBy('lecture_id'))
            ->delete();

        Schema::table('deck_state', function (Blueprint $table): void {
            $table->dropUnique(['lecture_id', 'event_id']);
            $table->dropConstrainedForeignId('event_id');
            $table->unique('lecture_id');
        });
    }
};
