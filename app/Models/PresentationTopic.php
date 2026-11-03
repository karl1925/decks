<?php

namespace App\Models;

use App\Models\Concerns\ScopedToLecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationTopic extends Model
{
    use ScopedToLecture;

    protected $fillable = ['chapter_id', 'section_id', 'title', 'position'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(PresentationSection::class, 'section_id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(PresentationChapter::class, 'chapter_id');
    }

    public function decks(): HasMany
    {
        return $this->hasMany(PresentationDeck::class, 'topic_id')->orderBy('position')->orderBy('id');
    }
}
