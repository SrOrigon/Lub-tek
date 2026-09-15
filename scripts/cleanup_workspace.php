<?php
/**
 * Remove cache, sessões expiradas, artefatos de teste e cópias obsoletas.
 * Não apaga: bancos SQLite, uploads, config.local.php, assets/img.
 *
 * Uso: php scripts/cleanup_workspace.php [--dry-run]
 */
$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv ?? [], true);
$removed = 0;
$bytes = 0;

function cleanupPath(string $path, bool $dryRun, int &$removed, int &$bytes): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path)) {
        $size = filesize($path) ?: 0;
        echo ($dryRun ? '[DRY] ' : '') . "file: $path ($size B)\n";
        if (!$dryRun) {
            @unlink($path);
        }
        $removed++;
        $bytes += $size;
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $fp = $item->getPathname();
        $size = $item->isFile() ? (filesize($fp) ?: 0) : 0;
        echo ($dryRun ? '[DRY] ' : '') . ($item->isDir() ? 'dir: ' : 'file: ') . "$fp" . ($size ? " ($size B)" : '') . "\n";
        if (!$dryRun) {
            $item->isDir() ? @rmdir($fp) : @unlink($fp);
        }
        $removed++;
        $bytes += $size;
    }
    if (!$dryRun) {
        @rmdir($path);
    }
}

echo "=== LUB-TEK cleanup_workspace ===" . ($dryRun ? ' (dry-run)' : '') . "\n\n";

// 1. Sessões PHP locais
$sessDir = $root . '/data/sessions';
if (is_dir($sessDir)) {
    foreach (glob($sessDir . '/sess_*') ?: [] as $f) {
        cleanupPath($f, $dryRun, $removed, $bytes);
    }
}

// 2. Cache IA (regenerável)
$aiCache = $root . '/data/ai_cache';
if (is_dir($aiCache)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($aiCache, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $item) {
        if ($item->isFile() && strtolower($item->getExtension()) === 'json') {
            cleanupPath($item->getPathname(), $dryRun, $removed, $bytes);
        }
    }
}

// 3. Logs e previews de teste
foreach ([
    $root . '/debug-dd3d14.log',
    $root . '/data/test_mensal.html',
    $root . '/data/test_plan_preview.html',
] as $f) {
    cleanupPath($f, $dryRun, $removed, $bytes);
}

// 4. Deploy duplicado (regenerável via build_*.php)
foreach (['hostinger-pwa-fix', 'lubteksystem-production'] as $pkg) {
    cleanupPath($root . '/deploy/' . $pkg, $dryRun, $removed, $bytes);
}

// 5. Backups SQLite antigos locais (cópias; DB ativo fica em banco de dados/)
foreach (glob($root . '/backups/backup_*.sqlite') ?: [] as $f) {
    cleanupPath($f, $dryRun, $removed, $bytes);
}
foreach (glob($root . '/backups/audit_exports/audit_*.csv') ?: [] as $f) {
    cleanupPath($f, $dryRun, $removed, $bytes);
}

// 6. Scripts de diagnóstico pontual (não usados em produção)
foreach ([
    $root . '/scripts/repro_admin_login.php',
    $root . '/scripts/inspect_users.php',
] as $f) {
    cleanupPath($f, $dryRun, $removed, $bytes);
}

// 7. View 3D legada (index.php usa view_3d_revolutionary.php)
cleanupPath($root . '/includes/views/view_3d.php', $dryRun, $removed, $bytes);

// 8. JS/PHP obsoletos (não referenciados pelo index)
foreach ([
    $root . '/assets/js/scripts_extra_utf8.js',
    $root . '/assets/js/scripts_v8.js',
    $root . '/assets/js/scripts_main.php',
    $root . '/assets/js/scripts_extra.php',
    $root . '/includes/views/view_3d_revolutionary_FIX.js',
    $root . '/includes/turbo_loader.php',
    $root . '/includes/turbo_loader_clean.php',
] as $f) {
    cleanupPath($f, $dryRun, $removed, $bytes);
}

// 9. Mídia órfã/legada (só remove se não estiver no banco)
if (!$dryRun) {
    require_once __DIR__ . '/scan_orphan_media.php';
    try {
        require_once __DIR__ . '/../db.php';
        $mediaResult = lubtek_scan_orphan_media($root, DB::getInstance(), true, false);
        $removed += $mediaResult['removed'];
        $bytes += $mediaResult['bytes'];
        if ($mediaResult['removed'] > 0) {
            echo "orphan media: {$mediaResult['removed']} itens, " . round($mediaResult['bytes'] / 1024 / 1024, 2) . " MB\n";
        }
    } catch (Exception $e) {
        echo "orphan media skip: " . $e->getMessage() . "\n";
    }
}

// Garante estrutura mínima
if (!$dryRun) {
    foreach ([
        $root . '/data/sessions',
        $root . '/data/ai_cache/default',
        $root . '/deploy',
        $root . '/backups/audit_exports',
    ] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    if (!is_file($root . '/deploy/.gitkeep')) {
        file_put_contents($root . '/deploy/.gitkeep', '');
    }
}

echo "\n=== RESULTADO ===\n";
echo "Itens: $removed\n";
echo "Espaço: " . round($bytes / 1024, 1) . " KB\n";
exit(0);
