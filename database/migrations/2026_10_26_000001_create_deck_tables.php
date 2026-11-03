<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->longText('content')->nullable();
            $table->text('media_url')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->string('year', 40);
            $table->string('section', 80);
            $table->string('name', 160);
            $table->text('answer')->nullable();
            $table->foreignId('answered_slide_id')->nullable()->constrained('slides')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('deck_state', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_live')->default(false);
            $table->unsignedBigInteger('current_slide_id')->nullable();
            $table->timestamps();
        });
        DB::table('deck_state')->insert(['id' => 1, 'is_live' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('deck_state');
        Schema::dropIfExists('attendees');
        Schema::dropIfExists('slides');
    }
};
