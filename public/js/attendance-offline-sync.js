(function () {
    'use strict';

    const form = document.getElementById('attendance-form');
    const lookupForm = document.getElementById('attendance-lookup-form');
    const statusNode = document.getElementById('attendance-sync-status');
    const errorsNode = document.getElementById('attendance-submit-errors');
    const queueNode = document.getElementById('attendance-queue-entries');
    if (!form || !statusNode || !window.indexedDB || !window.fetch) return;

    const databaseName = 'attendance-offline-queue';
    const version = 2;
    const eventId = Number(form.dataset.eventId);
    let databasePromise;
    let syncing = false;
    let recoverySubmissionId = null;
    let signatureRestorePromise = Promise.resolve();

    function openDatabase() {
        if (!databasePromise) {
            databasePromise = new Promise((resolve, reject) => {
                const request = indexedDB.open(databaseName, version);
                request.onupgradeneeded = function () {
                    const database = request.result;
                    if (!database.objectStoreNames.contains('submissions')) {
                        database.createObjectStore('submissions', { keyPath: 'id' });
                    }
                    if (!database.objectStoreNames.contains('lookupRecords')) {
                        database.createObjectStore('lookupRecords', { keyPath: 'key' });
                    }
                    if (!database.objectStoreNames.contains('confirmed')) {
                        database.createObjectStore('confirmed', { keyPath: 'id' });
                    }
                };
                request.onsuccess = function () { resolve(request.result); };
                request.onerror = function () { reject(request.error); };
                request.onblocked = function () { reject(new Error('Local attendance storage is busy.')); };
            });
        }
        return databasePromise;
    }

    async function write(stores, operation) {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(stores, 'readwrite');
            let result;
            try {
                result = operation(transaction);
            } catch (error) {
                reject(error);
                return;
            }
            transaction.oncomplete = function () { resolve(result); };
            transaction.onerror = function () { reject(transaction.error); };
            transaction.onabort = function () { reject(transaction.error); };
        });
    }

    async function all(storeName) {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const request = database.transaction(storeName, 'readonly')
                .objectStore(storeName).getAll();
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error); };
        });
    }

    function put(storeName, record) {
        return write([storeName], transaction => transaction.objectStore(storeName).put(record));
    }

    async function read(storeName, key) {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const request = database.transaction(storeName, 'readonly')
                .objectStore(storeName).get(key);
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error); };
        });
    }

    function currentEventRecords() {
        return Promise.all([all('submissions'), all('confirmed')]).then(([queued, confirmed]) => [
            ...queued.filter(record => record.eventId === eventId && record.state !== 'rejected'),
            ...confirmed.filter(record => record.eventId === eventId),
        ]);
    }

    function showStatus(heading, message, state) {
        statusNode.querySelector('[data-status-heading]').textContent = heading;
        statusNode.querySelector('[data-status-message]').textContent = message;
        statusNode.dataset.state = state || 'info';
        statusNode.hidden = false;
    }

    function maskedEmail(email) {
        const [name, domain] = String(email || '').split('@');
        if (!domain) return '';
        return `${name.slice(0, 1)}•••@${domain}`;
    }

    async function fetchWithTimeout(url, options, timeoutMs = 15000) {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), timeoutMs);
        try {
            return await fetch(url, { ...options, signal: controller.signal });
        } finally {
            window.clearTimeout(timeout);
        }
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

    function normalizePhone(value) {
        const digits = String(value || '').replace(/\D/g, '');
        return digits.startsWith('0') ? '60' + digits.slice(1) : digits;
    }

    async function lookupKey(email, phone) {
        const source = `${eventId}\n${email.trim().toLowerCase()}\n${normalizePhone(phone)}`;
        if (!crypto.subtle || !window.TextEncoder) throw new Error('Secure local lookup storage is unavailable.');
        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(source));
        return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
    }

    function sessionOption(sessionId) {
        return Array.from(form.elements.session_id?.options || [])
            .find(option => option.value === String(sessionId));
    }

    function updateSessionAvailability() {
        if (navigator.onLine) return;
        const now = Date.now();
        Array.from(form.elements.session_id?.options || []).forEach(option => {
            if (!option.value) return;
            const opens = Date.parse(option.dataset.opensAt || '');
            const closes = Date.parse(option.dataset.closesAt || '');
            option.disabled = !Number.isFinite(opens) || !Number.isFinite(closes) || now < opens || now > closes;
        });
    }

    function validateLocally(payload) {
        const option = sessionOption(payload.session_id);
        if (!option || option.disabled) {
            return 'Choose a session that is currently available on this device.';
        }
        const opens = Date.parse(option.dataset.opensAt || '');
        const closes = Date.parse(option.dataset.closesAt || '');
        const now = Date.now();
        if (!Number.isFinite(opens) || !Number.isFinite(closes)) {
            return 'This session schedule is not available offline. Reconnect before submitting.';
        }
        if (now < opens || now > closes) {
            return 'The saved session schedule says attendance is not open now. Reconnect to verify the session.';
        }
        const phoneLength = normalizePhone(payload.phone).length;
        if (phoneLength < 7 || phoneLength > 15) {
            return 'Enter a phone number containing 7 to 15 digits.';
        }
        return null;
    }

    async function localDuplicate(payload) {
        const records = await currentEventRecords();
        const email = String(payload.email || '').trim().toLowerCase();
        const phone = normalizePhone(payload.phone);
        const sessionId = Number(payload.session_id);
        for (const record of records) {
            const recordEmail = String(record.email ?? record.payload?.email ?? '').trim().toLowerCase();
            const recordPhone = normalizePhone(record.phone ?? record.payload?.phone ?? '');
            const recordSession = Number(record.sessionId ?? record.payload?.session_id);
            if (recordEmail === email && recordSession === sessionId) {
                return record.state === 'queued'
                    ? 'This check-in is already saved on this device and waiting to sync.'
                    : 'This attendee is already recorded on this device for this session.';
            }
            if (phone && recordPhone === phone && (recordEmail !== email || recordSession === sessionId)) {
                return recordEmail !== email
                    ? 'This phone number is already linked to another locally saved attendance record for this event.'
                    : 'This phone number is already saved for this session on this device.';
            }
        }
        return null;
    }

    function makePayload(sourceForm) {
        const payload = {};
        new FormData(sourceForm).forEach((value, key) => {
            if (key !== '_token' && key !== 'action' && typeof value === 'string') payload[key] = value;
        });
        return payload;
    }

    function csrfToken() {
        return form.querySelector('input[name="_token"]')?.value || '';
    }

    async function sendAttendance(record, synchronize) {
        const payload = new FormData();
        Object.entries(record.payload).forEach(([key, value]) => payload.append(key, value));
        payload.set('_token', csrfToken());
        payload.set('offline_submission_id', record.id);
        payload.set('client_submitted_at', record.clientSubmittedAt);

        return fetchWithTimeout(form.getAttribute('action'), {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: synchronize
                ? { Accept: 'application/json', 'X-Attendance-Sync': '1' }
                : { Accept: 'application/json' },
        });
    }

    async function confirmAndRemove(record, attendanceId) {
        const confirmed = {
            id: record.id,
            eventId,
            sessionId: Number(record.payload.session_id),
            email: String(record.payload.email || '').trim().toLowerCase(),
            phone: normalizePhone(record.payload.phone),
            serverAttendanceId: attendanceId || null,
            clientSubmittedAt: record.clientSubmittedAt,
            confirmedAt: new Date().toISOString(),
        };
        await write(['confirmed', 'submissions'], transaction => {
            transaction.objectStore('confirmed').put(confirmed);
            transaction.objectStore('submissions').delete(record.id);
        });
        if (recoverySubmissionId === record.id) recoverySubmissionId = null;
    }

    function isSuccessPage(html) {
        return /class=["'][^"']*success-page/.test(html);
    }

    async function submitOnline(record) {
        const response = await sendAttendance(record, false);
        const responsePath = new URL(response.url).pathname;
        if (response.redirected && responsePath !== new URL(form.action).pathname) {
            window.location.assign(response.url);
            return 'navigated';
        }

        const contentType = response.headers.get('content-type') || '';
        if (response.ok && contentType.includes('text/html')) {
            const html = await response.text();
            if (isSuccessPage(html)) {
                await confirmAndRemove(record, response.headers.get('X-Attendance-Id'));
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
            if (response.ok && result.status === 'synchronized') {
                await confirmAndRemove(record, result.attendance_id);
                showStatus('Attendance synchronized', 'The server confirmed and recorded this check-in.', 'success');
                return 'success';
            }
            const errors = result.errors || { attendance: [result.message || 'Attendance could not be submitted.'] };
            showErrors(errors);
            if (recoverySubmissionId === record.id) {
                record.state = 'rejected';
                record.lastError = Object.values(errors).flat().join(' ');
                await put('submissions', record);
                showRejectedRecord(record);
                await refreshQueueState();
            }
            return 'rejected';
        }

        if (response.status >= 500 || response.status === 0) throw new Error('The server did not confirm this attendance.');
        if (recoverySubmissionId === record.id) {
            record.state = 'rejected';
            record.lastError = `The server did not accept this saved check-in (HTTP ${response.status}).`;
            await put('submissions', record);
            showRejectedRecord(record);
            await refreshQueueState();
            return 'rejected';
        }
        showStatus('Attendance not recorded', 'Review the form or reconnect, then try again.', 'rejected');
        return 'rejected';
    }

    async function submitAttendance() {
        showErrors({});
        const payload = makePayload(form);
        const localError = validateLocally(payload);
        if (localError) {
            showErrors({ attendance: [localError] });
            return;
        }

        const duplicate = await localDuplicate(payload);
        if (duplicate) {
            showErrors({ attendance: [duplicate] });
            return;
        }

        const recoveredRecord = recoverySubmissionId
            ? await read('submissions', recoverySubmissionId)
            : null;
        if (recoveredRecord?.eventId !== eventId) recoverySubmissionId = null;
        const record = {
            id: recoveredRecord?.eventId === eventId ? recoveredRecord.id : uuid(),
            eventId,
            sessionId: Number(payload.session_id),
            email: String(payload.email || '').trim().toLowerCase(),
            phone: normalizePhone(payload.phone),
            clientSubmittedAt: recoveredRecord?.clientSubmittedAt || new Date().toISOString(),
            state: 'queued',
            attempts: recoveredRecord?.attempts || 0,
            lastError: null,
            payload,
        };

        if (navigator.onLine) {
            showStatus('Submitting attendance', 'The server must confirm the check-in before it is recorded.', 'info');
            try {
                const outcome = await submitOnline(record);
                if (outcome === 'success' || outcome === 'navigated') return;
                if (outcome === 'rejected') {
                    if (recoverySubmissionId !== record.id) statusNode.hidden = true;
                    return;
                }
            } catch (error) {
                // A lost response can follow a committed request; retry the same ID safely.
            }
        }

        try {
            await put('submissions', record);
            showStatus('Saved on this device', 'This check-in is queued locally and is not yet confirmed by the server.', 'queued');
            await refreshQueueState();
            if (navigator.onLine) synchronize();
        } catch (error) {
            showStatus('Attendance was not saved', 'This browser could not write to local storage. Keep the page open and reconnect before retrying.', 'rejected');
        }
    }

    function fillAttendance(details) {
        for (const [name, value] of Object.entries(details || {})) {
            const input = form.elements.namedItem(name);
            if (input && typeof value === 'string') input.value = value;
        }
    }

    function renderQueueEntries(records) {
        if (!queueNode) return;
        queueNode.replaceChildren();

        records.forEach(record => {
            const row = document.createElement('div');
            row.className = 'attendance-queue-entry';

            const details = document.createElement('div');
            details.className = 'attendance-queue-entry-details';
            const identity = document.createElement('strong');
            identity.textContent = record.payload?.full_name || maskedEmail(record.email);
            const summary = document.createElement('span');
            const option = sessionOption(record.sessionId ?? record.payload?.session_id);
            const sessionName = option?.dataset.name || `Session ${record.sessionId ?? record.payload?.session_id ?? ''}`;
            const state = record.state === 'rejected'
                ? `Needs attention${record.lastError ? ` — ${record.lastError}` : ''}`
                : record.state === 'syncing'
                    ? 'Synchronization in progress'
                    : `Pending synchronization${record.lastError ? ` — ${record.lastError}` : ''}`;
            summary.textContent = `${sessionName} · ${state}`;
            details.append(identity, summary);
            row.append(details);

            if (record.state === 'rejected') {
                const actions = document.createElement('div');
                actions.className = 'attendance-queue-entry-actions';
                const restore = document.createElement('button');
                restore.type = 'button';
                restore.className = 'btn btn-secondary btn-sm';
                restore.dataset.restoreSubmission = record.id;
                restore.textContent = 'Load saved details';
                const discard = document.createElement('button');
                discard.type = 'button';
                discard.className = 'btn btn-secondary btn-sm';
                discard.dataset.discardSubmission = record.id;
                discard.textContent = 'Discard saved entry';
                actions.append(restore, discard);
                row.append(actions);
            }

            queueNode.append(row);
        });

        queueNode.hidden = records.length === 0;
    }

    function showRejectedRecord(record) {
        const option = sessionOption(record.sessionId ?? record.payload?.session_id);
        const identity = record.payload?.full_name || maskedEmail(record.email) || 'Saved attendee';
        const sessionName = option?.dataset.name || `Session ${record.sessionId ?? record.payload?.session_id ?? ''}`;
        showStatus(
            'Saved check-in needs attention',
            `${identity} · ${sessionName}. ${record.lastError || 'The server rejected this saved attendance.'} The original details and signature are still saved on this device. Load them to review and retry, or discard this entry.`,
            'rejected'
        );
    }

    async function restoreRejectedRecord(id) {
        const record = await read('submissions', id);
        if (!record || record.eventId !== eventId || record.state !== 'rejected') return;

        fillAttendance(record.payload);
        recoverySubmissionId = record.id;

        const session = form.elements.namedItem('session_id');
        if (session && sessionOption(record.sessionId ?? record.payload?.session_id)) {
            session.value = String(record.sessionId ?? record.payload?.session_id);
            session.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const canvas = document.getElementById('signature-pad');
        const signature = form.elements.namedItem('signature');
        if (signature) signature.value = record.payload?.signature || '';
        if (canvas && record.payload?.signature) {
            const context = canvas.getContext('2d');
            const image = new Image();
            canvas.dataset.restoringSignature = 'true';
            signatureRestorePromise = new Promise(resolve => {
                image.onload = () => {
                    context.clearRect(0, 0, canvas.width, canvas.height);
                    context.drawImage(image, 0, 0, canvas.width, canvas.height);
                    canvas.dataset.restoringSignature = 'false';
                    resolve();
                };
                image.onerror = () => {
                    canvas.dataset.restoringSignature = 'false';
                    resolve();
                };
            });
            image.src = record.payload.signature;
        }

        showStatus('Saved details loaded', 'Review the attendee details and signature, correct any issues, then submit to retry this same saved entry.', 'warning');
        await refreshQueueState();
    }

    async function discardRejectedRecord(id) {
        const record = await read('submissions', id);
        if (!record || record.eventId !== eventId || record.state !== 'rejected') return;
        const identity = record.payload?.full_name || maskedEmail(record.email) || 'this saved attendee';
        if (!window.confirm(`Discard the rejected saved check-in for ${identity}? This removes it from this device and cannot be undone.`)) return;

        await write(['submissions'], transaction => transaction.objectStore('submissions').delete(id));
        if (recoverySubmissionId === id) recoverySubmissionId = null;
        await showPendingState();
    }

    if (queueNode) {
        queueNode.addEventListener('click', event => {
            const restore = event.target.closest('[data-restore-submission]');
            const discard = event.target.closest('[data-discard-submission]');
            const task = restore
                ? restoreRejectedRecord(restore.dataset.restoreSubmission)
                : discard
                    ? discardRejectedRecord(discard.dataset.discardSubmission)
                    : null;
            if (task) task.catch(() => showStatus('Saved entry unavailable', 'The browser could not read or update this saved check-in. Its local record was not intentionally removed.', 'rejected'));
        });
    }

    async function refreshQueueState() {
        const records = (await all('submissions'))
            .filter(record => record.eventId === eventId)
            .sort((left, right) => String(left.clientSubmittedAt).localeCompare(String(right.clientSubmittedAt)));
        renderQueueEntries(records);
        return records;
    }

    async function restoreInterruptedSyncs() {
        const records = (await all('submissions'))
            .filter(record => record.eventId === eventId && record.state === 'syncing')
            .filter(record => !Number.isFinite(Date.parse(record.syncStartedAt))
                || Date.now() - Date.parse(record.syncStartedAt) > 120000);
        for (const record of records) {
            record.state = 'queued';
            record.lastError = 'A previous synchronization was interrupted; this entry will retry.';
            await put('submissions', record);
        }
    }

    async function storeLookup(details, email, phone) {
        const key = await lookupKey(email, phone);
        const closes = Array.from(form.elements.session_id.options)
            .map(option => Date.parse(option.dataset.closesAt || ''))
            .filter(Number.isFinite);
        const retentionEnd = closes.length ? Math.max(...closes) + 24 * 60 * 60 * 1000 : Date.now() + 24 * 60 * 60 * 1000;
        await put('lookupRecords', {
            key,
            eventId,
            email: email.trim().toLowerCase(),
            phone: normalizePhone(phone),
            details,
            expiresAt: retentionEnd,
        });
    }

    async function cachedLookup(email, phone) {
        const record = await read('lookupRecords', await lookupKey(email, phone));
        if (!record || record.eventId !== eventId) return null;
        if (record.expiresAt <= Date.now()) {
            await write(['lookupRecords'], transaction => transaction.objectStore('lookupRecords').delete(record.key));
            return null;
        }
        return record;
    }

    async function lookupOffline(email, phone) {
        try {
            const cached = await cachedLookup(email, phone);
            if (cached) {
                fillAttendance(cached.details);
                showStatus('Pre-registration found on this device', 'The saved details are filled in. Verify them before submitting attendance.', 'success');
                return;
            }
        } catch (error) {
            showStatus('Local lookup unavailable', 'This browser could not read saved lookup data. You can enter attendance details manually.', 'warning');
            return;
        }
        showStatus('Pre-registration could not be verified offline', 'No matching lookup is saved on this device. You may enter the attendance details manually.', 'warning');
    }

    async function lookupPreRegistration() {
        if (!lookupForm || !lookupForm.reportValidity()) return;
        const email = lookupForm.elements.lookup_email.value.trim().toLowerCase();
        const phone = lookupForm.elements.lookup_phone.value;
        const button = lookupForm.querySelector('button[type="submit"]');
        showErrors({});

        if (button) button.disabled = true;
        lookupForm.setAttribute('aria-busy', 'true');
        try {
            if (!navigator.onLine) {
                await lookupOffline(email, phone);
                return;
            }

            showStatus('Checking pre-registration', 'Looking up the email and phone for this event.', 'info');
            let response;
            try {
                response = await fetchWithTimeout(lookupForm.getAttribute('action'), {
                    method: 'POST',
                    body: new FormData(lookupForm),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
            } catch (error) {
                if (!navigator.onLine) {
                    await lookupOffline(email, phone);
                } else {
                    showStatus('Lookup service unavailable', 'The server could not be reached, so no registration result was confirmed. Retry or enter attendance details manually.', 'warning');
                }
                return;
            }

            if (response.redirected) {
                showStatus('Attendance session needs refreshing', 'The server redirected this lookup instead of returning a result. Reconnect, reload the attendance page, then retry.', 'warning');
                return;
            }

            const contentType = response.headers.get('content-type') || '';
            if (response.status === 422 && contentType.includes('application/json')) {
                const result = await response.json();
                showErrors(result.errors || { lookup_email: [result.message || 'Check the lookup details.'] });
                statusNode.hidden = true;
                return;
            }

            if (!response.ok) {
                showStatus('Lookup request failed', `The server returned HTTP ${response.status}. No registration result was confirmed. You can retry or enter attendance details manually.`, 'rejected');
                return;
            }

            if (!contentType.includes('application/json')) {
                showStatus('Unexpected lookup response', 'The server did not return the expected lookup result. No registration was treated as found. Reload while connected and try again.', 'rejected');
                return;
            }

            let result;
            try {
                result = await response.json();
            } catch (error) {
                showStatus('Invalid lookup response', 'The server response could not be read. No registration was treated as found. You can retry.', 'rejected');
                return;
            }

            const detailsAreValid = result?.details
                && ['full_name', 'email', 'phone', 'position', 'unit']
                    .every(key => typeof result.details[key] === 'string');
            if (result?.status === 'found' && detailsAreValid) {
                fillAttendance(result.details);
                try {
                    await storeLookup(result.details, email, phone);
                } catch (error) {
                    // Online lookup still succeeds if local browser storage is unavailable.
                }
                showStatus(
                    'Pre-registration found',
                    result.registration_status === 'pending'
                        ? 'Details are filled in. The registration is still pending approval; verify them before attendance.'
                        : 'Details are filled in. Verify them before submitting attendance.',
                    'success'
                );
                return;
            }

            if (result?.status === 'not_found') {
                showStatus('Pre-registration not found', 'No matching pre-registration was found for this event. You can enter attendance details manually.', 'warning');
                return;
            }

            showStatus('Invalid lookup response', 'The server returned an unrecognized result. No registration was treated as found. You can retry.', 'rejected');
        } catch (error) {
            showStatus('Lookup could not be completed', 'An unexpected error interrupted the lookup. No registration was treated as found. You can correct the inputs and retry.', 'rejected');
        } finally {
            if (button) button.disabled = false;
            lookupForm.removeAttribute('aria-busy');
        }
    }

    async function queuedRecords() {
        return (await all('submissions'))
            .filter(record => record.eventId === eventId && record.state === 'queued');
    }

    async function synchronize() {
        if (syncing || !navigator.onLine) return;
        syncing = true;
        try {
            await restoreInterruptedSyncs();
            const records = await queuedRecords();
            if (!records.length) {
                const current = await refreshQueueState();
                const rejected = current.find(record => record.state === 'rejected');
                if (rejected) showRejectedRecord(rejected);
                else if (!current.length) statusNode.hidden = true;
                return;
            }

            showStatus('Synchronizing attendance', `${records.length} saved check-in${records.length === 1 ? '' : 's'} waiting for server confirmation.`, 'syncing');
            await refreshQueueState();
            for (const record of records) {
                try {
                    record.attempts = (record.attempts || 0) + 1;
                    record.state = 'syncing';
                    record.syncStartedAt = new Date().toISOString();
                    await put('submissions', record);
                    await refreshQueueState();
                    const response = await sendAttendance(record, true);
                    const contentType = response.headers.get('content-type') || '';
                    let result = null;
                    if (contentType.includes('application/json')) {
                        try {
                            result = await response.json();
                        } catch (error) {
                            result = null;
                        }
                    }

                    if (response.ok && contentType.includes('application/json')) {
                        if (result?.status === 'synchronized') {
                            await confirmAndRemove(record, result.attendance_id);
                            continue;
                        }
                    }

                    const transientFailure = response.status === 0
                        || [408, 425, 429].includes(response.status)
                        || response.status >= 500
                        || (response.ok && !response.redirected && !result?.status);

                    if (transientFailure) {
                        record.state = 'queued';
                        record.lastError = response.ok
                            ? 'The server response did not confirm this check-in. It remains saved and will retry with the same submission ID.'
                            : `The server temporarily failed (HTTP ${response.status}). This entry remains saved and will retry.`;
                        await put('submissions', record);
                        break;
                    }

                    if (!response.ok || response.redirected || result?.status !== 'synchronized') {
                        const messages = Object.values(result?.errors || {}).flat();
                        record.state = 'rejected';
                        record.lastError = messages.join(' ')
                            || result?.message
                            || (response.redirected
                                ? 'The server redirected the request. Reload the attendance page while connected, then review this saved entry.'
                                : `The server rejected this saved attendance (HTTP ${response.status}). Check the details and session.`);
                        await put('submissions', record);
                        continue;
                    }
                } catch (error) {
                    record.state = 'queued';
                    record.lastError = 'The server could not be reached. The check-in remains queued.';
                    await put('submissions', record);
                    break;
                }
            }

            const currentRecords = await refreshQueueState();
            const rejected = currentRecords.find(record => record.state === 'rejected');
            const remaining = currentRecords.filter(record => record.state === 'queued' || record.state === 'syncing');
            if (rejected) {
                showRejectedRecord(rejected);
            } else if (remaining.length) {
                showStatus('Pending synchronization', `${remaining.length} check-in${remaining.length === 1 ? '' : 's'} remain saved on this device and will retry when the server is reachable.`, 'queued');
            } else {
                showStatus('Attendance synchronized', 'The server confirmed the saved check-in. Live Attendance will update on its next refresh.', 'success');
            }
        } catch (error) {
            showStatus('Synchronization paused', 'Local storage or the server is unavailable. Pending entries remain on this device.', 'rejected');
        } finally {
            syncing = false;
        }
    }

    async function showPendingState() {
        try {
            await restoreInterruptedSyncs();
            const records = await refreshQueueState();
            if (!records.length) {
                statusNode.hidden = true;
                if (!navigator.onLine) {
                    showStatus('Offline attendance ready', 'This event form is available locally. New check-ins will wait for server verification.', 'warning');
                }
                return;
            }

            const queued = records.filter(record => record.state === 'queued').length;
            const inProgress = records.filter(record => record.state === 'syncing').length;
            const rejected = records.find(record => record.state === 'rejected');
            if (rejected) {
                showRejectedRecord(rejected);
                if (queued) synchronize();
            } else if (queued) {
                showStatus('Pending synchronization', `${queued} check-in${queued === 1 ? '' : 's'} remain saved on this device.`, 'queued');
                synchronize();
            } else if (inProgress) {
                showStatus('Synchronization in progress', `${inProgress} saved check-in${inProgress === 1 ? '' : 's'} currently being sent.`, 'syncing');
            } else if (!navigator.onLine) {
                showStatus('Offline attendance ready', 'This event form is available locally. New check-ins will wait for server verification.', 'warning');
            }
        } catch (error) {
            if (!navigator.onLine) showStatus('Offline storage unavailable', 'Attendance cannot be queued from this browser right now.', 'rejected');
        }
    }

    function requestPageCache(registration) {
        const worker = registration?.active;
        const url = new URL(window.location.href);
        if (worker && /^\/attendance\/\d+$/.test(url.pathname)) {
            worker.postMessage({ type: 'cache-attendance-page', url: `${url.origin}${url.pathname}` });
        }
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .then(registration => requestPageCache(registration))
            .catch(() => showStatus(
                'Offline page caching unavailable',
                'The currently open page can queue check-ins, but it may not reopen offline on this device.',
                'warning'
            ));
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        submitAttendance().catch(() => showStatus(
            'Attendance was not saved',
            'The browser could not complete local validation or storage. Keep the page open and reconnect before retrying.',
            'rejected'
        ));
    });

    if (lookupForm) {
        lookupForm.addEventListener('submit', event => {
            event.preventDefault();
            lookupPreRegistration().catch(() => showStatus(
                'Lookup unavailable',
                'No authoritative lookup could be completed. Enter the attendance details manually or retry when connected.',
                'warning'
            ));
        });
    }

    window.addEventListener('online', () => {
        updateSessionAvailability();
        synchronize();
    });
    window.addEventListener('offline', () => {
        updateSessionAvailability();
        showStatus('Offline attendance ready', 'Local session data and check-in entry are available; server confirmation and uncached lookups must wait.', 'warning');
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) synchronize();
    });
    window.setInterval(() => {
        updateSessionAvailability();
        synchronize();
    }, 5000);

    updateSessionAvailability();
    showPendingState();
})();
