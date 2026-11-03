@php
    $features = collect();
    foreach ($deck->slides as $deckSlide) {
        if ($deckSlide->layout_key === 'leaderboard') {
            $features->push('leaderboard');
            continue;
        }
        if ($deckSlide->type === 'assessment') {
            $features->push('assessment');
            continue;
        }
        $layout = $deckSlide->layout_data ?? [];
        $slideFeatures = collect();
        if ($deckSlide->type === 'video') $slideFeatures->push('video');
        if (!empty($layout['image_url']) || ($deckSlide->type !== 'video' && $deckSlide->media_url)) $slideFeatures->push('image');
        if (!empty($layout['table'])) $slideFeatures->push('table');
        if (!empty($layout['cards'])) $slideFeatures->push('graphics');
        $features = $features->merge($slideFeatures->isEmpty() ? ['text'] : $slideFeatures);
    }
    $features = $features->unique()->values();
    $deckVisual = $features->count() > 1 ? 'mixed' : ($features->first() ?? 'empty');
    $deckIcon = match($deckVisual) {
        'assessment' => '✳',
        'leaderboard' => '♛',
        'video' => '▶',
        'image' => '▧',
        'table' => '▦',
        'graphics' => '◈',
        'text' => 'T',
        'mixed' => '◫',
        default => '＋',
    };
    $deckLabel = match($deckVisual) {
        'assessment' => 'Assessment deck',
        'leaderboard' => 'Leaderboard deck',
        'video' => 'Video deck',
        'image' => 'Image deck',
        'table' => 'Table deck',
        'graphics' => 'Graphics deck',
        'text' => 'Text only deck',
        'mixed' => 'Mixed content: '.$features->implode(', '),
        default => 'Empty deck',
    };
    if ($deck->deck_type === 'content' && $deck->content_type === 'discussion') $deckLabel = 'Discussion deck';
    if ($deck->deck_type === 'content' && $deck->content_type === 'assessment') $deckLabel = 'Assessment deck';
@endphp
<div class="sidebar-sort-item sidebar-deck-sort-item sidebar-deck {{ $presenterMode && $current?->deck_id === $deck->id ? 'sidebar-deck-active' : '' }} {{ $deck->slides->isEmpty() ? 'sidebar-deck-empty' : '' }}" data-sort-kind="deck" data-sort-id="{{ $deck->id }}" data-sort-special="{{ $deck->deck_type !== 'content' ? 'true' : 'false' }}" draggable="{{ !$presenterMode ? 'true' : 'false' }}">
    <span class="sidebar-drag-handle" title="Drag to reorder or move">⠿</span>
    <div class="sidebar-deck-link"><span class="sidebar-deck-icon deck-icon-{{ $deck->content_type === 'assessment' ? 'assessment' : $deckVisual }}" role="img" aria-label="{{ $deckLabel }}" title="{{ $deckLabel }}">{{ $deck->content_type === 'assessment' ? '✳' : $deckIcon }}</span><span class="sidebar-deck-copy"><strong>{{ $deck->title }}</strong><small>{{ $deck->slides->count() }} {{ \Illuminate\Support\Str::plural('slide', $deck->slides->count()) }} · {{ $deckLabel }}@if($deck->slides->isEmpty()) · Needs slides @endif @if($deck->content_type === 'discussion' && $deck->slides->first()) · {{ str_replace('_',' ',$deck->slides->first()->layout_key) }} layout @endif @if($deck->deck_type !== 'content') · {{ ucfirst($deck->deck_type) }} @if($deck->duration_minutes) · {{ $deck->duration_minutes }} min @endif @endif</small></span></div>
    @if($presenterMode)<form method="post" action="{{ route('admin.control') }}">@csrf<input type="hidden" name="action" value="start"><input type="hidden" name="deck_id" value="{{ $deck->id }}"><button type="submit" @disabled($deck->slides->isEmpty()) title="{{ $deck->slides->isEmpty() ? 'Add slides to present this deck' : 'Present this deck' }}">▶</button></form>@else<span class="outline-item-actions"><button type="button" class="outline-icon-button" title="Rename deck" aria-label="Rename deck" onclick="editOutlineItem('deck',{{ $deck->id }},@js($deck->title))">✎</button><button type="button" class="outline-icon-button danger" title="Delete deck" aria-label="Delete {{ $deck->title }}" onclick="deleteOutlineItem('deck',{{ $deck->id }},@js($deck->title))">×</button></span>@endif
</div>
