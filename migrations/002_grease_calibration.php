<?php
/**
 * LUB-TEK Migration 002 — Calibração de Engraxadeiras e Dosadores
 * Compatível com SQLite e MySQL (PDO).
 */
return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isMysql = ($driver === 'mysql');

    if ($isMysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS engraxadeiras_calibracao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identificacao VARCHAR(120) NOT NULL,
            tipo VARCHAR(50) DEFAULT 'manual',
            gramas_por_golpe DECIMAL(8,3) DEFAULT 1.500,
            data_calibracao DATE NULL,
            proxima_calibracao DATE NULL,
            responsavel VARCHAR(120) NULL,
            ativo TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS engraxadeiras_calibracao (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identificacao TEXT NOT NULL,
            tipo TEXT DEFAULT 'manual',
            gramas_por_golpe REAL DEFAULT 1.500,
            data_calibracao TEXT,
            proxima_calibracao TEXT,
            responsavel TEXT,
            ativo INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Adiciona colunas auxiliares em ordens de forma aditiva e idempotente
    try {
        if ($isMysql) {
            $cols = $pdo->query("SHOW COLUMNS FROM ordens")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $cols = $pdo->query("PRAGMA table_info(ordens)")->fetchAll(PDO::FETCH_COLUMN, 1);
        }

        if (is_array($cols)) {
            if (!in_array('golpes_aplicados', $cols, true)) {
                $type = $isMysql ? 'INT DEFAULT NULL' : 'INTEGER';
                $pdo->exec("ALTER TABLE ordens ADD COLUMN golpes_aplicados $type");
            }
            if (!in_array('engraxadeira_id', $cols, true)) {
                $type = $isMysql ? 'INT DEFAULT NULL' : 'INTEGER';
                $pdo->exec("ALTER TABLE ordens ADD COLUMN engraxadeira_id $type");
            }
        }
    } catch (Exception $e) {
        error_log("Migration 002 add columns skipped: " . $e->getMessage());
    }
};
