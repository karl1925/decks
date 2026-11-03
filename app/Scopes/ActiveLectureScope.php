<?php

namespace App\Scopes;

use App\Support\LectureContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ActiveLectureScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $lectureId = app(LectureContext::class)->id();

        if ($lectureId !== null) {
            $builder->where($model->qualifyColumn('lecture_id'), $lectureId);
        }
    }
}
