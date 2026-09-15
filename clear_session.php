<?php
/**
 * CLEAR SESSION — limpeza manual de sessão (uso interno / suporte)
 */
require_once __DIR__ . '/includes/session_bootstrap.php';

SessionBootstrap::ensure();

// Guarda o id da sessão ATUAL antes de destruí-la — nunca apagar sessões de outros usuários.
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

// Remove apenas o arquivo da sessão ATUAL (nunca todas as sessões do sistema —
// isso derrubaria todos os usuários/tenants logados de uma vez).
$sessionDir = SessionBootstrap::getSessionDir();
if (is_dir($sessionDir) && $currentSid !== '') {
    $ownFile = $sessionDir . '/sess_' . $currentSid;
    if (is_file($ownFile)) {
        @unlink($ownFile);
    }
}

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>
<html lang=\"pt-BR\">
<head>
    <meta charset=\"UTF-8\">
    <title>Sessão Limpa</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: white; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .container { text-align: center; background: #1e293b; padding: 40px; border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        h1 { color: #10b981; margin-bottom: 20px; }
        .btn { background: #0ea5e9; color: white; padding: 15px 30px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn:hover { background: #0284c7; }
    </style>
</head>
<body>
    <div class=\"container\">
        <h1>Sessão Limpa</h1>
        <p>Todas as sessões foram destruídas.</p>
        <p>Redirecionando para o login em 3 segundos...</p>
        <a href=\"login.php\" class=\"btn\">Ir para Login</a>
    </div>
    <script>setTimeout(() => { window.location.href = 'login.php'; }, 3000);</script>
</body>
</html>";
