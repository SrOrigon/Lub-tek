<?php
/**
 * LUB-TEK - Setup Documentos
 * Migração de tabelas de documentos — SOMENTE CLI ou Admin autenticado.
 * Uso via CLI: php scripts/setup_documents.php
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';

if (php_sapi_name() !== 'cli') {
    $auth = new AuthSystem();
    $user = $auth->getCurrentUser();
    if (!$user || !in_array($user['role'] ?? '', ['developer', 'gestor'], true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Acesso negado. Execução restrita a CLI ou Admin autenticado.', 'code' => 403], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $db = DB::getInstance();

    $queries = [
        "CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            discipline TEXT,
            ativo_id INTEGER REFERENCES ativos(id) ON DELETE SET NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER
        )",
        "CREATE TABLE IF NOT EXISTS physical_files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_path TEXT NOT NULL,
            file_name TEXT NOT NULL,
            file_type TEXT,
            file_size INTEGER,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            uploaded_by INTEGER
        )",
        "CREATE TABLE IF NOT EXISTS document_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER REFERENCES documents(id) ON DELETE CASCADE,
            version_number INTEGER NOT NULL,
            physical_file_id INTEGER REFERENCES physical_files(id),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            change_log TEXT,
            is_current BOOLEAN DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            color TEXT DEFAULT '#808080',
            category TEXT
        )",
        "CREATE TABLE IF NOT EXISTS document_tags (
            document_id INTEGER REFERENCES documents(id) ON DELETE CASCADE,
            tag_id INTEGER REFERENCES tags(id) ON DELETE CASCADE,
            PRIMARY KEY (document_id, tag_id)
        )"
    ];

    foreach ($queries as $q) {
        $db->exec($q);
    }

    if (php_sapi_name() === 'cli') {
        echo "[OK] Tabelas de documentacao verificadas e criadas com sucesso.\n";
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Tabelas de documentação criadas com sucesso.'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    if (php_sapi_name() === 'cli') {
        echo "[ERRO] " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Erro ao configurar tabelas de documentos.'], JSON_UNESCAPED_UNICODE);
    }
}
