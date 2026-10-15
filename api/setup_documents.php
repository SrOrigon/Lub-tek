<?php
/**
 * LUB-TEK - Setup Documentos (Proxy Protegido)
 * Execução pública bloqueada. Requer nível Gestor/Developer ou execução via CLI.
 */

if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../scripts/setup_documents.php';
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
$auth = new AuthSystem();
$user = $auth->getCurrentUser();

if (!$user || !in_array($user['role'] ?? '', ['developer', 'gestor'], true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'ok' => false,
        'error' => 'Acesso negado. Esta rota é restrita a administradores ou execução via CLI.',
        'code' => 403
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../scripts/setup_documents.php';
