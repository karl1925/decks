<div class="sidebar-sort-item sidebar-root-sort-item" data-sort-kind="section" data-sort-id="{{ $section->id }}" draggable="{{ $section->section_type !== 'title' ? 'true' : 'false' }}">
    <details class="sidebar-outline-node sidebar-section-node" open><summary><span class="sidebar-drag-handle" title="Drag to reorder or move">⠿</span><span class="sidebar-tree-caret">⌄</span><span class="sidebar-tree-kind">SECTION</span><a class="sidebar-tree-title {{ $selectedSection?->id === $section->id ? 'selected-outline-link' : '' }}" href="{{ $eventId ? route('events.lecture',['event'=>$eventId,'lecture'=>app(\App\Support\LectureContext::class)->id(),'section'=>$section->id]) : route('admin.lecture',['lecture'=>app(\App\Support\LectureContext::class)->id(),'section'=>$section->id]) }}">{{ $section->title }}</a></summary>
        <span class="sidebar-section-meta">{{ ucfirst($section->section_type) }}@if($section->duration_minutes) · {{ $section->duration_minutes }} min @endif</span>
        <div class="sidebar-sort-container" data-sort-parent-kind="section" data-sort-parent-id="{{ $section->id }}" data-sort-child-kind="deck">
            @foreach($section->decks as $deck)@include('partials.sidebar-outline-deck',['deck'=>$deck])@endforeach
        </div>
    </details>
</div>
