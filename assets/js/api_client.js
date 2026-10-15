/**
 * Cliente HTTP unificado LUB-TEK (CSRF, JSON, timeout, FormData).
 */
(function (global) {
    'use strict';

    const DEFAULT_TIMEOUT_MS = 30000;

    function buildHeaders(extra, isFormData) {
        const headers = Object.assign({}, extra || {});
        if (!isFormData && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json; charset=UTF-8';
        }
        if (global.csrfToken) {
            headers['X-CSRF-TOKEN'] = global.csrfToken;
        }
        headers['X-Requested-With'] = 'XMLHttpRequest';
        return headers;
    }

    function lockTrigger() {
        if (!global.document) return null;
        const el = global.document.activeElement;
        if (!el || typeof el.matches !== 'function') return null;
        if (!el.matches('button, [role="button"], input[type="submit"], input[type="button"], .btn')) return null;
        if (el.disabled) return { el: el, already: true };
        el.disabled = true;
        el.setAttribute('aria-busy', 'true');
        el.classList.add('is-loading');
        return { el: el };
    }

    function unlockTrigger(lock) {
        if (!lock || !lock.el || lock.already) return;
        lock.el.disabled = false;
        lock.el.removeAttribute('aria-busy');
        lock.el.classList.remove('is-loading');
    }

    async function request(action, options) {
        options = options || {};
        const method = (options.method || 'POST').toUpperCase();
        const mutating = method === 'POST' || method === 'PUT' || method === 'DELETE';
        const trigger = mutating ? lockTrigger() : null;
        const timeoutMs = options.timeoutMs || DEFAULT_TIMEOUT_MS;
        const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
        const url = 'api.php?action=' + encodeURIComponent(action);

        let body = options.body;
        if (body !== undefined && body !== null && !isFormData && typeof body !== 'string') {
            body = JSON.stringify(body);
        }

        const controller = new AbortController();
        const timer = setTimeout(function () { controller.abort(); }, timeoutMs);

        try {
            const res = await fetch(url, {
                method: method,
                credentials: 'same-origin',
                headers: buildHeaders(options.headers, isFormData),
                body: body,
                signal: controller.signal
            });

            if (res.status === 401) {
                global.location.href = 'login.php';
                throw new Error('Sessão expirada');
            }

            const text = await res.text();
            let data;
            try {
                data = text ? JSON.parse(text) : {};
            } catch (parseErr) {
                throw new Error('Resposta inválida do servidor');
            }

            const ok = res.ok && data.success !== false && data.ok !== false;
            if (!ok) {
                const err = new Error(data.error || data.message || ('HTTP ' + res.status));
                err.response = data;
                err.status = res.status;
                throw err;
            }
            return data.data !== undefined ? data.data : data;
        } catch (err) {
            if (err && err.name === 'AbortError') {
                const timeoutErr = new Error('Tempo esgotado na requisição');
                if (global.LubtekA11y) global.LubtekA11y.announce(timeoutErr.message, 'assertive');
                throw timeoutErr;
            }
            if (global.LubtekA11y && err && err.message) {
                global.LubtekA11y.announce(err.message, 'assertive');
            }
            throw err;
        } finally {
            clearTimeout(timer);
            if (typeof lubtekUnlockTrigger === 'function') lubtekUnlockTrigger(trigger);
            unlockTrigger(trigger);
        }
    }

    const LubtekApi = {
        request: request,
        get: function (action) { return request(action, { method: 'GET' }); },
        post: function (action, body) { return request(action, { method: 'POST', body: body }); },
        upload: function (action, formData) {
            return request(action, { method: 'POST', body: formData });
        }
    };

    global.LubtekApi = LubtekApi;

    if (typeof global.api !== 'function') {
        global.api = function (action, payload) {
            return LubtekApi.post(action, payload || {});
        };
    }
})(typeof window !== 'undefined' ? window : globalThis);
