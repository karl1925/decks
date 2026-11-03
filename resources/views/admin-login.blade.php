<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin sign in · Decks</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('theme.css') }}">
    <link rel="stylesheet" href="{{ asset('social.css') }}">
</head>
<body class="client-bg admin-login-bg">
    <main class="join-shell admin-login-shell">
        <a class="wordmark" href="{{ route('admin.login') }}">
            <span class="brand-mark dict-brand-mark">D</span>
            <span class="client-brand-copy">Decks<small>DICT · REGION II</small></span>
        </a>
        <section class="join-card admin-login-card">
            <div class="eyebrow">PRESENTATION HOST</div>
            <h1>Admin<br><em>sign in.</em></h1>
            <p class="muted intro">Sign in to manage the presentation and student room.</p>
            @if ($errors->any())
                <div class="alert" role="alert">{{ $errors->first() }}</div>
            @endif
            <form action="{{ route('admin.login.store') }}" method="post" class="stack-form">
                @csrf
                <label>Email address
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                </label>
                <label>Password
                    <input type="password" name="password" autocomplete="current-password" maxlength="255" required>
                </label>
                <label class="admin-remember"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                <button class="button primary full" type="submit">Sign in to studio <span>↗</span></button>
            </form>
            <div class="social-divider"><span></span>OR CONTINUE WITH<span></span></div>
            <div class="social-buttons admin-social-buttons"><a class="social-button" href="{{ route('admin.google.redirect') }}"><span class="google-g">G</span><span>Google</span></a></div>
        </section>
        <footer class="join-footer"><span>ADMIN ACCESS</span><span>Decks · {{ now()->format('Y') }}</span></footer>
    </main>
    <script src="{{ asset('theme.js').'?v=20261103-event-view-toggle' }}"></script>
</body>
</html>
