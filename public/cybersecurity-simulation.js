(() => {
    const form = document.getElementById('training-login');
    const card = document.getElementById('login-card');
    const note = document.getElementById('safety-note');
    const takeover = document.getElementById('takeover');
    const continueLink = document.getElementById('continue-link');

    if (!form || !card || !note || !takeover || !continueLink) return;

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        // Never read, send, or store the values: this is a front-end-only simulation.
        form.querySelectorAll('input').forEach((input) => {
            input.value = '';
        });

        document.querySelector('.intro').hidden = true;
        card.hidden = true;
        note.hidden = true;
        document.querySelector('.page-shell').classList.add('is-takeover');
        takeover.hidden = false;

        window.setTimeout(() => {
            continueLink.classList.add('is-visible');
            continueLink.removeAttribute('aria-hidden');
            continueLink.focus({ preventScroll: true });
        }, 2600);
    });
})();
