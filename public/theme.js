(() => {
    const key = 'decks-theme';
    const saved = localStorage.getItem(key);
    const theme = saved === 'dark' || saved === 'light' ? saved : 'light';
    document.documentElement.dataset.theme = theme;

    const workspaceSidebars = document.querySelectorAll('.project-sidebar, .client-event-sidebar');
    if (workspaceSidebars.length > 0) {
        const sidebarKey = 'decks-sidebar';
        const isMobile = window.matchMedia('(max-width: 760px)').matches;
        if (! isMobile && localStorage.getItem(sidebarKey) === 'collapsed') {
            document.documentElement.dataset.sidebar = 'collapsed';
        }

        document.querySelectorAll('[data-sidebar-collapse]').forEach((toggle) => {
            const renderSidebarToggle = () => {
                const collapsed = document.documentElement.dataset.sidebar === 'collapsed';
                toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                toggle.setAttribute('aria-expanded', String(! collapsed));
                toggle.innerHTML = `<i class="bi ${collapsed ? 'bi-layout-sidebar-inset-reverse' : 'bi-layout-sidebar-inset'}" aria-hidden="true"></i>`;
            };

            renderSidebarToggle();
            toggle.addEventListener('click', () => {
                const collapsed = document.documentElement.dataset.sidebar !== 'collapsed';
                document.documentElement.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
                localStorage.setItem(sidebarKey, collapsed ? 'collapsed' : 'expanded');
                renderSidebarToggle();
            });
        });

        const mobileToggle = document.createElement('button');
        mobileToggle.type = 'button';
        mobileToggle.className = 'mobile-sidebar-toggle';
        mobileToggle.setAttribute('aria-label', 'Open navigation');
        mobileToggle.setAttribute('aria-controls', workspaceSidebars[0].id || 'workspace-sidebar');
        mobileToggle.innerHTML = '<i class="bi bi-list" aria-hidden="true"></i>';
        workspaceSidebars[0].id ||= 'workspace-sidebar';

        const backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.className = 'mobile-sidebar-backdrop';
        backdrop.setAttribute('aria-label', 'Close navigation');
        document.body.prepend(backdrop, mobileToggle);

        const setSidebarOpen = (open) => {
            document.documentElement.dataset.sidebarOpen = open ? 'true' : 'false';
            mobileToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            mobileToggle.setAttribute('aria-expanded', String(open));
            mobileToggle.innerHTML = `<i class="bi ${open ? 'bi-x-lg' : 'bi-list'}" aria-hidden="true"></i>`;
        };

        mobileToggle.addEventListener('click', () => setSidebarOpen(document.documentElement.dataset.sidebarOpen !== 'true'));
        backdrop.addEventListener('click', () => setSidebarOpen(false));
        workspaceSidebars.forEach((sidebar) => {
            sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setSidebarOpen(false)));
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setSidebarOpen(false);
            }
        });
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'theme-toggle';
    button.setAttribute('aria-label', 'Switch color theme');
    const render = () => {
        const dark = document.documentElement.dataset.theme === 'dark';
        button.innerHTML = dark ? '<span aria-hidden="true">☀</span> Light mode' : '<span aria-hidden="true">☾</span> Dark mode';
        button.setAttribute('aria-pressed', String(dark));
    };
    button.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        localStorage.setItem(key, next);
        render();
    });
    render();

    if (!document.getElementById('studio-confirmation')) {
        const confirmationDialog = document.createElement('dialog');
        confirmationDialog.className = 'studio-confirmation-dialog shared-confirmation-dialog';
        confirmationDialog.setAttribute('aria-labelledby', 'shared-confirmation-title');
        confirmationDialog.setAttribute('aria-describedby', 'shared-confirmation-message');
        confirmationDialog.innerHTML = '<div class="confirmation-icon" aria-hidden="true">!</div><div class="eyebrow muted-eyebrow">CONFIRM ACTION</div><h2 id="shared-confirmation-title">Please confirm</h2><p id="shared-confirmation-message"></p><div class="dialog-actions"><button type="button" class="button ghost" data-confirm-cancel>Cancel</button><button type="button" class="button danger-button" data-confirm-accept>Continue</button></div>';
        document.body.append(confirmationDialog);

        let activeConfirmation = null;
        const finishConfirmation = (confirmed) => {
            if (!activeConfirmation) {
                return;
            }

            const { form, submitter } = activeConfirmation;
            activeConfirmation = null;
            confirmationDialog.close();

            if (confirmed) {
                form.dataset.confirmed = 'true';
                form.requestSubmit(submitter || undefined);
                delete form.dataset.confirmed;
            }

        };

        confirmationDialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => finishConfirmation(false));
        confirmationDialog.querySelector('[data-confirm-accept]').addEventListener('click', () => finishConfirmation(true));
        confirmationDialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finishConfirmation(false);
        });
        confirmationDialog.addEventListener('click', (event) => {
            if (event.target === confirmationDialog) {
                finishConfirmation(false);
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-confirm]');
            if (!form || form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();
            const submitter = event.submitter;
            confirmationDialog.querySelector('#shared-confirmation-title').textContent = form.dataset.confirmTitle || 'Please confirm';
            confirmationDialog.querySelector('#shared-confirmation-message').textContent = form.dataset.confirm;
            confirmationDialog.querySelector('[data-confirm-accept]').textContent = form.dataset.confirmAccept || 'Continue';
            confirmationDialog.showModal();
            activeConfirmation = { form, submitter };
            confirmationDialog.querySelector('[data-confirm-cancel]').focus();
        }, { capture: true });

        confirmationDialog.addEventListener('close', () => {
            if (activeConfirmation) {
                activeConfirmation = null;
            }
        });
    }

    const studentAccess = document.querySelector('[data-student-access]');
    if (studentAccess) {
        const tabs = studentAccess.querySelectorAll('[data-student-mode]');
        const panels = studentAccess.querySelectorAll('[data-student-panel]');
        const heading = studentAccess.querySelector('[data-student-heading]');
        const intro = studentAccess.querySelector('[data-student-intro]');
        const accessUrl = studentAccess.dataset.accessUrl;
        const copy = {
            register: {
                title: 'Take your seat.',
                emphasis: 'Stay curious.',
                intro: 'Create your student account with your class details. No email required.',
            },
            login: {
                title: 'Welcome back.',
                emphasis: 'Pick up where you left off.',
                intro: 'Sign in with your class details to return to the current presentation and resume your assessment.',
            },
        };
        const setStudentMode = (mode) => {
            const activeMode = mode === 'login' ? 'login' : 'register';
            studentAccess.dataset.activeMode = activeMode;
            tabs.forEach((tab) => {
                const active = tab.dataset.studentMode === activeMode;
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', String(active));
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.studentPanel !== activeMode;
            });
            heading.firstChild.textContent = copy[activeMode].title;
            heading.querySelector('em').textContent = copy[activeMode].emphasis;
            intro.textContent = copy[activeMode].intro;
            const url = new URL(accessUrl, window.location.origin);
            if (activeMode === 'login') {
                url.searchParams.set('mode', 'login');
            } else {
                url.searchParams.delete('mode');
            }
            window.history.replaceState({}, '', url);
        };

        tabs.forEach((tab) => tab.addEventListener('click', () => setStudentMode(tab.dataset.studentMode)));
        setStudentMode(studentAccess.dataset.initialMode);
    }

    const hasFullscreenControl = document.body.classList.contains('client-bg')
        || document.body.classList.contains('presenter-mode')
        || document.body.classList.contains('designer-mode');

    if (hasFullscreenControl) {
        const displayControls = document.createElement('div');
        displayControls.className = 'presentation-display-controls';
        displayControls.setAttribute('role', 'group');
        displayControls.setAttribute('aria-label', 'Display controls');

        let outlineButton = null;
        if (document.body.classList.contains('presenter-mode') || document.body.classList.contains('designer-mode')) {
            outlineButton = document.createElement('a');
            outlineButton.className = 'outline-designer-toggle';
            const isPresenter = document.body.classList.contains('presenter-mode');
            outlineButton.href = isPresenter ? document.body.dataset.outlineUrl : document.body.dataset.presenterUrl;
            outlineButton.setAttribute('aria-label', isPresenter ? 'Open outline designer' : 'Open presenter view');
            outlineButton.setAttribute('title', isPresenter ? 'Outline designer' : 'Presenter view');
            outlineButton.innerHTML = `<i class="bi ${isPresenter ? 'bi-pencil-square' : 'bi-easel'}" aria-hidden="true"></i>`;
        }

        const fullscreenButton = document.createElement('button');
        fullscreenButton.type = 'button';
        fullscreenButton.className = 'fullscreen-toggle';
        fullscreenButton.textContent = '⛶';

        const renderFullscreenButton = () => {
            const isFullscreen = Boolean(document.fullscreenElement);
            fullscreenButton.setAttribute('aria-label', isFullscreen ? 'Exit full screen' : 'Enter full screen');
            fullscreenButton.setAttribute('title', isFullscreen ? 'Exit full screen' : 'Enter full screen');
        };

        fullscreenButton.addEventListener('click', async () => {
            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen();
                } else {
                    await document.documentElement.requestFullscreen();
                }
            } catch (error) {
                fullscreenButton.setAttribute('title', 'Full screen is unavailable in this browser');
            }
        });
        document.addEventListener('fullscreenchange', renderFullscreenButton);
        renderFullscreenButton();

        if (outlineButton) {
            displayControls.append(outlineButton);
        }
        displayControls.append(fullscreenButton, button);
        document.body.append(displayControls);
    } else {
        document.body.append(button);
    }
})();
