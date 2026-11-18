(() => {
    'use strict';

    const selector = '[data-camera-capture]';

    function findPreview(input) {
        const id = input.dataset.cameraPreview;
        if (id) return document.getElementById(id);
        return input.closest('[data-camera-field]')?.querySelector('[data-camera-preview]') || null;
    }

    function clearPreview(input) {
        const preview = findPreview(input);
        if (input.dataset.objectUrl) {
            URL.revokeObjectURL(input.dataset.objectUrl);
            delete input.dataset.objectUrl;
        }
        if (preview) {
            preview.removeAttribute('src');
            preview.hidden = true;
        }
    }

    document.addEventListener('change', (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.matches(selector)) return;

        clearPreview(input);
        const file = input.files && input.files[0];
        const preview = findPreview(input);
        if (!file || !preview || !file.type.startsWith('image/')) return;

        const objectUrl = URL.createObjectURL(file);
        input.dataset.objectUrl = objectUrl;
        preview.src = objectUrl;
        preview.hidden = false;
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-camera-clear]');
        if (!button) return;
        const inputId = button.dataset.cameraClear;
        const input = inputId ? document.getElementById(inputId) : button.closest('[data-camera-field]')?.querySelector(selector);
        if (!(input instanceof HTMLInputElement)) return;
        input.value = '';
        clearPreview(input);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    window.addEventListener('beforeunload', () => {
        document.querySelectorAll(selector).forEach((input) => {
            if (input.dataset.objectUrl) URL.revokeObjectURL(input.dataset.objectUrl);
        });
    });
})();
