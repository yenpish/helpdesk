(function () {
    'use strict';

    const form = document.getElementById('attendance-pin-form');
    const status = document.getElementById('attendance-pin-status');
    if (!form || !status) return;

    function showStatus(heading, message, state) {
        status.querySelector('[data-status-heading]').textContent = heading;
        status.querySelector('[data-status-message]').textContent = message;
        status.dataset.state = state || 'info';
        status.hidden = false;
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.reportValidity()) return;

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        showStatus('Verifying PIN', 'A connection is required to verify the event PIN.', 'info');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
            });
            const path = new URL(response.url).pathname;

            if (response.ok && /^\/attendance\/\d+$/.test(path)) {
                window.location.assign(response.url);
                return;
            }

            if (response.ok && response.headers.get('content-type')?.includes('text/html')) {
                document.open();
                document.write(await response.text());
                document.close();
                return;
            }

            showStatus(
                'PIN could not be verified',
                response.status === 419
                    ? 'This page has expired. Reload the page and try again.'
                    : 'Check the PIN and try again while connected.',
                'rejected'
            );
        } catch (error) {
            showStatus(
                'PIN verification needs a connection',
                'Reconnect to verify the event PIN, then try again.',
                'warning'
            );
        } finally {
            if (submitButton && !document.hidden) submitButton.disabled = false;
        }
    });
})();
