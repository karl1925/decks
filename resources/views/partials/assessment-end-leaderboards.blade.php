@if(!empty($assessment_results))
<section class="assessment-end-leaderboards" data-assessment-results-version="{{ $assessment_results['version'] ?? 0 }}">
    <h2>Assessment results</h2>

    <section class="assessment-results-group">
        <h3>This assessment · Top 3</h3>
        <div class="assessment-end-leaderboard-grid">
            @include('partials.assessment-end-board', ['board' => $assessment_results['assessment'] ?? null, 'heading' => 'Top students'])
        </div>
    </section>

    <section class="assessment-results-group">
        <h3>Overall · Top 3</h3>
        <div class="assessment-end-leaderboard-grid">
            @include('partials.assessment-end-board', ['board' => $assessment_results['overall'] ?? null, 'heading' => 'Top students'])
        </div>
    </section>
</section>
@endif
