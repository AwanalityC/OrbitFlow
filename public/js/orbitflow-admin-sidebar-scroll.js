(() => {
    'use strict';

    const NAV_SELECTOR = '.admin-nav';
    const STORAGE_KEY = 'orbitflow.admin.sidebar.scroll.v1';

    const getSavedPosition = () => {
        try {
            const raw = window.sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return null;

            const saved = JSON.parse(raw);
            return {
                top: Number.isFinite(saved.top) ? Math.max(0, saved.top) : 0,
                left: Number.isFinite(saved.left) ? Math.max(0, saved.left) : 0,
            };
        } catch (error) {
            // Keep navigation usable when browser storage is unavailable.
            return null;
        }
    };

    const savePosition = (nav) => {
        if (!nav) return;

        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                top: nav.scrollTop,
                left: nav.scrollLeft,
            }));
        } catch (error) {
            // Scroll persistence is an enhancement; never block navigation.
        }
    };

    const restorePosition = (nav) => {
        if (!nav) return;

        const saved = getSavedPosition();
        if (!saved) return;

        // Save both axes: desktop uses vertical scrolling, compact screens use horizontal scrolling.
        nav.scrollTop = saved.top;
        nav.scrollLeft = saved.left;
    };

    const initialize = () => {
        const nav = document.querySelector(NAV_SELECTOR);
        if (!nav || nav.dataset.scrollPersistenceReady === 'true') return;

        nav.dataset.scrollPersistenceReady = 'true';

        const save = () => savePosition(nav);

        nav.addEventListener('scroll', save, { passive: true });
        window.addEventListener('pagehide', save);
        window.addEventListener('beforeunload', save);

        // Save immediately on normal sidebar navigation, including keyboard activation.
        nav.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank') return;
            if (event.defaultPrevented || event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            save();
        });

        // Restore after layout has measured the nav's scrollable dimensions.
        window.requestAnimationFrame(() => restorePosition(nav));
        window.addEventListener('pageshow', () => {
            window.requestAnimationFrame(() => restorePosition(nav));
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
