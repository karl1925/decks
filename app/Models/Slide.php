<?php

namespace App\Models;

use App\Models\Concerns\ScopedToLecture;
use Illuminate\Database\Eloquent\Model;

class Slide extends Model
{
    use ScopedToLecture;

    protected $fillable = ['deck_id', 'title', 'type', 'content', 'instructions', 'media_url', 'options', 'position', 'duration_minutes', 'randomize_questions', 'notes', 'layout_key', 'layout_data'];

    public function presentationDeck()
    {
        return $this->belongsTo(PresentationDeck::class, 'deck_id');
    }

    protected function casts(): array
    {
        return ['options' => 'array', 'layout_data' => 'array', 'randomize_questions' => 'boolean'];
    }
}
