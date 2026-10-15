<?php
/**
 * LUB-TEK Migration 004 — Índice de login e-mail → tenant (master)
 */
return static function (PDO $pdo): void {
    require_once dirname(__DIR__) . '/includes/tenant_login_index.php';
    TenantLoginIndex::ensureTable($pdo);
};
