<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('slides')->where('type', 'assessment')->whereNull('instructions')->update([
            'instructions' => 'Read each question carefully and answer every item. Your answers save as you work. You may move between questions while the timer is running. Submit before time runs out; submitted answers cannot be changed.',
        ]);
    }

    public function down(): void
    {
        // Keep the generated guidance if this migration is rolled back.
    }
};
