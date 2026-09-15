<?php
/**
 * Auditoria SAP ERP — import/export e rotas API.
 * Uso: php scripts/test_sap_integration.php
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../api/SAPController.php';
require __DIR__ . '/../api/AssetsController.php';

$pdo = DB::getInstance();
$fail = 0;

function check(bool $ok, string $msg): void {
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . " $msg\n";
    if (!$ok) {
        $fail++;
    }
}

$user = ['id' => 1, 'role' => 'developer', 'nome' => 'Test Admin'];

// --- Rotas registradas ---
$api = file_get_contents(dirname(__DIR__) . '/api.php') ?: '';
foreach (['sap_import_orders', 'sap_import_materials', 'sap_export_orders', 'sap_export_assets', 'sap_export_materials', 'save_asset_batch'] as $route) {
    check(strpos($api, "'$route'") !== false, "rota api.php: $route");
}

// --- Permissões gestor ---
foreach (['sap_import_orders', 'sap_export_orders', 'save_asset_batch'] as $action) {
    check(Permissions::canAccessApiAction('gestor', $action), "gestor pode $action");
}
check(!Permissions::canAccessApiAction('trabalhador', 'sap_import_orders'), 'trabalhador bloqueado em sap_import_orders');

// --- Import ordens ---
$sap = new SAPController($pdo, $user, []);
$res = $sap->importOrders();
check(($res['ok'] ?? false) === true && ($res['count'] ?? -1) === 0, 'importOrders vazio retorna ok');

$orderPayload = [
    'items' => [[
        'sap_order' => 'TEST-SAP-' . time(),
        'desc' => 'Ordem teste SAP audit',
        'asset_ref' => 'NA',
        'prio' => '2',
        'date' => date('Y-m-d'),
        'resp' => 'PCM Teste',
    ]],
];
$sap = new SAPController($pdo, $user, $orderPayload);
$res = $sap->importOrders();
check(($res['inserted'] ?? 0) >= 1, 'importOrders insere ordem nova');
$sapOrder = $orderPayload['items'][0]['sap_order'];

$res2 = $sap->importOrders();
check(($res2['updated'] ?? 0) >= 1, 'importOrders atualiza ordem existente (upsert)');

// --- Import materiais ---
$matCode = 'MAT-SAP-' . time();
$sap = new SAPController($pdo, $user, [
    'items' => [[
        'codigo' => $matCode,
        'nome' => 'Óleo Teste SAP',
        'tipo' => 'Lubrificante',
        'estoque_atual' => 10,
    ]],
]);
$res = $sap->importMaterials();
check(($res['inserted'] ?? 0) >= 1, 'importMaterials insere material');

$sap = new SAPController($pdo, $user, [
    'items' => [[
        'codigo' => $matCode,
        'nome' => 'Óleo Teste SAP Atualizado',
        'tipo' => 'Lubrificante',
        'estoque_atual' => 15,
    ]],
]);
$res = $sap->importMaterials();
check(($res['updated'] ?? 0) >= 1, 'importMaterials atualiza material existente');

// --- Export ordens ---
$sap = new SAPController($pdo, $user, ['start_date' => date('Y-m-d', strtotime('-30 days')), 'end_date' => date('Y-m-d')]);
$exported = $sap->exportOrders();
check(is_array($exported), 'exportOrders retorna array');
$found = false;
foreach ($exported as $row) {
    if (($row['ORDEM_SAP'] ?? '') === $sapOrder) {
        $found = true;
        break;
    }
}
check($found, 'exportOrders inclui ordem importada');

// --- Export assets / materials ---
check(is_array($sap->exportAssets()), 'exportAssets retorna array');
$materials = $sap->exportMaterials();
check(is_array($materials), 'exportMaterials retorna array');
$matFound = false;
foreach ($materials as $m) {
    if (($m['CODIGO_SAP'] ?? '') === $matCode) {
        $matFound = true;
        break;
    }
}
check($matFound, 'exportMaterials inclui material importado');

// --- Import ativos via saveBatch (IH01) ---
$assets = new AssetsController($pdo, $user, [
    'items' => [[
        'virtual_id' => 'vsap_root',
        'nome' => 'Planta SAP Test ' . time(),
        'tipo' => 'unidade',
        'tag' => 'SAP-ROOT-' . time(),
    ], [
        'virtual_id' => 'vsap_child',
        'nome' => 'Equip SAP Test',
        'tipo' => 'equipamento',
        'tag' => 'SAP-EQ-' . time(),
        'pai_virtual_id' => 'vsap_root',
    ]],
]);
$batch = $assets->saveBatch();
check(($batch['count'] ?? 0) >= 2, 'saveBatch importa hierarquia SAP (IH01)');

$createdIds = array_values($batch['virtual_map'] ?? []);
if (!empty($createdIds)) {
    $in = implode(',', array_map('intval', $createdIds));
    $pdo->exec("DELETE FROM ativos WHERE id IN ($in)");
}
require_once __DIR__ . '/../includes/sap_test_plant_cleanup.php';
SapTestPlantCleanup::purge($pdo);

// --- View SAP ---
$view = file_get_contents(dirname(__DIR__) . '/includes/views/sap_integration.php') ?: '';
check(strpos($view, 'executeSAPImport') !== false, 'view sap_integration tem executeSAPImport');
check(strpos($view, 'executeSAPExport') !== false, 'view sap_integration tem executeSAPExport');
check(strpos($view, 'readAsText') !== false, 'view sap_integration suporta CSV/TXT');
check(strpos($view, 'res.ok !== false') !== false, 'view sap_integration valida resposta ok');

// cleanup test order
$pdo->prepare("DELETE FROM ordens WHERE materiais_sap = ?")->execute([$sapOrder]);
$pdo->prepare("DELETE FROM catalogo WHERE codigo = ?")->execute([$matCode]);

exit($fail === 0 ? 0 : 1);
