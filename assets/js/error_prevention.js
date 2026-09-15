/**
 * LUB-TEK Error Prevention & Resilience Layer
 * Prevents UI crashes due to missing external libraries or failed API calls.
 */

// 1. LUCIDE POLYFILL (Icon Safety)
if (typeof lucide === 'undefined') {
    window.lucide = {
        createIcons: () => console.warn('[SafeMode] Lucide icons skipped (CDN Offline)'),
        replace: () => {}
    };
}

// 2. CHART.JS POLYFILL (Dashboard Safety)
if (typeof Chart === 'undefined') {
    window.Chart = class {
        constructor() { console.warn('[SafeMode] Chart creation skipped (CDN Offline)'); }
        update() {}
        destroy() {}
    };
}

// 3. TOAST FALLBACK
if (typeof showToast !== 'function') {
    window.showToast = (msg, type) => {
        console.log(`[Toast Fallback] ${type}: ${msg}`);
        // Basic alert as last resort
        if (type === 'error') alert(msg);
    };
}

// 4. GLOBAL ERROR HANDLER
window.onerror = function(msg, url, line, col, error) {
    if (msg.includes('ResizeObserver') || msg.includes('Script error')) return false;
    console.error(`[AppCrash] ${msg} at ${line}:${col}`);
    return false;
};

// 5. API FALLBACK
if (typeof api !== 'function') {
    window.api = async () => ({ ok: false, error: 'API handler missing' });
}
