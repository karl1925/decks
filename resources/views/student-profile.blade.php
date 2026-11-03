<!doctype html>
<html lang="en"><head>
    @include('partials.favicon')<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Finish student profile · Decks</title><link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('student.css') }}"><link rel="stylesheet" href="{{ asset('social.css') }}"><link rel="stylesheet" href="{{ asset('theme.css') }}"></head>
<body class="client-bg"><main class="join-shell">
    <a class="wordmark" href="{{ route('client.join') }}"><span class="brand-mark dict-brand-mark">D</span><span class="client-brand-copy">Decks<small>DICT · REGION II</small></span></a>
    <section class="join-card student-access-card">
        <div class="eyebrow"><span class="live-dot"></span> CONNECTED WITH {{ strtoupper($socialSignup['provider']) }}</div>
        <h1>One last step.<br><em>Set your class.</em></h1>
        <p class="muted intro">Confirm your name to finish your student account.</p>
        @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
        <form action="{{ route('student.save-social') }}" method="post" class="stack-form">@csrf
            <label>Complete name<input name="name" value="{{ old('name', $socialSignup['name']) }}" placeholder="Your complete name" autocomplete="name" required maxlength="160"></label>
            @if(!empty($socialSignup['email']))<label>Google email<input value="{{ $socialSignup['email'] }}" readonly autocomplete="email"></label>@endif
            <button class="button primary full" type="submit">Finish &amp; enter student room <span>↗</span></button>
        </form>
        <div class="privacy-note"><span>✦</span> Your social account stays linked so you can sign back in and resume.</div>
    </section>
    <footer class="join-footer"><span>READY WHEN YOU ARE</span><span>Student access · {{ now()->format('Y') }}</span></footer>
</main><script src="{{ asset('theme.js').'?v=20261103-event-view-toggle' }}"></script></body></html>
