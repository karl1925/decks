<article class="assessment-end-board">
    <h4>{{ $heading }}</h4>
    @if(!$board || empty($board['entries']))
        <p class="leaderboard-empty">No scored submissions yet.</p>
    @else
        <ol>
            @foreach($board['entries'] as $entry)
                <li>
                    <span class="end-rank">{{ $entry['rank'] }}</span>
                    <strong>{{ $entry['name'] }}</strong>
                    <span>{{ $entry['score'] }}/{{ $entry['max_score'] }}</span>
                </li>
            @endforeach
        </ol>
        <p class="leaderboard-note">Ties share a rank.</p>
    @endif
</article>
