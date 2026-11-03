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
        Schema::table('lectures', function (Blueprint $table): void {
            $table->string('visibility', 20)->default('private');
            $table->boolean('is_active')->default(true);
            $table->index(['visibility', 'is_active', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropIndex('lectures_visibility_is_active_archived_at_index');
            $table->dropColumn(['visibility', 'is_active']);
        });
    }
};
