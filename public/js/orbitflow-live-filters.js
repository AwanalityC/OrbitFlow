(() => {
    'use strict';

    const FORM_SELECTOR = 'form[data-live-filter]';
    const activeFilters = new WeakMap();

    const createStatus = (form) => {
        const status = document.createElement('p');
        status.className = 'orbitflow-live-filter-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.hidden = true;
        status.style.cssText = 'margin:8px 0 0;color:#71839a;font-size:12px;line-height:1.4;';
        form.insertAdjacentElement('afterend', status);
        return status;
    };

    const initialise = (form) => {
        if (activeFilters.has(form)) return;

        const selector = form.dataset.liveTarget;
        if (!selector) return;

        let target;
        try {
            target = document.querySelector(selector);
        } catch (_) {
            return;
        }
        if (!target) return;

        const status = createStatus(form);
        const fallbackButtons = Array.from(form.querySelectorAll('.js-live-filter-fallback'));
        const delay = Math.max(100, Number.parseInt(form.dataset.liveDebounce || '180', 10) || 180);
        let debounceTimer = null;
        let activeController = null;
        let latestRequestId = 0;

        const setFallbackVisible = (visible) => {
            fallbackButtons.forEach((button) => {
                button.style.display = visible ? '' : 'none';
                button.setAttribute('aria-hidden', visible ? 'false' : 'true');
            });
        };

        const setStatus = (message, visible = true) => {
            status.textContent = message;
            status.hidden = !visible;
        };

        const syncFieldsFromUrl = (href) => {
            const params = new URL(href, window.location.origin).searchParams;
            Array.from(form.elements).forEach((field) => {
                if (!field.name || field.disabled) return;
                if (field.type === 'submit' || field.type === 'button') return;

                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = params.getAll(field.name).includes(field.value);
                } else if (field.tagName === 'SELECT' && field.multiple) {
                    const selected = params.getAll(field.name);
                    Array.from(field.options).forEach((option) => {
                        option.selected = selected.includes(option.value);
                    });
                } else {
                    field.value = params.has(field.name) ? (params.get(field.name) || '') : (field.tagName === 'SELECT' ? (field.options[0]?.value || '') : '');
                }
            });
        };

        const buildUrl = (targetHref = null) => {
            const url = new URL(targetHref || form.action || window.location.href, window.location.origin);

            if (!targetHref) {
                const params = new URLSearchParams();
                new FormData(form).forEach((value, key) => {
                    if (typeof value === 'string' && value.trim() !== '') {
                        params.append(key, value.trim());
                    }
                });
                params.delete('page');
                url.search = params.toString();
            }

            return url;
        };

        const refreshResults = async (targetHref = null, updateAddress = true) => {
            const url = buildUrl(targetHref);
            const requestId = ++latestRequestId;

            if (activeController) activeController.abort();
            activeController = new AbortController();

            form.classList.remove('has-error');
            setFallbackVisible(false);
            setStatus('Memperbarui hasil…');
            target.setAttribute('aria-busy', 'true');

            try {
                const requestHeaders = { 'Accept': 'text/html' };
                if (form.dataset.livePartial === 'true') {
                    requestHeaders['X-OrbitFlow-Live-Filter'] = 'partial';
                }

                const response = await fetch(url.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: requestHeaders,
                    signal: activeController.signal
                });

                if (!response.ok) {
                    throw new Error(`Permintaan filter gagal (HTTP ${response.status}).`);
                }

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('text/html')) {
                    throw new Error('Respons filter bukan halaman HTML yang dapat dibaca.');
                }

                const responseHtml = await response.text();
                let replacementHtml = responseHtml;

                // Partial mode receives only the rows and paginator, so there is
                // no need to parse a complete Laravel page with DOMParser.
                if (form.dataset.livePartial !== 'true') {
                    const parsed = new DOMParser().parseFromString(responseHtml, 'text/html');
                    const replacement = parsed.querySelector(selector);

                    if (!replacement) {
                        throw new Error('Bagian hasil pencarian tidak ditemukan pada respons server.');
                    }

                    replacementHtml = replacement.innerHTML;
                }

                if (requestId !== latestRequestId) return;

                target.innerHTML = replacementHtml;
                target.removeAttribute('aria-busy');

                if (updateAddress) {
                    window.history.replaceState(
                        { orbitflowLiveFilter: true },
                        '',
                        url.pathname + url.search + url.hash
                    );
                }

                setStatus('', false);
                form.classList.remove('has-error');
                setFallbackVisible(false);
            } catch (error) {
                if (error.name === 'AbortError' || requestId !== latestRequestId) return;

                form.classList.add('has-error');
                setFallbackVisible(true);
                setStatus('Pencarian otomatis gagal. Gunakan tombol Terapkan untuk memuat ulang hasil.');
            } finally {
                if (requestId === latestRequestId) {
                    activeController = null;
                    target.removeAttribute('aria-busy');
                }
            }
        };

        const scheduleSearch = () => {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(() => refreshResults(), delay);
        };

        form.addEventListener('input', (event) => {
            if (event.target.matches('input[type="search"], input[data-live-search]')) {
                scheduleSearch();
            }
        });

        form.addEventListener('change', (event) => {
            if (event.target.matches('select, input[type="date"], input[type="checkbox"], input[type="radio"]')) {
                window.clearTimeout(debounceTimer);
                refreshResults();
            }
        });

        form.addEventListener('submit', (event) => {
            // Leave normal GET submission available as an accessible fallback after a request error.
            if (form.classList.contains('has-error')) return;
            event.preventDefault();
            window.clearTimeout(debounceTimer);
            refreshResults();
        });

        form.addEventListener('click', (event) => {
            const reset = event.target.closest('[data-live-reset]');
            if (!reset) return;
            if (form.classList.contains('has-error')) return;

            event.preventDefault();
            window.clearTimeout(debounceTimer);
            syncFieldsFromUrl(reset.href);
            refreshResults(reset.href);
        });

        target.addEventListener('click', (event) => {
            const link = event.target.closest('.pagination a[href], .admin-pager a[href]');
            if (!link || form.classList.contains('has-error')) return;

            const destination = new URL(link.href, window.location.origin);
            const action = new URL(form.action, window.location.origin);
            if (destination.origin !== action.origin || destination.pathname !== action.pathname) return;

            event.preventDefault();
            window.clearTimeout(debounceTimer);
            syncFieldsFromUrl(link.href);
            refreshResults(link.href);
        });

        window.addEventListener('popstate', () => {
            const current = new URL(window.location.href);
            const action = new URL(form.action, window.location.origin);
            if (current.pathname !== action.pathname) return;
            window.clearTimeout(debounceTimer);
            syncFieldsFromUrl(window.location.href);
            refreshResults(window.location.href, false);
        });

        activeFilters.set(form, true);
        form.classList.add('is-live');
        setFallbackVisible(false);
        setStatus('', false);
    };

    const initialiseAll = () => {
        document.querySelectorAll(FORM_SELECTOR).forEach(initialise);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseAll, { once: true });
    } else {
        initialiseAll();
    }
})();
