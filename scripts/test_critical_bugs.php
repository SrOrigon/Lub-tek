<?php
/**
 * Verificações de bugs críticos corrigidos.
 * Uso: php scripts/test_critical_bugs.php
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gemini_service.php';
require_once __DIR__ . '/../api/SAPController.php';
require_once __DIR__ . '/../api/PIController.php';
require_once __DIR__ . '/../api/OrdersController.php';

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

// --- Arquivos / padrões ---
$api = file_get_contents(dirname(__DIR__) . '/api.php') ?: '';
$dbSrc = file_get_contents(dirname(__DIR__) . '/db.php') ?: '';
$piSrc = file_get_contents(dirname(__DIR__) . '/api/PIController.php') ?: '';
check(strpos($api, 'checkRateLimitSessionFallback') !== false, 'api.php rate limit fallback fail-closed');
check(strpos($api, 'API key via query string não é permitida') !== false, 'api.php bloqueia api_key na URL');
check(strpos($api, 'requireController') !== false, 'api.php loader de controller resiliente');
check(strpos($dbSrc, 'BEGIN IMMEDIATE') !== false, 'db.php BEGIN IMMEDIATE');
check(strpos($dbSrc, 'shouldAutoMigrate') !== false, 'db.php migração web desligada');
check(strpos($piSrc, 'CbmJobQueue::enqueue') !== false, 'PIController enfileira CBM');

$manifest = file_get_contents(dirname(__DIR__) . '/assets/pwa/manifest.webmanifest') ?: '';
check(strpos($manifest, '"start_url": "index.php"') !== false, 'manifest paths relativos');
check(strpos($manifest, '"/index.php"') === false, 'manifest sem path absoluto start_url');

$scripts = file_get_contents(dirname(__DIR__) . '/assets/js/scripts_main.js') ?: '';
check(strpos($scripts, 'function safeUrl') !== false, 'scripts_main.js safeUrl()');
check(strpos($scripts, 'vendor-offer-link') !== false, 'scripts_main.js vendor sem onclick inline');

$audit = file_get_contents(dirname(__DIR__) . '/includes/views/audit.php') ?: '';
check(strpos($audit, 'auditEscapeHtml') !== false, 'audit.php escape XSS');
check(strpos($audit, 'auditLoadInFlight') !== false, 'audit.php mutex carga');

$extra = file_get_contents(dirname(__DIR__) . '/assets/js/scripts_extra.js') ?: '';
check(strpos($extra, 'syncQueueRunning') !== false, 'scripts_extra.js mutex sync');
check(strpos($extra, 'json.ok === false') !== false, 'scripts_extra.js valida ok da API');

$view3d = file_get_contents(dirname(__DIR__) . '/includes/views/view_3d_revolutionary.php') ?: '';
check(strpos($view3d, 'THREE') !== false, 'view 3D ativo (revolutionary) presente');

// --- PIController array guard ---
$pi = new PIController($pdo, $user, ['points' => 'invalid']);
try {
    $pi->receiveTelemetry();
    check(false, 'PIController rejeita points não-array');
} catch (Exception $e) {
    check(strpos($e->getMessage(), 'array') !== false, 'PIController rejeita points não-array');
}

// --- SAPController array guard ---
$sap = new SAPController($pdo, $user, ['items' => 'bad']);
$res = $sap->importOrders();
check(($res['ok'] ?? true) === false, 'SAPController rejeita items não-array');

// --- OrdersController estoque ---
$orders = new OrdersController($pdo, $user, []);
$ref = new ReflectionClass($orders);
$method = $ref->getMethod('processStockConsumption');
$method->setAccessible(true);
$catRow = $pdo->query("SELECT id, estoque_atual FROM catalogo WHERE estoque_atual IS NOT NULL ORDER BY estoque_atual ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($catRow) {
    $need = (float) $catRow['estoque_atual'] + 100;
    try {
        $method->invoke($orders, $pdo, [['catalog_id' => (int) $catRow['id'], 'qtd' => $need]], 1);
        check(false, 'processStockConsumption bloqueia estoque insuficiente');
    } catch (Exception $e) {
        $decoded = json_decode($e->getMessage(), true);
        check(is_array($decoded) && ($decoded['error_code'] ?? '') === 'STOCK_LOW', 'processStockConsumption bloqueia estoque insuficiente');
    }
} else {
    check(true, 'processStockConsumption estoque (skip — catálogo vazio)');
}

// --- Trabalhador escopo O.S. ---
$worker = new OrdersController($pdo, ['id' => 99, 'role' => 'trabalhador', 'nome' => 'Outro Op'], []);
$refExec = new ReflectionClass($worker);
$exec = $refExec->getMethod('saveTaskExecution');
$exec->setAccessible(true);
$anyOs = $pdo->query("SELECT id, usuarios_id FROM ordens LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($anyOs && (int) ($anyOs['usuarios_id'] ?? 0) !== 99) {
    try {
        $exec->invoke($worker, ['id' => $anyOs['id'], 'done' => true]);
        check(false, 'trabalhador bloqueado em O.S. alheia');
    } catch (Exception $e) {
        check(strpos($e->getMessage(), 'atribuída') !== false, 'trabalhador bloqueado em O.S. alheia');
    }
} else {
    check(true, 'trabalhador escopo O.S. (skip — sem O.S. de outro usuário)');
}

exit($fail === 0 ? 0 : 1);
