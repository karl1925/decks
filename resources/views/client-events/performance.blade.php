<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My performance · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('student.css') }}"><link rel="stylesheet" href="{{ asset('theme.css') }}"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">@vite('resources/css/app.css')
</head>
<body class="client-bg client-events-page">
    @include('partials.client-events-sidebar')
    <main class="client-events-main">
        <header class="client-events-header"><div><div class="eyebrow">EVENT PERFORMANCE</div><h1>{{ $event->title }}</h1><p>{{ $event->lecture?->title }} · {{ $event->starts_at->format('M j, Y') }}</p></div><a class="button ghost" href="{{ route('client.events.history') }}">← History</a></header>
        <section class="client-event-performance-list" aria-label="Assessment scores">
            <div class="client-event-section-heading"><div><span class="client-event-kicker">ASSESSMENT RESULTS</span><h2>My scores</h2></div></div>
            @forelse ($assessments as $assessment)
                <article class="client-event-score-row"><div><h3>{{ $assessment->title }}</h3><small>Submitted {{ \Illuminate\Support\Carbon::parse($assessment->submitted_at)->format('M j, Y g:i A') }}</small></div>@if($assessment->score !== null && $assessment->max_score)<strong>{{ $assessment->score }} <span>/ {{ $assessment->max_score }}</span></strong>@else<span class="client-event-unscored">No automatic score</span>@endif</article>
            @empty
                <div class="client-event-empty"><span>✓</span><strong>No assessment scores for this event</strong><small>Completed assessment results will be listed here.</small></div>
            @endforelse
        </section>
        <section class="client-event-performance-list" aria-label="Group activity grades">
            <div class="client-event-section-heading"><div><span class="client-event-kicker">GROUP ACTIVITY RESULTS</span><h2>My group’s work</h2></div></div>
            @forelse ($groupActivities as $activity)
                <article class="client-event-score-row"><div><h3>{{ $activity->title }}</h3><small>{{ $activity->feedback ?: 'Group grade · shared with all members' }}</small></div><strong>{{ $activity->score }} <span>/ {{ $activity->max_score }}</span></strong></article>
            @empty
                <div class="client-event-empty"><span>◈</span><strong>No group activity grades yet</strong><small>Graded group submissions will be included here for every group member.</small></div>
            @endforelse
        </section>
    </main>
</body>
</html>
