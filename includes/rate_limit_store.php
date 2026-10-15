<?php
/**
 * Rate limit por IP (master DB). Schema via migrations/006_rate_limits_master.php
 */
class RateLimitStore
{
    public static function ensureTable(PDO $masterPdo): void
    {
        $driver = $masterPdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $masterPdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                ip_key VARCHAR(191) PRIMARY KEY,
                count INT NOT NULL DEFAULT 0,
                window_start INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return;
        }
        $masterPdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            ip_key TEXT PRIMARY KEY,
            count INTEGER NOT NULL DEFAULT 0,
            window_start INTEGER NOT NULL
        )");
    }
}
