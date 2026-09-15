<?php
/**
 * Verifica assets públicos (manifest PWA) — uso: php scripts/test_public_assets.php
 */
$root = dirname(__DIR__);
$fail = 0;

function check(bool $ok, string $msg): void {
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . " $msg\n";
    if (!$ok) {
        $fail++;
    }
}

ob_start();
include $root . '/manifest.php';
$manifestOut = ob_get_clean();

$webmanifest = $root . '/assets/pwa/manifest.webmanifest';
check(is_file($webmanifest), 'assets/pwa/manifest.webmanifest existe');
$wmDecoded = json_decode(file_get_contents($webmanifest) ?: '', true);
check(is_array($wmDecoded) && !empty($wmDecoded['short_name']), 'webmanifest JSON válido');

$htaccess = file_get_contents($root . '/.htaccess') ?: '';
check(strpos($htaccess, 'assets/pwa/manifest.webmanifest') !== false, 'htaccess rewrite manifest → webmanifest');
check(!preg_match('/FilesMatch.*\|json\|/', $htaccess), 'htaccess não bloqueia extensão .json globalmente');

check(strpos($manifestOut, '"short_name"') !== false, 'manifest.php retorna JSON');
check(strpos($manifestOut, 'LUB-TEK') !== false, 'manifest.php contém short_name');

$header = file_get_contents($root . '/includes/header.php') ?: '';
check(strpos($header, 'assets/pwa/manifest.webmanifest') !== false, 'header.php aponta para webmanifest');
check(strpos($header, 'debug_client_log') === false, 'header.php sem instrumentação debug');

$login = file_get_contents($root . '/login.php') ?: '';
check(strpos($login, 'assets/pwa/manifest.webmanifest') !== false, 'login.php aponta para webmanifest');

$sw = file_get_contents($root . '/sw.js') ?: '';
check(strpos($sw, 'manifest.webmanifest') !== false, 'sw.js referencia webmanifest');

$swLegacy = file_get_contents($root . '/service-worker.js') ?: '';
check(strpos($swLegacy, 'manifest.webmanifest') !== false, 'service-worker.js referencia webmanifest');

exit($fail === 0 ? 0 : 1);
