<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $archived ? 'Event archive' : 'Events' }} · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('projects.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('theme.css').'?v=20261103-event-scoped-slide-types' }}">
</head>
<body class="project-workspace">
    @include('partials.admin-sidebar', ['activeNav' => $activeNav])

    <main class="project-main">
        <header class="project-header bg-gradient-to-r from-sky-50 via-white to-indigo-50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950">
            <div><div class="project-eyebrow">EVENT MANAGEMENT{{ $archived ? ' / ARCHIVE' : '' }}</div><h1>{{ $archived ? 'Event archive' : 'Events' }}</h1><p>{{ $archived ? 'Restore an event or permanently remove it.' : 'Create events linked to your lectures or public lectures.' }}</p></div>
            <div class="project-header-actions">
                @if (! $archived)
                    <a class="project-button secondary" href="{{ route('events.archive') }}">▤ Event archive</a>
                    <button class="project-button primary" type="button" onclick="openEventModal()">＋ New Event</button>
                @else
                    <a class="project-button secondary" href="{{ route('events.index') }}">← Back to events</a>
                @endif
            </div>
        </header>

        @if (session('message'))<div class="project-toast" role="status">{{ session('message') }}</div>@endif
        @if ($errors->any())<div class="project-error" role="alert">{{ $errors->first() }}</div>@endif

        <form method="get" class="project-filters event-filters">
            <label class="project-search"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search event, lecturer, location"></label>
            <label class="event-filter-select"><span class="sr-only">Filter by lecture</span><select name="lecture_id"><option value="">All lectures</option>@foreach ($lectures as $lecture)<option value="{{ $lecture->id }}" @selected((string) request('lecture_id') === (string) $lecture->id)>{{ $lecture->title }}</option>@endforeach</select></label>
            <label class="event-filter-select"><span class="sr-only">Filter by creator</span><select name="creator_id"><option value="">All creators</option>@foreach ($creators as $creator)<option value="{{ $creator->id }}" @selected((string) request('creator_id') === (string) $creator->id)>{{ $creator->name }}</option>@endforeach</select></label>
            <label class="event-filter-date"><span class="sr-only">From date</span><input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="From date"></label>
            <label class="event-filter-date"><span class="sr-only">To date</span><input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="To date"></label>
            <button class="project-button secondary" type="submit">Filter</button>
        </form>

        <section class="project-table-card" aria-label="{{ $archived ? 'Archived events' : 'Events list' }}">
            <div class="project-table-top"><div><h2>{{ $archived ? 'Archived events' : 'All events' }}</h2><p>{{ $events->total() }} {{ Str::plural('event', $events->total()) }}</p></div><span class="project-table-count">{{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }} of {{ $events->total() }}</span></div>
            <div class="project-table-scroll"><table class="project-table event-table">
                <thead><tr><th>EVENT</th><th>LECTURE</th><th>LECTURER</th><th>LOCATION</th><th>AUDIENCE</th><th>FROM / TO</th><th>PARTICIPANTS / GROUPS</th><th class="actions-heading">ACTIONS</th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        @php($isOngoingEvent = ! $archived && $event->starts_at && $event->ends_at && $event->starts_at->lte(now()) && $event->ends_at->gt(now()))
                        @php($canOpenPresenterMode = auth()->user()->isSuperAdmin() || (int) $event->lecture?->creator_id === (int) auth()->id())
                        <tr>
                            <td><div class="project-name event-name">@if ($isOngoingEvent)<span class="event-live-marker" role="img" aria-label="Ongoing event" title="Ongoing event"></span>@endif<span class="event-name-title">{{ $event->title }}</span></div><small>Created by {{ $event->creator?->name ?? 'Unknown' }}</small></td>
                            <td>{{ $event->lecture?->title ?? 'Lecture unavailable' }}</td>
                            <td>{{ $event->lecturerUser?->name ?? $event->lecturer ?? 'Unassigned' }}</td>
                            <td>{{ $event->location }}</td>
                            <td>{{ $event->audience }}</td>
                            <td><span class="project-date">{{ $event->starts_at?->format('M j, Y g:i A') ?? '—' }}</span><small>to {{ $event->ends_at?->format('M j, Y g:i A') ?? '—' }}</small></td>
                            <td>{{ number_format($event->attendees_count) }} / {{ number_format($event->expected_participants) }}<small>{{ $event->group_count ? $event->group_count.' groups' : 'No groups' }}</small></td>
                            <td><div class="project-row-actions">
                                @if ($isOngoingEvent && $canOpenPresenterMode)
                                    <a class="project-icon-action" href="{{ route('events.presenter', $event) }}" target="_blank" rel="noopener" title="Open presenter mode" aria-label="Open presenter mode for {{ $event->title }}"><i class="bi bi-easel" aria-hidden="true"></i></a>
                                @endif
                                @if ($isOngoingEvent)
                                    <a class="project-icon-action" href="{{ route('client.event.master', $event) }}" target="_blank" rel="noopener" title="Open client master screen" aria-label="Open client master screen for {{ $event->title }}"><i class="bi bi-display" aria-hidden="true"></i></a>
                                    <a class="project-icon-action" href="{{ route('client.event.presentation', $event) }}" target="_blank" rel="noopener" title="Open client presentation" aria-label="Open client presentation for {{ $event->title }}"><i class="bi bi-person-video3" aria-hidden="true"></i></a>
                                @endif
                                <button class="project-icon-action" type="button" title="View registered participants" aria-label="View registered participants for {{ $event->title }}" data-event-participants data-url="{{ route('events.participants', $event) }}">♙</button>
                                @if(! $archived && $event->group_count > 0)
                                    <button class="project-icon-action" type="button" title="View and grade group works" aria-label="Group works for {{ $event->title }}" data-event-group-works data-url="{{ route('events.group-works', $event) }}" data-event-id="{{ $event->id }}"><i class="bi bi-clipboard-data" aria-hidden="true"></i></button>
                                @endif
                                @if (! $archived && ((int) $event->creator_id === (int) auth()->id() || auth()->user()->isSuperAdmin()))
                                    <button class="project-icon-action delete-action" type="button" title="Reset event data" aria-label="Reset data for {{ $event->title }}" data-event-reset data-url="{{ route('events.reset', $event) }}" data-title="{{ $event->title }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                                @endif
                                @if (! $archived && (int) $event->creator_id === (int) auth()->id())
                                    <button class="project-icon-action" type="button" title="Edit event" aria-label="Edit {{ $event->title }}" data-event-edit data-id="{{ $event->id }}" data-title="{{ $event->title }}" data-location="{{ $event->location }}" data-audience="{{ $event->audience }}" data-starts-at="{{ $event->starts_at?->format('Y-m-d\TH:i') }}" data-ends-at="{{ $event->ends_at?->format('Y-m-d\TH:i') }}" data-lecturer-id="{{ $event->lecturer_id }}" data-lecture-id="{{ $event->lecture_id }}" data-expected-participants="{{ $event->expected_participants }}" data-group-count="{{ $event->group_count }}" data-allow-pre-registration="{{ $event->allow_pre_registration ? '1' : '0' }}" data-background-image-url="{{ $event->background_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($event->background_image_path) : '' }}" data-background-opacity="{{ $event->background_opacity }}">✎</button>
                                @endif
                                @if (! $archived && ((int) $event->creator_id === (int) auth()->id() || auth()->user()->isSuperAdmin()))
                                    <form method="post" action="{{ route('events.store-in-archive', $event) }}" data-confirm="Archive {{ $event->title }}? You can restore it later.">@csrf<button class="project-icon-action archive-action" type="submit" title="Archive event" aria-label="Archive {{ $event->title }}">▤</button></form>
                                @elseif ($archived && (int) $event->creator_id === (int) auth()->id())
                                    <form method="post" action="{{ route('events.restore', $event) }}">@csrf<button class="project-icon-action enter" type="submit" title="Restore event" aria-label="Restore {{ $event->title }}">↶</button></form>
                                @endif
                                @if ($archived && auth()->user()->isSuperAdmin())
                                    <form method="post" action="{{ route('events.destroy', $event) }}" data-confirm="Permanently delete {{ $event->title }}? This cannot be undone.">@csrf @method('DELETE')<button class="project-icon-action delete-action" type="submit" title="Delete event permanently" aria-label="Delete {{ $event->title }}">×</button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="project-empty"><span>▣</span><strong>{{ $archived ? 'No archived events.' : 'No events found.' }}</strong><small>{{ $archived ? 'Events you archive will appear here.' : 'Create an event to get started.' }}</small></td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @if ($events->hasPages())<div class="project-pagination">@include('partials.project-pagination', ['paginator' => $events])</div>@endif
        </section>
    </main>

    @if (! $archived)
        <dialog class="project-modal" id="event-modal" aria-labelledby="event-modal-title">
            <form id="event-form" method="post" action="{{ route('events.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="event-method" name="_method" value="">
                <div class="project-modal-header"><div><div class="project-eyebrow">EVENT DETAILS</div><h2 id="event-modal-title">New Event</h2><p>Connect this event to one of your lectures or a public lecture.</p></div><button class="project-modal-close" type="button" onclick="document.getElementById('event-modal').close()" aria-label="Close">×</button></div>
                <label>Event title<input id="event-title" name="title" maxlength="180" required></label>
                <label>Lecture<select id="event-lecture-id" name="lecture_id" required><option value="">Select a lecture</option>@foreach ($lectures as $lecture)<option value="{{ $lecture->id }}">{{ $lecture->title }}</option>@endforeach</select></label>
                <label>Lecturer<select id="event-lecturer-id" name="lecturer_id" required><option value="">Select an admin</option>@foreach ($admins as $admin)<option value="{{ $admin->id }}">{{ $admin->name }}</option>@endforeach</select></label>
                <div class="event-form-grid"><label>Location<input id="event-location" name="location" maxlength="255" required></label><label>Audience<input id="event-audience" name="audience" maxlength="255" required></label><label>From<input id="event-starts-at" type="datetime-local" name="starts_at" required></label><label>To<input id="event-ends-at" type="datetime-local" name="ends_at" required></label><label>Expected participants<input id="event-expected-participants" type="number" name="expected_participants" min="1" max="1000000" required></label><label>Number of groups<input id="event-group-count" type="number" name="group_count" min="0" max="26" required value="0"><small>Use 0 when there are no groups.</small></label></div>
                <fieldset class="event-background-settings"><legend>Event background · all decks</legend><label>Background image<input id="event-background-image" type="file" name="background_image" accept="image/jpeg,image/png,image/webp,image/gif"><small>JPG, PNG, WebP, or GIF · up to 10 MB. This image appears behind every slide in this event.</small></label><div class="event-background-preview" id="event-background-preview-wrap" hidden><img id="event-background-preview" alt="Event background preview"></div><label class="project-checkbox-label event-background-remove" id="event-background-remove-wrap" hidden><input type="hidden" name="remove_background_image" value="0"><input id="event-background-remove" type="checkbox" name="remove_background_image" value="1"> Remove current background image</label><label class="event-background-opacity-label">Image opacity <output id="event-background-opacity-value">20%</output><input id="event-background-opacity" type="range" name="background_opacity" min="0" max="100" value="20"><small>Choose how strongly the image shows through the event presentation background.</small></label></fieldset>
                <label class="project-checkbox-label"><input type="hidden" name="allow_pre_registration" value="0"><input type="checkbox" id="event-pre-registration" name="allow_pre_registration" value="1"> Allow pre-registration</label>
                <div class="project-modal-actions"><button class="project-button secondary" type="button" onclick="document.getElementById('event-modal').close()">Cancel</button><button class="project-button primary" id="event-save" type="submit">Create event</button></div>
            </form>
        </dialog>

        <dialog class="project-modal event-reset-modal" id="event-reset-modal" aria-labelledby="event-reset-title">
            <form id="event-reset-form" method="post">
                @csrf
                <div class="project-modal-header"><div><div class="project-eyebrow">DESTRUCTIVE ACTION</div><h2 id="event-reset-title">Reset event data</h2><p id="event-reset-description">Choose one type of event data to erase. This cannot be undone.</p></div><button class="project-modal-close" type="button" onclick="document.getElementById('event-reset-modal').close()" aria-label="Close">×</button></div>
                <fieldset class="event-reset-options" aria-describedby="event-reset-description">
                    <legend>Data to erase <span aria-hidden="true">*</span></legend>
                    <label><input type="radio" name="scope" value="assessments" required><span><strong>Assessment submissions</strong><small>Remove attendee assessment attempts and their answers.</small></span></label>
                    <label><input type="radio" name="scope" value="group_submissions" required><span><strong>Group submissions</strong><small>Remove group outputs, comments, and grades.</small></span></label>
                    <label><input type="radio" name="scope" value="registrations" required><span><strong>Student registrations</strong><small>Remove registrations, attendance, group assignments, and leader assignments.</small></span></label>
                    <label><input type="radio" name="scope" value="all" required><span><strong>All event data</strong><small>Remove assessment submissions, group submissions and grades, registrations, and event presenter state. Event details and lecture/deck content are kept.</small></span></label>
                </fieldset>
                <div class="project-modal-actions"><button class="project-button secondary" type="button" onclick="document.getElementById('event-reset-modal').close()">Cancel</button><button class="project-button primary danger" type="submit">Erase selected data</button></div>
            </form>
        </dialog>
    @endif

    <dialog class="project-modal participants-modal" id="participants-modal" aria-labelledby="participants-modal-title">
        <div class="participants-modal-content">
            <div class="project-modal-header"><div><div class="project-eyebrow">EVENT REGISTRATIONS</div><h2 id="participants-modal-title">Registered participants</h2><p id="participants-total"></p></div><button class="project-modal-close" type="button" onclick="document.getElementById('participants-modal').close()" aria-label="Close">×</button></div>
            <div class="participants-groups" id="participants-groups" aria-label="Participants by group"></div>
            <div class="participants-table-wrap"><table class="participants-table"><thead><tr><th>PARTICIPANT</th><th>GROUP</th><th>STATUS</th></tr></thead><tbody id="participants-rows"><tr><td colspan="3">Choose an event to load participants.</td></tr></tbody></table></div>
            <div class="participants-pagination"><button class="project-button secondary" id="participants-previous" type="button">Previous</button><span id="participants-page"></span><button class="project-button secondary" id="participants-next" type="button">Next</button></div>
        </div>
    </dialog>

    <dialog class="project-modal group-works-modal" id="group-works-modal" aria-labelledby="group-works-modal-title">
        <div class="group-works-modal-content">
            <div class="project-modal-header"><div><div class="project-eyebrow">GROUP ACTIVITY RESULTS</div><h2 id="group-works-modal-title">Group works</h2><p id="group-works-event-title"></p></div><button class="project-modal-close" type="button" onclick="document.getElementById('group-works-modal').close()" aria-label="Close">×</button></div>
            <div id="group-works-content" class="group-works-content"><p>Choose an event to load group works.</p></div>
        </div>
    </dialog>

    <script>
        const eventModal = document.getElementById('event-modal');
        const eventForm = document.getElementById('event-form');
        const eventMethod = document.getElementById('event-method');
        const eventUpdateTemplate = @js(route('events.update', ['event' => '__EVENT__']));
        const eventBackgroundImage = document.getElementById('event-background-image');
        const eventBackgroundPreviewWrap = document.getElementById('event-background-preview-wrap');
        const eventBackgroundPreview = document.getElementById('event-background-preview');
        const eventBackgroundRemoveWrap = document.getElementById('event-background-remove-wrap');
        const eventBackgroundRemove = document.getElementById('event-background-remove');
        const eventBackgroundOpacity = document.getElementById('event-background-opacity');
        const eventBackgroundOpacityValue = document.getElementById('event-background-opacity-value');
        let savedEventBackgroundUrl = '';
        let temporaryEventBackgroundUrl = '';

        function showEventBackgroundPreview(url) {
            eventBackgroundPreviewWrap.hidden = !url;
            if (url) {
                eventBackgroundPreview.src = url;
            } else {
                eventBackgroundPreview.removeAttribute('src');
            }
        }

        function openEventModal(event = null) {
            eventForm.action = event ? eventUpdateTemplate.replace('__EVENT__', event.id) : @js(route('events.store'));
            eventMethod.value = event ? 'PUT' : '';
            document.getElementById('event-modal-title').textContent = event ? 'Edit event' : 'New Event';
            document.getElementById('event-save').textContent = event ? 'Save changes' : 'Create event';
            document.getElementById('event-title').value = event?.title ?? '';
            document.getElementById('event-location').value = event?.location ?? '';
            document.getElementById('event-audience').value = event?.audience ?? '';
            document.getElementById('event-starts-at').value = event?.startsAt ?? '';
            document.getElementById('event-ends-at').value = event?.endsAt ?? '';
            document.getElementById('event-lecturer-id').value = event?.lecturerId ?? @js((string) auth()->id());
            document.getElementById('event-lecture-id').value = event?.lectureId ?? '';
            document.getElementById('event-expected-participants').value = event?.expectedParticipants ?? '';
            document.getElementById('event-group-count').value = event?.groupCount ?? 0;
            document.getElementById('event-pre-registration').checked = event ? event.allowPreRegistration === '1' : false;
            if (eventBackgroundImage) {
                eventBackgroundImage.value = '';
                if (temporaryEventBackgroundUrl) {
                    URL.revokeObjectURL(temporaryEventBackgroundUrl);
                    temporaryEventBackgroundUrl = '';
                }
                savedEventBackgroundUrl = event?.backgroundImageUrl ?? '';
                showEventBackgroundPreview(savedEventBackgroundUrl);
                eventBackgroundRemoveWrap.hidden = !savedEventBackgroundUrl;
                eventBackgroundRemove.checked = false;
                eventBackgroundOpacity.value = event?.backgroundOpacity ?? 20;
                eventBackgroundOpacityValue.value = `${eventBackgroundOpacity.value}%`;
            }
            eventModal.showModal();
        }

        document.querySelectorAll('[data-event-edit]').forEach((button) => button.addEventListener('click', () => openEventModal(button.dataset)));
        const eventResetModal = document.getElementById('event-reset-modal');
        const eventResetForm = document.getElementById('event-reset-form');
        document.querySelectorAll('[data-event-reset]').forEach((button) => button.addEventListener('click', () => {
            eventResetForm.action = button.dataset.url;
            eventResetForm.reset();
            document.getElementById('event-reset-description').textContent = `Choose one type of data to erase from “${button.dataset.title}”. This cannot be undone.`;
            eventResetModal.showModal();
        }));
        if (eventBackgroundImage) {
            eventBackgroundImage.addEventListener('change', () => {
                const image = eventBackgroundImage.files[0];
                eventBackgroundRemove.checked = false;
                eventBackgroundRemoveWrap.hidden = true;
                if (temporaryEventBackgroundUrl) {
                    URL.revokeObjectURL(temporaryEventBackgroundUrl);
                    temporaryEventBackgroundUrl = '';
                }
                if (image) {
                    temporaryEventBackgroundUrl = URL.createObjectURL(image);
                    showEventBackgroundPreview(temporaryEventBackgroundUrl);
                } else {
                    showEventBackgroundPreview(savedEventBackgroundUrl);
                    eventBackgroundRemoveWrap.hidden = !savedEventBackgroundUrl;
                }
            });
            eventBackgroundRemove.addEventListener('change', () => showEventBackgroundPreview(eventBackgroundRemove.checked ? '' : savedEventBackgroundUrl));
            eventBackgroundOpacity.addEventListener('input', () => {
                eventBackgroundOpacityValue.value = `${eventBackgroundOpacity.value}%`;
            });
        }
        const participantsModal = document.getElementById('participants-modal');
        let participantPage = 1;
        let participantUrl = '';

        async function loadParticipants(page = 1) {
            participantPage = page;
            document.getElementById('participants-rows').innerHTML = '<tr><td colspan="3">Loading participants…</td></tr>';
            const response = await fetch(`${participantUrl}?page=${page}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                document.getElementById('participants-rows').innerHTML = '<tr><td colspan="3">Could not load participants.</td></tr>';
                return;
            }
            const data = await response.json();
            document.getElementById('participants-total').textContent = `${data.total} registered`;
            document.getElementById('participants-groups').replaceChildren(...data.groups.map((group) => {
                const chip = document.createElement('span');
                chip.className = 'participants-group-chip';
                chip.textContent = `${group.label}: ${group.total}`;
                return chip;
            }));
            const rows = data.participants.map((participant) => {
                const row = document.createElement('tr');
                const name = document.createElement('td');
                name.textContent = participant.name;
                const group = document.createElement('td');
                group.textContent = participant.group_label || 'No group';
                const status = document.createElement('td');
                status.textContent = participant.attended_at ? 'Attended' : 'Registered';
                row.append(name, group, status);
                return row;
            });
            if (! rows.length) {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="3">No participants registered.</td>';
                rows.push(row);
            }
            document.getElementById('participants-rows').replaceChildren(...rows);
            document.getElementById('participants-page').textContent = `Page ${data.current_page} of ${data.last_page}`;
            document.getElementById('participants-previous').disabled = data.current_page <= 1;
            document.getElementById('participants-next').disabled = data.current_page >= data.last_page;
        }

        document.querySelectorAll('[data-event-participants]').forEach((button) => button.addEventListener('click', () => {
            participantUrl = button.dataset.url;
            const title = button.closest('tr').querySelector('.project-name').textContent;
            document.getElementById('participants-modal-title').textContent = `Participants · ${title}`;
            participantsModal.showModal();
            loadParticipants();
        }));
        document.getElementById('participants-previous').addEventListener('click', () => loadParticipants(participantPage - 1));
        document.getElementById('participants-next').addEventListener('click', () => loadParticipants(participantPage + 1));

        const groupWorksModal = document.getElementById('group-works-modal');
        const groupWorksContent = document.getElementById('group-works-content');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        function groupWorksNode(tag, className, content) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (content !== undefined) node.textContent = content;
            return node;
        }
        function renderGroupWorks(groups, eventId) {
            groupWorksContent.replaceChildren();
            for (const group of groups) {
                const groupCard = groupWorksNode('article', 'group-works-group');
                const groupTitle = groupWorksNode('h3', '', `Group ${group.label}`);
                const members = group.members.map((member) => `${member.name}${member.is_group_leader ? ' · Leader' : ''}`).join(', ') || 'No registered members';
                groupCard.append(groupTitle, groupWorksNode('p', 'group-works-members', members));
                for (const activity of group.activities) {
                    const activityCard = groupWorksNode('section', 'group-works-activity');
                    activityCard.append(groupWorksNode('h4', '', activity.title));
                    if (!activity.sections.length) activityCard.append(groupWorksNode('p', 'group-works-empty', 'This activity has no sections yet.'));
                    for (const section of activity.sections) {
                        const sectionCard = groupWorksNode('article', 'group-works-section');
                        sectionCard.append(groupWorksNode('h5', '', `${section.title} · ${section.input_type}`));
                        if (section.text_content) sectionCard.append(groupWorksNode('pre', 'group-works-output', section.text_content));
                        if (section.file_url) {
                            const fileLink = groupWorksNode('a', '', section.file_name || 'View group output');
                            fileLink.href = section.file_url;
                            fileLink.target = '_blank';
                            fileLink.rel = 'noopener';
                            sectionCard.append(fileLink);
                        }
                        const comments = section.comments || [];
                        for (const status of ['considered', 'disregarded', 'pending']) {
                            const matching = comments.filter((comment) => comment.status === status);
                            const bucket = groupWorksNode('div', `group-works-comments status-${status}`);
                            bucket.append(groupWorksNode('strong', '', `${status[0].toUpperCase()}${status.slice(1)} · ${matching.length}`));
                            for (const comment of matching) bucket.append(groupWorksNode('p', '', `${comment.name}: ${comment.body}`));
                            sectionCard.append(bucket);
                        }
                        activityCard.append(sectionCard);
                    }
                    const gradeForm = document.createElement('form');
                    gradeForm.method = 'post';
                    gradeForm.action = `/admin/events/${eventId}/group-works/${activity.id}/${group.label}/grade`;
                    gradeForm.className = 'group-works-grade-form';
                    const token = document.createElement('input');
                    token.type = 'hidden'; token.name = '_token'; token.value = csrfToken;
                    const scoreLabel = groupWorksNode('label', '', 'Grade / 100');
                    const score = document.createElement('input');
                    score.type = 'number'; score.name = 'score'; score.min = '0'; score.max = '100'; score.required = true;
                    score.value = activity.grade?.score ?? '';
                    scoreLabel.append(score);
                    const feedbackLabel = groupWorksNode('label', '', 'Feedback');
                    const feedback = document.createElement('textarea');
                    feedback.name = 'feedback'; feedback.rows = 2; feedback.maxLength = 2000;
                    feedback.value = activity.grade?.feedback ?? '';
                    feedbackLabel.append(feedback);
                    const saveGrade = groupWorksNode('button', 'project-button primary', activity.grade ? 'Update grade' : 'Save grade');
                    saveGrade.type = 'submit';
                    gradeForm.append(token, scoreLabel, feedbackLabel, saveGrade);
                    activityCard.append(gradeForm);
                    groupCard.append(activityCard);
                }
                groupWorksContent.append(groupCard);
            }
            if (!groups.length) groupWorksContent.append(groupWorksNode('p', 'group-works-empty', 'No groups are configured for this event.'));
        }
        document.querySelectorAll('[data-event-group-works]').forEach((button) => button.addEventListener('click', async () => {
            groupWorksContent.replaceChildren(groupWorksNode('p', '', 'Loading group submissions…'));
            document.getElementById('group-works-event-title').textContent = button.closest('tr').querySelector('.project-name').textContent;
            groupWorksModal.showModal();
            try {
                const response = await fetch(button.dataset.url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Could not load group works.');
                const data = await response.json();
                renderGroupWorks(data.groups, button.dataset.eventId);
            } catch (error) {
                groupWorksContent.replaceChildren(groupWorksNode('p', 'project-error', error.message));
            }
        }));
    </script>
</body>
</html>
