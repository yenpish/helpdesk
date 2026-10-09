(function () {
    'use strict';

    const form = document.getElementById('attendance-form');
    const statusNode = document.getElementById('attendance-sync-status');
    const errorsNode = document.getElementById('attendance-submit-errors');

    if (!form || !statusNode || !window.indexedDB || !window.fetch) {
        return;
    }

    const databaseName = 'attendance-offline-queue';
    const storeName = 'submissions';
    const eventId = Number(form.dataset.eventId);
    let databasePromise;
    let syncing = false;

    function openDatabase() {
        if (!databasePromise) {
            databasePromise = new Promise((resolve, reject) => {
                const request = indexedDB.open(databaseName, 1);
                request.onupgradeneeded = function () {
                    const database = request.result;
                    if (!database.objectStoreNames.contains(storeName)) {
                        database.createObjectStore(storeName, { keyPath: 'id' });
                    }
                };
                request.onsuccess = function () { resolve(request.result); };
                request.onerror = function () { reject(request.error); };
            });
        }

        return databasePromise;
    }

    async function withStore(mode, operation) {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(storeName, mode);
            const store = transaction.objectStore(storeName);
            let result;
            try {
                result = operation(store);
            } catch (error) {
                reject(error);
                return;
            }
            transaction.oncomplete = function () { resolve(result && result.result); };
            transaction.onerror = function () { reject(transaction.error); };
            transaction.onabort = function () { reject(transaction.error); };
        });
    }

    function save(record) {
        return withStore('readwrite', store => store.put(record));
    }

    function remove(id) {
        return withStore('readwrite', store => store.delete(id));
    }

    async function recordsForEvent() {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const request = database.transaction(storeName, 'readonly')
                .objectStore(storeName).getAll();
            request.onsuccess = function () {
                resolve(request.result.filter(record => record.eventId === eventId));
            };
            request.onerror = function () { reject(request.error); };
        });
    }

    function showStatus(message, state) {
        statusNode.textContent = message;
        statusNode.dataset.state = state || 'info';
        statusNode.hidden = false;
    }

    function showErrors(errors) {
        if (!errorsNode) return;
        errorsNode.replaceChildren();
        Object.values(errors || {}).flat().forEach(message => {
            const line = document.createElement('div');
            line.textContent = message;
            errorsNode.appendChild(line);
        });
        errorsNode.hidden = errorsNode.childElementCount === 0;
        if (!errorsNode.hidden) errorsNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function uuid() {
        if (crypto.randomUUID) return crypto.randomUUID();
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        return Array.from(bytes, (byte, index) => {
            const value = byte.toString(16).padStart(2, '0');
            return [4, 6, 8, 10].includes(index) ? '-' + value : value;
        }).join('');
    }

    function csrfToken() {
        return form.querySelector('input[name="_token"]')?.value || '';
    }

    async function send(record, isSync) {
        const payload = new FormData();
        Object.entries(record.payload).forEach(([key, value]) => payload.append(key, value));
        payload.set('_token', csrfToken());
        payload.set('offline_submission_id', record.id);

        return fetch(form.action, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: isSync
                ? { Accept: 'application/json', 'X-Attendance-Sync': '1' }
                : { Accept: 'application/json' },
        });
    }

    function isSuccessPage(html) {
        return /class=["'][^"']*success-page/.test(html);
    }

    async function submitOnline(record) {
        const response = await send(record, false);

        if (response.redirected && new URL(response.url).pathname !== new URL(form.action).pathname) {
            window.location.assign(response.url);
            return 'navigated';
        }

        const contentType = response.headers.get('content-type') || '';
        if (response.ok && contentType.includes('text/html')) {
            const html = await response.text();
            if (isSuccessPage(html)) {
                document.open();
                document.write(html);
                document.close();
                return 'success';
            }
            window.location.assign(response.url);
            return 'navigated';
        }

        if (contentType.includes('application/json')) {
            const result = await response.json();
            if (response.ok && result.status === 'synchronized') return 'success';
            showErrors(result.errors || { attendance: [result.message || 'Attendance could not be submitted.'] });
            return 'rejected';
        }

        if (response.status >= 500) throw new Error('The server did not confirm the submission.');
        showStatus('Attendance could not be submitted. Please review the form and try again.', 'rejected');
        return 'rejected';
    }

    async function queuedRecords() {
        return (await recordsForEvent()).filter(record => record.state === 'queued');
    }

    async function synchronize() {
        if (syncing || !navigator.onLine) return;
        syncing = true;
        try {
            const records = await queuedRecords();
            if (!records.length) return;

            for (const record of records) {
                try {
                    const response = await send(record, true);
                    const contentType = response.headers.get('content-type') || '';
                    if (response.ok && contentType.includes('application/json')) {
                        const result = await response.json();
                        if (result.status === 'synchronized') {
                            await remove(record.id);
                            continue;
                        }
                    }

                    if (response.status === 422 || response.status === 409 || response.status === 419 || response.redirected) {
                        const result = contentType.includes('application/json') ? await response.json() : {};
                        record.state = 'rejected';
                        record.error = result.message || 'The server rejected this saved attendance. Check the session and attendance window.';
                        await save(record);
                        showStatus(record.error + ' The entry remains saved on this device.', 'rejected');
                        continue;
                    }

                    if (response.status >= 500) break;
                } catch (error) {
                    break;
                }
            }

            const remaining = await queuedRecords();
            if (remaining.length === 0) {
                const rejected = (await recordsForEvent()).some(record => record.state === 'rejected');
                if (!rejected) showStatus('Attendance synchronized and recorded by the server.', 'success');
            } else {
                showStatus('Saved attendance is waiting to sync when a connection is available.', 'info');
            }
        } catch (error) {
            showStatus('Offline attendance storage is unavailable. Keep this page open and try again when connected.', 'rejected');
        } finally {
            syncing = false;
        }
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        showErrors({});

        const record = {
            id: uuid(),
            eventId,
            state: 'queued',
            payload: {},
        };
        new FormData(form).forEach((value, key) => {
            if (key !== '_token' && typeof value === 'string') record.payload[key] = value;
        });

        if (navigator.onLine) {
            showStatus('Submitting attendance…', 'info');
            try {
                const outcome = await submitOnline(record);
                if (outcome === 'success' || outcome === 'navigated') return;
                if (outcome === 'rejected') {
                    statusNode.hidden = true;
                    return;
                }
            } catch (error) {
                // A lost response may follow a successful database commit; retain the same UUID for safe retry.
            }
        }

        try {
            await save(record);
            showStatus('Attendance saved on this device and will sync when a connection is available.', 'info');
            if (navigator.onLine) synchronize();
        } catch (error) {
            showStatus('Attendance was not saved on this device. Keep this page open and try again when connected.', 'rejected');
        }
    });

    recordsForEvent().then(records => {
        const rejected = records.find(record => record.state === 'rejected');
        const hasQueued = records.some(record => record.state === 'queued');
        if (rejected) showStatus(rejected.error + ' The entry remains saved on this device.', 'rejected');
        else if (hasQueued) {
            showStatus('Saved attendance is waiting to sync when a connection is available.', 'info');
        }
        if (hasQueued) synchronize();
    }).catch(() => {
        // The regular online attendance form remains available if local storage cannot be opened.
    });

    window.addEventListener('online', synchronize);
    window.setInterval(synchronize, 5000);
})();
