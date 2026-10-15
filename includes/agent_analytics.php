<?php
/**
 * Agregações de telemetria IA e feedback dos agentes.
 */
require_once __DIR__ . '/ai_usage_logger.php';
require_once __DIR__ . '/agent_feedback.php';

class AgentAnalytics
{
    public static function aiUsageSummary(PDO $pdo, int $days = 7): array
    {
        AiUsageLogger::ensureTable($pdo);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $since = $driver === 'mysql'
            ? "DATE_SUB(CURDATE(), INTERVAL " . (int) $days . " DAY)"
            : "date('now', '-" . (int) $days . " days')";

        $todaySql = $driver === 'mysql'
            ? 'COALESCE(SUM(tokens_in + tokens_out), 0) FROM ai_usage_log WHERE created_at >= CURDATE()'
            : "COALESCE(SUM(tokens_in + tokens_out), 0) FROM ai_usage_log WHERE date(created_at) = date('now')";

        $totals = $pdo->query(
            "SELECT COUNT(*) AS calls,
                    COALESCE(SUM(tokens_in), 0) AS tokens_in,
                    COALESCE(SUM(tokens_out), 0) AS tokens_out,
                    COALESCE(SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END), 0) AS cache_hits
             FROM ai_usage_log WHERE created_at >= {$since}"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $tokensToday = (int) $pdo->query('SELECT ' . $todaySql)->fetchColumn();
        $budget = defined('AI_DAILY_TOKEN_BUDGET') ? (int) AI_DAILY_TOKEN_BUDGET : 50000;

        $byFeature = $pdo->query(
            "SELECT feature,
                    COUNT(*) AS calls,
                    COALESCE(SUM(tokens_in + tokens_out), 0) AS tokens
             FROM ai_usage_log WHERE created_at >= {$since}
             GROUP BY feature ORDER BY tokens DESC LIMIT 12"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $calls = (int) ($totals['calls'] ?? 0);
        $cacheHits = (int) ($totals['cache_hits'] ?? 0);

        return [
            'days' => $days,
            'calls' => $calls,
            'tokens_in' => (int) ($totals['tokens_in'] ?? 0),
            'tokens_out' => (int) ($totals['tokens_out'] ?? 0),
            'tokens_total' => (int) (($totals['tokens_in'] ?? 0) + ($totals['tokens_out'] ?? 0)),
            'cache_hit_rate' => $calls > 0 ? round(100 * $cacheHits / $calls, 1) : 0,
            'tokens_today' => $tokensToday,
            'budget_daily' => $budget,
            'budget_used_percent' => $budget > 0 ? round(100 * $tokensToday / $budget, 1) : 0,
            'by_feature' => $byFeature,
        ];
    }

    public static function agentFeedbackStats(PDO $pdo, int $days = 30): array
    {
        AgentFeedback::ensureTable($pdo);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $since = $driver === 'mysql'
            ? "DATE_SUB(CURDATE(), INTERVAL " . (int) $days . " DAY)"
            : "date('now', '-" . (int) $days . " days')";

        $rows = $pdo->query(
            "SELECT agent, action, COUNT(*) AS cnt
             FROM agent_feedback WHERE created_at >= {$since}
             GROUP BY agent, action"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $accept = 0;
        $reject = 0;
        $byAgent = [];
        foreach ($rows as $r) {
            $agent = $r['agent'] ?? 'unknown';
            $action = $r['action'] ?? '';
            $cnt = (int) ($r['cnt'] ?? 0);
            if (!isset($byAgent[$agent])) {
                $byAgent[$agent] = ['accept' => 0, 'reject' => 0];
            }
            if ($action === 'accept') {
                $accept += $cnt;
                $byAgent[$agent]['accept'] += $cnt;
            } elseif ($action === 'reject') {
                $reject += $cnt;
                $byAgent[$agent]['reject'] += $cnt;
            }
        }

        $outcomes = $pdo->query(
            "SELECT outcome, COUNT(*) AS cnt FROM agent_feedback
             WHERE outcome IS NOT NULL AND outcome != '' AND created_at >= {$since}
             GROUP BY outcome"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $topRejectAssets = $pdo->query(
            "SELECT asset_id, COUNT(*) AS cnt FROM agent_feedback
             WHERE action = 'reject' AND asset_id IS NOT NULL AND created_at >= {$since}
             GROUP BY asset_id ORDER BY cnt DESC LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = $accept + $reject;

        return [
            'days' => $days,
            'accept' => $accept,
            'reject' => $reject,
            'accept_rate' => $total > 0 ? round(100 * $accept / $total, 1) : null,
            'by_agent' => $byAgent,
            'outcomes' => $outcomes,
            'top_rejected_assets' => $topRejectAssets,
        ];
    }
}
