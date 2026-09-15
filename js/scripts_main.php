<?php
/**
 * @deprecated Proxy de compatibilidade — fonte canônica: /assets/js/scripts_main.js
 * Mantido para deploys legados na Hostinger que referenciam /js/scripts_main.php
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-LUB-TEK-Canonical: /assets/js/scripts_main.js');
$source = __DIR__ . '/../assets/js/scripts_main.js';
if (!file_exists($source)) {
    http_response_code(404);
    echo "console.error('[LUB-TEK] scripts_main.js não encontrado.');";
    exit;
}
readfile($source);
