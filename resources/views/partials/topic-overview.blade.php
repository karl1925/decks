<section class="chapter-overview-screen outline-overview-screen" data-topic-id="{{ $presented_topic['id'] }}" data-topic-version="{{ $presented_topic['version'] }}">
    <div class="eyebrow"><span class="live-dot"></span> TOPIC OVERVIEW</div>
    <div class="outline-overview-breadcrumb">{{ $presented_topic['chapter_title'] }}</div>
    <h1>{{ $presented_topic['title'] }}</h1>
    <p class="chapter-overview-intro">Decks in this topic</p>
    @forelse($presented_topic['decks'] as $deck)
        <div class="chapter-overview-deck"><span>▤</span>{{ $deck['title'] }} <small>{{ $deck['slide_count'] }} {{ \Illuminate\Support\Str::plural('slide', $deck['slide_count']) }}</small></div>
    @empty
        <p class="chapter-overview-empty">No decks in this topic yet.</p>
    @endforelse
</section>
