<?php
/**
 * Migra schema em todos os bancos (admin + clientes)
 *
 * SOMENTE CLI / Cron — migrações em massa não podem sofrer timeout do PHP web.
 *
 * Uso: php migrate_all_tenants.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    die("Acesso negado. Migrações em massa devem rodar via SSH/Cron:\n  php migrate_all_tenants.php\n");
}

require_once __DIR__ . '/db.php';

$results = DB::forceMigrationAll();

foreach ($results as $tenant => $status) {
    echo "[$tenant] $status\n";
}

echo "[OK] Migração concluída em " . count($results) . " banco(s).\n";
