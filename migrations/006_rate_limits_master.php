<?php
/**
 * LUB-TEK Migration 006 — rate_limits no master (sem DROP em request)
 */
return static function (PDO $pdo): void {
    require_once dirname(__DIR__) . '/includes/rate_limit_store.php';
    RateLimitStore::ensureTable($pdo);
};
