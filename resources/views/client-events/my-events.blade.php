<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Events · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('student.css') }}"><link rel="stylesheet" href="{{ asset('theme.css') }}"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">@vite('resources/css/app.css')
</head>
<body class="client-bg client-events-page">
    @include('partials.client-events-sidebar')
    <main class="client-events-main">
        <header class="client-events-header"><div><div class="eyebrow">STUDENT EVENTS</div><h1>My Events</h1><p>Keep track of your upcoming events and live sessions.</p></div><a class="button primary" href="{{ route('client.events.register') }}">Register for an event <span>→</span></a></header>
        @if (session('message'))<div class="client-event-message" role="status">{{ session('message') }}</div>@endif
        @if ($errors->any())<div class="client-event-error" role="alert">{{ $errors->first() }}</div>@endif

        <section class="client-event-section">
            <div class="client-event-section-heading"><div><span class="client-event-kicker live">LIVE NOW</span><h2>Ongoing events</h2></div><span>{{ $ongoingEvents->count() }} {{ Str::plural('event', $ongoingEvents->count()) }}</span></div>
            <div class="client-event-grid">
                @forelse ($ongoingEvents as $event)
                    @if ($event->group_count > 0 && blank($event->pivot->group_label))
                        <article class="client-event-card ongoing"><span class="client-event-status live">Ongoing</span><h3>{{ $event->title }}</h3><p>{{ $event->lecture?->title }}</p><div class="client-event-meta"><span>{{ $event->location }}</span><span>{{ $event->starts_at->format('M j · g:i A') }} – {{ $event->ends_at->format('g:i A') }}</span></div><a class="button primary" href="{{ route('client.events.groups', $event) }}">Choose your group <span>→</span></a></article>
                    @else
                        <a class="client-event-card ongoing" href="{{ route('client.event.presentation', $event) }}"><span class="client-event-status live"><i></i> Ongoing · Join now</span><h3>{{ $event->title }}</h3><p>{{ $event->lecture?->title }}</p><div class="client-event-meta"><span>{{ $event->location }}</span><span>{{ $event->starts_at->format('M j · g:i A') }} – {{ $event->ends_at->format('g:i A') }}</span></div>@if($event->group_count)<span class="client-event-group-chip">Group {{ $event->pivot->group_label }}</span>@endif<span class="client-event-card-link">Open presentation <b>↗</b></span></a>
                    @endif
                @empty
                    <div class="client-event-empty"><span>▣</span><strong>No ongoing events</strong><small>Join a live event when it starts.</small></div>
                @endforelse
            </div>
        </section>

        <section class="client-event-section">
            <div class="client-event-section-heading"><div><span class="client-event-kicker">COMING UP</span><h2>Upcoming events</h2></div><span>{{ $upcomingEvents->count() }} {{ Str::plural('event', $upcomingEvents->count()) }}</span></div>
            <div class="client-event-grid">
                @forelse ($upcomingEvents as $event)
                    <article class="client-event-card upcoming">
                        <button class="client-event-details-trigger" type="button" data-event-details data-title="{{ $event->title }}" data-lecture="{{ $event->lecture?->title }}" data-lecturer="{{ $event->lecturerUser?->name ?? $event->lecturer }}" data-location="{{ $event->location }}" data-audience="{{ $event->audience }}" data-starts-at="{{ $event->starts_at->format('M j, Y g:i A') }}" data-ends-at="{{ $event->ends_at->format('M j, Y g:i A') }}" data-capacity="{{ $event->expected_participants }}" data-groups="{{ $event->group_count }}">View event details <span>↗</span></button>
                        <span class="client-event-status">Upcoming</span><h3>{{ $event->title }}</h3><p>{{ $event->lecture?->title }}</p><div class="client-event-meta"><span>{{ $event->location }}</span><span>{{ $event->starts_at->format('M j · g:i A') }}</span></div>
                        @if ($event->group_count > 0 && blank($event->pivot->group_label))<a class="client-event-inline-link" href="{{ route('client.events.groups', $event) }}">Choose your group</a>@elseif($event->group_count > 0)<span class="client-event-group-chip">Group {{ $event->pivot->group_label }}</span>@endif
                        <form method="post" action="{{ route('client.events.unregister', $event) }}" data-confirm="Unregister from {{ $event->title }}?">@csrf @method('DELETE')<button class="client-event-unregister" type="submit">Unregister</button></form>
                    </article>
                @empty
                    <div class="client-event-empty"><span>◷</span><strong>No upcoming events</strong><small>Browse events and reserve your place.</small><a class="button primary" href="{{ route('client.events.register') }}">Register for an event <span>→</span></a></div>
                @endforelse
            </div>
        </section>
    </main>

    <dialog class="client-event-dialog" id="event-details-dialog"><form method="dialog"><button class="client-event-dialog-close" aria-label="Close">×</button></form><span class="client-event-status">Event details</span><h2 id="details-title"></h2><p id="details-lecture"></p><dl><div><dt>Lecturer</dt><dd id="details-lecturer"></dd></div><div><dt>Location</dt><dd id="details-location"></dd></div><div><dt>Audience</dt><dd id="details-audience"></dd></div><div><dt>From</dt><dd id="details-starts"></dd></div><div><dt>To</dt><dd id="details-ends"></dd></div><div><dt>Expected participants</dt><dd id="details-capacity"></dd></div><div><dt>Groups</dt><dd id="details-groups"></dd></div></dl></dialog>
    <script>
        const eventDetailsDialog = document.getElementById('event-details-dialog');
        const detailFields = { title: 'details-title', lecture: 'details-lecture', lecturer: 'details-lecturer', location: 'details-location', audience: 'details-audience', startsAt: 'details-starts', endsAt: 'details-ends', capacity: 'details-capacity', groups: 'details-groups' };
        document.querySelectorAll('[data-event-details]').forEach((button) => button.addEventListener('click', () => {
            Object.entries(detailFields).forEach(([key, id]) => { document.getElementById(id).textContent = button.dataset[key] || '—'; });
            document.getElementById('details-groups').textContent = button.dataset.groups === '0' ? 'No groups' : button.dataset.groups;
            eventDetailsDialog.showModal();
        }));
    </script>
</body>
</html>
