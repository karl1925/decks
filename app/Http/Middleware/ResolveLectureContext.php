<?php

namespace App\Http\Middleware;

use App\Models\Lecture;
use App\Support\LectureContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ResolveLectureContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('lectures')) {
            $lectureQuery = Lecture::query()->whereNull('archived_at')->where('is_active', true);
            if ($request->user() && ! $request->user()->isSuperAdmin()) {
                $lectureQuery->where('creator_id', $request->user()->id);
            }

            $lecture = $request->user()
                ? (clone $lectureQuery)->find($request->session()->get('active_lecture_id'))
                : null;

            $eventParameter = $request->route('event');
            $eventId = is_object($eventParameter) ? $eventParameter->getKey() : $eventParameter;
            $eventId ??= $request->input('event_id');
            if ($eventId && Schema::hasTable('events')) {
                $eventLectureId = DB::table('events')
                    ->where('id', $eventId)
                    ->whereNull('archived_at')
                    ->value('lecture_id');
                $eventLecture = $eventLectureId ? (clone $lectureQuery)->find($eventLectureId) : null;

                if ($eventLecture) {
                    $lecture = $eventLecture;
                    $request->session()->put(['active_lecture_id' => $eventLecture->id, 'active_event_id' => (int) $eventId]);
                    app(LectureContext::class)->set($eventLecture);
                }
            }

            $lectureParameter = $request->route('lecture');
            if (! $eventId && $lectureParameter && $request->user()) {
                $requestedLecture = (clone $lectureQuery)->find($lectureParameter);

                if ($requestedLecture) {
                    $lecture = $requestedLecture;
                    $request->session()->put('active_lecture_id', $requestedLecture->id);
                    $request->session()->forget('active_event_id');
                    app(LectureContext::class)->set($requestedLecture);
                }
            }

            if (! $lecture && ! $request->user() && filled($request->session()->get('active_event_id')) && Schema::hasTable('events')) {
                $eventLectureId = DB::table('events')
                    ->where('id', $request->session()->get('active_event_id'))
                    ->whereNull('archived_at')
                    ->value('lecture_id');
                $lecture = $eventLectureId ? (clone $lectureQuery)->find($eventLectureId) : null;
            }

            if (! $lecture && Schema::hasTable('deck_state')) {
                $liveLectureId = DB::table('deck_state')
                    ->join('lectures', 'lectures.id', '=', 'deck_state.lecture_id')
                    ->where('deck_state.is_live', true)
                    ->whereNull('lectures.archived_at')
                    ->orderByDesc('deck_state.updated_at')
                    ->value('deck_state.lecture_id');
                $lecture = $liveLectureId ? (clone $lectureQuery)->find($liveLectureId) : null;
            }

            $lecture ??= (clone $lectureQuery)->orderBy('id')->first();

            if ($lecture) {
                app(LectureContext::class)->set($lecture);
            }
        }

        return $next($request);
    }
}
