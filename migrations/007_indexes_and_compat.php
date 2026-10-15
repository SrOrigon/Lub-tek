<?php
/**
 * LUB-TEK Migration 007 — Índices de performance (SQLite/MySQL)
 */
return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isMysql = ($driver === 'mysql');

    $indexes = [
        ['usuarios', 'idx_usuarios_email', 'email'],
        ['ordens', 'idx_ordens_ativo_situacao', 'ativo_id, situacao'],
        ['ativos', 'idx_ativos_pai_id', 'pai_id'],
    ];

    foreach ($indexes as [$table, $name, $cols]) {
        try {
            if ($isMysql) {
                $pdo->exec("CREATE INDEX {$name} ON {$table} ({$cols})");
            } else {
                $pdo->exec("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$cols})");
            }
        } catch (Exception $e) {
            // idempotente
        }
    }
};
