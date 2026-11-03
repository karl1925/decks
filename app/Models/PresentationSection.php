<?php

namespace App\Models;

use App\Models\Concerns\ScopedToLecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresentationSection extends Model
{
    use ScopedToLecture;

    protected $fillable = ['title', 'section_type', 'duration_minutes', 'position'];

    public function decks(): HasMany
    {
        return $this->hasMany(PresentationDeck::class, 'section_id')->orderBy('position')->orderBy('id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(PresentationTopic::class, 'section_id')->orderBy('position')->orderBy('id');
    }
}
