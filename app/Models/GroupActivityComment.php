<?php

namespace App\Models;

use Database\Factories\GroupActivityCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupActivityComment extends Model
{
    /** @use HasFactory<GroupActivityCommentFactory> */
    use HasFactory;

    protected $fillable = ['submission_id', 'attendee_id', 'body', 'status', 'reviewed_by'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GroupActivitySubmission::class, 'submission_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'attendee_id');
    }
}
