(() => {
    'use strict';

    const sidebar = document.getElementById('admin-sidebar');
    const toggle = document.querySelector('[data-admin-nav-toggle]');
    const closeButton = document.querySelector('[data-admin-nav-close]');
    const scrim = document.querySelector('[data-admin-nav-scrim]');

    if (!sidebar || !toggle || !closeButton || !scrim) return;

    const mobileQuery = window.matchMedia('(max-width: 820px)');
    const focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const setOpen = (open, restoreFocus = true) => {
        const shouldOpen = mobileQuery.matches && open;

        sidebar.classList.toggle('is-open', shouldOpen);
        sidebar.setAttribute('aria-hidden', String(mobileQuery.matches && !shouldOpen));
        sidebar.inert = mobileQuery.matches && !shouldOpen;
        scrim.hidden = !shouldOpen;
        toggle.setAttribute('aria-expanded', String(shouldOpen));
        toggle.setAttribute('aria-label', shouldOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        document.body.classList.toggle('admin-nav-open', shouldOpen);

        if (shouldOpen) {
            const firstFocusable = Array.from(sidebar.querySelectorAll(focusableSelector))
                .find((element) => !element.hasAttribute('disabled') && element.getAttribute('aria-hidden') !== 'true');
            firstFocusable?.focus();
        } else if (restoreFocus && mobileQuery.matches) {
            toggle.focus();
        }
    };

    toggle.addEventListener('click', () => {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    closeButton.addEventListener('click', () => setOpen(false));
    scrim.addEventListener('click', () => setOpen(false));

    sidebar.addEventListener('click', (event) => {
        const link = event.target.closest('nav a[href]');
        if (link && mobileQuery.matches) setOpen(false, false);
    });

    document.addEventListener('keydown', (event) => {
        if (!mobileQuery.matches || !sidebar.classList.contains('is-open')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = Array.from(sidebar.querySelectorAll(focusableSelector))
            .filter((element) => !element.hasAttribute('disabled') && element.getAttribute('aria-hidden') !== 'true');

        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    const handleViewportChange = () => {
        document.body.classList.toggle('admin-nav-enhanced', mobileQuery.matches);
        if (!mobileQuery.matches) {
            sidebar.classList.remove('is-open');
            sidebar.removeAttribute('aria-hidden');
            sidebar.inert = false;
            scrim.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Buka menu navigasi');
            document.body.classList.remove('admin-nav-open');
        } else {
            setOpen(false, false);
        }
    };

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', handleViewportChange);
    } else {
        mobileQuery.addListener(handleViewportChange);
    }

    handleViewportChange();
})();
