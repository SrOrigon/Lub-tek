<?php
/**
 * Telemetria de uso da IA (tokens, cache, latência) por tenant.
 */
class AiUsageLogger
{
    public static function ensureTable(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_usage_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                feature VARCHAR(64) NOT NULL,
                model VARCHAR(64) NULL,
                prompt_version VARCHAR(32) NULL,
                tokens_in INT DEFAULT 0,
                tokens_out INT DEFAULT 0,
                latency_ms INT DEFAULT 0,
                cache_hit TINYINT DEFAULT 0,
                source VARCHAR(24) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_usage_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feature TEXT NOT NULL,
            model TEXT,
            prompt_version TEXT,
            tokens_in INTEGER DEFAULT 0,
            tokens_out INTEGER DEFAULT 0,
            latency_ms INTEGER DEFAULT 0,
            cache_hit INTEGER DEFAULT 0,
            source TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public static function log(
        PDO $pdo,
        string $feature,
        array $meta = []
    ): void {
        try {
            self::ensureTable($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO ai_usage_log (feature, model, prompt_version, tokens_in, tokens_out, latency_ms, cache_hit, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $feature,
                $meta['model'] ?? null,
                $meta['prompt_version'] ?? null,
                (int) ($meta['tokens_in'] ?? 0),
                (int) ($meta['tokens_out'] ?? 0),
                (int) ($meta['latency_ms'] ?? 0),
                !empty($meta['cache_hit']) ? 1 : 0,
                $meta['source'] ?? null,
            ]);
        } catch (Exception $e) {
            error_log('AiUsageLogger: ' . $e->getMessage());
        }
    }

    public static function dailyTokenTotal(PDO $pdo): int
    {
        self::ensureTable($pdo);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $sql = 'SELECT COALESCE(SUM(tokens_in + tokens_out), 0) FROM ai_usage_log WHERE created_at >= CURDATE()';
        } else {
            $sql = "SELECT COALESCE(SUM(tokens_in + tokens_out), 0) FROM ai_usage_log WHERE date(created_at) = date('now')";
        }
        return (int) $pdo->query($sql)->fetchColumn();
    }

    public static function isDailyBudgetExceeded(PDO $pdo): bool
    {
        $budget = defined('AI_DAILY_TOKEN_BUDGET') ? (int) AI_DAILY_TOKEN_BUDGET : 50000;
        if ($budget <= 0) {
            return false;
        }
        return self::dailyTokenTotal($pdo) >= $budget;
    }
}
