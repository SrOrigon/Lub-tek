<?php
/**
 * Empacota correção PWA para o domínio oficial lubteksystem.com.
 * Uso: php scripts/build_lubtek_deploy.php
 */
$root = dirname(__DIR__);
$dest = $root . '/deploy/lubteksystem-production';

$files = [
    'assets/pwa/manifest.webmanifest',
    'assets/pwa/.htaccess',
    'includes/header.php',
    'login.php',
    'sw.js',
    'service-worker.js',
    '.htaccess',
    'manifest.php',
    'config.php',
    'sitemap.php',
];

if (is_dir($dest)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dest, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f) : @unlink($f);
    }
}

$copied = 0;
foreach ($files as $rel) {
    $src = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($src)) {
        echo "SKIP (missing) $rel\n";
        continue;
    }
    $target = $dest . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    copy($src, $target);
    echo "OK $rel\n";
    $copied++;
}

echo "\n=== DEPLOY LUBTEKSYSTEM.COM ===\n";
echo "Pacote: $dest ($copied arquivos)\n";
echo "Destino FTP: raiz do domínio lubteksystem.com na Hostinger\n";
echo "Após upload: DevTools > Application > Unregister SW > Ctrl+Shift+R\n";
