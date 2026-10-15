<?php
/**
 * LUB-TEK — CSRF Protection Guard
 * Gera e valida tokens anti-CSRF para requisições com sessão ativa.
 */
class CsrfGuard
{
    /**
     * Retorna o token CSRF da sessão atual (cria um se ainda não existir).
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida o token recebido contra o armazenado na sessão.
     * Aceita header X-CSRF-TOKEN, X-CSRF-Token ou campo POST 'csrf_token'.
     */
    public static function validate(?string $token = null): bool
    {
        // session_write_close() deixa status NONE, mas $_SESSION permanece legível.
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $_SERVER['HTTP_X_XSRF_TOKEN']
                ?? $_POST['csrf_token']
                ?? '';
        }

        return is_string($token) && $token !== '' && hash_equals($expected, $token);
    }
}
