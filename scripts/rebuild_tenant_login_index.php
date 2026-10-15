<?php
/**
 * CLI: reconstrói tenant_login_index a partir dos usuários de cada tenant.
 * php scripts/rebuild_tenant_login_index.php
 */
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Execute via CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/tenant.php';
require_once dirname(__DIR__) . '/includes/tenant_login_index.php';
require_once dirname(__DIR__) . '/db.php';

$master = DB::getMaster();
TenantLoginIndex::ensureTable($master);
$master->exec('DELETE FROM tenant_login_index');

$count = 0;
foreach (TenantResolver::listTenantSlugs() as $slug) {
    try {
        $pdo = DB::getInstance($slug);
    } catch (Exception $e) {
        continue;
    }
    $rows = $pdo->query('SELECT email FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $email) {
        if (!is_string($email) || strpos($email, '@') === false) {
            continue;
        }
        TenantLoginIndex::upsert($email, $slug);
        $count++;
    }
}

echo "Índice reconstruído: {$count} entradas.\n";
