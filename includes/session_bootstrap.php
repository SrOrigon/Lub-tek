<?php
/**
 * Inicialização de sessão — deve rodar ANTES de qualquer header HTTP.
 */
class SessionBootstrap
{
    private static $timeout = 7200;

    public static function ensure(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) self::$timeout);

        $sessionDir = __DIR__ . '/../data/sessions';
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0755, true);
        }
        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            session_save_path($sessionDir);
        } else {
            // Fallback quando data/sessions não é gravável no servidor (ex.: Hostinger)
            $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'lubtek_sessions';
            if (!is_dir($fallback)) {
                @mkdir($fallback, 0700, true);
            }
            if (is_dir($fallback) && is_writable($fallback)) {
                session_save_path($fallback);
            }
        }

        // Só confia em headers de proxy (X-Forwarded-*) quando TRUSTED_PROXY estiver habilitado,
        // igual ao critério usado em SecurityGuard::getClientIp() — evita spoofing pelo cliente.
        $trustProxy = (defined('TRUSTED_PROXY') && TRUSTED_PROXY) || (getenv('TRUSTED_PROXY') === '1');
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || ($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || ($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function getSessionDir(): string
    {
        return __DIR__ . '/../data/sessions';
    }
}
