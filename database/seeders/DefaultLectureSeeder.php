<?php

namespace Database\Seeders;

use App\Models\Lecture;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultLectureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $creator = User::query()->where('email', User::SUPER_ADMIN_EMAIL)->firstOrFail();

        $lecture = Lecture::query()->firstOrCreate(
            ['title' => 'Cyber Tibay'],
            [
                'creator_id' => $creator->id,
                'description' => 'An Incident Response Perspective Based on DICT NCERT References',
                'visibility' => 'public',
                'is_active' => true,
            ],
        );

        if ($lecture->creator_id === null) {
            $lecture->update(['creator_id' => $creator->id]);
        }
    }
}
