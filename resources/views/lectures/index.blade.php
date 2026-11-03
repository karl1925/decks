<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $archived ? 'Lecture archive' : 'Lectures' }} · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('projects.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('theme.css') }}">
</head>
<body class="project-workspace">
    @include('partials.admin-sidebar', ['activeNav' => 'lectures'])

    <main class="project-main">
        <header class="project-header bg-gradient-to-r from-sky-50 via-white to-indigo-50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950">
            <div>
                <div class="project-eyebrow">{{ $archived ? 'LECTURE LIBRARY / ARCHIVE' : 'LECTURE LIBRARY' }}</div>
                <h1>{{ $archived ? 'Lecture archive' : 'Lectures' }}</h1>
                <p>{{ $archived ? 'Reactivate inactive lectures or restore archived lectures.' : 'Create and organize your active lectures.' }}</p>
            </div>
            <div class="project-header-actions">
                @if (! $archived)
                    <a class="project-button secondary" href="{{ route('lectures.archive') }}"><span aria-hidden="true">▤</span> View archive</a>
                    <button class="project-button primary" type="button" onclick="openLectureModal()"><span aria-hidden="true">＋</span> New Lecture</button>
                @else
                    <a class="project-button secondary" href="{{ route('lectures.index') }}">← Back to lectures</a>
                @endif
            </div>
        </header>

        @if (session('message'))<div class="project-toast" role="status">{{ session('message') }}</div>@endif
        @if ($errors->any())<div class="project-error" role="alert">{{ $errors->first() }}</div>@endif

        <form method="get" class="project-filters">
            <label class="project-search"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search lectures, descriptions, or creators"></label>
            @if (! $archived)
                <label class="project-author-filter"><span class="sr-only">Filter by visibility</span><select name="visibility" onchange="this.form.submit()"><option value="">All visibility</option><option value="public" @selected(request('visibility') === 'public')>Public</option><option value="private" @selected(request('visibility') === 'private')>Private</option></select></label>
            @endif
            <button class="project-button secondary" type="submit">Search</button>
        </form>

        <section class="project-table-card" aria-label="{{ $archived ? 'Archived lectures' : 'Lectures list' }}">
            <div class="project-table-top"><div><h2>{{ $archived ? 'Archived lectures' : 'All lectures' }}</h2><p>{{ $lectures->total() }} {{ Str::plural('lecture', $lectures->total()) }}</p></div><span class="project-table-count">{{ $lectures->firstItem() ?? 0 }}–{{ $lectures->lastItem() ?? 0 }} of {{ $lectures->total() }}</span></div>
            <div class="project-table-scroll">
                <table class="project-table">
                    <thead><tr><th>LECTURE</th><th>VISIBILITY</th><th>CREATOR</th><th>{{ $archived ? 'ARCHIVED' : 'LAST UPDATED' }}</th><th class="actions-heading">ACTIONS</th></tr></thead>
                    <tbody>
                    @forelse($lectures as $lecture)
                        <tr>
                            <td><div class="project-name">{{ $lecture->title }}</div><div class="project-description">{{ $lecture->description ?: 'No description added.' }}</div><small>{{ $lecture->chapters_count }} chapters · {{ $lecture->decks_count }} decks</small></td>
                            
                            <td><span class="project-role-badge">{{ ucfirst($lecture->visibility) }}</span>@if (! $lecture->is_active)<small>Inactive</small>@endif</td>
                            <td><span class="project-creator">{{ $lecture->creator?->name ?? 'Unknown' }}</span><small>{{ $lecture->creator?->email }}</small></td>
                            <td><span class="project-date">{{ $archived ? ($lecture->archived_at?->format('M j, Y') ?? 'Inactive') : $lecture->updated_at?->format('M j, Y') }}</span></td>
                            <td><div class="project-row-actions">
                                @if (! $archived)
                                    <a class="project-icon-action enter" href="{{ route('admin.lecture', $lecture) }}" title="Open this lecture in the Studio" aria-label="Open in Studio: {{ $lecture->title }}">↗</a>
                                    <button class="project-icon-action" type="button" title="Edit lecture" aria-label="Edit lecture {{ $lecture->title }}" data-lecture-edit data-id="{{ $lecture->id }}" data-title="{{ $lecture->title }}" data-description="{{ $lecture->description }}" data-visibility="{{ $lecture->visibility }}" data-is-active="{{ $lecture->is_active ? 1 : 0 }}">✎</button>
                                    <button class="project-icon-action" type="button" title="Duplicate lecture" aria-label="Duplicate lecture {{ $lecture->title }}" data-lecture-duplicate data-id="{{ $lecture->id }}" data-title="{{ $lecture->title }}" data-description="{{ $lecture->description }}">⧉</button>
                                    <form method="post" action="{{ route('lectures.store-in-archive', $lecture) }}" data-confirm="Archive {{ $lecture->title }}? You can restore it later.">@csrf<button class="project-icon-action archive-action" type="submit" title="Archive lecture" aria-label="Archive lecture {{ $lecture->title }}">▤</button></form>
                                @else
                                    <form method="post" action="{{ route('lectures.restore', $lecture) }}">@csrf<button class="project-icon-action enter" type="submit" title="Restore lecture" aria-label="Restore lecture {{ $lecture->title }}">↶</button></form>
                                    <form method="post" action="{{ route('lectures.destroy', $lecture) }}" data-confirm="Permanently delete {{ $lecture->title }}? This cannot be undone.">@csrf @method('DELETE')<button class="project-icon-action delete-action" type="submit" title="Delete permanently" aria-label="Delete {{ $lecture->title }}">×</button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="project-empty"><span>▦</span><strong>{{ $archived ? 'Your archive is empty.' : 'No lectures yet.' }}</strong><small>{{ $archived ? 'Archived lectures will appear here.' : 'Add your first lecture to get started.' }}</small></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($lectures->hasPages())<div class="project-pagination">@include('partials.project-pagination', ['paginator' => $lectures])</div>@endif
        </section>
    </main>

    @if (! $archived)
        <dialog class="project-modal" id="project-modal" aria-labelledby="project-modal-title">
            <form id="project-form" method="post" action="{{ route('lectures.store') }}">
                @csrf
                <input type="hidden" id="project-method" name="_method" value="">
                <div class="project-modal-header"><div><div class="project-eyebrow">LECTURE DETAILS</div><h2 id="project-modal-title">New Lecture</h2><p>The lecture will be created under your account.</p></div><button class="project-modal-close" type="button" onclick="document.getElementById('project-modal').close()" aria-label="Close">×</button></div>
                <label>Lecture title<input id="project-title" name="title" maxlength="180" required placeholder="e.g. Cybersecurity Fundamentals"></label>
                <label>Description<textarea id="project-description" name="description" rows="4" maxlength="5000" placeholder="What will this lecture cover?"></textarea></label>
                <label>Visibility<select id="lecture-visibility" name="visibility" required><option value="private">Private</option><option value="public">Public</option></select></label>
                <label class="project-checkbox-label"><input type="hidden" name="is_active" value="0"><input type="checkbox" id="lecture-is-active" name="is_active" value="1" checked> Active</label>
                                <div class="project-modal-actions"><button class="project-button secondary" type="button" onclick="document.getElementById('project-modal').close()">Cancel</button><button class="project-button primary" id="project-save" type="submit">New Lecture</button></div>
            </form>
        </dialog>

        <dialog class="project-modal duplicate-modal" id="duplicate-modal" aria-labelledby="duplicate-modal-title">
            <form method="post" action="#" id="duplicate-form">
                @csrf
                <div class="project-modal-header"><div><div class="project-eyebrow">COPY LECTURE</div><h2 id="duplicate-modal-title">Duplicate lecture</h2><p>The outline and slides will be copied. Learner accounts and assessment results are not copied.</p></div><button class="project-modal-close" type="button" onclick="document.getElementById('duplicate-modal').close()" aria-label="Close">×</button></div>
                <label>New lecture title<input name="title" id="duplicate-title" maxlength="180" required></label>
                <label>Description<textarea name="description" id="duplicate-description" rows="3" maxlength="5000"></textarea></label>
                <div class="project-modal-actions"><button class="project-button secondary" type="button" onclick="document.getElementById('duplicate-modal').close()">Cancel</button><button class="project-button primary" type="submit">Duplicate lecture</button></div>
            </form>
        </dialog>
    @endif

    <script>
        const lectureModal = document.getElementById('project-modal');
        const lectureForm = document.getElementById('project-form');
        const lectureMethod = document.getElementById('project-method');
        const projectEditTemplate = @js(route('lectures.update', ['lecture' => '__LECTURE__']));
        const duplicateForm = document.getElementById('duplicate-form');
        const duplicateRouteTemplate = @js(route('lectures.duplicate', ['lecture' => '__LECTURE__']));

        function openLectureModal(lecture = null) {
            lectureForm.action = lecture ? projectEditTemplate.replace('__LECTURE__', lecture.id) : @js(route('lectures.store'));
            lectureMethod.value = lecture ? 'PUT' : '';
            document.getElementById('project-modal-title').textContent = lecture ? 'Edit lecture' : 'New Lecture';
            document.getElementById('project-save').textContent = lecture ? 'Save changes' : 'New Lecture';
            document.getElementById('project-title').value = lecture?.title ?? '';
            document.getElementById('project-description').value = lecture?.description ?? '';
            document.getElementById('lecture-visibility').value = lecture?.visibility ?? 'private';
            document.getElementById('lecture-is-active').checked = lecture ? lecture.isActive === '1' : true;
            lectureModal.showModal();
        }

        document.querySelectorAll('[data-lecture-edit]').forEach((button) => button.addEventListener('click', () => openLectureModal(button.dataset)));
        document.querySelectorAll('[data-lecture-duplicate]').forEach((button) => button.addEventListener('click', () => {
            duplicateForm.action = duplicateRouteTemplate.replace('__LECTURE__', button.dataset.id);
            document.getElementById('duplicate-title').value = `Copy of ${button.dataset.title}`;
            document.getElementById('duplicate-description').value = button.dataset.description ?? '';
            document.getElementById('duplicate-modal').showModal();
        }));
    </script>
</body>
</html>
