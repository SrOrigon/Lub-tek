<?php
/**
 * Verifica rotas críticas da API (handlers registrados).
 * Uso: php scripts/test_api_routes.php
 */
require_once __DIR__ . '/../config.php';

$apiSource = file_get_contents(__DIR__ . '/../api.php');
if ($apiSource === false) {
    echo "FAIL  não leu api.php\n";
    exit(1);
}

$critical = [
    'update_asset_image' => ['handler' => 'handle_update_asset_image', 'file' => 'api.php'],
    'upload_image' => ['handler' => 'handle_upload_image', 'file' => 'api.php'],
    'get_lubrication_plan_meta' => ['handler' => 'getLubricationPlanMeta', 'file' => 'api/PdfController.php'],
    'get_analysis_history' => ['handler' => 'handle_get_analysis_history', 'file' => 'api.php'],
    'get_thickener' => ['handler' => 'handle_get_thickener', 'file' => 'api.php'],
];

$failures = [];
foreach ($critical as $action => $spec) {
    $handler = $spec['handler'];
    $file = __DIR__ . '/../' . $spec['file'];
    $fileSource = file_get_contents($file) ?: '';
    $routeOk = (bool) preg_match("/['\"]{$action}['\"]\s*=>/", $apiSource);
    $handlerOk = strpos($fileSource, "function {$handler}") !== false;

    if ($routeOk && $handlerOk) {
        echo "PASS  {$action} -> {$handler}\n";
    } else {
        $failures[] = $action;
        echo "FAIL  {$action} route=" . ($routeOk ? 'ok' : 'missing') . " handler=" . ($handlerOk ? 'ok' : 'missing') . "\n";
    }
}

if (!empty($failures)) {
    exit(1);
}

echo "\nOK\n";
exit(0);
