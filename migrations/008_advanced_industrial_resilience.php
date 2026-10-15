<?php
/**
 * LUB-TEK Migration 008 — Resiliência Industrial Avançada
 * - Suporte a QR Code desacoplado (qr_hash indexado)
 * - Metadados de Food Grade (is_food_grade_required)
 * - Fila Outbox e Timestamps separados (executed_at vs synced_at)
 * - Fila de Integração ERP/SAP resiliente (integration_queue)
 */
return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isMysql = ($driver === 'mysql');

    // 1. ATIVOS: qr_hash e is_food_grade_required
    $ativoCols = [];
    try {
        if ($isMysql) {
            $stmt = $pdo->query("SHOW COLUMNS FROM ativos");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ativoCols[] = strtolower($row['Field']);
            }
        } else {
            $stmt = $pdo->query("PRAGMA table_info(ativos)");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ativoCols[] = strtolower($row['name']);
            }
        }
    } catch (Throwable $e) {}

    if (!in_array('qr_hash', $ativoCols, true)) {
        try {
            $pdo->exec($isMysql
                ? "ALTER TABLE ativos ADD COLUMN qr_hash VARCHAR(64) NULL AFTER ip"
                : "ALTER TABLE ativos ADD COLUMN qr_hash TEXT NULL");
        } catch (Throwable $e) {}
    }

    if (!in_array('is_food_grade_required', $ativoCols, true)) {
        try {
            $pdo->exec($isMysql
                ? "ALTER TABLE ativos ADD COLUMN is_food_grade_required TINYINT(1) NOT NULL DEFAULT 0"
                : "ALTER TABLE ativos ADD COLUMN is_food_grade_required INTEGER NOT NULL DEFAULT 0");
        } catch (Throwable $e) {}
    }

    // Criar índice em qr_hash
    try {
        if ($isMysql) {
            $pdo->exec("CREATE INDEX idx_ativos_qr_hash ON ativos (qr_hash)");
        } else {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ativos_qr_hash ON ativos (qr_hash)");
        }
    } catch (Throwable $e) {}

    // Preenche qr_hash para ativos existentes que ainda não tenham
    try {
        $stmtEmpty = $pdo->query("SELECT id, tag, ip FROM ativos WHERE qr_hash IS NULL OR qr_hash = ''");
        $rows = $stmtEmpty->fetchAll(PDO::FETCH_ASSOC);
        $updateStmt = $pdo->prepare("UPDATE ativos SET qr_hash = ? WHERE id = ?");
        foreach ($rows as $r) {
            $hash = 'qr_' . substr(hash('sha256', ($r['id'] ?? '') . '|' . ($r['tag'] ?? '') . '|' . ($r['ip'] ?? '') . '|salt_lubtek_2026'), 0, 16);
            $updateStmt->execute([$hash, $r['id']]);
        }
    } catch (Throwable $e) {}

    // 2. ORDENS: idempotency_key, executed_at, synced_at, dosage_amount, dosage_unit
    $ordensCols = [];
    try {
        if ($isMysql) {
            $stmt = $pdo->query("SHOW COLUMNS FROM ordens");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ordensCols[] = strtolower($row['Field']);
            }
        } else {
            $stmt = $pdo->query("PRAGMA table_info(ordens)");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ordensCols[] = strtolower($row['name']);
            }
        }
    } catch (Throwable $e) {}

    $neededCols = [
        'idempotency_key' => $isMysql ? "VARCHAR(191) NULL" : "TEXT NULL",
        'executed_at' => $isMysql ? "DATETIME NULL" : "TEXT NULL",
        'synced_at' => $isMysql ? "DATETIME NULL" : "TEXT NULL",
        'dosage_amount' => $isMysql ? "DECIMAL(12,4) NULL" : "REAL NULL",
        'dosage_unit' => $isMysql ? "VARCHAR(30) NULL" : "TEXT NULL",
    ];

    foreach ($neededCols as $col => $typeDef) {
        if (!in_array($col, $ordensCols, true)) {
            try {
                $pdo->exec("ALTER TABLE ordens ADD COLUMN {$col} {$typeDef}");
            } catch (Throwable $e) {}
        }
    }

    try {
        if ($isMysql) {
            $pdo->exec("CREATE INDEX idx_ordens_idempotency_key ON ordens (idempotency_key)");
        } else {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ordens_idempotency_key ON ordens (idempotency_key)");
        }
    } catch (Throwable $e) {}

    // 3. TABELA DE FILA DE INTEGRAÇÃO ERP/SAP RESILIENTE
    if ($isMysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS integration_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            external_system VARCHAR(32) NOT NULL DEFAULT 'SAP',
            action VARCHAR(64) NOT NULL,
            reference_id VARCHAR(128) NULL,
            payload MEDIUMTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            processed_at DATETIME NULL,
            INDEX idx_integration_status (status, external_system),
            INDEX idx_integration_ref (reference_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS integration_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_system TEXT NOT NULL DEFAULT 'SAP',
            action TEXT NOT NULL,
            reference_id TEXT NULL,
            payload TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'PENDING',
            attempts INTEGER NOT NULL DEFAULT 0,
            max_attempts INTEGER NOT NULL DEFAULT 3,
            error_message TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            processed_at TEXT NULL
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_integration_status ON integration_queue (status, external_system)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_integration_ref ON integration_queue (reference_id)");
    }
};
