<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->string('password')->nullable();
        });

        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->unsignedInteger('current_position')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropColumn('current_position');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
