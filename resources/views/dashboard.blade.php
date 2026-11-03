<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('projects.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('theme.css') }}">
</head>
<body class="project-workspace">
    @include('partials.admin-sidebar', ['activeNav' => 'dashboard'])

    <main class="project-main">
        <header class="project-header bg-gradient-to-r from-sky-50 via-white to-indigo-50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950">
            <div><div class="project-eyebrow">Decks WORKSPACE</div><h1>Dashboard</h1><p>Manage lectures, public content, and upcoming events.</p></div>
            <div class="project-header-actions"><a class="project-button secondary" href="{{ route('lectures.index') }}">↗ Manage lectures</a></div>
        </header>

        <section class="dashboard-stat-grid" aria-label="Workspace summary">
            <a class="dashboard-stat-card" href="{{ route('lectures.index') }}"><span class="dashboard-stat-icon">▦</span><span class="dashboard-stat-label">Active lectures</span><strong>{{ $lectureCount }}</strong><small>Created by you</small></a>
            <a class="dashboard-stat-card" href="{{ route('public-lectures.index') }}"><span class="dashboard-stat-icon">◉</span><span class="dashboard-stat-label">Public lectures</span><strong>{{ $publicLectureCount }}</strong><small>Visible to everyone</small></a>
            <a class="dashboard-stat-card" href="{{ route('events.index') }}"><span class="dashboard-stat-icon">▣</span><span class="dashboard-stat-label">Upcoming events</span><strong>{{ $upcomingEventCount }}</strong><small>Across all lecturers</small></a>
        </section>

        <section class="project-table-card dashboard-upcoming-card" aria-label="Upcoming events">
            <div class="project-table-top"><div><h2>Upcoming events</h2><p>Events scheduled from today onward</p></div><a class="project-button secondary" href="{{ route('events.index') }}">View events</a></div>
            @if ($upcomingEvents->isNotEmpty())
                <div class="project-table-scroll"><table class="project-table"><thead><tr><th>EVENT</th><th>DATE</th><th>LECTURER</th><th>LECTURE</th><th>CREATED BY</th></tr></thead><tbody>
                    @foreach ($upcomingEvents as $event)
                        <tr><td><div class="project-name">{{ $event->title }}</div><div class="project-description">{{ $event->location }} · {{ $event->audience }}</div></td><td><span class="project-date">{{ $event->starts_at->format('M j, Y') }}</span></td><td>{{ $event->lecturerUser?->name ?? $event->lecturer ?? 'Unassigned' }}</td><td>{{ $event->lecture?->title ?? 'Lecture unavailable' }}</td><td>{{ $event->creator?->name ?? 'Unknown' }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @else
                <div class="dashboard-empty"><span>▣</span><strong>No upcoming events</strong><small>Create an event to put it on the calendar.</small><button class="project-button primary" type="button" onclick="window.location.href='{{ route('events.index') }}'">Manage events</button></div>
            @endif
        </section>
    </main>
</body>
</html>
