<?php

namespace App\Http\Controllers;

use App\Models\AssessmentQuestion;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Lecture;
use App\Models\PresentationChapter;
use App\Models\PresentationDeck;
use App\Models\PresentationSection;
use App\Models\PresentationTopic;
use App\Models\Slide;
use App\Scopes\ActiveLectureScope;
use App\Support\DeckHtml;
use App\Support\LectureContext;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class DeckController extends Controller
{
    private function state(): object
    {
        $lectureId = app(LectureContext::class)->id();
        $query = DB::table('deck_state');
        if ($lectureId !== null) {
            $query->where('lecture_id', $lectureId);
            $eventId = $this->currentEventId();
            $eventId === null ? $query->whereNull('event_id') : $query->where('event_id', $eventId);
            if (! $query->exists()) {
                DB::table('deck_state')->insertOrIgnore([
                    'lecture_id' => $lectureId,
                    'event_id' => $eventId,
                    'is_live' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $state = $query->first();
        } else {
            $state = DB::table('deck_state')->where('id', 1)->first();
        }
        if ($state->pause_ends_at && now()->greaterThanOrEqualTo($state->pause_ends_at)) {
            $this->updateDeckState([
                'is_live' => true,
                'pause_message' => null,
                'pause_ends_at' => null,
                'updated_at' => now(),
            ]);
            $state->is_live = true;
            $state->pause_message = null;
            $state->pause_ends_at = null;
        }
        // Older local database files can remain connected to the dev server after
        // the outline migration was applied from another process.
        $state->current_topic_id ??= null;
        $state->presented_group_label ??= null;

        return $state;
    }

    private function updateDeckState(array $values): void
    {
        $values = array_filter($values, fn ($value, $column) => $column !== 'current_topic_id'
            || Schema::hasColumn('deck_state', $column),
            ARRAY_FILTER_USE_BOTH,
        );
        $query = DB::table('deck_state');
        $lectureId = app(LectureContext::class)->id();
        if ($lectureId) {
            $query->where('lecture_id', $lectureId);
            $eventId = $this->currentEventId();
            $eventId === null ? $query->whereNull('event_id') : $query->where('event_id', $eventId);
        } else {
            $query->where('id', 1);
        }
        $query->update($values);
    }

    private function outlineUrl(array $parameters = []): string
    {
        $lectureId = app(LectureContext::class)->id();
        $eventId = $this->currentEventId();

        return $eventId
            ? route('events.lecture', ['event' => $eventId, 'lecture' => $lectureId] + $parameters)
            : route('admin.lecture', ['lecture' => $lectureId] + $parameters);
    }

    private function presenterUrl(): string
    {
        $eventId = $this->currentEventId();

        return $eventId ? route('events.presenter.view', $eventId) : route('admin.presenter');
    }

    private function payload(): array
    {
        $state = $this->state();
        $slides = Slide::with('presentationDeck.groupActivitySections')->orderBy('position')->orderBy('id')->get();
        $current = $state->presented_group_label ? null : $slides->firstWhere('id', $state->current_slide_id);

        return [
            'live' => (bool) $state->is_live,
            'slides' => $slides,
            'current' => $current,
            'current_version' => $current?->updated_at?->valueOf() ?? 0,
            'breadcrumbs' => $this->slideBreadcrumbs($current),
            'count' => $slides->count(),
            'state' => $state,
            'presented_group' => $this->groupPresentationPayload($state->presented_group_label),
            'pause_message' => match ($state->pause_message) {
                'health_break' => 'Health break',
                'lunch_break' => 'Lunch break',
                'snacks' => 'Snacks',
                'default' => 'Session paused',
                default => null,
            },
            'pause_ends_at' => $state->pause_ends_at ? Carbon::parse($state->pause_ends_at)->valueOf() : null,
            'leaderboard' => $this->leaderboardPayload($current),
        ];
    }

    private function leaderboardPayload(?Slide $slide): ?array
    {
        $lectureId = app(LectureContext::class)->id();
        $layout = $slide?->layout_data ?? [];
        $type = $layout['leaderboard_type'] ?? null;
        if ($slide?->layout_key !== 'leaderboard' || ! in_array($type, ['assessment', 'overall'], true)) {
            return null;
        }

        if ($type === 'assessment') {
            $attempts = DB::table('assessment_attempts as attempts')
                ->join('attendees', 'attendees.id', '=', 'attempts.attendee_id')
                ->when($lectureId, fn ($query) => $query->where('attempts.lecture_id', $lectureId))
                ->when($this->currentEventId(), fn ($query, int $eventId) => $query->where('attempts.event_id', $eventId))
                ->where('attempts.slide_id', $layout['assessment_slide_id'] ?? 0)
                ->whereNotNull('attempts.submitted_at')
                ->whereNotNull('attempts.score')
                ->where('attempts.max_score', '>', 0)
                ->groupBy('attendees.id', 'attendees.name')
                ->get([
                    'attendees.name',
                    DB::raw('MAX(attempts.score) as score'),
                    DB::raw('MAX(attempts.max_score) as max_score'),
                ]);
            $entries = $attempts->map(fn ($attempt) => [
                'name' => $attempt->name,
                'score' => (int) $attempt->score,
                'max_score' => (int) $attempt->max_score,
                'percent' => round($attempt->score / $attempt->max_score * 100, 4),
            ]);
        } else {
            $attempts = DB::table('assessment_attempts as attempts')
                ->join('attendees', 'attendees.id', '=', 'attempts.attendee_id')
                ->when($lectureId, fn ($query) => $query->where('attempts.lecture_id', $lectureId))
                ->when($this->currentEventId(), fn ($query, int $eventId) => $query->where('attempts.event_id', $eventId))
                ->whereNotNull('attempts.submitted_at')
                ->whereNotNull('attempts.score')
                ->where('attempts.max_score', '>', 0)
                ->groupBy('attendees.id', 'attendees.name')
                ->get([
                    'attendees.id as attendee_id',
                    'attendees.name',
                    DB::raw('SUM(attempts.score) as score'),
                    DB::raw('SUM(attempts.max_score) as max_score'),
                    DB::raw('COUNT(attempts.id) as assessment_count'),
                ]);
            $entriesByAttendee = $attempts->mapWithKeys(fn (object $attempt): array => [$attempt->attendee_id => [
                'name' => $attempt->name,
                'score' => (int) $attempt->score,
                'max_score' => (int) $attempt->max_score,
                'percent' => round($attempt->score / $attempt->max_score * 100, 4),
                'assessment_count' => (int) $attempt->assessment_count,
            ]]);
            $groupGrades = $this->groupGradeTotals($lectureId, $this->currentEventId());
            foreach ($groupGrades as $groupGrade) {
                $entry = $entriesByAttendee->get($groupGrade->attendee_id, [
                    'name' => $groupGrade->name,
                    'score' => 0,
                    'max_score' => 0,
                    'percent' => 0,
                    'assessment_count' => 0,
                ]);
                $entry['score'] += (int) $groupGrade->score;
                $entry['max_score'] += (int) $groupGrade->max_score;
                $entry['percent'] = $entry['max_score'] > 0
                    ? round($entry['score'] / $entry['max_score'] * 100, 4)
                    : 0;
                $entriesByAttendee->put($groupGrade->attendee_id, $entry);
            }
            $entries = $entriesByAttendee->values();
        }

        $ranked = $this->rankLeaderboardEntries($entries);
        $versionQuery = DB::table('assessment_attempts')->whereNotNull('submitted_at');
        if ($lectureId !== null) {
            $versionQuery->where('lecture_id', $lectureId);
        }
        if ($type === 'assessment') {
            $versionQuery->where('slide_id', $layout['assessment_slide_id'] ?? 0);
        }
        $lastSubmission = $versionQuery->max('updated_at');
        if ($type === 'overall') {
            $lastSubmission = max(
                (string) ($lastSubmission ?? ''),
                (string) ($this->groupGradeTotals($lectureId, $this->currentEventId())->max('updated_at') ?? ''),
            );
        }

        return ['type' => $type, 'title' => $slide->title, 'entries' => $ranked, 'version' => $lastSubmission ? Carbon::parse($lastSubmission)->valueOf() : 0];
    }

    private function groupGradeTotals(?int $lectureId, ?int $eventId): Collection
    {
        return DB::table('group_activity_grades as grades')
            ->join('events', 'events.id', '=', 'grades.event_id')
            ->join('attendee_event as registrations', function (JoinClause $join): void {
                $join->on('registrations.event_id', '=', 'grades.event_id')
                    ->on('registrations.group_label', '=', 'grades.group_label');
            })
            ->join('attendees', 'attendees.id', '=', 'registrations.attendee_id')
            ->when($lectureId, fn ($query) => $query->where('events.lecture_id', $lectureId))
            ->when($eventId, fn ($query) => $query->where('grades.event_id', $eventId))
            ->groupBy('attendees.id', 'attendees.name')
            ->get([
                'attendees.id as attendee_id',
                'attendees.name',
                DB::raw('SUM(grades.score) as score'),
                DB::raw('SUM(grades.max_score) as max_score'),
                DB::raw('MAX(grades.updated_at) as updated_at'),
            ]);
    }

    /**
     * @param  Collection<int, array{name: string, score: int, max_score: int, percent: float}>  $entries
     * @return array<int, array{rank: int, name: string, score: int, max_score: int, percent: float}>
     */
    private function rankLeaderboardEntries(Collection $entries): array
    {
        $entries = $entries->sort(function (array $left, array $right): int {
            return ($right['percent'] <=> $left['percent']) ?: strcasecmp($left['name'], $right['name']);
        })->values();
        $lastMetric = null;
        $rank = 0;
        $ranked = [];
        foreach ($entries as $index => $entry) {
            $metric = $entry['percent'];
            if ($lastMetric === null || $metric !== $lastMetric) {
                $rank = $index + 1;
                $lastMetric = $metric;
            }
            if ($rank > 3) {
                break;
            }
            $ranked[] = ['rank' => $rank] + $entry;
        }

        return $ranked;
    }

    private function slideBreadcrumbs(?Slide $slide): array
    {
        $deck = $slide?->presentationDeck;
        if (! $deck) {
            return [];
        }
        $topic = $deck->topic;
        $labels = [];
        $chapter = $deck->chapter ?? $topic?->chapter;
        $section = $deck->section ?? $topic?->section;
        if ($chapter) {
            $labels[] = $chapter->title;
        } elseif ($section) {
            $labels[] = $section->title;
        }
        if ($topic) {
            $labels[] = $topic->title;
        }

        return array_values(array_unique($labels));
    }

    private function chapterPresentationPayload(?int $chapterId): ?array
    {
        if (! $chapterId) {
            return null;
        }
        $chapter = PresentationChapter::with(['decks.slides', 'topics.decks.slides'])->find($chapterId);
        if (! $chapter) {
            return null;
        }
        $mapDecks = fn ($decks) => $decks->map(fn ($deck) => ['id' => $deck->id, 'title' => $deck->title, 'slide_count' => $deck->slides->count()])->values()->all();
        $timestamps = collect([$chapter->updated_at])
            ->merge($chapter->decks->pluck('updated_at'))
            ->merge($chapter->decks->flatMap->slides->pluck('updated_at'))
            ->merge($chapter->topics->pluck('updated_at'))
            ->merge($chapter->topics->flatMap->decks->pluck('updated_at'))
            ->merge($chapter->topics->flatMap->decks->flatMap->slides->pluck('updated_at'))
            ->filter();

        return [
            'id' => $chapter->id,
            'title' => $chapter->title,
            'version' => $timestamps->map(fn ($timestamp) => $timestamp->valueOf())->max() ?? 0,
            'decks' => $mapDecks($chapter->decks),
            'topics' => $chapter->topics->map(fn ($topic) => [
                'title' => $topic->title,
                'decks' => $mapDecks($topic->decks),
            ])->all(),
        ];
    }

    private function topicPresentationPayload(?int $topicId): ?array
    {
        if (! $topicId) {
            return null;
        }
        $topic = PresentationTopic::with(['chapter', 'decks.slides'])->find($topicId);
        if (! $topic) {
            return null;
        }
        $decks = fn ($items) => $items->map(fn ($deck) => ['id' => $deck->id, 'title' => $deck->title, 'slide_count' => $deck->slides->count()])->values()->all();
        $timestamps = collect([$topic->updated_at, $topic->chapter?->updated_at])
            ->merge($topic->decks->pluck('updated_at'))
            ->merge($topic->decks->flatMap->slides->pluck('updated_at'))
            ->filter();

        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'chapter_title' => $topic->chapter?->title,
            'version' => $timestamps->map(fn ($timestamp) => $timestamp->valueOf())->max() ?? 0,
            'decks' => $decks($topic->decks),
        ];
    }

    /** @return array<int, array{key:string, chapter_id:?int, topic_id:?int, slide_id:?int}> */
    private function presentationNavigationItems(): array
    {
        $chapters = PresentationChapter::with(['decks.slides', 'topics.decks.slides'])
            ->orderBy('position')->orderBy('id')->get();
        $rootDecks = PresentationDeck::with('slides')->whereNull('chapter_id')->whereNull('section_id')
            ->whereNull('topic_id')->where('deck_type', '!=', 'content')
            ->orderBy('position')->orderBy('id')->get();
        $roots = $chapters->concat($rootDecks)->sort(function ($left, $right): int {
            $leftTitle = $left instanceof PresentationDeck && $left->deck_type === 'title';
            $rightTitle = $right instanceof PresentationDeck && $right->deck_type === 'title';

            return ($rightTitle <=> $leftTitle) ?: (($left->position <=> $right->position) ?: ($left->id <=> $right->id));
        });
        $items = [];
        $activeEvent = $this->currentEventId() ? Event::find($this->currentEventId()) : null;
        $groupLabels = $activeEvent && $activeEvent->group_count > 0
            ? range('A', chr(ord('A') + $activeEvent->group_count - 1))
            : [];
        $appendDeck = function (PresentationDeck $deck) use (&$items): void {
            $slideId = $deck->slides->first()?->id;
            if ($slideId) {
                $items[] = ['key' => 'deck:'.$deck->id, 'chapter_id' => null, 'topic_id' => null, 'slide_id' => (int) $slideId];
            }
        };
        foreach ($roots as $root) {
            if ($root instanceof PresentationDeck) {
                $appendDeck($root);
                if ($root->deck_type === 'title') {
                    foreach ($groupLabels as $groupLabel) {
                        $items[] = [
                            'key' => 'group:'.$groupLabel,
                            'chapter_id' => null,
                            'topic_id' => null,
                            'slide_id' => null,
                            'group_label' => $groupLabel,
                        ];
                    }
                }

                continue;
            }
            $items[] = ['key' => 'chapter:'.$root->id, 'chapter_id' => (int) $root->id, 'topic_id' => null, 'slide_id' => null];
            foreach ($root->decks as $deck) {
                $appendDeck($deck);
            }
            foreach ($root->topics as $topic) {
                $items[] = ['key' => 'topic:'.$topic->id, 'chapter_id' => null, 'topic_id' => (int) $topic->id, 'slide_id' => null];
                foreach ($topic->decks as $deck) {
                    $appendDeck($deck);
                }
            }
        }

        return $items;
    }

    private function groupPresentationPayload(?string $groupLabel): ?array
    {
        $eventId = $this->currentEventId();
        if (! $groupLabel || ! $eventId) {
            return null;
        }

        $members = DB::table('attendee_event as registrations')
            ->join('attendees', 'attendees.id', '=', 'registrations.attendee_id')
            ->where('registrations.event_id', $eventId)
            ->where('registrations.group_label', $groupLabel)
            ->orderBy('attendees.name')
            ->get(['attendees.id', 'attendees.name', 'registrations.is_group_leader']);
        $version = DB::table('attendee_event')->where('event_id', $eventId)->where('group_label', $groupLabel)->max('updated_at');

        return [
            'label' => $groupLabel,
            'members' => $members,
            'version' => $version ? Carbon::parse($version)->valueOf() : 0,
        ];
    }

    private function eventGroupRoster(): ?array
    {
        $eventId = $this->currentEventId();
        $event = $eventId ? Event::find($eventId) : null;
        if (! $event || $event->group_count < 1) {
            return null;
        }

        $registrations = DB::table('attendee_event as registrations')
            ->join('attendees', 'attendees.id', '=', 'registrations.attendee_id')
            ->where('registrations.event_id', $event->id)
            ->orderBy('attendees.name')
            ->get(['attendees.id', 'attendees.name', 'registrations.group_label', 'registrations.is_group_leader']);

        return [
            'event' => $event,
            'groups' => collect(range('A', chr(ord('A') + $event->group_count - 1)))
                ->map(fn (string $label): array => [
                    'label' => $label,
                    'members' => $registrations->where('group_label', $label)->values(),
                ])->all(),
        ];
    }

    public function presentChapter(PresentationChapter $chapter)
    {
        $this->updateDeckState([
            'is_live' => true,
            'current_slide_id' => null,
            'current_chapter_id' => $chapter->id,
            'current_topic_id' => null,
            'presented_group_label' => null,
            'assessment_started_at' => null,
            'assessment_ends_at' => null,
            'section_timer_started_at' => null,
            'section_timer_ends_at' => null,
            'updated_at' => now(),
        ]);

        return redirect($this->presenterUrl())->with('message', 'Chapter outline is now on the client screens.');
    }

    public function presentTopic(PresentationTopic $topic)
    {
        $this->updateDeckState([
            'is_live' => true, 'current_slide_id' => null, 'current_chapter_id' => null,
            'current_topic_id' => $topic->id,
            'presented_group_label' => null,
            'assessment_started_at' => null, 'assessment_ends_at' => null,
            'section_timer_started_at' => null, 'section_timer_ends_at' => null, 'updated_at' => now(),
        ]);

        return redirect($this->presenterUrl())->with('message', 'Topic outline is now on the client screens.');
    }

    public function admin(Request $request)
    {
        $payload = $this->payload();
        $chapters = PresentationChapter::with(['decks.slides', 'topics.decks.slides'])->orderBy('position')->orderBy('id')->get();
        $rootDecks = PresentationDeck::with('slides')->whereNull('chapter_id')->whereNull('section_id')->whereNull('topic_id')->orderBy('position')->orderBy('id')->get();
        $outlineRoots = $chapters->concat($rootDecks)->sort(function ($left, $right): int {
            $leftIsTitle = $left instanceof PresentationDeck && $left->deck_type === 'title';
            $rightIsTitle = $right instanceof PresentationDeck && $right->deck_type === 'title';

            return ($rightIsTitle <=> $leftIsTitle) ?: (($left->position <=> $right->position) ?: ($left->id <=> $right->id));
        })->values();
        $selectedChapter = $request->integer('chapter') ? $chapters->firstWhere('id', $request->integer('chapter')) : null;

        return view('admin', $payload + [
            'presenterMode' => false,
            'eventId' => $this->currentEventId(),
            'eventGroupRoster' => null,
            'attendees' => Attendee::latest()->get(),
            'answers' => DB::table('assessment_answers')->where('lecture_id', app(LectureContext::class)->id())->whereNotNull('answer')->count(),
            'questions' => AssessmentQuestion::orderBy('order_index')->get()->groupBy('slide_id'),
            'chapters' => $chapters,
            'rootSections' => collect(),
            'rootDecks' => $rootDecks,
            'outlineRoots' => $outlineRoots,
            'selectedChapter' => $selectedChapter,
            'selectedSection' => null,
            'currentSection' => null,
            'sectionTimerActive' => false,
            'decks' => PresentationDeck::with(['chapter', 'section', 'topic.section', 'topic.chapter', 'groupActivitySections'])->orderBy('id')->get(),
        ]);
    }

    public function adminLecture(Request $request, Lecture $lecture, LectureContext $lectureContext): View
    {
        abort_unless((int) $lecture->creator_id === (int) $request->user()->id, 403);
        abort_if($lecture->archived_at || ! $lecture->is_active, 404);

        $lectureContext->set($lecture);
        $request->session()->put('active_lecture_id', $lecture->id);
        $request->session()->forget('active_event_id');

        return $this->admin($request);
    }

    public function eventLecture(Request $request, Event $event, Lecture $lecture, LectureContext $lectureContext): View
    {
        abort_unless((int) $event->lecture_id === (int) $lecture->id, 404);
        abort_if($event->archived_at || $lecture->archived_at || ! $lecture->is_active, 404);
        abort_unless($request->user()->isSuperAdmin() || (int) $lecture->creator_id === (int) $request->user()->id, 403);

        $lectureContext->set($lecture);
        $request->session()->put(['active_lecture_id' => $lecture->id, 'active_event_id' => $event->id]);
        $request->attributes->set('active_event_id', $event->id);

        return $this->admin($request);
    }

    public function presenter(Request $request)
    {
        if (! $request->route('event') && $this->currentEventId()) {
            return redirect()->route('events.presenter.view', $this->currentEventId());
        }

        $payload = $this->payload();
        $chapters = PresentationChapter::with(['decks.slides', 'topics.decks.slides'])->orderBy('position')->orderBy('id')->get();
        $rootDecks = PresentationDeck::with('slides')->whereNull('chapter_id')->whereNull('section_id')->whereNull('topic_id')->orderBy('position')->orderBy('id')->get();
        $outlineRoots = $chapters->concat($rootDecks)->sort(function ($left, $right): int {
            $leftIsTitle = $left instanceof PresentationDeck && $left->deck_type === 'title';
            $rightIsTitle = $right instanceof PresentationDeck && $right->deck_type === 'title';

            return ($rightIsTitle <=> $leftIsTitle) ?: (($left->position <=> $right->position) ?: ($left->id <=> $right->id));
        })->values();

        return view('admin', $payload + [
            'presenterMode' => true,
            'eventId' => $this->currentEventId(),
            'eventGroupRoster' => $this->eventGroupRoster(),
            'attendees' => Attendee::latest()->get(),
            'answers' => DB::table('assessment_answers')->where('lecture_id', app(LectureContext::class)->id())->whereNotNull('answer')->count(),
            'questions' => AssessmentQuestion::orderBy('order_index')->get()->groupBy('slide_id'),
            'chapters' => $chapters,
            'rootSections' => collect(),
            'rootDecks' => $rootDecks,
            'outlineRoots' => $outlineRoots,
            'selectedChapter' => null,
            'selectedSection' => null,
            'currentSection' => null,
            'sectionTimerActive' => false,
            'decks' => PresentationDeck::with(['chapter', 'section', 'topic.section', 'topic.chapter', 'groupActivitySections'])->orderBy('id')->get(),
        ]);
    }

    public function eventPresenter(Request $request, Event $event, LectureContext $lectureContext): RedirectResponse
    {
        abort_if(
            $event->archived_at
                || ! $event->starts_at
                || ! $event->ends_at
                || $event->starts_at->isFuture()
                || $event->ends_at->isPast(),
            404,
        );

        $lecture = $event->lecture;
        abort_if(! $lecture || $lecture->archived_at || ! $lecture->is_active, 404);
        abort_unless(
            $request->user()->isSuperAdmin()
                || (int) $lecture->creator_id === (int) $request->user()->id,
            403,
        );

        $lectureContext->set($lecture);
        $request->session()->put('active_lecture_id', $lecture->id);
        $request->session()->put('active_event_id', $event->id);

        return redirect()->route('events.presenter.view', $event);
    }

    public function eventPresenterView(Request $request, Event $event, LectureContext $lectureContext): View
    {
        $lecture = $event->lecture;
        abort_if(
            $event->archived_at
                || ! $event->starts_at
                || ! $event->ends_at
                || $event->starts_at->isFuture()
                || $event->ends_at->isPast()
                || ! $lecture
                || $lecture->archived_at
                || ! $lecture->is_active,
            404,
        );
        abort_unless($request->user()->isSuperAdmin() || (int) $lecture->creator_id === (int) $request->user()->id, 403);

        $lectureContext->set($lecture);
        $request->session()->put(['active_lecture_id' => $lecture->id, 'active_event_id' => $event->id]);
        $request->attributes->set('active_event_id', $event->id);

        return $this->presenter($request);
    }

    public function storeOutline(Request $request)
    {
        $data = $request->validate([
            'outline_kind' => ['required', 'in:chapter,topic,topic_deck,special_deck'],
            'title' => ['required', 'string', 'max:180'],
            'parent' => ['nullable', 'string', 'max:40'],
            'special_type' => ['nullable', 'in:title,instructions,break,custom,group_activity'],
            'deck_content_type' => ['nullable', 'in:content,discussion,assessment'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);
        $kind = $data['outline_kind'];
        $title = trim($data['title']);
        $parent = $this->resolveOutlineParent($data['parent'] ?? null);

        if ($kind === 'chapter') {
            $chapter = PresentationChapter::create(['title' => $title, 'position' => (int) PresentationChapter::max('position') + 1]);

            return redirect($this->outlineUrl(['chapter' => $chapter->id]))->with('message', 'Chapter added.');
        }
        if ($kind === 'topic') {
            abort_unless($parent instanceof PresentationChapter, 422, 'Choose a chapter for this topic.');
            $topic = $parent->topics()->create(['title' => $title, 'section_id' => null, 'position' => (int) $parent->topics()->max('position') + 1]);

            return redirect($this->outlineUrl(['chapter' => $parent->id]))->with('message', 'Topic added.');
        }

        $special = $kind === 'special_deck';
        $topicDeck = $kind === 'topic_deck';
        if ($special && ($data['special_type'] ?? null) === 'break' && empty($data['duration_minutes'])) {
            throw ValidationException::withMessages(['duration_minutes' => 'Timed breaks need a timer length.']);
        }
        abort_unless($special
            ? ($parent === null || $parent instanceof PresentationChapter || $parent instanceof PresentationTopic)
            : ($topicDeck && $parent instanceof PresentationTopic), 422, 'Choose a valid parent for this deck.');
        $contentType = $topicDeck ? ($data['deck_content_type'] ?? 'content') : null;
        $attributes = [
            'title' => $title,
            'deck_type' => $special ? ($data['special_type'] ?? 'custom') : 'content',
            'content_type' => $special ? null : $contentType,
            'duration_minutes' => $special && ($data['special_type'] ?? null) === 'break' ? ($data['duration_minutes'] ?? null) : null,
            'position' => (int) ($parent?->decks()->max('position') ?? PresentationDeck::max('position')) + 1,
        ];
        $deck = $parent instanceof PresentationTopic
            ? $parent->decks()->create($attributes)
            : ($parent instanceof PresentationChapter ? $parent->decks()->create($attributes) : PresentationDeck::create($attributes));
        $slide = null;
        if ($contentType === 'discussion' || $contentType === 'assessment' || ($special && ($data['special_type'] ?? null) === 'group_activity')) {
            $isAssessment = $contentType === 'assessment';
            $isGroupActivity = $special && ($data['special_type'] ?? null) === 'group_activity';
            $slide = $deck->slides()->create([
                'title' => $title,
                'type' => $isAssessment ? 'assessment' : ($isGroupActivity ? 'group_activity' : 'information'),
                'content' => null,
                'instructions' => $isAssessment ? 'Read each question carefully and answer every item. Your answers save as you work. You may move between questions while the timer is running. Submit before time runs out; submitted answers cannot be changed.' : null,
                'media_url' => null,
                'options' => null,
                'position' => (int) Slide::max('position') + 1,
                'duration_minutes' => 10,
                'randomize_questions' => false,
                'notes' => null,
                'layout_key' => 'standard',
                'layout_data' => [],
            ]);
        }
        $chapterId = $parent instanceof PresentationChapter ? $parent->id : ($parent instanceof PresentationTopic ? $parent->chapter_id : null);

        $redirect = redirect($this->outlineUrl(array_filter([
            'chapter' => $chapterId,
        ])));
        if ($slide?->type === 'assessment') {
            $redirect->with('open_question_slide_id', $slide->id);
        }

        return $redirect->with('message', $special ? (($data['special_type'] ?? null) === 'group_activity' ? 'Group activity deck added.' : 'Special deck added.') : match ($contentType) {
            'discussion' => 'Discussion deck and starter slide added.',
            'assessment' => 'Assessment deck added. Add its questions to finish setting it up.',
            default => 'Topic deck added to the outline.',
        });
    }

    public function updateOutlineItem(Request $request, string $kind, int $id)
    {
        abort_unless(in_array($kind, ['chapter', 'topic', 'deck'], true), 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:180']]);
        $model = match ($kind) {
            'chapter' => PresentationChapter::findOrFail($id),
            'topic' => PresentationTopic::findOrFail($id),
            'deck' => PresentationDeck::findOrFail($id),
        };
        $model->update(['title' => trim($data['title'])]);

        return response()->json(['saved' => true]);
    }

    public function deleteOutlineItem(string $kind, int $id)
    {
        abort_unless(in_array($kind, ['chapter', 'topic', 'deck'], true), 404);
        $model = match ($kind) {
            'chapter' => PresentationChapter::findOrFail($id),
            'topic' => PresentationTopic::findOrFail($id),
            'deck' => PresentationDeck::findOrFail($id),
        };
        $decks = match ($kind) {
            'chapter' => PresentationDeck::where('chapter_id', $id)->orWhereIn('topic_id', PresentationTopic::where('chapter_id', $id)->select('id'))->get(),
            'topic' => PresentationDeck::where('topic_id', $id)->get(),
            'deck' => collect([$model]),
        };
        $deckIds = $decks->pluck('id');
        $slideIds = Slide::whereIn('deck_id', $deckIds)->pluck('id');
        DB::transaction(function () use ($slideIds, $model, $kind, $id): void {
            $state = $this->state();
            $selectedNodeDeleted = ($kind === 'chapter' && (int) $state->current_chapter_id === $id)
                || ($kind === 'topic' && (int) $state->current_topic_id === $id);
            $currentSlideDeleted = $slideIds->contains((int) $state->current_slide_id);
            if ($selectedNodeDeleted || $currentSlideDeleted) {
                $orderedSlides = Slide::orderBy('position')->orderBy('id')->get();
                $currentIndex = $orderedSlides->search(fn (Slide $slide): bool => (int) $slide->id === (int) $state->current_slide_id);
                $nextSlide = $currentIndex === false
                    ? $orderedSlides->first(fn (Slide $slide): bool => ! $slideIds->contains((int) $slide->id))
                    : $orderedSlides->slice($currentIndex + 1)->first(fn (Slide $slide): bool => ! $slideIds->contains((int) $slide->id));

                if (! $nextSlide) {
                    $nextSlide = $orderedSlides->first(fn (Slide $slide): bool => ! $slideIds->contains((int) $slide->id));
                }

                $this->updateDeckState([
                    'is_live' => (bool) $state->is_live && (bool) $nextSlide,
                    'current_slide_id' => $nextSlide?->id,
                    'current_chapter_id' => null,
                    'current_topic_id' => null,
                    'presented_group_label' => null,
                    'assessment_started_at' => null,
                    'assessment_ends_at' => null,
                    'section_timer_started_at' => null,
                    'section_timer_ends_at' => null,
                    'updated_at' => now(),
                ]);
            }
            Slide::whereIn('id', $slideIds)->delete();
            $model->delete();
        });

        return response()->json(['deleted' => true]);
    }

    private function resolveOutlineParent(?string $value): PresentationChapter|PresentationTopic|null
    {
        if (! $value || $value === 'root') {
            return null;
        }
        [$kind, $id] = array_pad(explode(':', $value, 2), 2, null);

        return match ($kind) {
            'chapter' => PresentationChapter::findOrFail($id),
            'topic' => PresentationTopic::findOrFail($id),
            default => abort(422, 'Choose a valid outline parent.'),
        };
    }

    public function reorderOutline(Request $request)
    {
        $data = $request->validate([
            'containers' => ['required', 'array', 'min:1', 'max:2'],
            'containers.*.parent_kind' => ['required', 'in:root,chapter,topic'],
            'containers.*.parent_id' => ['nullable', 'integer'],
            'containers.*.items' => ['present', 'array', 'max:250'],
            'containers.*.items.*.kind' => ['required', 'in:chapter,topic,deck'],
            'containers.*.items.*.id' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['containers'] as $container) {
                $parentKind = $container['parent_kind'];
                $parentId = $container['parent_id'] ?? null;
                abort_unless($parentKind === 'root' || $parentId, 422, 'Choose a valid parent for this outline item.');
                $parent = match ($parentKind) {
                    'chapter' => PresentationChapter::findOrFail($parentId),
                    'topic' => PresentationTopic::findOrFail($parentId),
                    default => null,
                };
                $allowed = match ($parentKind) {
                    'root' => ['chapter', 'deck'],
                    'chapter' => ['topic', 'deck'],
                    'topic' => ['deck'],
                };
                $items = $container['items'];
                if ($parentKind === 'root') {
                    $titleDeck = PresentationDeck::where('deck_type', 'title')->whereNull('chapter_id')->whereNull('topic_id')->first();
                    if ($titleDeck) {
                        $items = collect($items)->reject(fn ($item) => $item['kind'] === 'deck' && (int) $item['id'] === $titleDeck->id)
                            ->prepend(['kind' => 'deck', 'id' => $titleDeck->id])->values()->all();
                    }
                }

                foreach ($items as $position => $item) {
                    abort_unless(in_array($item['kind'], $allowed, true), 422, 'This outline item cannot be moved to that level.');
                    $model = match ($item['kind']) {
                        'chapter' => PresentationChapter::findOrFail($item['id']),
                        'topic' => PresentationTopic::findOrFail($item['id']),
                        'deck' => PresentationDeck::findOrFail($item['id']),
                    };
                    if ($item['kind'] === 'deck' && $parentKind === 'root') {
                        abort_unless($model->deck_type !== 'content', 422, 'Only special decks can be placed at the presentation root.');
                    }

                    if ($item['kind'] === 'topic') {
                        $model->update(['chapter_id' => $parent->id, 'section_id' => null, 'position' => $position]);
                    } elseif ($item['kind'] === 'deck') {
                        $model->update([
                            'chapter_id' => $parentKind === 'chapter' ? $parent->id : null,
                            'section_id' => null,
                            'topic_id' => $parentKind === 'topic' ? $parent->id : null,
                            'position' => $position,
                        ]);
                    } else {
                        $model->update(['position' => $position]);
                    }
                }
            }

            $roots = PresentationChapter::all()->concat(PresentationDeck::whereNull('chapter_id')->whereNull('section_id')->whereNull('topic_id')->get())->sortBy('position')->values();
            $titleDeck = $roots->first(fn ($root) => $root instanceof PresentationDeck && $root->deck_type === 'title');
            if ($titleDeck) {
                $roots = $roots->reject(fn ($root) => $root->is($titleDeck))->prepend($titleDeck)->values();
            }

            $slidePosition = 0;
            $saveDeckSlides = function ($decks) use (&$slidePosition): void {
                foreach ($decks as $deck) {
                    foreach ($deck->slides()->orderBy('position')->orderBy('id')->get() as $slide) {
                        $slide->update(['position' => $slidePosition++]);
                    }
                }
            };
            foreach ($roots as $root) {
                if ($root instanceof PresentationDeck) {
                    $saveDeckSlides(collect([$root]));

                    continue;
                }
                $saveDeckSlides($root->decks);
                foreach ($root->topics as $topic) {
                    $saveDeckSlides($topic->decks);
                }
            }
        });

        return response()->json(['saved' => true]);
    }

    public function storeSlide(Request $request)
    {
        $data = $this->validateSlide($request);
        $groupActivitySections = $data['group_activity_sections'] ?? [];
        unset($data['group_activity_sections']);
        $data['position'] = ((int) Slide::max('position')) + 1;
        DB::transaction(function () use ($data, $groupActivitySections): void {
            $slide = Slide::create($data);
            if ($slide->type === 'group_activity') {
                $this->syncGroupActivitySections($slide->presentationDeck, $groupActivitySections);
            }
        });

        return back()->with('message', 'Slide added to your deck.');
    }

    public function updateSlide(Request $request, Slide $slide)
    {
        $data = $this->validateSlide($request, $slide);
        $groupActivitySections = $data['group_activity_sections'] ?? [];
        unset($data['group_activity_sections']);
        DB::transaction(function () use ($data, $groupActivitySections, $slide): void {
            $slide->update($data);
            if ($slide->type === 'group_activity') {
                $this->syncGroupActivitySections($slide->presentationDeck, $groupActivitySections);
            }
        });

        return back()->with('message', 'Slide changes saved.');
    }

    public function saveNotes(Request $request, Slide $slide)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:30000']]);
        $slide->update(['notes' => $data['notes'] ?? null]);

        return back()->with('message', 'Presenter notes saved.');
    }

    private function validateSlide(Request $request, ?Slide $slide = null): array
    {
        $isGroupActivity = $request->input('type') === 'group_activity';
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'deck_id' => [
                $isGroupActivity ? 'required' : 'nullable',
                'integer',
                Rule::exists('presentation_decks', 'id')->where('lecture_id', app(LectureContext::class)->id()),
            ],
            'type' => ['required', 'in:information,picture,video_only,video,assessment,group_activity'],
            'content' => ['nullable', 'string', 'max:20000'],
            'instructions' => [$request->input('type') === 'assessment' ? 'required' : 'nullable', 'string', 'max:10000'],
            'media_url' => [in_array($request->input('type'), ['video', 'video_only'], true) ? 'required' : 'nullable', 'url', 'max:2000'],
            'options_text' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => [$request->input('type') === 'assessment' ? 'required' : 'nullable', 'integer', 'min:1', 'max:180'],
            'group_activity_duration_minutes' => [$isGroupActivity ? 'required' : 'nullable', 'integer', 'min:1', 'max:180'],
            'group_activity_sections' => [$isGroupActivity ? 'required' : 'exclude_unless:type,group_activity', 'array', 'min:1', 'max:20'],
            'group_activity_sections.*.id' => [Rule::excludeIf(! $isGroupActivity), 'nullable', 'integer', Rule::exists('group_activity_sections', 'id')->where('deck_id', $request->input('deck_id'))],
            'group_activity_sections.*.title' => [$isGroupActivity ? 'required' : Rule::excludeIf(! $isGroupActivity), 'string', 'max:180'],
            'group_activity_sections.*.input_type' => [$isGroupActivity ? 'required' : Rule::excludeIf(! $isGroupActivity), Rule::in(['text', 'image', 'file'])],
            'randomize_questions' => ['nullable', 'boolean'],
            'layout_key' => ['nullable', 'in:standard,cover,split,image_focus,data_table,feature_grid,modular,video_focus,video_split'],
            'image_url' => ['nullable', 'url', 'max:2000'],
            'image_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'image_caption' => ['nullable', 'string', 'max:500'],
            'table_text' => ['nullable', 'string', 'max:12000'],
            'cards_text' => ['nullable', 'string', 'max:12000'],
            'speaker_name' => ['nullable', 'string', 'max:180'],
            'speaker_role' => ['nullable', 'string', 'max:180'],
            'speaker_org' => ['nullable', 'string', 'max:180'],
            'speaker_region' => ['nullable', 'string', 'max:180'],
        ]);
        if ($data['type'] === 'picture' && blank($data['image_url'] ?? null) && ! $request->hasFile('image_file')) {
            throw ValidationException::withMessages(['image_url' => 'Choose a picture URL or upload an image for this slide.']);
        }
        if ($isGroupActivity) {
            $duplicateActivityExists = Slide::query()
                ->where('deck_id', $data['deck_id'])
                ->where('type', 'group_activity')
                ->when($slide, fn ($query) => $query->whereKeyNot($slide->id))
                ->exists();
            if ($duplicateActivityExists) {
                throw ValidationException::withMessages(['deck_id' => 'A deck can have one group activity slide. Edit its existing activity slide to update its sections.']);
            }
        }
        $data['options'] = $data['type'] === 'assessment'
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['options_text'] ?? ''))))
            : null;
        unset($data['options_text']);
        $data['content'] = in_array($data['type'], ['picture', 'video_only', 'group_activity'], true)
            ? null
            : DeckHtml::sanitize($data['content'] ?? null);
        $data['instructions'] = $data['type'] === 'assessment' ? DeckHtml::sanitize($data['instructions'] ?? null) : null;
        $data['duration_minutes'] = match ($data['type']) {
            'assessment' => (int) $data['duration_minutes'],
            'group_activity' => (int) $data['group_activity_duration_minutes'],
            default => 10,
        };
        $data['randomize_questions'] = $data['type'] === 'assessment' ? $request->boolean('randomize_questions') : false;

        $imageUrl = $data['image_url'] ?? null;
        if ($request->hasFile('image_file')) {
            $imageUrl = url(Storage::disk('public')->url($request->file('image_file')->store('slides', 'public')));
        }
        $tableLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['table_text'] ?? ''))));
        $table = null;
        if (count($tableLines) >= 2) {
            $table = [
                'headers' => array_map(fn ($cell) => DeckHtml::sanitize(trim($cell)), explode('|', array_shift($tableLines))),
                'rows' => array_map(fn ($line) => array_map(fn ($cell) => DeckHtml::sanitize(trim($cell)), explode('|', $line)), $tableLines),
            ];
        }
        $cards = [];
        foreach (preg_split('/\r\n|\r|\n/', $data['cards_text'] ?? '') as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) >= 2 && $parts[0] !== '' && $parts[1] !== '') {
                $cards[] = ['title' => DeckHtml::sanitize($parts[0]), 'body' => DeckHtml::sanitize($parts[1]), 'icon' => $parts[2] ?? '✦'];
            }
        }
        $data['layout_key'] = in_array($data['type'], ['assessment', 'group_activity', 'picture', 'video_only'], true) ? 'standard' : ($data['layout_key'] ?? 'standard');
        $data['layout_data'] = [
            'image_url' => $imageUrl,
            'image_caption' => $data['image_caption'] ?? null,
            'table' => $table,
            'cards' => $cards,
            'speaker_name' => $data['speaker_name'] ?? null,
            'speaker_role' => $data['speaker_role'] ?? null,
            'speaker_org' => $data['speaker_org'] ?? null,
            'speaker_region' => $data['speaker_region'] ?? null,
        ];
        if ($data['type'] === 'picture') {
            $data['media_url'] = null;
        }
        unset($data['image_url'], $data['image_file'], $data['image_caption'], $data['table_text'], $data['cards_text'], $data['speaker_name'], $data['speaker_role'], $data['speaker_org'], $data['speaker_region'], $data['group_activity_duration_minutes']);

        return $data;
    }

    /** @param array<int, array{id?: int|null, title: string, input_type: string}> $sectionDefinitions */
    private function syncGroupActivitySections(PresentationDeck $deck, array $sectionDefinitions): void
    {
        $existingSections = $deck->groupActivitySections()->get()->keyBy('id');
        $retainedSectionIds = [];

        foreach ($sectionDefinitions as $position => $definition) {
            $attributes = [
                'title' => $definition['title'],
                'input_type' => $definition['input_type'],
                'position' => $position,
            ];
            $sectionId = $definition['id'] ?? null;

            if ($sectionId && $existingSections->has($sectionId)) {
                $section = $existingSections->get($sectionId);
                $section->update($attributes);
            } else {
                $section = $deck->groupActivitySections()->create($attributes);
            }

            $retainedSectionIds[] = $section->id;
        }

        $deck->groupActivitySections()->whereNotIn('id', $retainedSectionIds)->delete();
    }

    public function deleteSlide(Slide $slide)
    {
        $state = $this->state();
        if ((int) $state->current_slide_id === (int) $slide->id) {
            $orderedSlides = Slide::orderBy('position')->orderBy('id')->get();
            $currentIndex = $orderedSlides->search(fn (Slide $candidate): bool => (int) $candidate->id === (int) $slide->id);
            $nextSlide = $currentIndex === false
                ? null
                : $orderedSlides->slice($currentIndex + 1)->first(fn (Slide $candidate): bool => (int) $candidate->id !== (int) $slide->id);
            $nextSlide ??= $orderedSlides->first(fn (Slide $candidate): bool => (int) $candidate->id !== (int) $slide->id);
            $this->updateDeckState([
                'current_slide_id' => $nextSlide?->id,
                'is_live' => (bool) $state->is_live && (bool) $nextSlide,
                'current_chapter_id' => null,
                'current_topic_id' => null,
                'presented_group_label' => null,
                'assessment_started_at' => null,
                'assessment_ends_at' => null,
                'section_timer_started_at' => null,
                'section_timer_ends_at' => null,
                'updated_at' => now(),
            ]);
        }
        $slide->delete();

        return back()->with('message', 'Slide removed.');
    }

    public function control(Request $request)
    {
        $request->validate(['action' => ['required', 'in:start,pause,next,previous,next_deck,previous_deck,show,show_group']]);
        $state = $this->state();
        $action = $request->string('action')->toString();
        $pauseOptions = [];
        if ($action === 'pause') {
            $pauseOptions = $request->validate([
                'pause_message' => ['nullable', 'in:health_break,lunch_break,snacks'],
                'use_pause_timer' => ['nullable', 'boolean'],
                'pause_minutes' => ['nullable', 'required_if:use_pause_timer,1', 'integer', 'min:1', 'max:180'],
            ]);
        }
        $groupLabel = null;
        if ($action === 'show_group') {
            $event = $this->currentEventId() ? Event::find($this->currentEventId()) : null;
            abort_unless($event && $event->group_count > 0, 404);
            $labels = range('A', chr(ord('A') + $event->group_count - 1));
            $groupLabel = $request->validate(['group_label' => ['required', Rule::in($labels)]])['group_label'];
        }
        $requestedDeckId = $action === 'start' ? $request->validate(['deck_id' => ['nullable', 'integer', 'exists:presentation_decks,id']])['deck_id'] ?? null : null;
        $currentSlide = $state->current_slide_id ? Slide::with('presentationDeck.section')->find($state->current_slide_id) : null;
        // Navigation follows the complete presentation order so arrows cross deck
        // boundaries. Starting a selected deck still starts at that deck's first slide.
        $slides = Slide::orderBy('position')->orderBy('id')->get();
        $index = $slides->search(fn ($slide) => $slide->id == $state->current_slide_id);
        if ($index === false && $state->current_chapter_id) {
            $chapterSlideIds = Slide::whereHas('presentationDeck', function ($query) use ($state) {
                $query->where('chapter_id', $state->current_chapter_id)
                    ->orWhereHas('topic', fn ($topic) => $topic->where('chapter_id', $state->current_chapter_id));
            })->pluck('id')->all();
            $index = $slides->search(fn ($slide) => in_array($slide->id, $chapterSlideIds));
        }
        if ($index === false && $state->current_topic_id) {
            $topicSlideIds = Slide::whereHas('presentationDeck', fn ($query) => $query->where('topic_id', $state->current_topic_id))
                ->orderBy('position')->orderBy('id')->pluck('id')->all();
            $index = $slides->search(fn ($slide) => in_array($slide->id, $topicSlideIds));
        }
        $live = (bool) $state->is_live;
        $currentId = $state->current_slide_id;
        $currentChapterId = $state->current_chapter_id;
        $currentTopicId = $state->current_topic_id;
        $currentGroupLabel = $state->presented_group_label;
        $chapterOverview = ($state->current_chapter_id || $state->current_topic_id) && ! $state->current_slide_id && ! $currentGroupLabel;

        if ($action === 'start') {
            $live = true;
            $pauseOptions = [];
            $deckSlides = $requestedDeckId ? $slides->where('deck_id', $requestedDeckId) : collect();
            $currentId = $requestedDeckId ? $deckSlides->first()?->id : ($currentId ?: $slides->first()?->id);
            if ($currentId) {
                $currentChapterId = null;
                $currentTopicId = null;
            }
            $currentGroupLabel = null;
        }
        if ($action === 'pause') {
            $live = false;
        }
        if ($action === 'show') {
            $live = true;
            $currentId = $request->validate(['slide_id' => ['required', 'exists:slides,id']])['slide_id'];
            $currentChapterId = null;
            $currentTopicId = null;
            $currentGroupLabel = null;
        }
        if ($action === 'show_group') {
            $live = true;
            $currentId = null;
            $currentChapterId = null;
            $currentTopicId = null;
            $currentGroupLabel = $groupLabel;
        }
        $nextIndex = $index === false ? false : $index + ($chapterOverview ? 0 : 1);
        $titleDeckIsEnding = $currentSlide?->presentationDeck?->deck_type === 'title'
            && (int) $currentSlide->id === (int) $currentSlide->presentationDeck->slides->last()?->id;
        $advanceIntoGroupRosters = $action === 'next' && $titleDeckIsEnding && $this->currentEventHasGroups();
        $navigateFromGroupRoster = in_array($action, ['next', 'previous'], true) && $currentGroupLabel !== null;
        if (in_array($action, ['previous_deck', 'next_deck'], true) || $advanceIntoGroupRosters || $navigateFromGroupRoster) {
            $items = $this->presentationNavigationItems();
            $currentKey = $currentGroupLabel ? 'group:'.$currentGroupLabel
                : ($currentTopicId && ! $currentSlide ? 'topic:'.$currentTopicId
                    : ($currentChapterId && ! $currentSlide ? 'chapter:'.$currentChapterId
                        : ($currentSlide?->deck_id ? 'deck:'.$currentSlide->deck_id : null)));
            $navigationIndex = collect($items)->search(fn ($item) => $item['key'] === $currentKey);
            $isForwardNavigation = $action === 'next_deck' || $advanceIntoGroupRosters || ($navigateFromGroupRoster && $action === 'next');
            if ($navigationIndex === false && $isForwardNavigation) {
                $navigationIndex = -1;
            }
            if ($navigationIndex !== false) {
                $targetIndex = $navigationIndex + ($isForwardNavigation ? 1 : -1);
                if ($targetIndex >= 0 && $targetIndex < count($items)) {
                    $target = $items[$targetIndex];
                    $currentId = $target['slide_id'];
                    $currentChapterId = $target['chapter_id'];
                    $currentTopicId = $target['topic_id'];
                    $currentGroupLabel = $target['group_label'] ?? null;
                    $live = true;
                }
            }
        }
        if ($action === 'next' && ! $advanceIntoGroupRosters && ! $navigateFromGroupRoster && $nextIndex !== false && $nextIndex < $slides->count()) {
            $currentId = $slides[$nextIndex]->id;
            $currentChapterId = null;
            $currentTopicId = null;
        }
        if ($action === 'previous' && ! $navigateFromGroupRoster && $index !== false && $index > 0) {
            $currentId = $slides[$index - 1]->id;
            $currentChapterId = null;
            $currentTopicId = null;
        }

        $stateUpdate = [
            'is_live' => $live, 'current_slide_id' => $currentId,
            'current_chapter_id' => $currentId ? null : $currentChapterId,
            'current_topic_id' => $currentId ? null : $currentTopicId,
            'presented_group_label' => $currentId ? null : $currentGroupLabel,
            'updated_at' => now(),
        ];
        if ($action === 'start') {
            $stateUpdate['pause_message'] = null;
            $stateUpdate['pause_ends_at'] = null;
        }
        if ($action === 'pause') {
            $stateUpdate['pause_message'] = $pauseOptions['pause_message'] ?? 'default';
            $stateUpdate['pause_ends_at'] = ($pauseOptions['use_pause_timer'] ?? false)
                ? now()->addMinutes((int) $pauseOptions['pause_minutes'])
                : null;
        }
        if ((string) $currentId !== (string) $state->current_slide_id
            || (string) $currentChapterId !== (string) $state->current_chapter_id
            || (string) $currentTopicId !== (string) $state->current_topic_id
            || (string) $currentGroupLabel !== (string) $state->presented_group_label
        ) {
            $stateUpdate['assessment_started_at'] = null;
            $stateUpdate['assessment_ends_at'] = null;
            $nextSlide = $currentId ? Slide::with('presentationDeck.section')->find($currentId) : null;
            $oldSpecialDeckId = $currentSlide?->presentationDeck?->deck_type !== 'content' || $currentSlide?->type === 'group_activity'
                ? $currentSlide?->presentationDeck?->id
                : null;
            $newSpecialDeckId = $nextSlide?->presentationDeck?->deck_type !== 'content' || $nextSlide?->type === 'group_activity'
                ? $nextSlide?->presentationDeck?->id
                : null;
            if ((string) $oldSpecialDeckId !== (string) $newSpecialDeckId) {
                $stateUpdate['section_timer_started_at'] = null;
                $stateUpdate['section_timer_ends_at'] = null;
            }
            if ($nextSlide?->type === 'assessment') {
                DB::table('assessment_attempts')->where('slide_id', $nextSlide->id)->where('event_scope_id', 0)->delete();
            }
        }
        $this->updateDeckState($stateUpdate);

        return back();
    }

    public function assessmentSettings(Request $request, Slide $slide)
    {
        abort_unless($slide->type === 'assessment', 404);
        $data = $request->validate(['duration_minutes' => ['required', 'integer', 'min:1', 'max:180'], 'randomize_questions' => ['nullable', 'boolean']]);
        $slide->update(['duration_minutes' => $data['duration_minutes'], 'randomize_questions' => $request->boolean('randomize_questions')]);

        return back()->with('message', 'Assessment settings saved.');
    }

    public function stopAssessment()
    {
        $state = $this->state();
        $slide = $state->current_slide_id ? Slide::find($state->current_slide_id) : null;
        abort_unless($state->is_live && $slide?->type === 'assessment' && $state->assessment_ends_at, 422, 'There is no running assessment to stop.');
        $this->updateDeckState(['assessment_ends_at' => now(), 'updated_at' => now()]);
        DB::table('assessment_attempts')->where('slide_id', $slide->id)->whereNull('submitted_at')->get(['attendee_id', 'event_scope_id'])->each(fn (object $attempt) => $this->submitAssessmentAttempt((int) $attempt->attendee_id, $slide->id, (int) $attempt->event_scope_id));

        return back()->with('message', 'Assessment stopped. Student answers are locked and scores are available.');
    }

    public function adjustAssessmentTimer(Request $request)
    {
        $data = $request->validate(['remaining_minutes' => ['required', 'integer', 'min:1', 'max:180']]);
        $state = $this->state();
        $slide = $state->current_slide_id ? Slide::find($state->current_slide_id) : null;
        $endsAt = $state->assessment_ends_at ? Carbon::parse($state->assessment_ends_at) : null;
        abort_unless($state->is_live && $slide?->type === 'assessment' && $state->assessment_started_at && $endsAt && now()->lt($endsAt), 422, 'There is no running assessment timer to adjust.');
        $this->updateDeckState(['assessment_ends_at' => now()->addMinutes((int) $data['remaining_minutes']), 'updated_at' => now()]);

        return back()->with('message', 'Assessment timer adjusted to '.$data['remaining_minutes'].' remaining minutes.');
    }

    private function requireCurrentSection(PresentationSection $section): object
    {
        $state = $this->state();
        $currentSlide = $state->current_slide_id ? Slide::with('presentationDeck.section')->find($state->current_slide_id) : null;
        abort_unless($state->is_live && $currentSlide?->presentationDeck?->section?->id === $section->id, 422, 'Present a deck in this section before controlling its timer.');
        abort_unless($section->duration_minutes, 422, 'Set a timer length for this section first.');

        return $state;
    }

    public function startSectionTimer(PresentationSection $section)
    {
        $this->requireCurrentSection($section);
        $startedAt = now();
        $this->updateDeckState([
            'section_timer_started_at' => $startedAt,
            'section_timer_ends_at' => $startedAt->copy()->addMinutes((int) $section->duration_minutes),
            'updated_at' => $startedAt,
        ]);

        return back()->with('message', 'Section timer started for '.$section->duration_minutes.' minutes.');
    }

    public function stopSectionTimer(PresentationSection $section)
    {
        $state = $this->requireCurrentSection($section);
        abort_unless($state->section_timer_ends_at && now()->lt($state->section_timer_ends_at), 422, 'There is no running section timer to stop.');
        $this->updateDeckState(['section_timer_ends_at' => now(), 'updated_at' => now()]);

        return back()->with('message', 'Section timer stopped.');
    }

    public function adjustSectionTimer(Request $request, PresentationSection $section)
    {
        $data = $request->validate(['remaining_minutes' => ['required', 'integer', 'min:1', 'max:180']]);
        $state = $this->requireCurrentSection($section);
        abort_unless($state->section_timer_ends_at && now()->lt($state->section_timer_ends_at), 422, 'There is no running section timer to adjust.');
        $this->updateDeckState(['section_timer_ends_at' => now()->addMinutes((int) $data['remaining_minutes']), 'updated_at' => now()]);

        return back()->with('message', 'Section timer adjusted to '.$data['remaining_minutes'].' remaining minutes.');
    }

    private function requireCurrentSpecialDeck(PresentationDeck $deck): object
    {
        $state = $this->state();
        $currentSlide = $state->current_slide_id ? Slide::with('presentationDeck')->find($state->current_slide_id) : null;
        $isTimedSpecialDeck = $deck->deck_type !== 'content' && $deck->duration_minutes;
        $isTimedGroupActivity = $currentSlide?->type === 'group_activity' && $currentSlide->duration_minutes;
        abort_unless(($isTimedSpecialDeck || $isTimedGroupActivity) && $state->is_live && $currentSlide?->presentationDeck?->id === $deck->id, 422, 'Present a timed special deck or group activity before controlling its timer.');

        return $state;
    }

    public function startSpecialDeckTimer(PresentationDeck $deck)
    {
        $state = $this->requireCurrentSpecialDeck($deck);
        $currentSlide = $state->current_slide_id ? Slide::find($state->current_slide_id) : null;
        $durationMinutes = $currentSlide?->type === 'group_activity'
            ? (int) $currentSlide->duration_minutes
            : (int) $deck->duration_minutes;
        $startedAt = now();
        $this->updateDeckState(['section_timer_started_at' => $startedAt, 'section_timer_ends_at' => $startedAt->copy()->addMinutes($durationMinutes), 'updated_at' => $startedAt]);

        return back()->with('message', 'Activity timer started for '.$durationMinutes.' minutes.');
    }

    public function stopSpecialDeckTimer(PresentationDeck $deck)
    {
        $state = $this->requireCurrentSpecialDeck($deck);
        abort_unless($state->section_timer_ends_at && now()->lt($state->section_timer_ends_at), 422, 'There is no running special deck timer to stop.');
        $this->updateDeckState(['section_timer_ends_at' => now(), 'updated_at' => now()]);

        return back()->with('message', 'Special deck timer stopped.');
    }

    public function adjustSpecialDeckTimer(Request $request, PresentationDeck $deck)
    {
        $data = $request->validate(['remaining_minutes' => ['required', 'integer', 'min:1', 'max:180']]);
        $state = $this->requireCurrentSpecialDeck($deck);
        abort_unless($state->section_timer_ends_at && now()->lt($state->section_timer_ends_at), 422, 'There is no running special deck timer to adjust.');
        $this->updateDeckState(['section_timer_ends_at' => now()->addMinutes((int) $data['remaining_minutes']), 'updated_at' => now()]);

        return back()->with('message', 'Special deck timer adjusted.');
    }

    private function questionData(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:single_choice,multiple_choice,short_answer,true_false,ordering,matching'],
            'prompt' => ['required', 'string', 'max:4000'],
            'options_text' => ['nullable', 'string', 'max:10000'],
            'correct_answer_text' => ['required', 'string', 'max:10000'],
        ]);
        $options = [];
        if (in_array($data['type'], ['single_choice', 'multiple_choice', 'ordering'], true)) {
            $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['options_text'] ?? ''))));
            if (count($options) < 2) {
                throw ValidationException::withMessages(['options_text' => 'Add at least two choices, items, or steps on separate lines.']);
            }
        } elseif ($data['type'] === 'matching') {
            foreach (preg_split('/\r\n|\r|\n/', $data['options_text'] ?? '') as $line) {
                $parts = array_map('trim', explode('|', $line, 2));
                if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                    $options[] = ['left' => $parts[0], 'right' => $parts[1]];
                }
            }
            if (count($options) < 2) {
                throw ValidationException::withMessages(['options_text' => 'Add at least two pairs, one per line, using “left | right”.']);
            }
        } elseif ($data['type'] === 'true_false') {
            $options = ['True', 'False'];
        }

        $answerLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['correct_answer_text'])), fn ($value) => $value !== ''));
        $correctAnswer = null;
        if (in_array($data['type'], ['single_choice', 'true_false'], true)) {
            $correctAnswer = $answerLines[0] ?? '';
            if (count($answerLines) !== 1 || ! in_array($correctAnswer, $options, true)) {
                throw ValidationException::withMessages(['correct_answer_text' => 'Enter exactly one correct answer that matches an available choice.']);
            }
        } elseif ($data['type'] === 'multiple_choice') {
            $correctAnswer = array_values(array_unique($answerLines));
            if (! $correctAnswer || array_diff($correctAnswer, $options)) {
                throw ValidationException::withMessages(['correct_answer_text' => 'List one or more correct choices, each matching an available choice.']);
            }
        } elseif ($data['type'] === 'short_answer') {
            $correctAnswer = $answerLines;
            if (! $correctAnswer) {
                throw ValidationException::withMessages(['correct_answer_text' => 'Enter at least one accepted answer.']);
            }
        } elseif ($data['type'] === 'ordering') {
            $correctAnswer = $answerLines;
            $normalize = fn ($items) => array_map(fn ($item) => mb_strtolower(trim($item)), $items);
            $expected = $normalize($options);
            $provided = $normalize($correctAnswer);
            sort($expected);
            $check = $provided;
            sort($check);
            if (count($provided) !== count($options) || $check !== $expected) {
                throw ValidationException::withMessages(['correct_answer_text' => 'The answer key must contain every ordering item exactly once.']);
            }
        } elseif ($data['type'] === 'matching') {
            $correctAnswer = [];
            foreach ($answerLines as $line) {
                $parts = array_map('trim', explode('|', $line, 2));
                if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                    $correctAnswer[$parts[0]] = $parts[1];
                }
            }
            $expectedKeys = array_column($options, 'left');
            $expectedValues = array_column($options, 'right');
            if (count($correctAnswer) !== count($options) || array_diff($expectedKeys, array_keys($correctAnswer)) || array_diff($expectedValues, array_values($correctAnswer))) {
                throw ValidationException::withMessages(['correct_answer_text' => 'Enter every matching pair using “left | right”, with each right side used once.']);
            }
        }

        return ['type' => $data['type'], 'prompt' => $data['prompt'], 'options' => $options ?: null, 'correct_answer' => $correctAnswer];
    }

    public function storeQuestion(Request $request, Slide $slide)
    {
        abort_unless($slide->type === 'assessment', 404);
        try {
            $data = $this->questionData($request);
        } catch (ValidationException $exception) {
            $request->session()->flash('open_question_slide_id', $slide->id);
            $request->session()->flash('open_add_question', true);
            throw $exception;
        }
        $data['slide_id'] = $slide->id;
        $data['order_index'] = ((int) AssessmentQuestion::where('slide_id', $slide->id)->max('order_index')) + 1;
        AssessmentQuestion::create($data);

        return back()->with('message', 'Question added.');
    }

    public function updateQuestion(Request $request, AssessmentQuestion $question)
    {
        try {
            $question->update($this->questionData($request));
        } catch (ValidationException $exception) {
            $request->session()->flash('open_question_slide_id', $question->slide_id);
            $request->session()->flash('open_question_id', $question->id);
            throw $exception;
        }

        return back()->with('message', 'Question saved.');
    }

    public function deleteQuestion(AssessmentQuestion $question)
    {
        $question->delete();

        return back()->with('message', 'Question removed.');
    }

    public function startAssessment(Request $request)
    {
        $state = $this->state();
        $slide = Slide::findOrFail($state->current_slide_id);
        abort_unless($state->is_live && $slide->type === 'assessment', 422, 'Present the assessment lobby before starting its timer.');
        abort_unless(AssessmentQuestion::where('slide_id', $slide->id)->exists(), 422, 'Add at least one question first.');
        if ($state->assessment_ends_at && now()->greaterThanOrEqualTo(Carbon::parse($state->assessment_ends_at))) {
            DB::table('assessment_attempts')->where('slide_id', $slide->id)->where('event_scope_id', 0)->delete();
        }
        $startedAt = now();
        $this->updateDeckState([
            'assessment_started_at' => $startedAt,
            'assessment_ends_at' => $startedAt->copy()->addMinutes((int) $slide->duration_minutes),
            'updated_at' => now(),
        ]);

        return back()->with('message', 'Assessment timer started for everyone.');
    }

    public function status()
    {
        $payload = $this->payload();
        $state = $payload['state'];
        $current = $payload['current'];
        $questionCount = $current?->type === 'assessment' ? AssessmentQuestion::where('slide_id', $current->id)->count() : 0;
        $payload = $this->compactSyncPayload($payload);

        return response()->json($payload + [
            'attendee_count' => Attendee::count(),
            'assessment_started_at' => $state->assessment_started_at,
            'assessment_ends_at' => $state->assessment_ends_at,
            'question_count' => $questionCount,
        ]);
    }

    public function join(Request $request): View|RedirectResponse
    {
        $attendee = $this->attendee($request);
        if ($attendee) {
            return $this->clientLanding($attendee);
        }

        $errorBag = $request->session()->get('errors');
        $loginMode = $request->routeIs('client.login')
            || $request->query('mode') === 'login'
            || ($errorBag?->getBag('default')->has('credentials') ?? false)
            || ($errorBag?->getBag('default')->has('social') ?? false);

        return view('join', ['loginMode' => $loginMode]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);
        $data['name'] = trim($data['name']);
        $data['year'] = '';
        $data['section'] = '';
        $attendee = Attendee::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($data['name'])])
            ->whereNull('password')
            ->first();
        if ($attendee) {
            $attendee->update(['password' => $data['password']]);
        } else {
            $attendee = Attendee::create($data);
        }
        $request->session()->regenerate();
        $request->session()->put('attendee_id', $attendee->id);

        return $this->clientLanding($attendee);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'password' => ['required', 'string', 'max:72'],
        ]);
        $attendee = Attendee::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($data['name']))])
            ->get()
            ->first(fn (Attendee $student) => $student->password && Hash::check($data['password'], $student->password));

        if (! $attendee) {
            return back()->withErrors(['credentials' => 'Those student details and password did not match an account.'])->withInput($request->only('name'));
        }

        $request->session()->regenerate();
        $request->session()->put('attendee_id', $attendee->id);

        return $this->clientLanding($attendee);
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['attendee_id', 'active_event_id']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('client.join');
    }

    public function socialRedirect(string $provider)
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);
        $credentials = config("services.{$provider}");
        if (blank($credentials['client_id'] ?? null) || blank($credentials['client_secret'] ?? null)) {
            return redirect()->route('client.login')->withErrors(['social' => ucfirst($provider).' sign-in needs its app credentials configured first.']);
        }

        $scopes = $provider === 'google' ? ['openid', 'profile', 'email'] : ['public_profile'];

        return Socialite::driver($provider)->setScopes($scopes)->redirect();
    }

    public function socialCallback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);

        if ($provider === 'google' && $request->session()->pull('admin_google_auth')) {
            return app(AdminAuthController::class)->googleCallback($request);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $exception) {
            Log::warning('Student social sign-in failed.', ['provider' => $provider, 'exception' => $exception::class]);

            return redirect()->route('client.login')->withErrors(['social' => 'We could not sign you in with '.ucfirst($provider).'. Please try again or use your student password.']);
        }

        $providerColumn = $provider.'_id';
        $providerId = (string) $socialUser->getId();
        if ($providerId === '') {
            return redirect()->route('client.login')->withErrors(['social' => ucfirst($provider).' did not return a valid account ID.']);
        }

        $email = $provider === 'google' ? mb_strtolower(trim((string) $socialUser->getEmail())) : null;
        if ($provider === 'google' && ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            return redirect()->route('client.login')->withErrors(['social' => 'Google did not provide a valid email address. Please allow email access and try again.']);
        }
        if ($provider === 'google') {
            $emailVerified = $socialUser->user['email_verified'] ?? $socialUser->user['verified_email'] ?? false;
            if (! filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN)) {
                return redirect()->route('client.login')->withErrors(['social' => 'Google must provide a verified email address for student sign-in.']);
            }
        }

        $attendee = Attendee::where($providerColumn, $providerId)->first();
        if (! $attendee && $email) {
            $attendee = Attendee::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($attendee) {
                if (filled($attendee->google_id) && $attendee->google_id !== $providerId) {
                    return redirect()->route('client.login')->withErrors(['social' => 'That email is already linked to a different Google account. Sign in with your existing student account.']);
                }
                $attendee->forceFill(['google_id' => $providerId])->save();
            }
        }
        if ($attendee) {
            $request->session()->regenerate();
            $request->session()->put('attendee_id', $attendee->id);

            return $this->clientLanding($attendee);
        }

        $request->session()->put('social_signup', [
            'provider' => $provider,
            'provider_id' => $providerId,
            'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: '',
            'email' => $email,
        ]);

        return redirect()->route('student.complete-social');
    }

    public function completeSocialProfile(Request $request)
    {
        $socialSignup = $request->session()->get('social_signup');
        abort_unless(is_array($socialSignup) && in_array($socialSignup['provider'] ?? null, ['google', 'facebook'], true), 403);

        return view('student-profile', ['socialSignup' => $socialSignup]);
    }

    public function saveSocialProfile(Request $request): RedirectResponse
    {
        $socialSignup = $request->session()->get('social_signup');
        abort_unless(is_array($socialSignup) && in_array($socialSignup['provider'] ?? null, ['google', 'facebook'], true), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
        ]);
        $providerColumn = $socialSignup['provider'].'_id';
        $attendee = Attendee::firstOrCreate(
            [$providerColumn => $socialSignup['provider_id']],
            ['name' => trim($data['name']), 'email' => $socialSignup['email'] ?? null, 'year' => '', 'section' => ''],
        );
        $request->session()->forget('social_signup');
        $request->session()->regenerate();
        $request->session()->put('attendee_id', $attendee->id);

        return $this->clientLanding($attendee);
    }

    private function clientLanding(Attendee $attendee): RedirectResponse
    {
        $now = now();
        $ongoingEvents = $attendee->events()
            ->whereNull('events.archived_at')
            ->where('events.starts_at', '<=', $now)
            ->where('events.ends_at', '>=', $now)
            ->get(['events.id']);

        if ($ongoingEvents->count() === 1) {
            return redirect()->route('client.event.presentation', $ongoingEvents->first());
        }

        return redirect()->route('client.events.my');
    }

    private function attendee(Request $request): ?Attendee
    {
        if (! $request->session()->has('attendee_id')) {
            return null;
        }

        $attendeeQuery = Attendee::query();
        if ($this->currentEventId()) {
            $attendeeQuery->withoutGlobalScope(ActiveLectureScope::class);
        }

        return $attendeeQuery->find($request->session()->get('attendee_id'));
    }

    private function currentEventId(): ?int
    {
        $eventParameter = request()->route('event');
        $eventId = is_object($eventParameter) ? $eventParameter->getKey() : $eventParameter;
        $eventId ??= request()->input('event_id', request()->session()->get('active_event_id'));

        return $eventId ? (int) $eventId : null;
    }

    private function currentEventHasGroups(): bool
    {
        $eventId = $this->currentEventId();

        return $eventId !== null && Event::query()->whereKey($eventId)->where('group_count', '>', 0)->exists();
    }

    private function currentEventScopeId(): int
    {
        return $this->currentEventId() ?? 0;
    }

    public function client(Request $request, ?Event $event = null): View|RedirectResponse
    {
        $activeGroup = null;
        if ($event) {
            $attendee = $request->session()->has('attendee_id')
                ? Attendee::withoutGlobalScope(ActiveLectureScope::class)->find($request->session()->get('attendee_id'))
                : null;
            if (! $attendee) {
                return redirect()->route('client.join');
            }

            abort_if($event->archived_at || ! $event->starts_at || ! $event->ends_at || $event->starts_at->isFuture() || $event->ends_at->isPast(), 404);
            $registration = DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->first();
            abort_unless($registration, 403);
            if ($event->group_count > 0 && blank($registration->group_label)) {
                return redirect()->route('client.events.groups', $event);
            }
            $activeGroup = $registration->group_label;

            $request->session()->put('active_event_id', $event->id);
            app(LectureContext::class)->set($event->lecture()->firstOrFail());
            if (! $registration->attended_at) {
                DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->update(['attended_at' => now(), 'updated_at' => now()]);
            }
        } else {
            $attendee = $this->attendee($request);
            if (! $attendee) {
                return redirect()->route('client.join');
            }

            return redirect()->route('client.events.my');
        }

        return view('client', [
            'attendee' => $attendee,
            'activeEvent' => $event,
            'activeGroup' => $activeGroup,
            'activeNav' => 'presentation',
            'eventBackgroundUrl' => $event?->background_image_path ? Storage::disk('public')->url($event->background_image_path) : null,
            'eventBackgroundOpacity' => $event?->background_opacity ?? 20,
        ] + $this->clientPayload($attendee));
    }

    public function clientMaster(Request $request): RedirectResponse
    {
        $eventId = $request->session()->get('active_event_id');
        if ($eventId) {
            return redirect()->route('client.event.master', $eventId);
        }

        return redirect()->route($request->session()->has('attendee_id') ? 'client.events.my' : 'client.join');
    }

    public function clientEventMaster(Request $request, Event $event, LectureContext $lectureContext): View
    {
        $this->activateEventContext($request, $event, $lectureContext);

        return view('client-master', $this->clientMasterPayload() + [
            'masterStatusUrl' => route('client.event.master.status', $event),
        ]);
    }

    public function clientEventMasterStatus(Request $request, Event $event, LectureContext $lectureContext): JsonResponse
    {
        $this->activateEventContext($request, $event, $lectureContext);

        return $this->clientMasterStatus();
    }

    public function clientMasterStatus(): JsonResponse
    {
        return response()->json($this->compactSyncPayload($this->clientMasterPayload()));
    }

    private function activateEventContext(Request $request, Event $event, LectureContext $lectureContext): void
    {
        abort_if(
            $event->archived_at
                || ! $event->starts_at
                || ! $event->ends_at
                || $event->starts_at->isFuture()
                || $event->ends_at->isPast(),
            404,
        );

        $lecture = $event->lecture;
        abort_if(! $lecture || $lecture->archived_at || ! $lecture->is_active, 404);

        $request->session()->put('active_event_id', $event->id);
        $lectureContext->set($lecture);
    }

    private function clientMasterPayload(): array
    {
        $data = $this->payload();
        $eventId = $this->currentEventId();
        $event = $eventId ? Event::query()->whereNull('archived_at')->find($eventId) : null;
        $data['eventBackgroundUrl'] = $event?->background_image_path ? Storage::disk('public')->url($event->background_image_path) : null;
        $data['eventBackgroundOpacity'] = $event?->background_opacity ?? 20;

        foreach ($data['slides'] as $slide) {
            $slide->makeHidden(['notes']);
        }
        $data['current']?->makeHidden(['notes']);
        $data['section_timer'] = $this->sectionTimerPayload($data['current'], $data['state']);
        $data['presented_chapter'] = $this->chapterPresentationPayload($data['state']->current_chapter_id);
        $data['presented_topic'] = $this->topicPresentationPayload($data['state']->current_topic_id);
        $data['assessment'] = null;
        if ($data['current']?->type === 'assessment') {
            $state = $data['state'];
            $startedAt = $state->assessment_started_at ? Carbon::parse($state->assessment_started_at) : null;
            $endsAt = $state->assessment_ends_at ? Carbon::parse($state->assessment_ends_at) : null;
            $phase = ! $state->is_live ? 'waiting' : (! $startedAt ? 'lobby' : (now()->greaterThanOrEqualTo($endsAt) ? 'finished' : 'quiz'));
            $data['assessment'] = [
                'phase' => $phase,
                'title' => $data['current']->title,
                'instructions' => $data['current']->instructions,
                'ends_at_ms' => $endsAt?->valueOf(),
            ];
            if ($phase === 'finished') {
                $data['assessment_results'] = $this->assessmentEndResults($data['current']);
            }
        }
        unset($data['state']);

        return $data;
    }

    public function clientStatus(Request $request)
    {
        $attendee = $this->attendee($request);
        if (! $attendee) {
            return response()->json(['registered' => false], 401);
        }
        $eventId = $this->currentEventId();
        if ($eventId) {
            abort_unless(
                DB::table('attendee_event')->where('event_id', $eventId)->where('attendee_id', $attendee->id)->exists(),
                403,
            );
        }
        $data = $this->clientPayload($attendee);
        $data = $this->compactSyncPayload($data);
        $data['registered'] = true;
        $data['attendee_count'] = Attendee::count();

        return response()->json($data);
    }

    public function clientEventStatus(Request $request, Event $event, LectureContext $lectureContext): JsonResponse
    {
        $attendee = $this->attendee($request);
        abort_unless($attendee, 401);
        abort_unless(
            DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->exists(),
            403,
        );
        $this->activateEventContext($request, $event, $lectureContext);

        return $this->clientStatus($request);
    }

    private function clientPayload(Attendee $attendee): array
    {
        $data = $this->payload();
        foreach ($data['slides'] as $slide) {
            $slide->makeHidden(['notes']);
        }
        $data['current']?->makeHidden(['notes']);
        $data['section_timer'] = $this->sectionTimerPayload($data['current'], $data['state']);
        $data['presented_chapter'] = $this->chapterPresentationPayload($data['state']->current_chapter_id);
        $data['presented_topic'] = $this->topicPresentationPayload($data['state']->current_topic_id);
        $data['assessment'] = null;
        $data['assessment_results'] = null;
        if (! $data['current'] || $data['current']->type !== 'assessment') {
            return $data;
        }

        $slide = $data['current'];
        $questions = AssessmentQuestion::where('slide_id', $slide->id)->orderBy('order_index')->orderBy('id')->get();
        $state = $data['state'];
        $attempt = null;
        $startedAt = $state->assessment_started_at ? Carbon::parse($state->assessment_started_at) : null;
        $endsAt = $state->assessment_ends_at ? Carbon::parse($state->assessment_ends_at) : null;

        if ($state->is_live && $startedAt && $endsAt && $questions->isNotEmpty()) {
            $attempt = DB::table('assessment_attempts')->where('attendee_id', $attendee->id)->where('slide_id', $slide->id)->where('event_scope_id', $this->currentEventScopeId())->first();
            if (! $attempt) {
                $order = $questions->pluck('id')->all();
                if ($slide->randomize_questions) {
                    shuffle($order);
                }
                DB::table('assessment_attempts')->insertOrIgnore([
                    'attendee_id' => $attendee->id, 'slide_id' => $slide->id,
                    'lecture_id' => app(LectureContext::class)->id(),
                    'event_id' => $this->currentEventId(),
                    'event_scope_id' => $this->currentEventScopeId(),
                    'question_order' => json_encode($order), 'started_at' => $startedAt,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $attempt = DB::table('assessment_attempts')->where('attendee_id', $attendee->id)->where('slide_id', $slide->id)->where('event_scope_id', $this->currentEventScopeId())->first();
            }
        }

        $timeExpired = $endsAt && now()->greaterThanOrEqualTo($endsAt);
        if ($attempt && $timeExpired && ! $attempt->submitted_at) {
            $this->submitAssessmentAttempt($attendee->id, $slide->id, $this->currentEventScopeId());
            $attempt = DB::table('assessment_attempts')->where('attendee_id', $attendee->id)->where('slide_id', $slide->id)->where('event_scope_id', $this->currentEventScopeId())->first();
        }
        $isComplete = (bool) ($attempt?->submitted_at || $timeExpired);
        $phase = ! $state->is_live ? 'waiting' : (! $startedAt ? 'lobby' : ($isComplete ? 'complete' : 'quiz'));
        $orderedIds = $attempt ? json_decode($attempt->question_order, true) : [];
        $orderedQuestions = $orderedIds ? AssessmentQuestion::whereIn('id', $orderedIds)->get()->sortBy(fn ($question) => array_search($question->id, $orderedIds))->values() : collect();
        $saved = $attempt ? DB::table('assessment_answers')->where('attempt_id', $attempt->id)->pluck('answer', 'question_id') : collect();
        $data['assessment'] = [
            'phase' => $phase, 'slide_id' => $slide->id, 'title' => $slide->title, 'instructions' => $slide->instructions,
            'duration_minutes' => $slide->duration_minutes, 'randomize' => $slide->randomize_questions,
            'started_at' => $startedAt?->toIso8601String(), 'ends_at' => $endsAt?->toIso8601String(),
            'ends_at_ms' => $endsAt?->valueOf(), 'question_count' => $questions->count(),
            'resume_position' => $attempt ? (int) $attempt->current_position : 0,
            'score' => $attempt?->score,
            'max_score' => $attempt?->max_score,
            'scored_question_count' => (int) ($attempt?->max_score ?? 0),
            'questions' => $phase === 'quiz' ? $orderedQuestions->map(fn ($question) => [
                'id' => $question->id, 'type' => $question->type, 'prompt' => $question->prompt,
                'options' => $question->options ?? [],
                'answer' => isset($saved[$question->id]) ? json_decode($saved[$question->id], true) : null,
            ])->all() : [],
        ];
        if ($isComplete) {
            $data['assessment_results'] = $this->assessmentEndResults($slide);
        }

        return $data;
    }

    private function assessmentEndResults(Slide $slide): array
    {
        $assessmentAttempts = DB::table('assessment_attempts as attempts')
            ->join('attendees', 'attendees.id', '=', 'attempts.attendee_id')
            ->where('attempts.lecture_id', app(LectureContext::class)->id())
            ->when($this->currentEventId(), fn ($query, int $eventId) => $query->where('attempts.event_id', $eventId))
            ->where('attempts.slide_id', $slide->id)
            ->whereNotNull('attempts.submitted_at')
            ->whereNotNull('attempts.score')
            ->where('attempts.max_score', '>', 0)
            ->get(['attendees.name', 'attempts.score', 'attempts.max_score', 'attempts.updated_at']);

        $overallAttempts = DB::table('assessment_attempts as attempts')
            ->join('attendees', 'attendees.id', '=', 'attempts.attendee_id')
            ->where('attempts.lecture_id', app(LectureContext::class)->id())
            ->when($this->currentEventId(), fn ($query, int $eventId) => $query->where('attempts.event_id', $eventId))
            ->whereNotNull('attempts.submitted_at')
            ->whereNotNull('attempts.score')
            ->where('attempts.max_score', '>', 0)
            ->groupBy('attendees.id', 'attendees.name')
            ->get([
                'attendees.id as attendee_id',
                'attendees.name',
                DB::raw('SUM(attempts.score) as score'),
                DB::raw('SUM(attempts.max_score) as max_score'),
                DB::raw('COUNT(attempts.id) as assessment_count'),
                DB::raw('MAX(attempts.updated_at) as updated_at'),
            ]);

        $overallEntriesByAttendee = $overallAttempts->mapWithKeys(fn (object $attempt): array => [$attempt->attendee_id => [
            'name' => $attempt->name,
            'score' => (int) $attempt->score,
            'max_score' => (int) $attempt->max_score,
            'percent' => round($attempt->score / $attempt->max_score * 100, 4),
        ]]);
        $groupGrades = $this->groupGradeTotals(app(LectureContext::class)->id(), $this->currentEventId());
        foreach ($groupGrades as $groupGrade) {
            $entry = $overallEntriesByAttendee->get($groupGrade->attendee_id, [
                'name' => $groupGrade->name,
                'score' => 0,
                'max_score' => 0,
                'percent' => 0,
            ]);
            $entry['score'] += (int) $groupGrade->score;
            $entry['max_score'] += (int) $groupGrade->max_score;
            $entry['percent'] = round($entry['score'] / $entry['max_score'] * 100, 4);
            $overallEntriesByAttendee->put($groupGrade->attendee_id, $entry);
        }

        $assessmentEntries = $assessmentAttempts->map(fn (object $attempt): array => [
            'name' => $attempt->name,
            'score' => (int) $attempt->score,
            'max_score' => (int) $attempt->max_score,
            'percent' => round($attempt->score / $attempt->max_score * 100, 4),
        ]);
        $overallEntries = $overallEntriesByAttendee->values();

        $assessmentVersion = $assessmentAttempts->max('updated_at');
        $overallVersion = max(
            (string) ($overallAttempts->max('updated_at') ?? ''),
            (string) ($groupGrades->max('updated_at') ?? ''),
        );
        $assessmentVersion = $assessmentVersion ? Carbon::parse($assessmentVersion)->valueOf() : 0;
        $overallVersion = $overallVersion ? Carbon::parse($overallVersion)->valueOf() : 0;
        $assessmentBoard = [
            'type' => 'assessment',
            'title' => $slide->title,
            'entries' => $this->rankLeaderboardEntries($assessmentEntries),
            'version' => $assessmentVersion,
        ];
        $overallBoard = [
            'type' => 'overall',
            'title' => 'Overall Top 3 Students',
            'entries' => $this->rankLeaderboardEntries($overallEntries),
            'version' => $overallVersion,
        ];

        return [
            'assessment' => $assessmentBoard,
            'overall' => $overallBoard,
            'version' => max($assessmentVersion, $overallVersion),
        ];
    }

    private function compactSyncPayload(array $data): array
    {
        $currentId = $data['current']?->id;
        $chapter = $data['presented_chapter'] ?? null;
        $topic = $data['presented_topic'] ?? null;
        $group = $data['presented_group'] ?? null;
        $assessmentResults = $data['assessment_results'] ?? null;

        $data['leaderboard_version'] = $data['leaderboard']['version'] ?? 0;
        unset($data['slides'], $data['state'], $data['breadcrumbs'], $data['count'], $data['leaderboard']);

        $data['current'] = $currentId ? ['id' => $currentId] : null;
        $data['presented_chapter'] = $chapter
            ? ['id' => $chapter['id'], 'version' => $chapter['version']]
            : null;
        $data['presented_topic'] = $topic ? ['id' => $topic['id'], 'version' => $topic['version']] : null;
        $data['presented_group'] = $group ? ['label' => $group['label'], 'version' => $group['version']] : null;
        $data['assessment_results'] = $assessmentResults ? [
            'version' => $assessmentResults['version'] ?? 0,
        ] : null;

        if (is_array($data['assessment'] ?? null)) {
            unset($data['assessment']['questions']);
        }

        return $data;
    }

    private function sectionTimerPayload(?Slide $slide, object $state): ?array
    {
        $deck = $slide?->presentationDeck;
        $isGroupActivity = $slide?->type === 'group_activity';
        $durationMinutes = $isGroupActivity ? $slide->duration_minutes : $deck?->duration_minutes;
        if (! $deck || (! $isGroupActivity && $deck->deck_type === 'content') || ! $durationMinutes) {
            return null;
        }
        $startedAt = $state->section_timer_started_at ? Carbon::parse($state->section_timer_started_at) : null;
        $endsAt = $state->section_timer_ends_at ? Carbon::parse($state->section_timer_ends_at) : null;
        $phase = ! $startedAt ? 'ready' : (! $endsAt || now()->greaterThanOrEqualTo($endsAt) ? 'finished' : 'running');

        return [
            'section_id' => $isGroupActivity ? $slide->id : $deck->id,
            'title' => $isGroupActivity ? $slide->title : $deck->title,
            'section_type' => $isGroupActivity ? 'group_activity' : $deck->deck_type,
            'duration_minutes' => $durationMinutes,
            'phase' => $phase,
            'ends_at_ms' => $endsAt?->valueOf(),
        ];
    }

    private function normalizedAnswer(mixed $answer): mixed
    {
        if (is_string($answer)) {
            return mb_strtolower(trim($answer));
        }
        if (! is_array($answer)) {
            return $answer;
        }
        $normalized = [];
        foreach ($answer as $key => $value) {
            $normalized[is_string($key) ? mb_strtolower(trim($key)) : $key] = is_string($value) ? mb_strtolower(trim($value)) : $value;
        }

        return $normalized;
    }

    private function assessmentAnswerIsCorrect(AssessmentQuestion $question, mixed $answer): bool
    {
        $correct = $question->correct_answer;
        if ($correct === null || $answer === null) {
            return false;
        }
        $correct = $this->normalizedAnswer($correct);
        $answer = $this->normalizedAnswer($answer);

        if ($question->type === 'short_answer') {
            return in_array($answer, is_array($correct) ? $correct : [$correct], true);
        }
        if ($question->type === 'multiple_choice') {
            if (! is_array($answer) || ! is_array($correct)) {
                return false;
            }
            sort($answer);
            sort($correct);

            return $answer === $correct;
        }
        if ($question->type === 'ordering') {
            return is_array($answer) && is_array($correct) && $answer === $correct;
        }
        if ($question->type === 'matching') {
            if (! is_array($answer) || ! is_array($correct)) {
                return false;
            }
            ksort($answer);
            ksort($correct);

            return $answer === $correct;
        }

        return $answer === $correct;
    }

    private function submitAssessmentAttempt(int $attendeeId, int $slideId, int $eventScopeId): ?object
    {
        return DB::transaction(function () use ($attendeeId, $slideId, $eventScopeId) {
            $attempt = DB::table('assessment_attempts')->where('attendee_id', $attendeeId)->where('slide_id', $slideId)->where('event_scope_id', $eventScopeId)->lockForUpdate()->first();
            if (! $attempt) {
                return null;
            }
            if ($attempt->submitted_at) {
                return $attempt;
            }

            $questions = AssessmentQuestion::where('slide_id', $slideId)->get();
            $scorableQuestions = $questions->filter(fn ($question) => $question->correct_answer !== null);
            $score = null;
            $maxScore = null;
            if ($scorableQuestions->isNotEmpty()) {
                $answers = DB::table('assessment_answers')->where('attempt_id', $attempt->id)->pluck('answer', 'question_id');
                $score = $scorableQuestions->filter(function ($question) use ($answers) {
                    return isset($answers[$question->id]) && $this->assessmentAnswerIsCorrect($question, json_decode($answers[$question->id], true));
                })->count();
                $maxScore = $scorableQuestions->count();
            }

            DB::table('assessment_attempts')->where('id', $attempt->id)->update([
                'submitted_at' => now(), 'score' => $score, 'max_score' => $maxScore, 'updated_at' => now(),
            ]);

            return DB::table('assessment_attempts')->where('id', $attempt->id)->first();
        });
    }

    public function finishAssessment(Request $request)
    {
        $attendee = $this->attendee($request);
        abort_unless($attendee, 401);
        $data = $request->validate(['slide_id' => ['required', 'integer', 'exists:slides,id']]);
        $slide = Slide::whereKey($data['slide_id'])->where('type', 'assessment')->firstOrFail();
        $state = $this->state();
        abort_unless($state->is_live && (string) $state->current_slide_id === (string) $slide->id, 403);
        $attempt = $this->submitAssessmentAttempt($attendee->id, $slide->id, $this->currentEventScopeId());
        abort_unless($attempt, 403);

        return response()->json(['submitted' => true, 'score' => $attempt->score, 'max_score' => $attempt->max_score]);
    }

    public function answer(Request $request)
    {
        $attendee = $this->attendee($request);
        abort_unless($attendee, 401);
        $data = $request->validate(['slide_id' => ['required', 'exists:slides,id'], 'answer' => ['required', 'string', 'max:2000']]);
        abort_unless(Slide::whereKey($data['slide_id'])->where('type', 'assessment')->exists(), 422);
        $attendee->update(['answer' => $data['answer'], 'answered_slide_id' => $data['slide_id']]);

        return back()->with('message', 'Your response has been sent.');
    }

    public function saveAssessmentAnswer(Request $request)
    {
        $attendee = $this->attendee($request);
        abort_unless($attendee, 401);
        $data = $request->validate(['question_id' => ['required', 'integer', 'exists:assessment_questions,id'], 'answer' => ['present']]);
        $question = AssessmentQuestion::findOrFail($data['question_id']);
        $attempt = DB::table('assessment_attempts')->where('attendee_id', $attendee->id)->where('slide_id', $question->slide_id)->where('event_scope_id', $this->currentEventScopeId())->first();
        abort_unless($attempt, 403);
        abort_if($attempt->submitted_at, 423, 'This assessment has already been submitted.');
        $state = $this->state();
        abort_unless($state->is_live && (string) $state->current_slide_id === (string) $question->slide_id, 403);
        abort_if(! $state->assessment_ends_at || now()->greaterThanOrEqualTo(Carbon::parse($state->assessment_ends_at)), 410, 'The timer has ended.');
        $order = json_decode($attempt->question_order, true) ?: [];
        abort_unless(in_array($question->id, $order), 403);
        $answer = $data['answer'];
        $answer = json_encode($answer, JSON_UNESCAPED_UNICODE);
        DB::table('assessment_answers')->updateOrInsert(
            ['attempt_id' => $attempt->id, 'question_id' => $question->id],
            ['answer' => $answer, 'lecture_id' => app(LectureContext::class)->id(), 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function saveAssessmentProgress(Request $request)
    {
        $attendee = $this->attendee($request);
        abort_unless($attendee, 401);
        $data = $request->validate(['question_index' => ['required', 'integer', 'min:0']]);
        $state = $this->state();
        abort_unless($state->is_live && $state->current_slide_id, 403);
        $slide = Slide::whereKey($state->current_slide_id)->where('type', 'assessment')->firstOrFail();
        abort_if(! $state->assessment_ends_at || now()->greaterThanOrEqualTo(Carbon::parse($state->assessment_ends_at)), 410);
        $attempt = DB::table('assessment_attempts')->where('attendee_id', $attendee->id)->where('slide_id', $slide->id)->where('event_scope_id', $this->currentEventScopeId())->first();
        abort_unless($attempt, 403);
        abort_if($attempt->submitted_at, 423, 'This assessment has already been submitted.');
        $order = json_decode($attempt->question_order, true) ?: [];
        $position = min((int) $data['question_index'], max(0, count($order) - 1));
        DB::table('assessment_attempts')->where('id', $attempt->id)->update(['current_position' => $position, 'updated_at' => now()]);

        return response()->json(['saved' => true, 'question_index' => $position]);
    }
}
