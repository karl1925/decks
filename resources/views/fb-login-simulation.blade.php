<!doctype html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Social sign-in awareness exercise · Decks</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f0f2f5; color: #1c1e21; }
        .notice { padding: 11px 18px; background: #102a43; color: #fff; text-align: center; font-size: 13px; letter-spacing: .02em; }
        .layout { width: min(980px, calc(100% - 40px)); min-height: calc(100vh - 42px); margin: auto; display: grid; grid-template-columns: 1.1fr .9fr; align-items: center; gap: 72px; }
        .intro h1 { color: #0866ff; font-size: clamp(48px, 6vw, 62px); letter-spacing: -2.5px; margin: 0 0 12px; }
        .intro p { max-width: 440px; font-size: 27px; line-height: 1.25; margin: 0; }
        .login-column { text-align: center; }
        .card { padding: 18px 18px 22px; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px #0002; }
        .card h2 { font-size: 18px; font-weight: 400; margin: 0 0 16px; }
        .card > p { color: #606770; font-size: 14px; line-height: 1.45; margin: 0 0 17px; }
        label { display: block; margin: 0 0 12px; color: #344054; font-size: 13px; font-weight: 700; text-align: left; }
        input { display: block; width: 100%; margin-top: 6px; padding: 15px 16px; border: 1px solid #ccd0d5; border-radius: 6px; font-size: 17px; }
        input:focus { outline: 2px solid #1877f2; outline-offset: 1px; }
        button, .home-link { display: flex; width: 100%; justify-content: center; align-items: center; border: 0; border-radius: 6px; background: #0866ff; color: #fff; padding: 12px 16px; font-size: 20px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .forgot-link { display: inline-block; margin: 16px 0 18px; color: #0866ff; font-size: 14px; text-decoration: none; }
        .divider { display: flex; align-items: center; gap: 12px; color: #606770; font-size: 12px; margin: 0 0 18px; }
        .divider::before, .divider::after { content: ''; height: 1px; flex: 1; background: #dadde1; }
        .create-account { width: auto; margin: auto; padding: 12px 16px; background: #42b72a; font-size: 16px; }
        .fine-print { margin-top: 16px !important; font-size: 12px !important; }
        .create-page { margin: 25px 0 0; color: #1c1e21; font-size: 14px; }
        .create-page strong { font-weight: 700; }
        .site-footer { width: min(980px, calc(100% - 40px)); margin: -28px auto 0; padding: 18px 0; border-top: 1px solid #dddfe2; color: #737373; font-size: 12px; }
        .alert-screen { position: fixed; inset: 0; z-index: 2; display: grid; place-items: center; overflow-y: auto; padding: 28px; background: radial-gradient(ellipse at center, #421115 0, #190a0c 48%, #070809 100%); color: #fff; text-align: center; }
        .alert-screen[hidden], .return-link[hidden] { display: none; }
        .alert-screen:not([hidden]) { animation: alert-entry .55s ease-out both; }
        .code-rain { position: absolute; inset: 0; z-index: 0; width: 100%; height: 100%; }
        .alert-screen::before { position: absolute; inset: 0; content: ''; pointer-events: none; background: repeating-linear-gradient(0deg, #ffffff08 0, #ffffff08 1px, transparent 1px, transparent 4px); animation: scanline-flicker 1.8s steps(2, end) infinite; }
        .alert-content { position: relative; z-index: 1; width: min(820px, 100%); animation: content-arrive .7s cubic-bezier(.2,.8,.2,1) both; }
        .simulation-stamp { position: absolute; z-index: 2; top: 20px; left: 50%; width: min(620px, calc(100% - 32px)); transform: translateX(-50%); padding: 12px 16px; border: 2px solid #7cffb2; border-radius: 6px; background: #062719; color: #c6ffdc; font: 800 14px/1.4 Arial, sans-serif; letter-spacing: .08em; text-align: center; box-shadow: 0 0 24px #00ff8850; }
        .alert-content[hidden] { display: none; }
        .warning-label { color: #ff6873; font: 700 13px/1.4 monospace; letter-spacing: .22em; }
        .alert-content h1 { position: relative; margin: 22px 0 14px; color: #ff3546; font: 900 clamp(38px, 8vw, 76px)/.98 Arial, sans-serif; letter-spacing: -.04em; text-shadow: 0 0 28px #ff24365c; animation: warning-pulse 1.3s ease-in-out infinite alternate, warning-blink .7s steps(2, end) infinite; }
        .alert-content h1::before, .alert-content h1::after { position: absolute; inset: 0; overflow: hidden; content: attr(data-text); opacity: .55; pointer-events: none; }
        .alert-content h1::before { color: #32e8ff; animation: glitch-cyan 2.8s steps(1, end) infinite; }
        .alert-content h1::after { color: #ff244f; animation: glitch-red 3.2s steps(1, end) infinite; }
        .alert-content .claim { margin: 0 auto 24px; font-size: clamp(17px, 2.6vw, 23px); }
        .truth { margin: 0 auto; padding: 18px 20px; max-width: 620px; border: 1px solid #ff687366; border-radius: 8px; background: #ff354610; font-size: 16px; line-height: 1.6; }
        .return-link { width: min(330px, 100%); margin: 24px auto 0; background: #f4f6f8; color: #16191d; }
        .return-link[hidden] { display: none; }
        @keyframes alert-entry { from { opacity: 0; } to { opacity: 1; } }
        @keyframes content-arrive { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes warning-pulse { from { text-shadow: 0 0 16px #ff24364c; } to { text-shadow: 0 0 34px #ff2436b8, 0 0 70px #ff24364a; } }
        @keyframes warning-blink { 0%, 100% { color: #ff3546; } 50% { color: #bd1023; } }
        @keyframes scanline-flicker { 0%, 100% { opacity: .28; } 50% { opacity: .1; } }
        @keyframes glitch-cyan { 0%, 91%, 100% { clip-path: inset(0 0 100%); transform: translate(0); } 92% { clip-path: inset(10% 0 72%); transform: translate(-4px, 1px); } 94% { clip-path: inset(58% 0 23%); transform: translate(3px, -1px); } 96% { clip-path: inset(0 0 100%); } }
        @keyframes glitch-red { 0%, 87%, 100% { clip-path: inset(0 0 100%); transform: translate(0); } 89% { clip-path: inset(43% 0 39%); transform: translate(4px, -1px); } 91% { clip-path: inset(78% 0 5%); transform: translate(-3px, 1px); } 93% { clip-path: inset(0 0 100%); } }
        @media (prefers-reduced-motion: reduce) { .alert-screen:not([hidden]), .alert-screen::before, .alert-content, .alert-content h1, .alert-content h1::before, .alert-content h1::after { animation: none; } }
        @media (max-width: 700px) { .layout { width: min(420px, calc(100% - 32px)); grid-template-columns: 1fr; gap: 24px; align-content: center; padding: 32px 0 48px; } .intro { text-align: center; } .intro h1 { font-size: 46px; } .intro p { font-size: 19px; margin: auto; } .site-footer { margin: 0 auto; } }
    </style>
</head>
<body>
    <main class="layout">
        <section class="intro" aria-labelledby="page-title">
            <h1 id="page-title">facebook</h1>
            <p>Connect with friends and the world around you on Facebook.</p>
        </section>
        <section class="login-column" aria-label="Demonstration sign in">
          <div class="card">
            <h2>Log in to Facebook</h2>
            <form id="demo-form" autocomplete="off">
                <label>Email address or phone number
                    <input id="demo-identity" type="text" autocomplete="off" placeholder="Email address or phone number">
                </label>
                <label>Password
                    <input id="demo-password" type="password" autocomplete="new-password" placeholder="Password">
                </label>
                <button type="submit">Log in</button>
            </form>
            <a class="forgot-link" href="#forgot-password">Forgotten password?</a>
            <div class="divider">or</div>
            <button class="create-account" type="button" aria-label="Create a new account (demo only)">Create new account</button>
          </div>
          <p class="create-page"><strong>Create a Page</strong> for a celebrity, brand or business.</p>
            <p class="fine-print">Training page only. Nothing you type is sent, recorded, or saved.</p>
        </section>
    </main>
    <footer class="site-footer">English (US) · Español · Français (France) · Decks security awareness exercise</footer>

    <section id="simulation-alert" class="alert-screen" aria-labelledby="alert-title" hidden>
        {{-- <div class="simulation-stamp">Decks TRAINING SIMULATION · NO ACCOUNT WAS ACCESSED</div> --}}
        <div class="alert-content">
            <div class="warning-label">SECURITY INCIDENT · SIMULATION</div>
            <h1 id="alert-title">YOUR ACCOUNT HAS BEEN HACKED</h1>
            <p class="claim">I have taken full control of your account. Pay me to restore it.</p>
            <div id="alert-details" hidden>
                <p class="truth"><strong>This was only a simulation.</strong> No account was accessed. No credentials were sent or saved. Never pay a pop-up or message that demands money to restore an account. Close it and go directly to the service’s official app or website.</p>
                <a id="return-link" class="home-link return-link" href="{{ route('home') }}">Take me to the real Decks</a>
            </div>
        </div>
        <canvas id="code-rain" class="code-rain" aria-hidden="true"></canvas>
    </section>

    <script>
        const demoForm = document.getElementById('demo-form');
        const identityInput = document.getElementById('demo-identity');
        const passwordInput = document.getElementById('demo-password');
        const alertScreen = document.getElementById('simulation-alert');
        const alertDetails = document.getElementById('alert-details');
        const returnLink = document.getElementById('return-link');
        const alertContent = document.querySelector('.alert-content');
        const codeRain = document.getElementById('code-rain');
        const codeRainContext = codeRain.getContext('2d');
        const codeRainCharacters = 'アカサタナハマヤラワ012345789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const codeRainDuration = 3000;
        let codeRainColumns = [];
        let codeRainFrame = null;

        const resizeCodeRain = () => {
            const pixelRatio = window.devicePixelRatio || 1;
            codeRain.width = Math.floor(window.innerWidth * pixelRatio);
            codeRain.height = Math.floor(window.innerHeight * pixelRatio);
            codeRainContext.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
            codeRainColumns = Array.from({ length: Math.ceil(window.innerWidth / 18) }, () => Math.random() * -40);
        };

        const drawCodeRain = (startedAt) => {
            const progress = Math.min((performance.now() - startedAt) / codeRainDuration, 1);
            const hue = Math.round(125 * (1 - progress));
            codeRainContext.fillStyle = 'rgba(0, 0, 0, 0.16)';
            codeRainContext.fillRect(0, 0, window.innerWidth, window.innerHeight);
            codeRainContext.fillStyle = `hsl(${hue} 100% 58%)`;
            codeRainContext.font = '16px monospace';

            codeRainColumns.forEach((row, column) => {
                const character = codeRainCharacters[Math.floor(Math.random() * codeRainCharacters.length)];
                codeRainContext.fillText(character, column * 18, row * 18);

                if (row * 18 > window.innerHeight && Math.random() > 0.975) {
                    codeRainColumns[column] = 0;
                } else {
                    codeRainColumns[column] += 0.65;
                }
            });

            if (progress < 1) {
                codeRainFrame = window.requestAnimationFrame(() => drawCodeRain(startedAt));
                return;
            }

            codeRainContext.clearRect(0, 0, window.innerWidth, window.innerHeight);
            codeRain.hidden = true;
            alertContent.hidden = false;
        };

        demoForm.addEventListener('submit', (event) => {
            event.preventDefault();
            identityInput.value = '';
            passwordInput.value = '';
            alertScreen.hidden = false;
            document.body.style.overflow = 'hidden';

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                codeRain.hidden = true;
                alertContent.hidden = false;
            } else {
                codeRain.hidden = false;
                resizeCodeRain();
                codeRainContext.fillStyle = '#030806';
                codeRainContext.fillRect(0, 0, window.innerWidth, window.innerHeight);
                codeRainFrame = window.requestAnimationFrame(drawCodeRain.bind(null, performance.now()));
            }

            window.setTimeout(() => {
                alertDetails.hidden = false;
                returnLink.focus();
            }, 5000);
        });

        window.addEventListener('resize', () => {
            if (!codeRain.hidden) {
                resizeCodeRain();
            }
        });
    </script>
</body>
</html>
