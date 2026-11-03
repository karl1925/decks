<?php

namespace App\Actions;

use App\Models\AssessmentQuestion;
use App\Models\Lecture;
use App\Models\PresentationChapter;
use App\Models\PresentationDeck;
use App\Models\PresentationSection;
use App\Models\PresentationTopic;
use App\Models\Slide;
use App\Support\LectureContext;

class DuplicateStudioOutline
{
    /**
     * @param  array<int, string>  $selections
     */
    public function handle(array $selections, Lecture $target, LectureContext $lectureContext): void
    {
        $selected = collect($selections)
            ->mapWithKeys(function (string $selection): array {
                [$kind, $id] = explode(':', $selection, 2);

                return [$kind.':'.$id => ['kind' => $kind, 'id' => (int) $id]];
            })
            ->values();

        $chapterIds = $selected->where('kind', 'chapter')->pluck('id')->all();
        $sectionIds = $selected->where('kind', 'section')->pluck('id')->all();
        $topicIds = $selected->where('kind', 'topic')->pluck('id')->all();
        $deckIds = $selected->where('kind', 'deck')->pluck('id')->all();
        $slideIds = $selected->where('kind', 'slide')->pluck('id')->all();

        $chapters = PresentationChapter::with(['decks.slides', 'topics.decks.slides'])->whereKey($chapterIds)->get();
        $sections = PresentationSection::with(['decks.slides', 'topics.decks.slides'])->whereKey($sectionIds)->get();
        $topics = PresentationTopic::with('decks.slides')->whereKey($topicIds)->get();
        $decks = PresentationDeck::with('slides')->whereKey($deckIds)->get();
        $slides = Slide::with('presentationDeck')->whereKey($slideIds)->get();

        $lectureContext->set($target);

        foreach ($chapters as $chapter) {
            $this->duplicateChapter($chapter);
        }

        foreach ($sections as $section) {
            $this->duplicateSection($section);
        }

        foreach ($topics as $topic) {
            if (! in_array((int) $topic->chapter_id, $chapterIds, true) && ! in_array((int) $topic->section_id, $sectionIds, true)) {
                $this->duplicateTopic($topic);
            }
        }

        foreach ($decks as $deck) {
            $topicCovered = $deck->topic_id && in_array((int) $deck->topic_id, $topicIds, true);
            $chapterCovered = $deck->chapter_id && in_array((int) $deck->chapter_id, $chapterIds, true);
            $sectionCovered = $deck->section_id && in_array((int) $deck->section_id, $sectionIds, true);
            if (! $topicCovered && ! $chapterCovered && ! $sectionCovered) {
                $this->duplicateDeck($deck);
            }
        }

        foreach ($slides as $slide) {
            $deck = $slide->presentationDeck;
            $deckCovered = $deck && in_array((int) $deck->id, $deckIds, true);
            $topicCovered = $deck?->topic_id && in_array((int) $deck->topic_id, $topicIds, true);
            $chapterCovered = $deck?->chapter_id && in_array((int) $deck->chapter_id, $chapterIds, true);
            if (! $deckCovered && ! $topicCovered && ! $chapterCovered) {
                $this->duplicateSlide($slide, $deck?->id);
            }
        }
    }

    private function duplicateSection(PresentationSection $source): void
    {
        $section = $source->replicate();
        $section->title = $this->copyTitle($source->title);
        $section->position = ((int) PresentationSection::max('position')) + 1;
        $section->save();

        foreach ($source->decks as $deck) {
            $this->duplicateDeck($deck, null, null, $section);
        }

        foreach ($source->topics as $topic) {
            $this->duplicateTopic($topic, null, $section);
        }
    }

    private function duplicateChapter(PresentationChapter $source): void
    {
        $chapter = PresentationChapter::create([
            'title' => $this->copyTitle($source->title, fn (string $title): bool => PresentationChapter::where('title', $title)->exists()),
            'position' => ((int) PresentationChapter::max('position')) + 1,
        ]);

        foreach ($source->decks as $deck) {
            $this->duplicateDeck($deck, $chapter);
        }

        foreach ($source->topics as $topic) {
            $this->duplicateTopic($topic, $chapter);
        }
    }

    private function duplicateTopic(PresentationTopic $source, ?PresentationChapter $targetChapter = null, ?PresentationSection $targetSection = null): void
    {
        $chapterId = $targetChapter?->id ?? ($targetSection ? null : $source->chapter_id);
        $sectionId = $targetChapter ? null : ($targetSection?->id ?? $source->section_id);
        $topic = PresentationTopic::create([
            'chapter_id' => $chapterId,
            'section_id' => $sectionId,
            'title' => $this->copyTitle($source->title, fn (string $title): bool => PresentationTopic::query()
                ->where('section_id', $sectionId)->where('title', $title)->exists()),
            'position' => $this->nextTopicPosition($chapterId, $sectionId),
        ]);

        foreach ($source->decks as $deck) {
            $this->duplicateDeck($deck, null, $topic);
        }
    }

    private function duplicateDeck(PresentationDeck $source, ?PresentationChapter $targetChapter = null, ?PresentationTopic $targetTopic = null, ?PresentationSection $targetSection = null): void
    {
        $deck = $source->replicate();
        $deck->title = $this->copyTitle($source->title);
        $deck->position = $this->nextDeckPosition($source, $targetChapter, $targetTopic);

        if ($targetChapter) {
            $deck->chapter_id = $targetChapter->id;
            $deck->section_id = null;
            $deck->topic_id = null;
        } elseif ($targetTopic) {
            $deck->chapter_id = $targetTopic->chapter_id;
            $deck->section_id = $targetTopic->section_id;
            $deck->topic_id = $targetTopic->id;
        } elseif ($targetSection) {
            $deck->chapter_id = null;
            $deck->section_id = $targetSection->id;
            $deck->topic_id = null;
        }

        $deck->save();

        foreach ($source->slides as $slide) {
            $this->duplicateSlide($slide, $deck->id);
        }
    }

    private function duplicateSlide(Slide $source, ?int $targetDeckId): void
    {
        $slide = $source->replicate();
        $slide->deck_id = $targetDeckId;
        $slide->title = $this->copyTitle($source->title);
        $slide->position = ((int) Slide::max('position')) + 1;
        $slide->save();

        AssessmentQuestion::withoutGlobalScopes()
            ->where('slide_id', $source->id)
            ->where('lecture_id', $source->lecture_id)
            ->orderBy('order_index')
            ->get()
            ->each(function (AssessmentQuestion $question) use ($slide): void {
                $copy = $question->replicate();
                $copy->slide_id = $slide->id;
                $copy->save();
            });
    }

    private function nextTopicPosition(?int $chapterId, ?int $sectionId): int
    {
        $query = PresentationTopic::query();
        $chapterId ? $query->where('chapter_id', $chapterId) : $query->whereNull('chapter_id');
        $sectionId ? $query->where('section_id', $sectionId) : $query->whereNull('section_id');

        return ((int) $query->max('position')) + 1;
    }

    private function nextDeckPosition(PresentationDeck $source, ?PresentationChapter $targetChapter, ?PresentationTopic $targetTopic): int
    {
        $query = PresentationDeck::query();

        if ($targetTopic) {
            $query->where('topic_id', $targetTopic->id);
        } elseif ($targetChapter) {
            $query->where('chapter_id', $targetChapter->id)->whereNull('topic_id');
        } elseif ($targetSection) {
            $query->where('section_id', $targetSection->id)->whereNull('topic_id');
        } elseif ($source->topic_id) {
            $query->where('topic_id', $source->topic_id);
        } elseif ($source->chapter_id) {
            $query->where('chapter_id', $source->chapter_id)->whereNull('topic_id');
        } elseif ($source->section_id) {
            $query->where('section_id', $source->section_id);
        } else {
            $query->whereNull('chapter_id')->whereNull('section_id')->whereNull('topic_id');
        }

        return ((int) $query->max('position')) + 1;
    }

    private function copyTitle(string $title, ?callable $exists = null): string
    {
        $suffix = ' (Copy)';
        $candidate = mb_substr($title, 0, 180 - mb_strlen($suffix)).$suffix;
        $copy = 2;

        while ($exists && $exists($candidate)) {
            $suffix = ' (Copy '.$copy.')';
            $candidate = mb_substr($title, 0, 180 - mb_strlen($suffix)).$suffix;
            $copy++;
        }

        return $candidate;
    }
}
