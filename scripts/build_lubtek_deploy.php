<?php
/**
 * Empacota correção PWA + mídia essencial para o domínio oficial lubteksystem.com.
 * Uso: php scripts/build_lubtek_deploy.php
 */
$root = dirname(__DIR__);
$dest = $root . '/deploy/lubteksystem-production';

$files = [
    'assets/pwa/manifest.webmanifest',
    'assets/pwa/.htaccess',
    'includes/header.php',
    'includes/modals.php',
    'login.php',
    'sw.js',
    'service-worker.js',
    '.htaccess',
    'manifest.php',
    'config.php',
    'sitemap.php',
    'assets/css/desktop-layout.css',
    'assets/css/responsive.css',
    'assets/css/a11y-global.css',
    'assets/js/scripts_main.js',
    'assets/js/scripts_extra.js',
    'includes/lubrication_plan_image_helper.php',
    'api/AssetsController.php',
];

$dirs = [
    'assets/img/system',
    'uploads',
];

function copyTree(string $srcDir, string $destDir, array $allowedExt = []): int
{
    $count = 0;
    if (!is_dir($srcDir)) {
        return 0;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        if ($allowedExt !== [] && !in_array($ext, $allowedExt, true)) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($srcDir) + 1);
        $target = $destDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($file->getPathname(), $target);
        $count++;
    }
    return $count;
}

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

$mediaExt = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
foreach ($dirs as $relDir) {
    $srcDir = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
    $targetDir = $dest . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
    $n = copyTree($srcDir, $targetDir, $mediaExt);
    echo "OK dir $relDir ($n arquivos de mídia)\n";
    $copied += $n;
}

echo "\n=== DEPLOY LUBTEKSYSTEM.COM ===\n";
echo "Pacote: $dest ($copied arquivos)\n";
echo "Destino FTP: raiz do domínio lubteksystem.com na Hostinger\n";
echo "Após upload: DevTools > Application > Unregister SW > Ctrl+Shift+R\n";
