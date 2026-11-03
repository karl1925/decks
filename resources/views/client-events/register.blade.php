<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Register · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('student.css') }}"><link rel="stylesheet" href="{{ asset('theme.css') }}"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">@vite('resources/css/app.css')
</head>
<body class="client-bg client-events-page">
    @include('partials.client-events-sidebar')
    <main class="client-events-main">
        <header class="client-events-header"><div><div class="eyebrow">FIND YOUR NEXT SESSION</div><h1>Register</h1><p>Browse ongoing and upcoming events connected to your lectures.</p></div></header>
        @if ($errors->any())<div class="client-event-error" role="alert">{{ $errors->first() }}</div>@endif
        <div class="client-event-grid register-event-grid">
            @forelse ($events as $event)
                @php($ongoing = $event->starts_at?->lte(now()))
                @php($canRegister = $ongoing || $event->allow_pre_registration)
                <article class="client-event-card {{ $ongoing ? 'ongoing' : 'upcoming' }}">
                    <span class="client-event-status {{ $ongoing ? 'live' : '' }}">{{ $ongoing ? 'Ongoing · Join now' : 'Upcoming' }}</span>
                    <h2>{{ $event->title }}</h2><p>{{ $event->lecture?->title }}</p>
                    <div class="client-event-meta"><span>{{ $event->location }}</span><span>{{ $event->starts_at->format('M j, Y · g:i A') }}</span></div>
                    <div class="client-event-capacity"><span>{{ number_format($event->registered_count) }} / {{ number_format($event->expected_participants) }} registered</span>@if($event->group_count)<span>{{ $event->group_count }} groups</span>@endif</div>
                    @if ($event->group_count > 0)
                        <div class="client-event-group-counts">@foreach(range('A', chr(ord('A') + $event->group_count - 1)) as $group)<span>{{ $group }} <small>{{ $event->group_counts[$group] ?? 0 }}/{{ intdiv($event->expected_participants, $event->group_count) }}</small></span>@endforeach</div>
                    @endif
                    @if ($event->registration_full)
                        <button class="button ghost full" type="button" disabled>Event is full</button>
                    @elseif (! $canRegister)
                        <button class="button ghost full" type="button" disabled>Pre-registration is closed</button>
                    @elseif ($event->group_count > 0)
                        <a class="button primary full" href="{{ route('client.events.groups', $event) }}">Choose a group <span>→</span></a>
                    @else
                        <form method="post" action="{{ route('client.events.register.store', $event) }}">@csrf<button class="button primary full" type="submit">{{ $ongoing ? 'Join event' : 'Register' }} <span>→</span></button></form>
                    @endif
                </article>
            @empty
                <div class="client-event-empty"><span>▣</span><strong>No events to register for</strong><small>New ongoing and upcoming events will appear here.</small><a class="button ghost" href="{{ route('client.events.my') }}">Back to My Events</a></div>
            @endforelse
        </div>
    </main>
</body>
</html>
