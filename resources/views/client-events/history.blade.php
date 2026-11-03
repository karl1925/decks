<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Event history · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('student.css') }}"><link rel="stylesheet" href="{{ asset('theme.css') }}"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">@vite('resources/css/app.css')
</head>
<body class="client-bg client-events-page">
    @include('partials.client-events-sidebar')
    <main class="client-events-main">
        <header class="client-events-header"><div><div class="eyebrow">YOUR PAST SESSIONS</div><h1>History</h1><p>Events you joined and the assessments you completed.</p></div></header>
        <div class="client-event-grid">
            @forelse ($events as $event)
                <article class="client-event-card history-event-card"><span class="client-event-status">Attended</span><h2>{{ $event->title }}</h2><p>{{ $event->lecture?->title }}</p><div class="client-event-meta"><span>{{ $event->location }}</span><span>{{ $event->starts_at->format('M j, Y') }}</span></div><a class="button ghost full" href="{{ route('client.events.performance', $event) }}">View my performance <span>→</span></a></article>
            @empty
                <div class="client-event-empty"><span>◷</span><strong>No event history yet</strong><small>Events you attend will appear here after they end.</small><a class="button primary" href="{{ route('client.events.register') }}">Find an event <span>→</span></a></div>
            @endforelse
        </div>
    </main>
</body>
</html>
