<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;

class CybersecurityChaptersSeeder extends Seeder
{
    private const FIXTURE = 'seeders/data/decks_default.json';

    private ?int $lectureId = null;

    public function run(): void
    {
        $path = database_path(self::FIXTURE);
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The default Decks outline fixture could not be read.');
        }

        try {
            $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The default Decks outline fixture is invalid JSON.', previous: $exception);
        }

        foreach ($fixture['local_image_assets'] ?? [] as $filename) {
            if (! is_file(public_path('deck-images/'.$filename))) {
                throw new RuntimeException("A default Decks image is missing: {$filename}");
            }
        }

        $this->lectureId = (int) DB::table('lectures')->where('title', 'Cyber Tibay')->value('id');

        if (DB::table('slides')->where('lecture_id', $this->lectureId)->exists()) {
            foreach ($fixture['slides'] ?? [] as $slide) {
                if (blank($slide['notes'] ?? null)) {
                    continue;
                }

                DB::table('slides')
                    ->where('lecture_id', $this->lectureId)
                    ->where('title', $slide['title'])
                    ->update(['notes' => $slide['notes'], 'updated_at' => now()]);
            }

            return;
        }

        foreach (['presentation_chapters', 'presentation_sections', 'presentation_topics', 'presentation_decks', 'slides', 'attendees'] as $table) {
            if (DB::table($table)->where('lecture_id', '!=', $this->lectureId)->exists()) {
                throw new RuntimeException('The default Cyber Tibay outline can only be initialized before other lectures have studio content.');
            }
        }

        DB::transaction(function () use ($fixture): void {
            // Remove dependent learner results before replacing their slide/question IDs.
            foreach (['assessment_answers', 'assessment_attempts', 'assessment_questions', 'slides', 'presentation_decks', 'presentation_topics', 'presentation_sections', 'presentation_chapters', 'attendees'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            $this->insertRows('presentation_chapters', $fixture['presentation_chapters'] ?? []);
            $this->insertRows('presentation_sections', $fixture['presentation_sections'] ?? []);
            $this->insertRows('presentation_topics', $fixture['presentation_topics'] ?? []);
            $this->insertRows('presentation_decks', $fixture['presentation_decks'] ?? []);
            $this->insertRows('slides', $fixture['slides'] ?? [], ['options', 'layout_data']);
            $this->insertRows('assessment_questions', $fixture['assessment_questions'] ?? [], ['options', 'correct_answer']);

            $state = [
                'is_live' => false,
                'current_slide_id' => $fixture['start_slide_id'] ?? null,
                'current_chapter_id' => null,
                'current_topic_id' => null,
                'assessment_started_at' => null,
                'assessment_ends_at' => null,
                'section_timer_started_at' => null,
                'section_timer_ends_at' => null,
                'updated_at' => now(),
            ];
            $state = array_filter($state, fn (string $column): bool => Schema::hasColumn('deck_state', $column), ARRAY_FILTER_USE_KEY);
            DB::table('deck_state')->where('id', 1)->update($state);
        });
    }

    private function insertRows(string $table, array $rows, array $jsonColumns = []): void
    {
        if ($rows === [] || ! Schema::hasTable($table)) {
            return;
        }

        $timestamp = now();
        foreach ($rows as &$row) {
            foreach ($jsonColumns as $column) {
                if (array_key_exists($column, $row) && $row[$column] !== null) {
                    $row[$column] = json_encode($row[$column], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            $row['created_at'] = $timestamp;
            $row['updated_at'] = $timestamp;
            if ($this->lectureId && Schema::hasColumn($table, 'lecture_id')) {
                $row['lecture_id'] = $this->lectureId;
            }
        }
        unset($row);

        foreach (array_chunk($rows, 40) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
