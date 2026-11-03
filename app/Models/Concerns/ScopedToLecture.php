<?php

namespace App\Models\Concerns;

use App\Scopes\ActiveLectureScope;
use App\Support\LectureContext;
use Illuminate\Database\Eloquent\Model;

trait ScopedToLecture
{
    protected static function bootScopedToLecture(): void
    {
        static::addGlobalScope(new ActiveLectureScope);

        static::creating(function (Model $model): void {
            $model->setAttribute('lecture_id', app(LectureContext::class)->id());
        });
    }
}
