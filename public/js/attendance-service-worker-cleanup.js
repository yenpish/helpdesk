(() => {
    if (!('serviceWorker' in navigator)) return;

    const workerPath = '/service-worker.js';

    navigator.serviceWorker.getRegistrations()
        .then(registrations => Promise.all(registrations.map(registration => {
            const worker = registration.active || registration.waiting || registration.installing;
            if (!worker || new URL(worker.scriptURL).pathname !== workerPath) return false;

            return registration.unregister();
        })))
        .catch(() => {});

    if ('caches' in window) {
        caches.keys()
            .then(cacheNames => Promise.all(cacheNames
                .filter(name => name.startsWith('attendance-shell-'))
                .map(name => caches.delete(name))))
            .catch(() => {});
    }
})();
