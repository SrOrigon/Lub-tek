<?php
/**
 * Entrega pública do Web App Manifest (evita 403 do .htaccess em *.json).
 */
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$path = __DIR__ . '/assets/pwa/manifest.webmanifest';
if (!is_file($path)) {
    $path = __DIR__ . '/manifest.json';
}
if (!is_file($path)) {
    http_response_code(404);
    echo json_encode(['error' => 'manifest not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

readfile($path);
