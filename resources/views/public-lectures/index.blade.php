<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Public Lectures · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('projects.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('theme.css') }}">
</head>
<body class="project-workspace">
    @include('partials.admin-sidebar', ['activeNav' => 'public-lectures'])

    <main class="project-main">
        <header class="project-header bg-gradient-to-r from-sky-50 via-white to-indigo-50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950"><div><div class="project-eyebrow">OPEN LIBRARY</div><h1>Public Lectures</h1><p>Browse lectures published by Decks creators.</p></div></header>
        <form method="get" class="project-filters">
            <label class="project-search"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search titles, descriptions, or creators"></label>
            <label class="project-author-filter"><span class="sr-only">Filter by creator</span><select name="creator" onchange="this.form.submit()"><option value="">All creators</option>@foreach ($creators as $creator)<option value="{{ $creator->id }}" @selected((string) request('creator') === (string) $creator->id)>{{ $creator->name }}</option>@endforeach</select></label>
            <button class="project-button secondary" type="submit">Search</button>
        </form>

        <section class="public-lecture-grid" aria-label="Public lectures">
            @forelse ($lectures as $lecture)
                <article class="public-lecture-card"><span class="project-role-badge">Public lecture</span><h2>{{ $lecture->title }}</h2><p>{{ $lecture->description ?: 'No description provided.' }}</p><div class="public-lecture-creator"><span class="project-avatar">{{ strtoupper(substr($lecture->creator?->name ?? '?', 0, 1)) }}</span><span>{{ $lecture->creator?->name ?? 'Decks creator' }}</span></div></article>
            @empty
                <div class="dashboard-empty public-lecture-empty"><span>◉</span><strong>No public lectures found</strong><small>Try another search or creator filter.</small></div>
            @endforelse
        </section>
        @if ($lectures->hasPages())<div class="project-pagination public-lecture-pagination">@include('partials.project-pagination', ['paginator' => $lectures])</div>@endif
    </main>
</body>
</html>
