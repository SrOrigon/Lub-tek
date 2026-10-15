/**
 * Barramento de eventos unificado (substitui Events / AppEvents duplicados).
 */
(function (global) {
    'use strict';
    const listeners = {};

    function on(event, fn) {
        if (!listeners[event]) listeners[event] = [];
        listeners[event].push(fn);
        return function off() {
            listeners[event] = (listeners[event] || []).filter(function (h) { return h !== fn; });
        };
    }

    function emit(event, payload) {
        (listeners[event] || []).slice().forEach(function (fn) {
            try { fn(payload); } catch (e) { console.warn('AppEvents', event, e); }
        });
    }

    const bus = { on: on, emit: emit, off: function () {} };
    global.AppEvents = bus;
    if (!global.Events || typeof global.Events.on !== 'function') {
        global.Events = bus;
    }
})(typeof window !== 'undefined' ? window : globalThis);
