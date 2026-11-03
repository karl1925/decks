<?php

namespace App\Models;

use Database\Factories\GroupActivitySubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupActivitySubmission extends Model
{
    /** @use HasFactory<GroupActivitySubmissionFactory> */
    use HasFactory;

    protected $fillable = ['event_id', 'section_id', 'group_label', 'text_content', 'file_path', 'file_name', 'submitted_by'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(GroupActivitySection::class, 'section_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(GroupActivityComment::class, 'submission_id')->orderBy('created_at')->orderBy('id');
    }
}
