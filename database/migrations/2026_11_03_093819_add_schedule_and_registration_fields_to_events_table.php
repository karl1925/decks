<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dateTime('starts_at')->nullable()->after('date');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->foreignId('lecturer_id')->nullable()->after('lecturer')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('expected_participants')->default(0)->after('lecturer_id');
            $table->unsignedTinyInteger('group_count')->default(0)->after('expected_participants');
            $table->boolean('allow_pre_registration')->default(false)->after('group_count');
            $table->index(['starts_at', 'ends_at', 'archived_at']);
        });

        DB::table('events')->select(['id', 'date', 'lecturer'])->orderBy('id')->chunkById(200, function ($events): void {
            foreach ($events as $event) {
                $lecturerId = DB::table('users')
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $event->lecturer))])
                    ->value('id');

                DB::table('events')->where('id', $event->id)->update([
                    'starts_at' => Carbon::parse($event->date)->startOfDay()->toDateTimeString(),
                    'ends_at' => Carbon::parse($event->date)->endOfDay()->toDateTimeString(),
                    'lecturer_id' => $lecturerId,
                ]);
            }
        });

        Schema::create('attendee_event', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained()->cascadeOnDelete();
            $table->string('group_label', 8)->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();
            $table->unique(['attendee_id', 'event_id']);
            $table->index(['event_id', 'group_label']);
        });

        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_scope_id')->default(0)->after('slide_id');
            $table->foreignId('event_id')->nullable()->after('event_scope_id')->constrained()->cascadeOnDelete();
            $table->dropUnique('assessment_attempts_attendee_id_slide_id_unique');
            $table->unique(['attendee_id', 'slide_id', 'event_scope_id']);
            $table->index(['event_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropUnique(['attendee_id', 'slide_id', 'event_scope_id']);
            $table->dropIndex(['event_id', 'attendee_id']);
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn('event_scope_id');
        });

        Schema::dropIfExists('attendee_event');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['starts_at', 'ends_at', 'archived_at']);
            $table->dropConstrainedForeignId('lecturer_id');
            $table->dropColumn(['starts_at', 'ends_at', 'expected_participants', 'group_count', 'allow_pre_registration']);
        });
    }
};
