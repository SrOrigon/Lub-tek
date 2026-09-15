<?php
/**
 * Eventos de incompatibilidade de graxa + coluna de espessante na projeção.
 * Aditivo: não apaga dados_tecnicos nem ativos.
 */
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS grease_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ativo_id INTEGER,
        from_thickener TEXT,
        to_thickener TEXT,
        from_material TEXT,
        to_material TEXT,
        confirmed INTEGER DEFAULT 0,
        wash_os_id INTEGER,
        usuario TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try {
        $cols = $pdo->query('PRAGMA table_info(ativos_lubrificacao)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (is_array($cols) && !in_array('espessante', $cols, true)) {
            $pdo->exec('ALTER TABLE ativos_lubrificacao ADD COLUMN espessante TEXT');
        }
    } catch (Exception $e) {
        // tabela pode ainda não existir em banco recém-criado; ensureTable cria depois
    }
};
