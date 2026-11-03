<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\GroupActivityComment;
use App\Models\GroupActivityGrade;
use App\Models\GroupActivitySection;
use App\Models\GroupActivitySubmission;
use App\Models\PresentationDeck;
use App\Scopes\ActiveLectureScope;
use App\Support\LectureContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GroupActivityController extends Controller
{
    public function manage(Request $request, PresentationDeck $deck): View
    {
        $this->authorizeDeck($deck);
        abort_unless($this->supportsGroupActivity($deck), 404);

        return view('group-activities.manage', [
            'deck' => $deck->load('groupActivitySections'),
            'eventId' => $request->integer('event_id') ?: $request->session()->get('active_event_id'),
        ]);
    }

    public function storeSection(Request $request, PresentationDeck $deck): RedirectResponse
    {
        $this->authorizeDeck($deck);
        abort_unless($this->supportsGroupActivity($deck), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'input_type' => ['required', Rule::in(['text', 'image', 'file'])],
        ]);
        $deck->groupActivitySections()->create($data + [
            'position' => (int) $deck->groupActivitySections()->max('position') + 1,
        ]);

        return back()->with('message', 'Activity section added.');
    }

    public function deleteSection(GroupActivitySection $section): RedirectResponse
    {
        $deck = $section->deck;
        abort_unless($deck, 404);
        $this->authorizeDeck($deck);
        abort_unless($this->supportsGroupActivity($deck), 404);
        $section->delete();

        return back()->with('message', 'Activity section deleted.');
    }

    public function clientLive(Request $request, PresentationDeck $deck): JsonResponse
    {
        abort_unless($this->supportsGroupActivity($deck), 404);
        [$event, , $registration] = $this->clientContext($request, $deck);

        return response()->json($this->clientActivityData($event, $deck, $registration));
    }

    public function saveSubmission(Request $request, GroupActivitySection $section): RedirectResponse
    {
        $deck = $section->deck;
        abort_unless($deck && $this->supportsGroupActivity($deck), 404);
        [$event, $attendee, $registration] = $this->clientContext($request, $deck);
        abort_unless($registration->is_group_leader, 403);
        $this->assertActivityOpen($event, $deck);

        $rules = $section->input_type === 'text'
            ? ['text_content' => ['required', 'string', 'max:20000']]
            : ['submission_file' => $section->input_type === 'image'
                ? ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480']
                : ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,zip,mp3,wav,mp4,mov', 'max:20480']];
        $data = $request->validate($rules);
        $submission = GroupActivitySubmission::firstOrNew([
            'event_id' => $event->id,
            'section_id' => $section->id,
            'group_label' => $registration->group_label,
        ]);
        $previousFile = $submission->file_path;
        $attributes = [
            'submitted_by' => $attendee->id,
            'text_content' => $section->input_type === 'text' ? $data['text_content'] : null,
        ];
        if ($section->input_type === 'text') {
            $attributes['file_path'] = null;
            $attributes['file_name'] = null;
        }
        if ($request->hasFile('submission_file')) {
            $path = $request->file('submission_file')->store('group-activity-outputs', 'local');
            if (! $path) {
                return back()->withErrors(['submission_file' => 'The group output could not be stored. Please try again.']);
            }
            $attributes['file_path'] = $path;
            $attributes['file_name'] = $request->file('submission_file')->getClientOriginalName();
        }
        $submission->fill($attributes)->save();
        if ($previousFile && ($request->hasFile('submission_file') || $section->input_type === 'text')) {
            Storage::disk('local')->delete($previousFile);
        }

        return back()->with('message', 'Group output saved.');
    }

    public function storeComment(Request $request, GroupActivitySection $section): RedirectResponse
    {
        $deck = $section->deck;
        abort_unless($deck && $this->supportsGroupActivity($deck), 404);
        [$event, $attendee, $registration] = $this->clientContext($request, $deck);
        $this->assertActivityOpen($event, $deck);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $submission = GroupActivitySubmission::firstOrCreate([
            'event_id' => $event->id,
            'section_id' => $section->id,
            'group_label' => $registration->group_label,
        ]);
        $submission->comments()->create(['attendee_id' => $attendee->id, 'body' => $data['body']]);

        return back()->with('message', 'Suggestion added to your group.');
    }

    public function reviewComment(Request $request, GroupActivityComment $comment): RedirectResponse
    {
        $submission = $comment->submission;
        $deck = $submission?->section?->deck;
        abort_unless($deck && $this->supportsGroupActivity($deck), 404);
        [$event, $attendee, $registration] = $this->clientContext($request, $deck);
        abort_unless($registration->is_group_leader, 403);
        abort_unless((int) $submission->event_id === (int) $event->id && $submission->group_label === $registration->group_label, 403);
        $this->assertActivityOpen($event, $deck);
        $data = $request->validate(['status' => ['required', Rule::in(['considered', 'disregarded'])]]);

        DB::transaction(function () use ($comment, $data, $attendee): void {
            $lockedComment = GroupActivityComment::query()->lockForUpdate()->findOrFail($comment->id);

            if ($lockedComment->status !== 'pending') {
                return;
            }

            if ($data['status'] === 'considered') {
                $lockedSubmission = GroupActivitySubmission::query()->lockForUpdate()->findOrFail($lockedComment->submission_id);
                $section = GroupActivitySection::query()->findOrFail($lockedSubmission->section_id);

                if ($section->input_type === 'text') {
                    $existingText = (string) $lockedSubmission->text_content;
                    $appendedText = ($existingText === '' ? '' : "\n\n").$lockedComment->body;

                    if (mb_strlen($existingText.$appendedText) > 20000) {
                        throw ValidationException::withMessages([
                            'comment' => 'This suggestion cannot be added because the group output has reached its text limit.',
                        ]);
                    }

                    $lockedSubmission->update(['text_content' => $existingText.$appendedText]);
                }
            }

            $lockedComment->update(['status' => $data['status'], 'reviewed_by' => $attendee->id]);
        });

        return back()->with('message', 'Suggestion status updated.');
    }

    public function file(Request $request, GroupActivitySubmission $submission): Response
    {
        $deck = $submission->section?->deck;
        abort_unless($deck && $this->supportsGroupActivity($deck), 404);
        [$event, , $registration] = $this->clientContext($request, $deck);
        abort_unless((int) $submission->event_id === (int) $event->id && $submission->group_label === $registration->group_label, 403);
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        $mimeType = Storage::disk('local')->mimeType($submission->file_path) ?: 'application/octet-stream';
        if (str_starts_with($mimeType, 'image/')) {
            return response()->file(Storage::disk('local')->path($submission->file_path), [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return Storage::disk('local')->download($submission->file_path, $submission->file_name);
    }

    public function adminFile(GroupActivitySubmission $submission): Response
    {
        $deck = $submission->section?->deck;
        abort_unless($deck, 404);
        $this->authorizeDeck($deck);
        abort_unless($this->supportsGroupActivity($deck), 404);
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);
        $mimeType = Storage::disk('local')->mimeType($submission->file_path) ?: 'application/octet-stream';

        return str_starts_with($mimeType, 'image/')
            ? response()->file(Storage::disk('local')->path($submission->file_path), ['Content-Type' => $mimeType, 'X-Content-Type-Options' => 'nosniff'])
            : Storage::disk('local')->download($submission->file_path, $submission->file_name);
    }

    public function eventWorks(Event $event): JsonResponse
    {
        abort_unless($event->group_count > 0, 404);
        $decks = PresentationDeck::withoutGlobalScope(ActiveLectureScope::class)
            ->where('lecture_id', $event->lecture_id)
            ->where(function ($query): void {
                $query->where('deck_type', 'group_activity')
                    ->orWhereIn('id', DB::table('slides')->select('deck_id')->where('type', 'group_activity'));
            })
            ->with(['groupActivitySections', 'slides' => fn ($slides) => $slides->withoutGlobalScope(ActiveLectureScope::class)->where('type', 'group_activity')])
            ->orderBy('position')
            ->get();
        $registrations = DB::table('attendee_event as registrations')
            ->join('attendees', 'attendees.id', '=', 'registrations.attendee_id')
            ->where('registrations.event_id', $event->id)
            ->orderBy('attendees.name')
            ->get(['registrations.group_label', 'registrations.is_group_leader', 'attendees.name']);
        $groups = $this->groupLabels($event)->map(function (string $label) use ($registrations, $decks, $event): array {
            $members = $registrations->where('group_label', $label)->values();
            $activities = $decks->map(function (PresentationDeck $deck) use ($label, $event): array {
                $grade = GroupActivityGrade::query()
                    ->where('event_id', $event->id)
                    ->where('deck_id', $deck->id)
                    ->where('group_label', $label)
                    ->first();
                $sections = $deck->groupActivitySections->map(function (GroupActivitySection $section) use ($event, $label): array {
                    $submission = GroupActivitySubmission::query()->with('comments.attendee:id,name')
                        ->where('event_id', $event->id)
                        ->where('section_id', $section->id)
                        ->where('group_label', $label)
                        ->first();

                    return [
                        'title' => $section->title,
                        'input_type' => $section->input_type,
                        'text_content' => $submission?->text_content,
                        'file_name' => $submission?->file_name,
                        'file_url' => $submission?->file_path ? route('admin.group-activity.file', ['submission' => $submission, 'event_id' => $event->id]) : null,
                        'comments' => $submission?->comments->map(fn (GroupActivityComment $comment): array => [
                            'name' => $comment->attendee?->name ?? 'Former attendee',
                            'body' => $comment->body,
                            'status' => $comment->status,
                        ])->all() ?? [],
                    ];
                })->all();

                return ['id' => $deck->id, 'title' => $deck->slides->firstWhere('type', 'group_activity')?->title ?? $deck->title, 'sections' => $sections, 'grade' => $grade];
            })->all();

            return ['label' => $label, 'members' => $members, 'activities' => $activities];
        });

        return response()->json(['groups' => $groups]);
    }

    public function grade(Request $request, Event $event, int $deckId, string $groupLabel): RedirectResponse
    {
        $deck = PresentationDeck::withoutGlobalScope(ActiveLectureScope::class)->findOrFail($deckId);
        abort_unless($event->group_count > 0 && $this->supportsGroupActivity($deck) && (int) $deck->lecture_id === (int) $event->lecture_id, 404);
        abort_unless($this->groupLabels($event)->contains($groupLabel), 404);
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);
        GroupActivityGrade::query()->updateOrCreate(
            ['event_id' => $event->id, 'deck_id' => $deck->id, 'group_label' => $groupLabel],
            ['score' => $data['score'], 'max_score' => 100, 'feedback' => $data['feedback'] ?? null, 'graded_by' => $request->user()->id],
        );

        return back()->with('message', "Group {$groupLabel} grade saved.");
    }

    public function updateLeader(Request $request, Event $event, int $attendeeId): RedirectResponse
    {
        abort_unless((int) $event->creator_id === (int) $request->user()->id || $request->user()->isSuperAdmin(), 403);
        abort_unless($event->group_count > 0, 404);
        $data = $request->validate(['is_group_leader' => ['required', 'boolean']]);
        $registration = DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendeeId)->first();
        abort_unless($registration && filled($registration->group_label), 404);
        DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendeeId)->update([
            'is_group_leader' => $data['is_group_leader'],
            'updated_at' => now(),
        ]);

        return back()->with('message', $data['is_group_leader'] ? 'Group leader added.' : 'Group leader removed.');
    }

    private function supportsGroupActivity(PresentationDeck $deck): bool
    {
        return $deck->deck_type === 'group_activity'
            || DB::table('slides')->where('deck_id', $deck->id)->where('type', 'group_activity')->exists();
    }

    private function assertActivityOpen(Event $event, PresentationDeck $deck): void
    {
        $activitySlideId = DB::table('slides')
            ->where('deck_id', $deck->id)
            ->where('type', 'group_activity')
            ->value('id');
        abort_unless($activitySlideId, 404);

        $deckState = DB::table('deck_state')
            ->where('lecture_id', $event->lecture_id)
            ->where('event_id', $event->id)
            ->first();
        abort_unless(
            $deckState
                && $deckState->is_live
                && (int) $deckState->current_slide_id === (int) $activitySlideId,
            423,
            'This group activity is no longer open.'
        );
        abort_if(
            $deckState->section_timer_ends_at
                && now()->greaterThanOrEqualTo($deckState->section_timer_ends_at),
            423,
            'The activity timer has ended. Group work can no longer be changed.'
        );
    }

    private function authorizeDeck(PresentationDeck $deck): void
    {
        abort_unless((int) $deck->lecture_id === (int) app(LectureContext::class)->id(), 404);
    }

    private function clientContext(Request $request, PresentationDeck $deck): array
    {
        $attendeeId = $request->session()->get('attendee_id');
        abort_unless($attendeeId, 401);
        $attendee = Attendee::withoutGlobalScope(ActiveLectureScope::class)->findOrFail((int) $attendeeId);
        $eventId = $request->integer('event_id') ?: (int) $request->session()->get('active_event_id');
        $event = Event::query()->whereKey($eventId)->firstOrFail();
        abort_unless($event->group_count > 0 && (int) $event->lecture_id === (int) $deck->lecture_id, 403);
        $registration = DB::table('attendee_event')->where('event_id', $event->id)->where('attendee_id', $attendee->id)->first();
        abort_unless($registration && filled($registration->group_label), 403);

        return [$event, $attendee, $registration];
    }

    private function clientActivityData(Event $event, PresentationDeck $deck, object $registration): array
    {
        $deckState = DB::table('deck_state')
            ->where('lecture_id', $event->lecture_id)
            ->where('event_id', $event->id)
            ->first();
        $timerFinished = $deckState?->section_timer_ends_at
            ? now()->greaterThanOrEqualTo($deckState->section_timer_ends_at)
            : false;
        $sections = $deck->groupActivitySections()->get()->map(function (GroupActivitySection $section) use ($event, $registration): array {
            $submission = GroupActivitySubmission::query()->with('comments.attendee:id,name')
                ->where('event_id', $event->id)
                ->where('section_id', $section->id)
                ->where('group_label', $registration->group_label)
                ->first();

            return [
                'id' => $section->id,
                'title' => $section->title,
                'input_type' => $section->input_type,
                'submission_url' => route('client.group-activity.submission', ['section' => $section, 'event_id' => $event->id]),
                'comment_url' => route('client.group-activity.comments.store', ['section' => $section, 'event_id' => $event->id]),
                'submission' => $submission ? [
                    'text_content' => $submission->text_content,
                    'file_name' => $submission->file_name,
                    'file_url' => $submission->file_path ? route('client.group-activity.file', ['submission' => $submission, 'event_id' => $event->id]) : null,
                    'updated_at' => $submission->updated_at?->toIso8601String(),
                    'updated_at_ms' => $submission->updated_at?->valueOf(),
                ] : null,
                'comments' => $submission?->comments->map(fn (GroupActivityComment $comment): array => [
                    'id' => $comment->id,
                    'name' => $comment->attendee?->name ?? 'Former attendee',
                    'body' => $comment->body,
                    'status' => $comment->status,
                    'created_at' => $comment->created_at?->format('M j, g:i A'),
                    'updated_at_ms' => $comment->updated_at?->valueOf(),
                ])->all() ?? [],
            ];
        });
        $version = $sections->flatMap(fn (array $section): array => collect($section['comments'])
            ->pluck('updated_at_ms')
            ->push($section['submission']['updated_at_ms'] ?? 0)
            ->all())->max() ?? 0;

        return [
            'event_id' => $event->id,
            'group_label' => $registration->group_label,
            'is_group_leader' => (bool) $registration->is_group_leader,
            'timer_finished' => $timerFinished,
            'can_edit' => ! $timerFinished,
            'sections' => $sections->all(),
            'version' => $version,
        ];
    }

    private function groupLabels(Event $event): Collection
    {
        return collect($event->group_count > 0 ? range('A', chr(ord('A') + $event->group_count - 1)) : []);
    }
}
