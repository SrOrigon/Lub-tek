<?php
/**
 * Manutenção periódica do LUB-TEK (admin + todos os tenants)
 *
 * SOMENTE CLI / Cron — evita timeout e execução parcial via navegador.
 *
 * Uso recomendado via CRON diário: php maintenance.php
 *
 * Ações:
 *   all                 → exporta logs + purge rate limits + vacuum (todos os bancos)
 *   export_audit_logs   → exporta logs > 30 dias para CSV
 *   purge_rate_limits   → limpa rate limits expirados
 *   vacuum              → WAL checkpoint + VACUUM
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    die("Acesso negado. Execute via SSH/Cron:\n  php maintenance.php all\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/htaccess_guard.php';

$action = $argv[1] ?? 'all';
$retentionDays = 30;
$exportDir = __DIR__ . '/backups/audit_exports';

if (!is_dir($exportDir)) {
    mkdir($exportDir, 0755, true);
}
HtaccessGuard::writeDenyAll($exportDir);

function getReferencedFilesForTenant(?string $tenant): array
{
    $pdo = DB::getInstance($tenant);
    $referenced = [];

    // 1. Ativos (imagem, imagem_3d, dados_tecnicos)
    try {
        $stmt = $pdo->query("SELECT imagem, imagem_3d, dados_tecnicos FROM ativos");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['imagem'])) {
                $referenced[] = str_replace('\\', '/', trim($row['imagem']));
            }
            if (!empty($row['imagem_3d'])) {
                $referenced[] = str_replace('\\', '/', trim($row['imagem_3d']));
            }
            if (!empty($row['dados_tecnicos'])) {
                $json = json_decode($row['dados_tecnicos'], true);
                if (is_array($json) && !empty($json['model_3d'])) {
                    $referenced[] = str_replace('\\', '/', trim($json['model_3d']));
                }
            }
        }
    } catch (Exception $e) {
        // Ignora se tabela ou coluna não existir
    }

    // 2. Catalogo (imagem)
    try {
        $stmt = $pdo->query("SELECT imagem FROM catalogo");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['imagem'])) {
                $referenced[] = str_replace('\\', '/', trim($row['imagem']));
            }
        }
    } catch (Exception $e) {}

    // 3. Analises (arquivo)
    try {
        $stmt = $pdo->query("SELECT arquivo FROM analises");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['arquivo'])) {
                $referenced[] = str_replace('\\', '/', trim($row['arquivo']));
            }
        }
    } catch (Exception $e) {}

    // 4. System Meta (company_logo)
    try {
        $stmt = $pdo->query("SELECT mval FROM system_meta WHERE mkey = 'company_logo'");
        $logo = $stmt->fetchColumn();
        if (!empty($logo)) {
            $referenced[] = str_replace('\\', '/', trim($logo));
        }
    } catch (Exception $e) {}

    // Limpa e filtra caminhos
    $normalized = [];
    foreach ($referenced as $path) {
        if (strpos($path, 'uploads/') === 0) {
            $normalized[] = $path;
        }
    }

    return array_unique($normalized);
}

function runGarbageCollector(?string $tenant): void
{
    $label = $tenant ?? 'admin';
    echo "[GC] Executando Coletor de Lixo em $label...\n";

    $referenced = getReferencedFilesForTenant($tenant);
    $baseUploadsDir = __DIR__ . '/uploads';
    $scanDir = $tenant ? $baseUploadsDir . '/' . $tenant : $baseUploadsDir;

    if (!is_dir($scanDir)) {
        echo "[GC] Pasta de uploads não existe para esta empresa ($scanDir).\n";
        return;
    }

    $physicalFiles = [];
    $dirIterator = new DirectoryIterator($scanDir);
    foreach ($dirIterator as $fileInfo) {
        if ($fileInfo->isFile()) {
            $filename = $fileInfo->getFilename();
            // Pula .htaccess
            if (strpos($filename, '.') === 0) {
                continue;
            }
            
            $relPath = $tenant ? 'uploads/' . $tenant . '/' . $filename : 'uploads/' . $filename;
            $physicalFiles[$relPath] = $fileInfo->getPathname();
        }
    }

    $deletedCount = 0;
    foreach ($physicalFiles as $relPath => $fullPath) {
        if (!in_array($relPath, $referenced, true)) {
            $realUploads = realpath($baseUploadsDir);
            $realFile = realpath($fullPath);
            if ($realFile && $realUploads && strpos($realFile, $realUploads) === 0) {
                if (@unlink($realFile)) {
                    echo "  -> Removido arquivo órfão: $relPath\n";
                    $deletedCount++;
                } else {
                    echo "  -> [AVISO] Falha ao remover arquivo: $relPath\n";
                }
            }
        }
    }

    echo "[GC] Concluído. Órfãos removidos: $deletedCount\n";
}

function runMaintenanceForTenant(?string $tenant, string $action, int $retentionDays, string $exportDir): void
{
    $label = $tenant ?? 'admin';
    echo "=== Tenant: $label ===\n";

    $pdo = DB::getInstance($tenant);

    if ($action === 'all' || $action === 'export_audit_logs') {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        $stmt = $pdo->prepare("SELECT * FROM logs_auditoria WHERE data < ? ORDER BY data ASC");
        $stmt->execute([$cutoff]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $filename = $exportDir . '/audit_' . $label . '_' . date('Y-m-d_His') . '.csv';
            $fp = fopen($filename, 'w');
            fputcsv($fp, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);

            $ids = array_column($rows, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM logs_auditoria WHERE id IN ($placeholders)")->execute($ids);

            echo "[OK] Exportados " . count($rows) . " logs → $filename\n";
        } else {
            echo "[OK] Nenhum log antigo para exportar.\n";
        }
    }

    if ($action === 'all' || $action === 'purge_rate_limits') {
        try {
            $cutoff = time() - 120;
            $deleted = $pdo->prepare("DELETE FROM rate_limits WHERE window_start < ?");
            $deleted->execute([$cutoff]);
            echo "[OK] Rate limits removidos: " . $deleted->rowCount() . "\n";
        } catch (Exception $e) {
            echo "[SKIP] rate_limits: " . $e->getMessage() . "\n";
        }
    }

    // --- 4. PURGE IOT TELEMETRY (Evita Inchaço do SQLite) ---
    if ($action === 'all' || $action === 'purge_telemetry') {
        try {
            // Mantém telemetria bruta apenas dos últimos 15 dias.
            // Para análises preditivas mais longas, o Motor Neural e o SAP já possuem resumos gerados.
            $telemetryCutoff = date('Y-m-d H:i:s', strtotime('-15 days'));
            $deletedTelemetry = $pdo->prepare("DELETE FROM pi_telemetry WHERE timestamp < ?");
            $deletedTelemetry->execute([$telemetryCutoff]);
            $linhasApagadas = $deletedTelemetry->rowCount();
            
            if ($linhasApagadas > 0) {
                echo "[OK] Telemetria antiga podada: $linhasApagadas registros removidos.\n";
            } else {
                echo "[OK] Nenhuma telemetria velha para podar.\n";
            }
        } catch (Exception $e) {
            echo "[SKIP] Falha ao podar pi_telemetry: " . $e->getMessage() . "\n";
        }
    }

    if ($action === 'all' || $action === 'gc') {
        runGarbageCollector($tenant);
    }

    if ($action === 'all' || $action === 'vacuum') {
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE);');
        $pdo->exec('VACUUM;');
        echo "[OK] WAL checkpoint e VACUUM concluídos.\n";
    }
}

try {
    runMaintenanceForTenant(null, $action, $retentionDays, $exportDir);
    foreach (TenantResolver::listTenantSlugs() as $slug) {
        runMaintenanceForTenant($slug, $action, $retentionDays, $exportDir);
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log('Maintenance failed: ' . $e->getMessage());
    exit(1);
}
