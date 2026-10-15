<?php
/**
 * Persistência de feedback do operador sobre decisões dos agentes.
 */
class AgentFeedback
{
    public static function ensureTable(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS agent_feedback (
                id INT AUTO_INCREMENT PRIMARY KEY,
                agent VARCHAR(64) NOT NULL,
                decision_id VARCHAR(128) NULL,
                asset_id INT NULL,
                order_id INT NULL,
                action VARCHAR(16) NOT NULL,
                reason TEXT NULL,
                outcome VARCHAR(32) NULL,
                meta_json TEXT NULL,
                created_by VARCHAR(120) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                outcome_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent TEXT NOT NULL,
            decision_id TEXT,
            asset_id INTEGER,
            order_id INTEGER,
            action TEXT NOT NULL,
            reason TEXT,
            outcome TEXT,
            meta_json TEXT,
            created_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            outcome_at DATETIME
        )");
    }

    public static function save(PDO $pdo, array $row): int
    {
        self::ensureTable($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO agent_feedback (agent, decision_id, asset_id, order_id, action, reason, outcome, meta_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $metaJson = isset($row['meta']) ? json_encode($row['meta'], JSON_UNESCAPED_UNICODE) : null;
        $stmt->execute([
            $row['agent'] ?? 'unknown',
            $row['decision_id'] ?? null,
            isset($row['asset_id']) ? (int) $row['asset_id'] : null,
            isset($row['order_id']) ? (int) $row['order_id'] : null,
            $row['action'] ?? 'unknown',
            $row['reason'] ?? null,
            $row['outcome'] ?? null,
            $metaJson,
            $row['created_by'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function setOutcomeForOrder(PDO $pdo, int $orderId, string $outcome): void
    {
        self::ensureTable($pdo);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $stmt = $pdo->prepare(
            "UPDATE agent_feedback SET outcome = ?, outcome_at = {$nowExpr} WHERE order_id = ? AND outcome IS NULL"
        );
        $stmt->execute([$outcome, $orderId]);
    }
}
