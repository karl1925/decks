<?php

namespace App\Models;

use Database\Factories\GroupActivityGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupActivityGrade extends Model
{
    /** @use HasFactory<GroupActivityGradeFactory> */
    use HasFactory;

    protected $fillable = ['event_id', 'deck_id', 'group_label', 'score', 'max_score', 'feedback', 'graded_by'];

    protected function casts(): array
    {
        return ['score' => 'integer', 'max_score' => 'integer'];
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(PresentationDeck::class, 'deck_id');
    }
}
