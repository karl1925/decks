<?php

namespace App\Models;

use App\Scopes\ActiveLectureScope;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'creator_id', 'lecture_id', 'lecturer_id', 'title', 'location', 'audience', 'date', 'starts_at', 'ends_at',
        'lecturer', 'expected_participants', 'group_count', 'allow_pre_registration', 'background_image_path', 'background_opacity', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expected_participants' => 'integer',
            'group_count' => 'integer',
            'allow_pre_registration' => 'boolean',
            'background_opacity' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }

    public function lecturerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(Attendee::class, 'attendee_event')
            ->withPivot(['group_label', 'is_group_leader', 'attended_at'])
            ->withTimestamps()
            ->withoutGlobalScope(ActiveLectureScope::class);
    }
}
