<?php

namespace App\Support;

use App\Models\Lecture;
use Illuminate\Http\Request;

class LectureContext
{
    public function __construct(private Request $request) {}

    public function id(): ?int
    {
        $lectureId = $this->request->attributes->get('active_lecture_id')
            ?? ($this->request->hasSession() ? $this->request->session()->get('active_lecture_id') : null);

        return $lectureId ? (int) $lectureId : null;
    }

    public function set(Lecture $lecture): void
    {
        $this->request->attributes->set('active_lecture_id', $lecture->id);
        if ($this->request->hasSession()) {
            $this->request->session()->put('active_lecture_id', $lecture->id);
        }
    }

    public function clear(): void
    {
        $this->request->attributes->remove('active_lecture_id');
        if ($this->request->hasSession()) {
            $this->request->session()->forget('active_lecture_id');
        }
    }
}
