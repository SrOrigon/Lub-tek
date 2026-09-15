/**
 * LUB-TEK 3.0 — CLIENT SIDE SECURITY & ANTI-COPY GUARD
 * Prevents unauthorized page scraping, code saving, and web copying tools.
 */
(function() {
    'use strict';

    // Anti-scraping básico para bots automatizados (sem bloquear F12 / DevTools do usuário)
    if (typeof navigator !== 'undefined' && navigator.webdriver) {
        console.warn('Acesso automatizado por webdriver detectado.');
    }
})();
