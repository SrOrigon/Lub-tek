<?php
/**
 * Eval integrado: agente de O.S. em SQLite de teste (dry_run).
 */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/work_order_generation_agent.php';
require_once __DIR__ . '/eval/bootstrap_eval_db.php';

$pdo = eval_bootstrap_pdo();
$agent = new WorkOrderGenerationAgent($pdo, ['source' => 'eval']);
$result = $agent->run(true, true);

$fail = 0;
if (empty($result['success'])) {
    echo "FAIL agent run success\n";
    $fail++;
}
if (($result['due_points'] ?? 0) < 1) {
    echo "FAIL expected at least 1 due point, got " . ($result['due_points'] ?? 0) . "\n";
    $fail++;
}
$decisions = $result['decisions'] ?? [];
$hasCreate = false;
foreach ($decisions as $d) {
    if (($d['action'] ?? '') === 'create' || ($d['action'] ?? '') === 'would_create') {
        $hasCreate = true;
        break;
    }
}
if (!$hasCreate) {
    echo "FAIL expected at least one create/would_create decision\n";
    $fail++;
}

if ($fail > 0) {
    fwrite(STDERR, "test_work_order_agent_eval: {$fail} falha(s)\n");
    exit(1);
}

echo "test_work_order_agent_eval: OK (due={$result['due_points']}, decisions=" . count($decisions) . ")\n";
exit(0);
