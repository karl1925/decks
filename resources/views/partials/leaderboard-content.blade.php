<section class="leaderboard-content" data-leaderboard-version="{{ $leaderboard['version'] ?? 0 }}">
    <div class="deck-eyebrow">{{ ($leaderboard['type'] ?? null) === 'overall' ? 'OVERALL RESULTS' : 'ASSESSMENT RESULTS' }}</div>
    <h1>{{ $leaderboard['title'] ?? $slide->title }}</h1>
    @if (empty($leaderboard['entries']))
        <p class="leaderboard-empty">No scored submissions yet.</p>
    @else
        <p class="leaderboard-subtitle">Top three positions · tied students share a rank</p>
        <div class="leaderboard-list">
            @foreach ($leaderboard['entries'] as $entry)
                <article class="leaderboard-row">
                    <span class="leaderboard-rank">{{ $entry['rank'] }}</span>
                    <strong class="leaderboard-name">{{ $entry['name'] }}</strong>
                    <span class="leaderboard-score">{{ $entry['score'] }}<i>/</i>{{ $entry['max_score'] }}</span>
                    <span class="leaderboard-percent">{{ number_format($entry['percent'], 1) }}%</span>
                </article>
            @endforeach
        </div>
        @if (($leaderboard['type'] ?? null) === 'overall')
            <p class="leaderboard-note">Overall scores combine automatically scored questions from submitted assessments.</p>
        @endif
    @endif
</section>
