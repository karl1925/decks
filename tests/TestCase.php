<?php

namespace Tests;

use App\Models\Lecture;
use App\Support\LectureContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('lectures')) {
            return;
        }

        $lecture = Lecture::query()->where('title', 'Cyber Tibay')->first();

        if ($lecture) {
            app(LectureContext::class)->set($lecture);
        }
    }
}
