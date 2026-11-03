<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Lecture;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lecture = Lecture::query()->where('is_active', true)->whereNull('archived_at')->orderBy('id')->first();

        if (! $lecture) {
            return;
        }

        Event::factory()->count(3)->create([
            'lecture_id' => $lecture->id,
            'creator_id' => $lecture->creator_id,
        ]);
    }
}
