<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\Event;
use App\Scopes\ActiveLectureScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientEventController extends Controller
{
    public function myEvents(Request $request): View
    {
        $attendee = $this->attendee($request);
        $now = now();
        $events = $attendee->events()
            ->whereNull('events.archived_at')
            ->where('events.ends_at', '>=', $now)
            ->with(['lecture:id,title', 'lecturerUser:id,name'])
            ->orderBy('events.starts_at')
            ->get();

        return view('client-events.my-events', [
            'ongoingEvents' => $events->filter(fn (Event $event): bool => $event->starts_at?->lte($now) ?? false)->values(),
            'upcomingEvents' => $events->filter(fn (Event $event): bool => $event->starts_at?->gt($now) ?? false)->values(),
            'attendee' => $attendee,
            'activeNav' => 'my-events',
        ]);
    }

    public function registerableEvents(Request $request): View
    {
        $attendee = $this->attendee($request);
        $registeredEventIds = DB::table('attendee_event')->where('attendee_id', $attendee->id)->pluck('event_id');
        $events = Event::query()
            ->whereNull('archived_at')
            ->where('ends_at', '>=', now())
            ->whereNotIn('id', $registeredEventIds)
            ->with(['lecture:id,title', 'lecturerUser:id,name'])
            ->orderBy('starts_at')
            ->get();
        $counts = DB::table('attendee_event')
            ->whereIn('event_id', $events->pluck('id'))
            ->select('event_id', 'group_label', DB::raw('COUNT(*) as total'))
            ->groupBy('event_id', 'group_label')
            ->get()
            ->groupBy('event_id');

        $events->each(function (Event $event) use ($counts): void {
            $eventCounts = $counts->get($event->id, collect());
            $registeredCount = (int) $eventCounts->sum('total');
            $groupCounts = $eventCounts->pluck('total', 'group_label')->all();
            $allGroupsFull = $event->group_count > 0 && collect($this->groupLabels($event))->every(
                fn (string $label): bool => (int) ($groupCounts[$label] ?? 0) >= intdiv($event->expected_participants, $event->group_count),
            );
            $event->setAttribute('registered_count', $registeredCount);
            $event->setAttribute('registration_full', $event->group_count > 0 ? $allGroupsFull : $registeredCount >= $event->expected_participants);
            $event->setAttribute('group_counts', $groupCounts);
        });

        return view('client-events.register', ['events' => $events, 'attendee' => $attendee, 'activeNav' => 'register']);
    }

    public function history(Request $request): View
    {
        $attendee = $this->attendee($request);
        $events = $attendee->events()
            ->wherePivotNotNull('attended_at')
            ->where('events.ends_at', '<', now())
            ->with(['lecture:id,title', 'lecturerUser:id,name'])
            ->orderByDesc('events.ends_at')
            ->get();

        return view('client-events.history', ['events' => $events, 'attendee' => $attendee, 'activeNav' => 'history']);
    }

    public function groups(Request $request, Event $event): View
    {
        $attendee = $this->attendee($request);
        $this->ensureEventCanBeJoined($event, $attendee);

        return view('client-events.groups', [
            'event' => $event->load(['lecture:id,title', 'lecturerUser:id,name']),
            'groups' => $this->groupChoices($event, $attendee->id),
            'attendee' => $attendee,
            'registered' => DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->exists(),
            'activeNav' => 'register',
        ]);
    }

    public function register(Request $request, Event $event): RedirectResponse
    {
        $attendee = $this->attendee($request);
        $this->ensureEventCanBeJoined($event, $attendee);
        $data = $request->validate([
            'group_label' => [$event->group_count > 0 ? 'required' : 'nullable', 'string', Rule::in($this->groupLabels($event))],
        ]);

        $this->saveRegistration($event, $attendee, $data['group_label'] ?? null);

        if ($this->isOngoing($event)) {
            return redirect()->route('client.event.presentation', $event)->with('message', 'You joined the event.');
        }

        return redirect()->route('client.events.my')->with('message', 'You registered for the event.');
    }

    public function updateGroup(Request $request, Event $event): RedirectResponse
    {
        $attendee = $this->attendee($request);
        $this->ensureEventCanBeJoined($event, $attendee);
        abort_unless($event->group_count > 0, 404);
        $data = $request->validate(['group_label' => ['required', 'string', Rule::in($this->groupLabels($event))]]);
        $registration = DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->first();
        abort_unless($registration, 403);

        DB::transaction(function () use ($event, $attendee, $data, $registration): void {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->ensureGroupHasCapacity($lockedEvent, $data['group_label'], $attendee->id);
            DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->update([
                'group_label' => $data['group_label'],
                'is_group_leader' => $registration->group_label === $data['group_label'] && (bool) $registration->is_group_leader,
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('client.events.my')->with('message', 'Your group selection was saved.');
    }

    public function unregister(Request $request, Event $event): RedirectResponse
    {
        $attendee = $this->attendee($request);
        abort_unless($event->starts_at?->isFuture() && ! $event->archived_at, 403);
        DB::table('attendee_event')
            ->where('event_id', $event->id)
            ->where('attendee_id', $attendee->id)
            ->whereNull('attended_at')
            ->delete();

        return redirect()->route('client.events.my')->with('message', 'You unregistered from the event.');
    }

    public function performance(Request $request, Event $event): View
    {
        $attendee = $this->attendee($request);
        $registration = DB::table('attendee_event')
            ->where('event_id', $event->id)
            ->where('attendee_id', $attendee->id)
            ->whereNotNull('attended_at')
            ->first();
        abort_unless($registration, 403);

        $assessments = DB::table('assessment_attempts as attempts')
            ->join('slides', 'slides.id', '=', 'attempts.slide_id')
            ->where('attempts.event_id', $event->id)
            ->where('attempts.attendee_id', $attendee->id)
            ->whereNotNull('attempts.submitted_at')
            ->orderBy('attempts.submitted_at')
            ->get(['slides.title', 'attempts.score', 'attempts.max_score', 'attempts.submitted_at']);
        $groupActivities = $registration->group_label
            ? DB::table('group_activity_grades as grades')
                ->join('presentation_decks as decks', 'decks.id', '=', 'grades.deck_id')
                ->where('grades.event_id', $event->id)
                ->where('grades.group_label', $registration->group_label)
                ->orderBy('decks.position')
                ->get(['decks.title', 'grades.score', 'grades.max_score', 'grades.feedback', 'grades.updated_at'])
            : collect();

        return view('client-events.performance', [
            'event' => $event->load('lecture:id,title'),
            'assessments' => $assessments,
            'groupActivities' => $groupActivities,
            'attendee' => $attendee,
            'activeNav' => 'history',
        ]);
    }

    private function saveRegistration(Event $event, Attendee $attendee, ?string $groupLabel): void
    {
        DB::transaction(function () use ($event, $attendee, $groupLabel): void {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $existing = DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->first();

            if ($existing && ($lockedEvent->group_count === 0 || filled($existing->group_label))) {
                return;
            }

            $this->ensureEventCanBeJoined($lockedEvent, $attendee);
            if ($lockedEvent->group_count > 0) {
                $this->ensureGroupHasCapacity($lockedEvent, (string) $groupLabel, $attendee->id);
            } else {
                $registeredCount = DB::table('attendee_event')->where('event_id', $lockedEvent->id)->count();
                if ($registeredCount >= $lockedEvent->expected_participants) {
                    throw ValidationException::withMessages(['event' => 'This event has reached its participant limit.']);
                }
            }

            DB::table('attendee_event')->updateOrInsert(
                ['event_id' => $lockedEvent->id, 'attendee_id' => $attendee->id],
                ['group_label' => $groupLabel, 'updated_at' => now(), 'created_at' => $existing?->created_at ?? now()],
            );
        });
    }

    private function groupChoices(Event $event, int $attendeeId): Collection
    {
        $counts = DB::table('attendee_event')
            ->where('event_id', $event->id)
            ->select('group_label', DB::raw('COUNT(*) as total'))
            ->groupBy('group_label')
            ->pluck('total', 'group_label');
        $registration = DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendeeId)->first();
        $capacity = intdiv($event->expected_participants, max($event->group_count, 1));

        return collect($this->groupLabels($event))->map(fn (string $label): array => [
            'label' => $label,
            'count' => (int) ($counts[$label] ?? 0),
            'capacity' => $capacity,
            'selected' => $registration?->group_label === $label,
            'full' => (int) ($counts[$label] ?? 0) >= $capacity && $registration?->group_label !== $label,
        ]);
    }

    private function ensureGroupHasCapacity(Event $event, string $groupLabel, int $attendeeId): void
    {
        $currentGroup = DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendeeId)->value('group_label');
        if ($currentGroup === $groupLabel) {
            return;
        }

        $capacity = intdiv($event->expected_participants, max($event->group_count, 1));
        $groupCount = DB::table('attendee_event')->where('event_id', $event->id)->where('group_label', $groupLabel)->count();
        if ($groupCount >= $capacity) {
            throw ValidationException::withMessages(['group_label' => 'That group is full. Please select another group.']);
        }
    }

    private function ensureEventCanBeJoined(Event $event, Attendee $attendee): void
    {
        abort_if($event->archived_at || ! $event->ends_at || $event->ends_at->isPast(), 404);
        $isRegistered = DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->exists();
        $upcomingRegistrationAllowed = $event->starts_at?->isFuture() && $event->allow_pre_registration;
        abort_unless($this->isOngoing($event) || $upcomingRegistrationAllowed || $isRegistered, 403);
    }

    private function isOngoing(Event $event): bool
    {
        return (bool) ($event->starts_at?->lte(now()) && $event->ends_at?->gte(now()));
    }

    private function groupLabels(Event $event): array
    {
        return $event->group_count > 0 ? range('A', chr(ord('A') + $event->group_count - 1)) : [];
    }

    private function attendee(Request $request): Attendee
    {
        abort_unless($request->session()->has('attendee_id'), 401);

        return Attendee::withoutGlobalScope(ActiveLectureScope::class)->findOrFail((int) $request->session()->get('attendee_id'));
    }
}
