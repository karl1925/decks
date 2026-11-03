<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
        });

        DB::table('lectures')->update([
            'description' => DB::raw('(SELECT description FROM projects WHERE projects.id = lectures.project_id)'),
            'creator_id' => DB::raw('(SELECT creator_id FROM projects WHERE projects.id = lectures.project_id)'),
            'archived_at' => DB::raw('(SELECT archived_at FROM projects WHERE projects.id = lectures.project_id)'),
        ]);

        DB::table('projects')->orderBy('id')->get()->each(function (object $project): void {
            if (DB::table('lectures')->where('project_id', $project->id)->exists()) {
                return;
            }

            $slug = Str::slug($project->title) ?: 'lecture';
            if (DB::table('lectures')->where('slug', $slug)->exists()) {
                $slug .= '-'.$project->id;
            }

            DB::table('lectures')->insert([
                'project_id' => $project->id,
                'title' => $project->title,
                'slug' => $slug,
                'description' => $project->description,
                'creator_id' => $project->creator_id,
                'archived_at' => $project->archived_at,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]);
        });

        if (! DB::table('lectures')->where('title', 'Cyber Tibay')->exists()) {
            $projectId = DB::table('projects')->insertGetId([
                'title' => 'Cyber Tibay',
                'description' => 'Building cyber resilience through practical cybersecurity awareness.',
                'author' => 'Cyber Tibay',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('lectures')->insert([
                'project_id' => $projectId,
                'title' => 'Cyber Tibay',
                'slug' => 'cyber-tibay',
                'description' => 'Building cyber resilience through practical cybersecurity awareness.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropUnique('lectures_project_id_unique');
            $table->dropConstrainedForeignId('project_id');
            $table->dropUnique('lectures_slug_unique');
            $table->dropColumn('slug');
        });

        $contextTables = [
            'presentation_chapters',
            'presentation_sections',
            'presentation_topics',
            'presentation_decks',
            'slides',
            'assessment_questions',
            'attendees',
            'assessment_attempts',
            'assessment_answers',
            'deck_state',
        ];

        foreach ($contextTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('lecture_id')->nullable()->constrained('lectures')->cascadeOnDelete();
            });

            $defaultLectureId = DB::table('lectures')->where('title', 'Cyber Tibay')->value('id');
            DB::table($tableName)->whereNull('lecture_id')->update(['lecture_id' => $defaultLectureId]);
        }

        Schema::table('deck_state', function (Blueprint $table): void {
            $table->unique('lecture_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deck_state', function (Blueprint $table): void {
            $table->dropUnique('deck_state_lecture_id_unique');
        });

        foreach ([
            'assessment_answers',
            'assessment_attempts',
            'attendees',
            'assessment_questions',
            'slides',
            'presentation_decks',
            'presentation_topics',
            'presentation_sections',
            'presentation_chapters',
            'deck_state',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('lecture_id');
            });
        }

        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('creator_id');
            $table->dropColumn(['description', 'archived_at']);
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug', 200)->unique();
            $table->unique('project_id');
        });
    }
};
