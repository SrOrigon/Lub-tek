<?php
/**
 * Remove plantas "Planta SAP Test" de todos os bancos.
 * Uso: php scripts/cleanup_sap_test_plants.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only.\n");
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/sap_test_plant_cleanup.php';

$targets = [null];
foreach (TenantResolver::listTenantSlugs() as $slug) {
    $targets[] = $slug;
}

foreach ($targets as $slug) {
    $label = $slug ?? 'admin';
    $pdo = DB::getInstance($slug);
    $r = SapTestPlantCleanup::purge($pdo);
    echo "[$label] removidos {$r['deleted']} ativo(s) em {$r['roots']} raiz(es) de teste\n";
}
echo "OK\n";
