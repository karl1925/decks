<!doctype html>
<html lang="en"><head>
    @include('partials.favicon')<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student access · Decks</title><link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('student.css') }}"><link rel="stylesheet" href="{{ asset('social.css') }}"><link rel="stylesheet" href="{{ asset('theme.css') }}"></head>
<body class="client-bg"><main class="join-shell">
    <a class="wordmark" href="{{ route('client.join') }}"><span class="brand-mark dict-brand-mark">D</span><span class="client-brand-copy">Decks<small>DICT · REGION II</small></span></a>
    <section class="join-card student-access-card">
        <div class="eyebrow"><span class="live-dot"></span> STUDENT ROOM</div>
        <div data-student-access data-initial-mode="{{ $loginMode ? 'login' : 'register' }}" data-access-url="{{ route('client.join') }}">
        <h1><span data-student-heading>{{ $loginMode ? 'Welcome back.' : 'Take your seat.' }}<br><em>{{ $loginMode ? 'Pick up where you left off.' : 'Stay curious.' }}</em></span></h1>
        <p class="muted intro" data-student-intro>{{ $loginMode ? 'Sign in with your class details to return to the current presentation and resume your assessment.' : 'Create your student account with your class details. No email required.' }}</p>
        @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
        <div class="student-tabs" role="tablist" aria-label="Student access options"><button id="student-register-tab" type="button" role="tab" aria-controls="student-register-panel" aria-selected="{{ !$loginMode ? 'true' : 'false' }}" class="{{ !$loginMode ? 'active' : '' }}" data-student-mode="register">New student</button><button id="student-login-tab" type="button" role="tab" aria-controls="student-login-panel" aria-selected="{{ $loginMode ? 'true' : 'false' }}" class="{{ $loginMode ? 'active' : '' }}" data-student-mode="login">Sign in</button></div>
        <section id="student-login-panel" role="tabpanel" aria-labelledby="student-login-tab" data-student-panel="login" @if (!$loginMode) hidden @endif>
        <form action="{{ route('client.authenticate') }}" method="post" class="stack-form">@csrf
            <label>Complete name<input name="name" value="{{ old('name') }}" placeholder="e.g. Alex Santos" autocomplete="name" required maxlength="160"></label>
            <label>Password<input type="password" name="password" placeholder="Your password" autocomplete="current-password" required maxlength="72"></label>
            <button class="button primary full" type="submit">Sign in to student room <span>↗</span></button>
        </form>
        </section>
        <section id="student-register-panel" role="tabpanel" aria-labelledby="student-register-tab" data-student-panel="register" @if ($loginMode) hidden @endif>
        <form action="{{ route('client.register') }}" method="post" class="stack-form">@csrf
            <label>Complete name<input name="name" value="{{ old('name') }}" placeholder="e.g. Alex Santos" autocomplete="name" required maxlength="160"></label>
            <label>Create password<input type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" required minlength="8" maxlength="72"></label>
            <label>Confirm password<input type="password" name="password_confirmation" placeholder="Enter it again" autocomplete="new-password" required minlength="8" maxlength="72"></label>
            <button class="button primary full" type="submit">Create account &amp; enter <span>↗</span></button>
        </form>
        </section>
        <div class="social-divider"><span></span> OR CONTINUE WITH <span></span></div>
        <div class="social-buttons"><a class="social-button" href="{{ route('student.social.redirect','google') }}"><span class="google-g">G</span><span>Google</span></a></div>
        <div class="privacy-note"><span>✦</span> Use your class details or Google. No email entry needed.</div>
        </div>
    </section>
    <footer class="join-footer"><span>READY WHEN YOU ARE</span><span>Student access · {{ now()->format('Y') }}</span></footer>
</main><script src="{{ asset('theme.js').'?v=20261103-event-view-toggle' }}"></script></body></html>
