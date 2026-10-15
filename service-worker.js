/**
 * LUB-TEK Service Worker Bridge
 * Manifest: ./assets/pwa/manifest.webmanifest
 * Delega 100% da execução para o Service Worker canônico (sw.js)
 * eliminando divergências de cache e conflito de escopo no navegador.
 */
importScripts('./sw.js');
