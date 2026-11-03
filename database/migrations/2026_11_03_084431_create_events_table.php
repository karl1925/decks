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
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lecture_id')->constrained('lectures')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('location', 255);
            $table->string('audience', 255);
            $table->date('date');
            $table->string('lecturer', 180);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['lecture_id', 'date', 'archived_at']);
            $table->index(['creator_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
