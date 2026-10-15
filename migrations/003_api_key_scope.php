<?php
/**
 * LUB-TEK Migration 003 — Escopo das chaves X-API-KEY (master: tenant_api_keys)
 * Valores: full | iot_write | bi_read
 */
return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isMysql = ($driver === 'mysql');

    $hasTable = false;
    try {
        if ($isMysql) {
            $stmt = $pdo->query("SHOW TABLES LIKE 'tenant_api_keys'");
            $hasTable = $stmt && $stmt->fetchColumn();
        } else {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tenant_api_keys'");
            $hasTable = (bool) ($stmt && $stmt->fetchColumn());
        }
    } catch (Exception $e) {
        return;
    }

    if (!$hasTable) {
        return;
    }

    $cols = [];
    try {
        if ($isMysql) {
            $cols = $pdo->query('SHOW COLUMNS FROM tenant_api_keys')->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $cols = $pdo->query('PRAGMA table_info(tenant_api_keys)')->fetchAll(PDO::FETCH_COLUMN, 1);
        }
    } catch (Exception $e) {
        error_log('Migration 003 scope column check: ' . $e->getMessage());
        return;
    }

    if (!is_array($cols) || in_array('scope', $cols, true)) {
        return;
    }

    if ($isMysql) {
        $pdo->exec("ALTER TABLE tenant_api_keys ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'full'");
    } else {
        $pdo->exec("ALTER TABLE tenant_api_keys ADD COLUMN scope TEXT NOT NULL DEFAULT 'full'");
    }

    $pdo->exec("UPDATE tenant_api_keys SET scope = 'full' WHERE scope IS NULL OR scope = ''");
};
