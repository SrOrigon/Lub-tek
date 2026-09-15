<?php
/**
 * Auditoria completa do sistema — uso: php scripts/system_audit.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/lubrication_plan_builder.php';
require_once __DIR__ . '/../includes/lubrication_plan_image_helper.php';
require_once __DIR__ . '/../includes/lubrication_plan_ssma_content.php';
require_once __DIR__ . '/../includes/lubrication_plan_renderer.php';

$failures = [];
$checks = [];

function runTestScript(string $script): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
    exec($cmd, $output, $code);
    return ['code' => $code, 'output' => implode("\n", $output)];
}

echo "=== AUDITORIA LUB-TEK ===\n\n";

$testScripts = glob(__DIR__ . '/test_*.php') ?: [];
sort($testScripts);
foreach ($testScripts as $script) {
    if (basename($script) === 'system_audit.php') {
        continue;
    }
    $name = basename($script);
    $result = runTestScript($script);
    $ok = $result['code'] === 0;
    $checks[] = ['test' => $name, 'ok' => $ok];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $failures[] = "Teste $name falhou (exit {$result['code']})";
        echo $result['output'] . "\n";
    }
}

$pdo = DB::getInstance();
$builder = new LubricationPlanBuilder($pdo);
$renderer = new LubricationPlanRenderer(['name' => 'LUB-TEK']);

$scenarios = [
    'completo' => fn() => $builder->build(18, []),
    'mensal' => fn() => $builder->build(18, ['frequency' => 'Mensal']),
];

foreach ($scenarios as $label => $factory) {
    $plan = $factory();
    $html = $renderer->renderFull($plan, ['show_toolbar' => false, 'use_ai' => false]);
    $validation = LubricationPlanPatternGuard::validate($html);
    $actual = LubricationPlanPatternGuard::countSlides($html);
    $estimated = (int) ($plan['meta']['stats']['slides_estimated'] ?? 0);
    $drift = $estimated > 0 ? abs($actual - $estimated) / $actual : 0;

    echo "\n[Plano:$label] slides=$actual est=$estimated drift=" . round($drift * 100) . "% pattern=" . ($validation['ok'] ? 'OK' : 'FAIL') . "\n";

    if (!$validation['ok']) {
        foreach ($validation['errors'] as $e) {
            $failures[] = "Plano $label: $e";
            echo "  ERRO: $e\n";
        }
    }
    if ($drift > 0.35) {
        $failures[] = "Plano $label: estimativa de slides diverge {$estimated} vs {$actual} (>35%)";
        echo "  AVISO: estimativa de slides fora do tolerável\n";
    }
}

require_once __DIR__ . '/../includes/auth.php';
ob_start();
$auth = new AuthSystem();
$loginResult = $auth->login('Cocacola.Admin', 'changeme');
$loginWarnings = ob_get_clean();
$headersSentDuringLogin = headers_sent();

if (!empty($loginResult['success'])) {
    echo "\n[Auth] login OK, headers_sent=" . ($headersSentDuringLogin ? 'true' : 'false') . "\n";
} else {
    $failures[] = 'Login Cocacola.Admin falhou na auditoria';
    echo "\n[Auth] FAIL\n";
}

echo "\n=== RESULTADO ===\n";
if (empty($failures)) {
    echo "SISTEMA OK — nenhum problema detectado.\n";
    exit(0);
}

echo "FALHAS (" . count($failures) . "):\n";
foreach ($failures as $f) {
    echo " - $f\n";
}
exit(1);
