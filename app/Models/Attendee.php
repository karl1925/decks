<?php

namespace App\Models;

use App\Models\Concerns\ScopedToLecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attendee extends Model
{
    use ScopedToLecture;

    protected $fillable = ['year', 'section', 'name', 'email', 'password', 'google_id', 'facebook_id', 'answer', 'answered_slide_id'];

    protected $hidden = ['password', 'google_id', 'facebook_id'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'attendee_event')
            ->withPivot(['group_label', 'is_group_leader', 'attended_at'])
            ->withTimestamps();
    }
}
