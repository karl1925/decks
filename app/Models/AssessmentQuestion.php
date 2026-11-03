<?php

namespace App\Models;

use App\Models\Concerns\ScopedToLecture;
use Illuminate\Database\Eloquent\Model;

class AssessmentQuestion extends Model
{
    use ScopedToLecture;

    protected $fillable = ['slide_id', 'order_index', 'type', 'prompt', 'options', 'correct_answer'];

    protected function casts(): array
    {
        return ['options' => 'array', 'correct_answer' => 'array'];
    }
}
