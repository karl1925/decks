<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->json('correct_answer')->nullable();
        });

        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'score', 'max_score']);
        });
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropColumn('correct_answer');
        });
    }
};
