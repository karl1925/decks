<?php

namespace App\Models;

use Database\Factories\GroupActivitySectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupActivitySection extends Model
{
    /** @use HasFactory<GroupActivitySectionFactory> */
    use HasFactory;

    protected $fillable = ['deck_id', 'title', 'input_type', 'position'];

    public function deck(): BelongsTo
    {
        return $this->belongsTo(PresentationDeck::class, 'deck_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(GroupActivitySubmission::class, 'section_id');
    }
}
