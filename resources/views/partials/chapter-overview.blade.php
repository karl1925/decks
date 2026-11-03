<section class="chapter-overview-screen" data-chapter-id="{{ $presented_chapter['id'] }}" data-chapter-version="{{ $presented_chapter['version'] }}">
    <div class="eyebrow"><span class="live-dot"></span> CHAPTER OVERVIEW</div>
    <h1>{{ $presented_chapter['title'] }}</h1>
    <p class="chapter-overview-intro">Topics in this chapter</p>
    @foreach($presented_chapter['decks'] as $deck)<div class="chapter-overview-deck chapter-overview-chapter-deck"><span>▤</span>{{ $deck['title'] }}</div>@endforeach
    <div class="chapter-overview-topics">
        @forelse($presented_chapter['topics'] as $topicIndex => $topic)
            <article class="chapter-overview-topic"><div class="chapter-topic-number">{{ str_pad((string)($topicIndex+1),2,'0',STR_PAD_LEFT) }}</div><div class="chapter-topic-content"><div class="chapter-topic-kind">TOPIC</div><h2>{{ $topic['title'] }}</h2>@foreach($topic['decks'] as $deck)<div class="chapter-overview-deck"><span>▤</span>{{ $deck['title'] }}</div>@endforeach</div></article>
        @empty<p class="chapter-overview-empty">The host is still preparing this chapter’s outline.</p>@endforelse
    </div>
</section>
