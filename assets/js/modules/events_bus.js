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

    function off(event, fn) {
        if (!listeners[event]) return;
        if (typeof fn !== 'function') {
            delete listeners[event];
            return;
        }
        listeners[event] = listeners[event].filter(function (h) { return h !== fn; });
    }

    function once(event, fn) {
        function wrapper(payload) {
            off(event, wrapper);
            fn(payload);
        }
        return on(event, wrapper);
    }

    function emit(event, payload) {
        (listeners[event] || []).slice().forEach(function (fn) {
            try { fn(payload); } catch (e) { console.warn('AppEvents', event, e); }
        });
    }

    const bus = { on: on, once: once, off: off, emit: emit };
    global.AppEvents = bus;
    if (!global.Events || typeof global.Events.on !== 'function') {
        global.Events = bus;
    }
})(typeof window !== 'undefined' ? window : globalThis);
