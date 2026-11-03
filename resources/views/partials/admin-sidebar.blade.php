<aside class="project-sidebar">
    <div class="project-sidebar-top flex items-center justify-between gap-2"><a class="project-brand" href="{{ auth()->check() ? route('dashboard') : route('public-lectures.index') }}"><span class="project-brand-mark">D</span><span class="project-brand-copy">Decks<small>LECTURE WORKSPACE</small></span></a><button class="sidebar-collapse-toggle grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-sky-200 hover:bg-sky-50" type="button" data-sidebar-collapse aria-label="Collapse sidebar" title="Collapse sidebar"><i class="bi bi-layout-sidebar-inset"></i></button></div>
    <nav aria-label="Admin navigation">
        @auth
            <div class="project-nav-heading">WORKSPACE</div>
            <a class="project-nav-link {{ ($activeNav ?? '') === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span>Dashboard</span></a>
            <a class="project-nav-link {{ ($activeNav ?? '') === 'lectures' ? 'active' : '' }}" href="{{ route('lectures.index') }}" title="Lectures"><i class="bi bi-journals" aria-hidden="true"></i><span>Lectures</span></a>
            <a class="project-nav-link {{ ($activeNav ?? '') === 'public-lectures' ? 'active' : '' }}" href="{{ route('public-lectures.index') }}" title="Public Lectures"><i class="bi bi-globe2" aria-hidden="true"></i><span>Public Lectures</span></a>
            <a class="project-nav-link {{ ($activeNav ?? '') === 'events' ? 'active' : '' }}" href="{{ route('events.index') }}" title="Events"><i class="bi bi-calendar-event" aria-hidden="true"></i><span>Events</span></a>
            @if (auth()->user()->isSuperAdmin())
                <div class="project-nav-heading project-nav-spaced">ADMINISTRATION</div>
                <a class="project-nav-link {{ ($activeNav ?? '') === 'users' ? 'active' : '' }}" href="{{ route('users.index') }}" title="Users"><i class="bi bi-people" aria-hidden="true"></i><span>Users</span></a>
            @endif
        @else
            <a class="project-nav-link {{ ($activeNav ?? '') === 'public-lectures' ? 'active' : '' }}" href="{{ route('public-lectures.index') }}" title="Public Lectures"><i class="bi bi-globe2" aria-hidden="true"></i><span>Public Lectures</span></a>
        @endauth
    </nav>
    @auth
        <div class="project-sidebar-bottom">
            <div class="project-current-user"><span class="project-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span>{{ auth()->user()->name }}<small>{{ auth()->user()->isSuperAdmin() ? 'Super admin' : 'Admin' }}</small></span></div>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="project-signout" type="submit">Sign out</button></form>
        </div>
    @else
        <div class="project-sidebar-bottom"><a class="project-button secondary" href="{{ route('admin.login') }}">Admin sign in</a></div>
    @endauth
</aside>
<script src="{{ asset('theme.js') }}" defer></script>
