<?php
/**
 * Avaliação dos agentes (fixtures versionadas) — CI.
 */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

putenv('GEMINI_API_KEY=dummy_key');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/neural_knowledge.php';
require_once dirname(__DIR__) . '/includes/agent_weights.php';
require_once dirname(__DIR__) . '/includes/prompt_registry.php';
require_once dirname(__DIR__) . '/includes/agent_eval_scoring.php';
require_once dirname(__DIR__) . '/includes/agent_routing.php';

$fixturesDir = __DIR__ . '/eval/fixtures';
$failures = 0;
$ran = 0;

function evalFail(string $id, string $msg): void
{
    global $failures;
    echo "FAIL [{$id}] {$msg}\n";
    $failures++;
}

function loadFixture(string $file): array
{
    $path = $GLOBALS['fixturesDir'] . '/' . $file;
    if (!is_file($path)) {
        evalFail($file, 'arquivo ausente');
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        evalFail($file, 'JSON inválido');
        return [];
    }
    return $data;
}

// --- force_ai vs roteamento local ---
foreach (loadFixture('force_ai_routing.json') as $case) {
    $ran++;
    $id = $case['id'] ?? 'force-?';
    $localOnly = AgentRouting::shouldUseLocalOnly(
        (string) ($case['prompt'] ?? ''),
        !empty($case['force_ai'])
    );
    $expectLocal = !empty($case['expect_local_only']);
    if ($localOnly !== $expectLocal) {
        evalFail($id, $localOnly ? 'roteou local-only inesperado' : 'esperava local-only');
    }
}

// --- Router local vs Gemini ---
foreach (loadFixture('neural_local_router.json') as $case) {
    $ran++;
    $id = $case['id'] ?? 'router-?';
    $local = NeuralKnowledge::shouldUseLocalFirst($case['prompt'] ?? '');
    if (!empty($case['expect_source']) && $case['expect_source'] === 'local' && !$local) {
        evalFail($id, 'esperava roteamento local');
    }
    if (!empty($case['expect_not_blocked_local_only']) && $local) {
        evalFail($id, 'pergunta técnica bloqueada pelo router local');
    }
}

// --- Score urgência O.S. ---
$osWeights = AgentWeights::defaults()['os_agent'];
foreach (loadFixture('os_urgency_scores.json') as $case) {
    $ran++;
    $id = $case['id'] ?? 'os-?';
    $tech = $case['tech'] ?? [];
    $overdue = (int) ($case['overdue_days'] ?? 0);
    $freq = (int) ($case['freq_days'] ?? 30);
    $score = AgentEvalScoring::osUrgencyScore($tech, $overdue, $freq, $osWeights);
    $min = (int) ($case['expect_min'] ?? 0);
    $max = (int) ($case['expect_max'] ?? 100);
    if ($score < $min || $score > $max) {
        evalFail($id, "score={$score} fora de [{$min},{$max}]");
    }
    if (!empty($case['expect_priority'])) {
        $prio = AgentEvalScoring::osPriorityLabel($tech, $score, $overdue);
        if ($prio !== $case['expect_priority']) {
            evalFail($id, "prioridade={$prio} esperado {$case['expect_priority']}");
        }
    }
}

// --- Risco neural ---
$neuralWeights = AgentWeights::defaults()['neural_risk'];
foreach (loadFixture('neural_risk_signals.json') as $case) {
    $ran++;
    $id = $case['id'] ?? 'risk-?';
    $signals = $case['signals'] ?? [];
    $risk = AgentEvalScoring::neuralRiskPercentage($signals, $neuralWeights);
    $min = (float) ($case['expect_min'] ?? 0);
    $max = (float) ($case['expect_max'] ?? 99);
    if ($risk < $min || $risk > $max) {
        evalFail($id, "risk={$risk} fora de [{$min},{$max}]");
    }
    if (!empty($case['expect_health'])) {
        $health = AgentEvalScoring::neuralHealthLabel($risk);
        if ($health !== $case['expect_health']) {
            evalFail($id, "health={$health} esperado {$case['expect_health']}");
        }
    }
}

// --- Prompt registry ---
foreach (loadFixture('prompt_registry_required.json') as $case) {
    $ran++;
    $id = $case['id'] ?? 'prompt-?';
    $pid = $case['prompt_id'] ?? '';
    $text = PromptRegistry::get($pid);
    if ($text === '') {
        evalFail($id, "prompt vazio: {$pid}");
        continue;
    }
    $minLen = (int) ($case['min_length'] ?? 1);
    if (strlen($text) < $minLen) {
        evalFail($id, "prompt {$pid} curto demais");
    }
    $meta = PromptRegistry::meta($pid);
    if (($meta['prompt_hash'] ?? '') === '') {
        evalFail($id, 'hash ausente');
    }
}

// --- Defaults de pesos ---
$defaults = AgentWeights::defaults();
foreach (loadFixture('agent_weights_defaults.json') as $case) {
    $ran++;
    $id = $case['id'] ?? 'weights-?';
    $section = $case['section'] ?? '';
    $key = $case['key'] ?? '';
    if (!isset($defaults[$section][$key])) {
        evalFail($id, "chave ausente {$section}.{$key}");
        continue;
    }
    $val = $defaults[$section][$key];
    if (($case['expect_type'] ?? '') === 'float' && !is_numeric($val)) {
        evalFail($id, 'não numérico');
        continue;
    }
    $f = (float) $val;
    if ($f < (float) ($case['expect_min'] ?? -INF) || $f > (float) ($case['expect_max'] ?? INF)) {
        evalFail($id, "valor {$f} fora do intervalo");
    }
}

// --- Respostas locais (smoke) ---
foreach (loadFixture('local_answer_smoke.json') as $case) {
    $ran++;
    $id = $case['id'] ?? 'local-?';
    $context = $case['context'] ?? [];
    if (isset($case['page'])) {
        $context['page'] = $case['page'];
    }
    $answer = NeuralKnowledge::getLocalAnswer($case['prompt'] ?? '', $context);
    $lower = function_exists('mb_strtolower') ? mb_strtolower($answer) : strtolower($answer);
    foreach ($case['expect_substrings'] ?? [] as $needle) {
        $n = function_exists('mb_strtolower') ? mb_strtolower($needle) : strtolower($needle);
        $found = function_exists('mb_strpos') ? mb_strpos($lower, $n) : strpos($lower, $n);
        if ($found === false) {
            evalFail($id, "substring ausente: {$needle}");
        }
    }
}

if ($failures > 0) {
    fwrite(STDERR, "test_agent_eval: {$failures} falha(s) em {$ran} casos\n");
    exit(1);
}

echo "test_agent_eval: OK ({$ran} casos)\n";
exit(0);
