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
        Schema::table('deck_state', function (Blueprint $table): void {
            $table->string('pause_message', 32)->nullable();
            $table->timestamp('pause_ends_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deck_state', function (Blueprint $table): void {
            $table->dropColumn(['pause_message', 'pause_ends_at']);
        });
    }
};
