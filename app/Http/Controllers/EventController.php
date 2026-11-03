<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Lecture;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $events = $this->filteredEvents($request, false);

        return view('events.index', [
            'events' => $events,
            'lectures' => $this->availableLectures($request),
            'creators' => $this->eventCreators(),
            'admins' => $this->admins(),
            'archived' => false,
            'activeNav' => 'events',
        ]);
    }

    public function archive(Request $request): View
    {
        return view('events.index', [
            'events' => $this->filteredEvents($request, true),
            'lectures' => $this->availableLectures($request),
            'creators' => $this->eventCreators(),
            'admins' => $this->admins(),
            'archived' => true,
            'activeNav' => 'events',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->events()->create($this->eventData($request));

        return redirect()->route('events.index')->with('message', 'Event created.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeCreator($request, $event);
        abort_if($event->archived_at, 404);
        $previousBackground = $event->background_image_path;
        $event->update($this->eventData($request));
        if ($previousBackground && $previousBackground !== $event->background_image_path) {
            Storage::disk('public')->delete($previousBackground);
        }

        return redirect()->route('events.index')->with('message', 'Event updated.');
    }

    public function storeInArchive(Request $request, Event $event): RedirectResponse
    {
        abort_unless((int) $event->creator_id === (int) $request->user()->id || $request->user()->isSuperAdmin(), 403);
        abort_if($event->archived_at, 404);
        $event->update(['archived_at' => now()]);

        return redirect()->route('events.index')->with('message', 'Event archived.');
    }

    public function reset(Request $request, Event $event): RedirectResponse
    {
        abort_unless((int) $event->creator_id === (int) $request->user()->id || $request->user()->isSuperAdmin(), 403);
        abort_if($event->archived_at, 404);

        $data = $request->validate([
            'scope' => ['required', 'string', Rule::in(['assessments', 'group_submissions', 'registrations', 'all'])],
        ]);

        DB::transaction(function () use ($event, $data): void {
            if (in_array($data['scope'], ['assessments', 'all'], true)) {
                DB::table('assessment_attempts')->where('event_id', $event->id)->delete();
            }

            if (in_array($data['scope'], ['group_submissions', 'all'], true)) {
                DB::table('group_activity_submissions')->where('event_id', $event->id)->delete();
                DB::table('group_activity_grades')->where('event_id', $event->id)->delete();
            }

            if (in_array($data['scope'], ['registrations', 'all'], true)) {
                DB::table('attendee_event')->where('event_id', $event->id)->delete();
            }

            if ($data['scope'] === 'all') {
                DB::table('deck_state')->where('event_id', $event->id)->delete();
            }
        });

        $label = [
            'assessments' => 'assessment submissions',
            'group_submissions' => 'group submissions and grades',
            'registrations' => 'student registrations',
            'all' => 'all event data',
        ][$data['scope']];

        return redirect()->route('events.index')->with('message', 'Reset '.$label.' for "'.$event->title.'".');
    }

    public function restore(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeCreator($request, $event);
        abort_if(! $event->archived_at, 404);
        $event->update(['archived_at' => null]);

        return redirect()->route('events.archive')->with('message', 'Event restored.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if(! $event->archived_at, 404);
        $backgroundPath = $event->background_image_path;
        $event->delete();
        if ($backgroundPath) {
            Storage::disk('public')->delete($backgroundPath);
        }

        return redirect()->route('events.archive')->with('message', 'Event deleted.');
    }

    public function participants(Event $event): JsonResponse
    {
        $participants = DB::table('attendee_event as registrations')
            ->join('attendees', 'attendees.id', '=', 'registrations.attendee_id')
            ->where('registrations.event_id', $event->id)
            ->orderBy('registrations.group_label')
            ->orderBy('attendees.name')
            ->paginate(20, [
                'attendees.name',
                'registrations.group_label',
                'registrations.created_at as registered_at',
                'registrations.attended_at',
            ]);

        $groups = DB::table('attendee_event')
            ->where('event_id', $event->id)
            ->selectRaw("COALESCE(group_label, 'No group') as label, COUNT(*) as total")
            ->groupBy('group_label')
            ->orderBy('group_label')
            ->get();

        return response()->json([
            'participants' => $participants->items(),
            'groups' => $groups,
            'current_page' => $participants->currentPage(),
            'last_page' => $participants->lastPage(),
            'total' => $participants->total(),
        ]);
    }

    private function filteredEvents(Request $request, bool $archived): LengthAwarePaginator
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'lecture_id' => ['nullable', 'integer', 'exists:lectures,id'],
            'creator_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return Event::query()
            ->when($archived, fn (Builder $query) => $query->whereNotNull('archived_at'), fn (Builder $query) => $query->whereNull('archived_at'))
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $search = '%'.trim($filters['search']).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('location', 'like', $search)
                        ->orWhere('audience', 'like', $search)
                        ->orWhere('lecturer', 'like', $search)
                        ->orWhereHas('lecturerUser', fn (Builder $lecturer) => $lecturer->where('name', 'like', $search))
                        ->orWhereHas('lecture', fn (Builder $lecture) => $lecture->where('title', 'like', $search));
                });
            })
            ->when($request->filled('lecture_id'), fn (Builder $query) => $query->where('lecture_id', $request->integer('lecture_id')))
            ->when($request->filled('creator_id'), fn (Builder $query) => $query->where('creator_id', $request->integer('creator_id')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('starts_at', '>=', $filters['date_from']))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('starts_at', '<=', $filters['date_to']))
            ->with(['creator:id,name,email', 'lecture:id,title', 'lecturerUser:id,name'])
            ->withCount('attendees')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();
    }

    private function validatedEvent(Request $request): array
    {
        $data = $request->validate([
            'lecture_id' => ['required', 'integer', Rule::exists('lectures', 'id')->where(function ($query) use ($request): void {
                $query->where('is_active', true)->whereNull('archived_at')->where(function ($query) use ($request): void {
                    $query->where('creator_id', $request->user()->id)->orWhere('visibility', 'public');
                });
            })],
            'lecturer_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:180'],
            'location' => ['required', 'string', 'max:255'],
            'audience' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'expected_participants' => ['required', 'integer', 'min:1', 'max:1000000'],
            'group_count' => [
                'required', 'integer', 'min:0', 'max:26',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ((int) $value > 0 && (int) $value > (int) $request->input('expected_participants')) {
                        $fail('Expected participants must be at least the number of groups.');
                    }
                },
            ],
            'allow_pre_registration' => ['required', 'boolean'],
            'background_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'background_opacity' => ['required', 'integer', 'min:0', 'max:100'],
            'remove_background_image' => ['nullable', 'boolean'],
        ]);

        $data['date'] = Carbon::parse($data['starts_at'])->toDateString();
        $data['lecturer'] = User::query()->findOrFail($data['lecturer_id'])->name;

        return $data;
    }

    private function eventData(Request $request): array
    {
        $data = $this->validatedEvent($request);
        unset($data['background_image'], $data['remove_background_image']);

        if ($request->hasFile('background_image')) {
            $path = $request->file('background_image')->store('event-backgrounds', 'public');
            if (! $path) {
                throw ValidationException::withMessages(['background_image' => 'The event background could not be saved. Please try another image.']);
            }
            $data['background_image_path'] = $path;
        } elseif ($request->boolean('remove_background_image')) {
            $data['background_image_path'] = null;
        }

        return $data;
    }

    private function availableLectures(Request $request): Collection
    {
        return Lecture::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where(fn (Builder $query) => $query->where('creator_id', $request->user()->id)->orWhere('visibility', 'public'))
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    private function eventCreators(): Collection
    {
        return User::query()->whereHas('events')->orderBy('name')->get(['id', 'name']);
    }

    private function admins(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    private function authorizeCreator(Request $request, Event $event): void
    {
        abort_unless((int) $event->creator_id === (int) $request->user()->id, 403);
    }
}
