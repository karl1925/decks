<?php

namespace App\Http\Controllers;

use App\Actions\DuplicateStudioOutline;
use App\Models\Lecture;
use App\Models\PresentationChapter;
use App\Models\PresentationDeck;
use App\Models\PresentationSection;
use App\Models\PresentationTopic;
use App\Models\Slide;
use App\Support\LectureContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LectureController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:180'], 'visibility' => ['nullable', 'in:private,public']]);
        $lectures = $this->visibleLectures($request)
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $search = '%'.trim($filters['search']).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhereHas('creator', fn (Builder $creator) => $creator->where('name', 'like', $search));
                });
            })
            ->when(! empty($filters['visibility']), fn (Builder $query) => $query->where('visibility', $filters['visibility']))
            ->with('creator:id,name,email')
            ->withCount(['chapters', 'decks'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('lectures.index', ['lectures' => $lectures, 'archived' => false]);
    }

    public function archive(Request $request): View
    {
        $lectures = $this->visibleLectures($request)
            ->where(function (Builder $query): void {
                $query->whereNotNull('archived_at')->orWhere('is_active', false);
            })
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $search)->orWhere('description', 'like', $search));
            })
            ->with('creator:id,name,email')->withCount(['chapters', 'decks'])
            ->orderByDesc('archived_at')->orderByDesc('id')->paginate(12)->withQueryString();

        return view('lectures.index', ['lectures' => $lectures, 'archived' => true]);
    }

    public function publicIndex(Request $request): View
    {
        $lectures = Lecture::query()
            ->where('visibility', 'public')
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhereHas('creator', fn (Builder $creator) => $creator->where('name', 'like', $search));
                });
            })
            ->when($request->filled('creator'), fn (Builder $query) => $query->where('creator_id', $request->integer('creator')))
            ->with('creator:id,name')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $creators = Lecture::query()->where('visibility', 'public')->where('is_active', true)->whereNull('archived_at')
            ->with('creator:id,name')->get()->pluck('creator')->filter()->unique('id')->sortBy('name');

        return view('public-lectures.index', ['lectures' => $lectures, 'creators' => $creators]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', 'in:private,public'],
            'is_active' => ['required', 'boolean'],
        ]);

        $request->user()->lectures()->create($data);

        return redirect()->route('lectures.index')->with('message', 'Lecture created.');
    }

    public function update(Request $request, Lecture $lecture): RedirectResponse
    {
        $this->authorizeLecture($request, $lecture);
        abort_if($lecture->archived_at, 404);

        $lecture->update($request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', 'in:private,public'],
            'is_active' => ['required', 'boolean'],
        ]));

        return redirect()->route('lectures.index')->with('message', 'Lecture updated.');
    }

    public function duplicate(Request $request, Lecture $lecture, LectureContext $lectureContext, DuplicateStudioOutline $duplicateStudioOutline): RedirectResponse
    {
        $this->authorizeLecture($request, $lecture);
        abort_if($lecture->archived_at || ! $lecture->is_active, 404);
        $lectureContext->set($lecture);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $nodes = collect()
            ->merge(PresentationChapter::pluck('id')->map(fn (int $id): string => 'chapter:'.$id))
            ->merge(PresentationSection::pluck('id')->map(fn (int $id): string => 'section:'.$id))
            ->merge(PresentationTopic::pluck('id')->map(fn (int $id): string => 'topic:'.$id))
            ->merge(PresentationDeck::pluck('id')->map(fn (int $id): string => 'deck:'.$id))
            ->merge(Slide::pluck('id')->map(fn (int $id): string => 'slide:'.$id))
            ->all();

        DB::transaction(function () use ($request, $lecture, $data, $nodes, $duplicateStudioOutline, $lectureContext): void {
            $copy = $request->user()->lectures()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? $lecture->description,
            ]);

            $duplicateStudioOutline->handle($nodes, $copy, $lectureContext);
        });

        return redirect()->route('lectures.index')->with('message', 'Lecture and its outline duplicated. Learner accounts and assessment results were not copied.');
    }

    public function storeInArchive(Request $request, Lecture $lecture): RedirectResponse
    {
        $this->authorizeLecture($request, $lecture);
        abort_if($lecture->archived_at, 404);
        $lecture->update(['archived_at' => now(), 'is_active' => false]);
        DB::table('deck_state')->where('lecture_id', $lecture->id)->update(['is_live' => false, 'updated_at' => now()]);

        return redirect()->route('lectures.index')->with('message', 'Lecture archived.');
    }

    public function restore(Request $request, Lecture $lecture): RedirectResponse
    {
        $this->authorizeLecture($request, $lecture);
        abort_if(! $lecture->archived_at && $lecture->is_active, 404);
        $lecture->update(['archived_at' => null, 'is_active' => true]);

        return redirect()->route('lectures.archive')->with('message', 'Lecture restored.');
    }

    public function destroy(Request $request, Lecture $lecture): RedirectResponse
    {
        $this->authorizeLecture($request, $lecture);
        abort_if(! $lecture->archived_at && $lecture->is_active, 404);
        $lecture->delete();

        return redirect()->route('lectures.archive')->with('message', 'Archived lecture permanently deleted.');
    }

    private function visibleLectures(Request $request): Builder
    {
        return Lecture::query()->where('creator_id', $request->user()->id);
    }

    private function authorizeLecture(Request $request, Lecture $lecture): void
    {
        abort_unless((int) $lecture->creator_id === (int) $request->user()->id, 403);
    }
}
