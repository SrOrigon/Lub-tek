<?php
/**
 * LUB-TEK - Encerramento Seguro de Sessão
 * Destrói apenas a sessão do usuário atual e redireciona para o login.
 */
require_once __DIR__ . '/includes/session_bootstrap.php';

SessionBootstrap::ensure();

$currentSid = session_id();

$_SESSION = [];

if (isset($_COOKIE[session_name()])) {
    $trustProxy = (defined('TRUSTED_PROXY') && TRUSTED_PROXY) || (getenv('TRUSTED_PROXY') === '1');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || ($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

session_destroy();

$sessionDir = SessionBootstrap::getSessionDir();
if (is_dir($sessionDir) && $currentSid !== '') {
    $ownFile = $sessionDir . '/sess_' . preg_replace('/[^a-zA-Z0-9,-]/', '', $currentSid);
    if (is_file($ownFile)) {
        @unlink($ownFile);
    }
}

header('Location: login.php?cleared=1');
exit;
