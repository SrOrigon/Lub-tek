<?php
/**
 * CLI: calibra pesos heurísticos (grid + feedback/outcomes + fixtures de eval).
 * php scripts/calibrate_agent_weights.php [tenant_slug]
 */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/tenant.php';
require_once dirname(__DIR__) . '/includes/agent_weights.php';
require_once dirname(__DIR__) . '/includes/agent_feedback.php';
require_once dirname(__DIR__) . '/includes/agent_eval_scoring.php';
require_once dirname(__DIR__) . '/db.php';

$slug = $argv[1] ?? TenantResolver::listTenantSlugs()[0] ?? null;
if (!$slug) {
    fwrite(STDERR, "Nenhum tenant.\n");
    exit(1);
}

$pdo = DB::getInstance($slug);
AgentFeedback::ensureTable($pdo);

$accept = (int) $pdo->query("SELECT COUNT(*) FROM agent_feedback WHERE action = 'accept'")->fetchColumn();
$reject = (int) $pdo->query("SELECT COUNT(*) FROM agent_feedback WHERE action = 'reject'")->fetchColumn();
$completed = (int) $pdo->query("SELECT COUNT(*) FROM agent_feedback WHERE outcome = 'completed'")->fetchColumn();

$weights = AgentWeights::load($pdo);
$defaults = AgentWeights::defaults();

$fixturePath = __DIR__ . '/eval/fixtures/os_urgency_scores.json';
$fixtureCases = is_file($fixturePath)
    ? json_decode((string) file_get_contents($fixturePath), true)
    : [];
if (!is_array($fixtureCases)) {
    $fixtureCases = [];
}

$feedbackRows = $pdo->query(
    'SELECT agent, action, outcome, meta_json FROM agent_feedback ORDER BY id DESC LIMIT 500'
)->fetchAll(PDO::FETCH_ASSOC);

/**
 * @param array<int,array<string,mixed>> $cases
 */
function fixturePenalty(array $cases, float $overdueMult, float $criticalBonus): int
{
    $penalty = 0;
    $w = ['overdue_day_score' => $overdueMult, 'critical_bonus' => $criticalBonus];
    foreach ($cases as $case) {
        if (!is_array($case)) {
            continue;
        }
        $tech = $case['tech'] ?? [];
        $overdue = (int) ($case['overdue_days'] ?? 0);
        $freq = (int) ($case['freq_days'] ?? 30);
        $score = AgentEvalScoring::osUrgencyScore($tech, $overdue, $freq, $w);
        $min = (int) ($case['expect_min'] ?? 0);
        $max = (int) ($case['expect_max'] ?? 100);
        if ($score < $min || $score > $max) {
            $penalty += 10;
        }
        if (!empty($case['expect_priority'])) {
            $prio = AgentEvalScoring::osPriorityLabel($tech, $score, $overdue);
            if ($prio !== $case['expect_priority']) {
                $penalty += 5;
            }
        }
    }
    return $penalty;
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function feedbackScore(array $rows, float $overdueMult, float $neuralBase): int
{
    $score = 0;
    $osW = ['overdue_day_score' => $overdueMult, 'critical_bonus' => 25.0];
    $neuralW = array_merge(AgentWeights::defaults()['neural_risk'], ['base' => $neuralBase]);

    foreach ($rows as $row) {
        $action = (string) ($row['action'] ?? '');
        $outcome = (string) ($row['outcome'] ?? '');
        $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
        if (!is_array($meta)) {
            continue;
        }
        $accepted = ($action === 'accept');

        if (($row['agent'] ?? '') === 'os_agent' && isset($meta['overdue_days'])) {
            $tech = is_array($meta['tech'] ?? null) ? $meta['tech'] : [];
            $overdue = (int) $meta['overdue_days'];
            $freq = (int) ($meta['freq_days'] ?? 30);
            $urgency = AgentEvalScoring::osUrgencyScore($tech, $overdue, $freq, $osW);
            $wouldEscalate = $urgency >= 65;
            if ($accepted && $wouldEscalate) {
                $score += 3;
            } elseif (!$accepted && !$wouldEscalate) {
                $score += 2;
            } elseif ($accepted && !$wouldEscalate) {
                $score -= 1;
            } else {
                $score -= 2;
            }
        }

        if (($row['agent'] ?? '') === 'neural_engine' && isset($meta['signals']) && is_array($meta['signals'])) {
            $risk = AgentEvalScoring::neuralRiskPercentage($meta['signals'], $neuralW);
            $highRisk = $risk >= 55.0;
            if ($accepted && $highRisk) {
                $score += 3;
            } elseif (!$accepted && !$highRisk) {
                $score += 2;
            } elseif ($accepted && !$highRisk) {
                $score -= 1;
            } else {
                $score -= 2;
            }
        }

        if ($outcome === 'completed' && $accepted) {
            $score += 1;
        }
        if ($outcome === 'reopened' && $accepted) {
            $score -= 2;
        }
    }

    return $score;
}

$bestOsMult = (float) ($weights['os_agent']['overdue_day_score'] ?? $defaults['os_agent']['overdue_day_score']);
$bestCrit = (float) ($weights['os_agent']['critical_bonus'] ?? $defaults['os_agent']['critical_bonus']);
$bestFixturePenalty = fixturePenalty($fixtureCases, $bestOsMult, $bestCrit);

foreach ([3.0, 3.5, 4.0, 4.5, 5.0, 5.5, 6.0] as $mult) {
    foreach ([20.0, 25.0, 30.0] as $crit) {
        $p = fixturePenalty($fixtureCases, $mult, $crit);
        if ($p < $bestFixturePenalty) {
            $bestFixturePenalty = $p;
            $bestOsMult = $mult;
            $bestCrit = $crit;
        }
    }
}

$bestNeuralBase = (float) ($weights['neural_risk']['base'] ?? $defaults['neural_risk']['base']);
$bestFbScore = feedbackScore($feedbackRows, $bestOsMult, $bestNeuralBase);

foreach ([10.0, 12.0, 15.0, 18.0, 22.0, 25.0] as $base) {
    $s = feedbackScore($feedbackRows, $bestOsMult, $base);
    if ($s > $bestFbScore) {
        $bestFbScore = $s;
        $bestNeuralBase = $base;
    }
}

$ratio = ($accept + $reject) > 0 ? $accept / ($accept + $reject) : 0.5;
if ($ratio < 0.35 && $bestFbScore <= 0) {
    $bestNeuralBase = min(25.0, $bestNeuralBase + 2.0);
    $bestOsMult = min(6.0, $bestOsMult + 0.25);
} elseif ($ratio > 0.8 && $bestFbScore <= 0) {
    $bestNeuralBase = max(10.0, $bestNeuralBase - 1.0);
}

$weights['os_agent']['overdue_day_score'] = $bestOsMult;
$weights['os_agent']['critical_bonus'] = $bestCrit;
$weights['neural_risk']['base'] = $bestNeuralBase;

AgentWeights::save($pdo, $weights);

echo "Tenant {$slug}: accept={$accept} reject={$reject} completed_outcomes={$completed}\n";
echo "Grid: overdue_day_score={$bestOsMult} critical_bonus={$bestCrit} neural_base={$bestNeuralBase}\n";
echo "Fixture penalty={$bestFixturePenalty} feedback_score={$bestFbScore}\n";
echo "Pesos atualizados em system_meta.agent_weights\n";
