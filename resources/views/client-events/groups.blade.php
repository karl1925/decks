<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Choose a group · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('student.css') }}"><link rel="stylesheet" href="{{ asset('theme.css') }}"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">@vite('resources/css/app.css')
</head>
<body class="client-bg client-events-page">
    @include('partials.client-events-sidebar')
    <main class="client-events-main">
        <header class="client-events-header"><div><div class="eyebrow">{{ $registered ? 'UPDATE YOUR GROUP' : 'EVENT REGISTRATION' }}</div><h1>Choose a group</h1><p>{{ $event->title }} · {{ $event->starts_at->format('M j, Y · g:i A') }}</p></div><a class="button ghost" href="{{ route('client.events.register') }}">← Events</a></header>
        @if ($errors->any())<div class="client-event-error" role="alert">{{ $errors->first() }}</div>@endif
        <div class="client-event-group-grid">
            @foreach ($groups as $group)
                    <form method="post" action="{{ $registered ? route('client.events.group.update', $event) : route('client.events.register.store', $event) }}" class="client-event-group-form">
                    @csrf
                    @if($registered) @method('PUT') @endif
                    <input type="hidden" name="group_label" value="{{ $group['label'] }}">
                    <button class="client-event-group-card {{ $group['selected'] ? 'selected' : '' }} {{ $group['full'] ? 'full-group' : '' }}" type="submit" @disabled($group['full'])>
                        <span class="client-event-group-letter">{{ $group['label'] }}</span><strong>Group {{ $group['label'] }}</strong>
                        <span class="client-event-group-count">{{ $group['count'] }} of {{ $group['capacity'] }} clients</span>
                        <span class="client-event-group-cta">{{ $group['full'] ? 'Full' : ($group['selected'] ? 'Selected' : ($registered ? 'Choose group' : 'Join group')) }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </main>
</body>
</html>
