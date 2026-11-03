<?php

namespace App\Models;

use App\Models\Concerns\ScopedToLecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationDeck extends Model
{
    use ScopedToLecture;

    protected $fillable = ['chapter_id', 'section_id', 'topic_id', 'title', 'position', 'deck_type', 'content_type', 'duration_minutes'];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(PresentationChapter::class, 'chapter_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PresentationSection::class, 'section_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(PresentationTopic::class, 'topic_id');
    }

    public function outlinePath(): string
    {
        if ($this->topic) {
            return trim(($this->topic->chapter?->title ?? $this->topic->section?->title ?? '').' / '.$this->topic->title, ' /');
        }
        if ($this->section) {
            return $this->section->title;
        }

        return $this->chapter?->title ?? 'Unassigned outline';
    }

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class, 'deck_id')->orderBy('position')->orderBy('id');
    }

    public function groupActivitySections(): HasMany
    {
        return $this->hasMany(GroupActivitySection::class, 'deck_id')->orderBy('position')->orderBy('id');
    }
}
