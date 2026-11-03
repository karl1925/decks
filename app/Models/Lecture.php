<?php

namespace App\Models;

use App\Scopes\ActiveLectureScope;
use Database\Factories\LectureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lecture extends Model
{
    /** @use HasFactory<LectureFactory> */
    use HasFactory;

    protected $fillable = ['title', 'description', 'creator_id', 'archived_at', 'visibility', 'is_active'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(PresentationChapter::class)->withoutGlobalScope(ActiveLectureScope::class)->orderBy('position')->orderBy('id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PresentationSection::class)->withoutGlobalScope(ActiveLectureScope::class)->orderBy('position')->orderBy('id');
    }

    public function decks(): HasMany
    {
        return $this->hasMany(PresentationDeck::class)->withoutGlobalScope(ActiveLectureScope::class)->orderBy('position')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('starts_at')->orderBy('id');
    }
}
