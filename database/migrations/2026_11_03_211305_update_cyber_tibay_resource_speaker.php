<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->updateSpeakerMetadata([
            'speaker_label' => 'RESOURCE SPEAKER',
            'speaker_name' => 'Karl Steven A. Maddela, MIT',
            'speaker_role' => 'Project Development Officer III',
            'speaker_org' => 'DICT Regional Office 02',
            'speaker_region' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->updateSpeakerMetadata([
            'speaker_label' => 'COURSE AUDIENCE',
            'speaker_name' => "Saint Mary's University College Students",
            'speaker_role' => 'Target audience',
            'speaker_org' => 'DICT NCERT incident handling references',
            'speaker_region' => null,
        ], [
            'speaker_name' => 'Karl Steven A. Maddela, MIT',
            'speaker_role' => 'Project Development Officer III',
            'speaker_org' => 'DICT Regional Office 02',
        ]);
    }

    /**
     * @param  array<string, string|null>  $metadata
     * @param  array<string, string|null>|null  $expectedMetadata
     */
    private function updateSpeakerMetadata(array $metadata, ?array $expectedMetadata = null): void
    {
        $slides = DB::table('slides')
            ->where('title', 'Cyber Tibay: Building Cyber Resilience')
            ->where('layout_key', 'cover')
            ->get(['id', 'layout_data']);

        foreach ($slides as $slide) {
            try {
                $layoutData = json_decode($slide->layout_data ?? '{}', true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Could not decode layout data for slide {$slide->id}.", previous: $exception);
            }

            if (! is_array($layoutData)) {
                continue;
            }

            if ($expectedMetadata !== null) {
                foreach ($expectedMetadata as $key => $value) {
                    if (($layoutData[$key] ?? null) !== $value) {
                        continue 2;
                    }
                }
            }

            $updatedAt = now();
            DB::table('slides')->where('id', $slide->id)->update([
                'layout_data' => json_encode(array_merge($layoutData, $metadata), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $updatedAt,
            ]);
        }
    }
};
