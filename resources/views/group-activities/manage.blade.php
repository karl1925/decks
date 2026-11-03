<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Group activity · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('projects.css') }}">
    <link rel="stylesheet" href="{{ asset('theme.css').'?v=20261103-event-scoped-slide-types' }}">
    @vite('resources/css/app.css')
</head>
<body class="project-workspace">
    @include('partials.admin-sidebar', ['activeNav' => 'lectures'])
    <main class="project-main">
        <header class="project-header bg-gradient-to-r from-sky-50 via-white to-indigo-50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950">
            <div><div class="project-eyebrow">GROUP ACTIVITY DECK</div><h1>{{ $deck->title }}</h1><p>Add the sections students will work through together.</p></div>
            <a class="project-button secondary" href="{{ $eventId ? route('events.lecture', ['event' => $eventId, 'lecture' => $deck->lecture_id]) : route('admin.lecture', $deck->lecture_id) }}">← Back to outline</a>
        </header>
        @if(session('message'))<div class="project-toast" role="status">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="project-error" role="alert">{{ $errors->first() }}</div>@endif
        <section class="project-table-card group-activity-section-card">
            <div class="project-table-top"><div><h2>Activity sections</h2><p>Each section has one shared group output and its own suggestion thread.</p></div></div>
            <form class="group-activity-section-form" method="post" action="{{ route('group-activities.sections.store',$deck) }}">
                @csrf @if($eventId)<input type="hidden" name="event_id" value="{{ $eventId }}">@endif
                <label>Section title<input name="title" maxlength="180" placeholder="Example: Threat model" required></label>
                <label>Group submission type<select name="input_type"><option value="text">Text</option><option value="image">Image</option><option value="file">File</option></select></label>
                <button class="project-button primary" type="submit">＋ Add section</button>
            </form>
            <div class="project-table-scroll"><table class="project-table"><thead><tr><th>POSITION</th><th>SECTION</th><th>SUBMISSION TYPE</th><th>ACTION</th></tr></thead><tbody>
                @forelse($deck->groupActivitySections as $index => $section)
                    <tr><td>{{ $index + 1 }}</td><td class="project-name">{{ $section->title }}</td><td>{{ ucfirst($section->input_type) }}</td><td><form method="post" action="{{ route('group-activities.sections.delete',$section) }}" onsubmit="return confirm('Delete this section and its group submissions and comments?')">@csrf @method('DELETE') @if($eventId)<input type="hidden" name="event_id" value="{{ $eventId }}">@endif<button class="project-icon-action delete-action" type="submit" aria-label="Delete {{ $section->title }}" title="Delete section">×</button></form></td></tr>
                @empty
                    <tr><td colspan="4" class="project-empty"><span>☷</span><strong>No activity sections yet.</strong><small>Add a section to collect shared group work.</small></td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </main>
</body>
</html>
