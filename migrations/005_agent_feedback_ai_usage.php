<?php
/**
 * LUB-TEK Migration 005 — Feedback de agentes + log de uso de IA (por tenant)
 */
return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isMysql = ($driver === 'mysql');

    if ($isMysql) {
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
            outcome_at TIMESTAMP NULL,
            INDEX idx_agent_feedback_agent (agent),
            INDEX idx_agent_feedback_asset (asset_id),
            INDEX idx_agent_feedback_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ai_usage_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
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
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_agent_feedback_agent ON agent_feedback(agent)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_agent_feedback_asset ON agent_feedback(asset_id)');

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
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_usage_created ON ai_usage_log(created_at)');
    }
};
