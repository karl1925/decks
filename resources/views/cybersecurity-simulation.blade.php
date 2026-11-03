<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123b72">
    <title>Cyber Tibay · Building Cyber Resilience</title>
    <link rel="stylesheet" href="{{ asset('cyber-tibay.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/" aria-label="Cyber Tibay home">
            <span class="brand-mark" aria-hidden="true">C</span>
            <span>CYBER TIBAY</span>
        </a>
        <span class="framework-tag"><span></span> DICT NCERT INCIDENT RESPONSE</span>
    </header>

    <main class="page-shell">
        <section class="intro" aria-labelledby="page-title">
            <p class="eyebrow">Cybersecurity awareness · Saint Mary’s University</p>
            <h1 id="page-title">Build cyber resilience.<br>Respond with confidence.</h1>
            <p>
                Learn to recognize threats, report incidents safely, preserve useful information,
                and recover stronger—guided by DICT NCERT incident-handling practices.
            </p>
            <a class="primary-link" href="{{ route('client.join') }}">Join the live course <span aria-hidden="true">→</span></a>
            <a class="host-link" href="{{ route('admin.login') }}">Host sign-in</a>
        </section>

        <section class="course-card" aria-labelledby="course-title">
            <p class="card-kicker">THE CYBER TIBAY LIFECYCLE</p>
            <h2 id="course-title">Five resilience pillars</h2>
            <ol class="pillar-list">
                <li><span>01</span><strong>Prevent</strong><small>Reduce the chance of an incident.</small></li>
                <li><span>02</span><strong>Detect</strong><small>Recognize suspicious activity early.</small></li>
                <li><span>03</span><strong>Respond</strong><small>Contain impact and report appropriately.</small></li>
                <li><span>04</span><strong>Recover</strong><small>Restore systems to a secure state.</small></li>
                <li><span>05</span><strong>Improve</strong><small>Use lessons learned to strengthen defenses.</small></li>
            </ol>
            <p class="audience-note">For Saint Mary’s University College students</p>
        </section>
    </main>

    <footer class="page-footer">
        <span>CYBER TIBAY · BUILDING CYBER RESILIENCE</span>
        <span>Report early. Assess carefully. Preserve information.</span>
    </footer>
</body>
</html>
