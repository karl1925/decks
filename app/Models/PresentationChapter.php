<?php

namespace App\Models;

use App\Models\Concerns\ScopedToLecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationChapter extends Model
{
    use ScopedToLecture;

    protected $fillable = ['title', 'position'];

    public function topics(): HasMany
    {
        return $this->hasMany(PresentationTopic::class, 'chapter_id')->orderBy('position')->orderBy('id');
    }

    public function decks(): HasMany
    {
        return $this->hasMany(PresentationDeck::class, 'chapter_id')->orderBy('position')->orderBy('id');
    }
}
