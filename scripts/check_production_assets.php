<?php
/**
 * Verifica assets PWA em produção (Hostinger).
 * Uso: php scripts/check_production_assets.php [base_url]
 */
$base = rtrim($argv[1] ?? 'https://lubteksystem.com', '/');
$fail = 0;

function headStatus(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'LUB-TEK-Probe/1.0',
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['url' => $url, 'status' => $code];
    }

    $escaped = escapeshellarg($url);
    $out = shell_exec('curl.exe -sI ' . $escaped . ' 2>nul');
    $code = 0;
    if (is_string($out) && preg_match('/HTTP\/[\d.]+\s+(\d{3})/', $out, $m)) {
        $code = (int) $m[1];
    }
    return ['url' => $url, 'status' => $code];
}

echo "=== PRODUCTION ASSET PROBE ===\n";
echo "Base: $base\n\n";

$checks = [
    'webmanifest' => "$base/assets/pwa/manifest.webmanifest",
    'manifest.php' => "$base/manifest.php",
    'manifest.json' => "$base/manifest.json",
    'sw.js' => "$base/sw.js",
];

foreach ($checks as $label => $url) {
    $res = headStatus($url);
    $ok = false;
    if ($label === 'webmanifest') {
        $ok = $res['status'] === 200;
    } elseif ($label === 'manifest.json') {
        $ok = in_array($res['status'], [200, 301, 302], true);
    } elseif ($label === 'manifest.php') {
        $ok = in_array($res['status'], [200, 301, 302], true);
    } else {
        $ok = $res['status'] === 200;
    }
    echo ($ok ? 'PASS' : 'FAIL') . " {$label} HTTP {$res['status']}\n";
    if (!$ok) {
        $fail++;
    }
}

$html = @file_get_contents("$base/login.php");
if ($html === false) {
    echo "WARN login.php fetch (SSL/offline)\n";
} else {
    $hasWm = stripos($html, 'manifest.webmanifest') !== false;
    $hasJson = stripos($html, 'href="manifest.json"') !== false;
    echo ($hasWm ? 'PASS' : 'FAIL') . " login.php referencia webmanifest\n";
    echo ($hasJson ? 'FAIL' : 'PASS') . " login.php sem manifest.json legado\n";
    if (!$hasWm || $hasJson) {
        $fail++;
    }
}

exit($fail === 0 ? 0 : 1);
