<aside class="client-event-sidebar">
    <div class="client-event-sidebar-top flex items-center justify-between gap-2"><a class="client-event-brand" href="{{ route('client.events.my') }}"><span class="brand-mark dict-brand-mark">D</span><span class="client-event-brand-copy">Decks<small>STUDENT EVENTS</small></span></a><button class="sidebar-collapse-toggle grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-sky-200 hover:bg-sky-50" type="button" data-sidebar-collapse aria-label="Collapse sidebar" title="Collapse sidebar"><i class="bi bi-layout-sidebar-inset"></i></button></div>
    <nav aria-label="Student events navigation">
        <a class="{{ $activeNav === 'my-events' ? 'active' : '' }}" href="{{ route('client.events.my') }}" title="My Events"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>My Events</span></a>
        <a class="{{ $activeNav === 'register' ? 'active' : '' }}" href="{{ route('client.events.register') }}" title="Register"><i class="bi bi-plus-circle" aria-hidden="true"></i><span>Register</span></a>
        <a class="{{ $activeNav === 'history' ? 'active' : '' }}" href="{{ route('client.events.history') }}" title="History"><i class="bi bi-clock-history" aria-hidden="true"></i><span>History</span></a>
    </nav>
    <div class="client-event-sidebar-bottom">
        <div class="client-event-user"><span class="client-event-avatar">{{ strtoupper(substr($attendee->name, 0, 1)) }}</span><span>{{ $attendee->name }}<small>Student</small></span></div>
        <form method="post" action="{{ route('client.logout') }}">@csrf<button class="client-event-signout" type="submit">Sign out</button></form>
    </div>
</aside>
<script src="{{ asset('theme.js').'?v=20261103-event-view-toggle' }}" defer></script>
