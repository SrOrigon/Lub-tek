<?php
/**
 * Smoke test estático de a11y/perf (CI) — verifica artefatos mínimos no repositório.
 */
$checks = [
    'skip-link' => 'includes/header.php',
    'a11y-live-region' => 'index.php',
    'id="main-content"' => 'includes/header.php',
    'a11y-global.css' => 'includes/header.php',
    'LubtekVendors' => 'assets/js/lazy_vendors.js',
    'LUBTEK_BUILD' => 'config.php',
];

$root = dirname(__DIR__);
$failed = [];

foreach ($checks as $needle => $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!is_file($path)) {
        $failed[] = "Arquivo ausente: {$file}";
        continue;
    }
    $body = file_get_contents($path);
    if ($body === false || strpos($body, $needle) === false) {
        $failed[] = "Esperado \"{$needle}\" em {$file}";
    }
}

if ($failed !== []) {
    fwrite(STDERR, "a11y_smoke FALHOU:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "a11y_smoke OK (" . count($checks) . " checks)\n";
exit(0);
