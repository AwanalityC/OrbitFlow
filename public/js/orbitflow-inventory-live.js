(() => {
    'use strict';

    const initialiseInventoryFilters = () => {
        const form = document.getElementById('inventory-live-filter');
        const results = document.getElementById('inventory-results');
        const count = document.getElementById('inventory-count');
        const status = document.getElementById('inventory-live-status');
        const reset = document.getElementById('inventory-filter-reset');

        if (!form || !results || !count || !status) return;

        const search = form.querySelector('input[name="q"]');
        const selects = form.querySelectorAll('select');
        const fallbackButton = form.querySelector('.js-filter-fallback');
        const equipmentSelect = document.getElementById('existing-equipment-id');
        const addUnitsForm = document.getElementById('add-existing-units-form');

        if (equipmentSelect && addUnitsForm) {
            equipmentSelect.addEventListener('change', () => {
                const template = addUnitsForm.dataset.unitRouteTemplate || '';
                if (template.includes('__EQUIPMENT_ID__')) {
                    addUnitsForm.action = template.replace(
                        '__EQUIPMENT_ID__',
                        encodeURIComponent(equipmentSelect.value)
                    );
                }
            });
        }

        let debounceTimer = null;
        let activeController = null;
        let latestRequestId = 0;

        const syncFieldsFromUrl = (targetUrl) => {
            const params = new URL(targetUrl, window.location.origin).searchParams;
            for (const field of form.elements) {
                if (!field.name) continue;
                field.value = params.get(field.name) || '';
            }
        };

        const buildUrl = (targetUrl = null) => {
            const url = new URL(targetUrl || form.action, window.location.origin);

            if (!targetUrl) {
                const params = new URLSearchParams();
                new FormData(form).forEach((value, key) => {
                    if (typeof value === 'string' && value.trim() !== '') {
                        params.set(key, value.trim());
                    }
                });
                url.search = params.toString();
                url.searchParams.delete('page');
            }

            return url;
        };

        const refreshResults = async (targetUrl = null) => {
            const url = buildUrl(targetUrl);
            const requestId = ++latestRequestId;

            if (activeController) activeController.abort();

            const controller = new AbortController();
            activeController = controller;

            status.classList.add('is-loading');
            status.textContent = 'Memperbarui hasil…';
            results.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal
                });

                if (!response.ok) {
                    throw new Error(`Permintaan filter gagal (HTTP ${response.status}).`);
                }

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error('Server tidak mengirim respons JSON untuk filter langsung.');
                }

                const payload = await response.json();
                if (
                    !payload ||
                    typeof payload.html !== 'string' ||
                    !Number.isFinite(Number(payload.count))
                ) {
                    throw new Error('Format hasil filter dari server tidak valid.');
                }

                if (requestId !== latestRequestId) return;

                results.innerHTML = payload.html;
                count.textContent = `${payload.count} unit`;
                status.textContent = `${payload.count} unit ditemukan.`;
                form.classList.remove('has-error');
                window.history.replaceState({}, '', url.pathname + url.search);
            } catch (error) {
                if (error.name === 'AbortError' || requestId !== latestRequestId) return;

                form.classList.add('has-error');
                status.textContent = 'Pencarian langsung tidak dapat memuat hasil. Gunakan Terapkan Filter atau muat ulang halaman.';
            } finally {
                if (requestId === latestRequestId) {
                    activeController = null;
                    status.classList.remove('is-loading');
                    results.removeAttribute('aria-busy');
                }
            }
        };

        if (search) {
            search.addEventListener('input', () => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(() => refreshResults(), 180);
            });
        }

        selects.forEach((select) => {
            select.addEventListener('change', () => {
                window.clearTimeout(debounceTimer);
                refreshResults();
            });
        });

        form.addEventListener('submit', (event) => {
            // If the AJAX request has failed, keep the normal GET form as a fallback.
            if (form.classList.contains('has-error')) return;

            event.preventDefault();
            window.clearTimeout(debounceTimer);
            refreshResults();
        });

        reset?.addEventListener('click', (event) => {
            event.preventDefault();
            window.clearTimeout(debounceTimer);
            syncFieldsFromUrl(reset.href);
            form.classList.remove('has-error');
            refreshResults(reset.href);
        });

        results.addEventListener('click', (event) => {
            const link = event.target.closest('.pagination a[href]');
            if (!link || form.classList.contains('has-error')) return;

            event.preventDefault();
            window.clearTimeout(debounceTimer);
            syncFieldsFromUrl(link.href);
            refreshResults(link.href);
        });

        window.addEventListener('popstate', () => {
            window.clearTimeout(debounceTimer);
            syncFieldsFromUrl(window.location.href);
            form.classList.remove('has-error');
            refreshResults(window.location.href);
        });

        // Enable live mode only after all event handlers are attached.
        form.classList.add('is-live');
        form.dataset.liveReady = 'true';
        status.textContent = 'Pencarian langsung aktif.';
        if (fallbackButton) fallbackButton.setAttribute('aria-hidden', 'true');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseInventoryFilters, { once: true });
    } else {
        initialiseInventoryFilters();
    }
})();
