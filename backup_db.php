<?php
/**
 * Backup de bancos SQLite (admin + tenants)
 *
 * SOMENTE CLI / Cron — nunca via navegador (evita timeout e exposição).
 *
 * Uso:
 *   php backup_db.php           → backup do banco admin (database.sqlite)
 *   php backup_db.php --all     → backup de todos os bancos + export off-site
 *   php backup_db.php cocacola  → backup de um tenant específico
 *
 * Off-site (config.local.php):
 *   BACKUP_EXPORT_DIR — pasta externa (ex: disco local ou mount NFS)
 *   BACKUP_EMAIL      — e-mail de segurança (anexo se < 10MB)
 *   BACKUP_FTP_*      — envio FTP/FTPS para servidor externo
 *   BACKUP_S3_*       — AWS S3, Cloudflare R2, Backblaze B2, MinIO
 *
 * Testar conexões off-site:
 *   php backup_db.php --test-export
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    die("Acesso negado. Execute via SSH/Cron:\n  php backup_db.php --all\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/htaccess_guard.php';
require_once __DIR__ . '/includes/backup_exporter.php';

$backupDir = __DIR__ . '/backups';
$retentionDays = 30;
$arg = $argv[1] ?? null;

if ($arg === '--test-export') {
    echo "[TEST] Verificando destinos off-site configurados...\n";
    $test = BackupExporter::testConnections();
    foreach ($test['messages'] as $msg) {
        echo "  $msg\n";
    }
    exit($test['ok'] ? 0 : 1);
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
HtaccessGuard::writeDenyAll($backupDir);

/**
 * @return string Caminho do arquivo gerado
 */
function backupSingleDatabase(?string $tenant, string $backupDir): string
{
    $label = $tenant ?? 'admin';
    $sourceStr = TenantResolver::resolvePath($tenant);

    if (!file_exists($sourceStr)) {
        echo "[SKIP] Banco '$label' não encontrado: $sourceStr\n";
        return '';
    }

    $date = date('Y-m-d_H-i-s');
    $dest = $backupDir . '/backup_' . $label . '_' . $date . '.sqlite';

    $pdo = DB::getInstance($tenant);
    $sqliteVersion = $pdo->query('select sqlite_version()')->fetchColumn();

    if (version_compare($sqliteVersion, '3.27.0', '>=')) {
        $pdo->exec("VACUUM INTO '$dest'");
        $method = 'VACUUM INTO';
    } else {
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE);');
        if (!copy($sourceStr, $dest)) {
            throw new Exception("Falha ao copiar banco '$label'.");
        }
        $method = 'File Copy (Checkpoint Forced)';
    }

    echo "[OK] Backup '$label': $dest ($method)\n";
    BackupExporter::appendManifest($backupDir, $dest, $label, $method);
    DB::log('SYSTEM', 'BACKUP_SUCCESS', $dest, null, ['tenant' => $label, 'method' => $method]);

    return $dest;
}

try {
    $created = [];

    if ($arg === '--all') {
        $path = backupSingleDatabase(null, $backupDir);
        if ($path !== '') {
            $created[] = ['path' => $path, 'label' => 'admin'];
        }
        foreach (TenantResolver::listTenantSlugs() as $slug) {
            $path = backupSingleDatabase($slug, $backupDir);
            if ($path !== '') {
                $created[] = ['path' => $path, 'label' => $slug];
            }
        }
    } elseif ($arg && $arg !== '--all') {
        $slug = TenantResolver::sanitizeSlug($arg);
        if (!$slug) {
            throw new Exception("Slug de tenant inválido: $arg");
        }
        $path = backupSingleDatabase($slug, $backupDir);
        if ($path !== '') {
            $created[] = ['path' => $path, 'label' => $slug];
        }
    } else {
        $path = backupSingleDatabase(null, $backupDir);
        if ($path !== '') {
            $created[] = ['path' => $path, 'label' => 'admin'];
        }
    }

    if ($created !== []) {
        echo "\n[EXPORT] Enviando cópias off-site (se configurado)...\n";
        foreach ($created as $item) {
            $export = BackupExporter::export($item['path'], $item['label']);
            BackupExporter::updateManifestOffsite($backupDir, $item['path'], $export);
            foreach ($export['messages'] as $msg) {
                echo "  [{$item['label']}] $msg\n";
            }
        }
    }

    $files = glob($backupDir . '/backup_*.sqlite');
    $now = time();
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file) >= 60 * 60 * 24 * $retentionDays)) {
            unlink($file);
            $deleted++;
        }
    }
    if ($deleted > 0) {
        echo "[CLEANUP] Removidos $deleted backups antigos (>{$retentionDays} dias).\n";
    }

    echo "\n[DICA] Agende no Cron da Hostinger (diário):\n";
    echo "  php " . __DIR__ . "/backup_db.php --all\n";

} catch (Exception $e) {
    echo "[ERROR] Backup Failed: " . $e->getMessage() . "\n";
    error_log('DB Backup Failed: ' . $e->getMessage());
    try {
        DB::logError($e);
    } catch (Exception $x) {
    }
    exit(1);
}
