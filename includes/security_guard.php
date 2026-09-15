<?php
/**
 * LUB-TEK 3.0 — SECURITY GUARD & ANTI-SCRAPING MIDDLEWARE
 * Provides rate limiting, brute force lockout, HTTP security headers, and scraper detection.
 */

class SecurityGuard
{
    private static $attemptsFile = __DIR__ . '/../data/sessions/login_attempts.json';
    private static $maxAttempts = 5;
    private static $lockoutWindow = 900; // 15 minutos em segundos

    /**
     * Aplica Headers de Segurança HTTP contra Iframe, Sniffing, XSS e Scrapers
     */
    public static function applySecurityHeaders()
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // Só confia em X-Forwarded-Proto quando TRUSTED_PROXY estiver habilitado (evita spoofing).
        $trustProxy = (defined('TRUSTED_PROXY') && TRUSTED_PROXY) || (getenv('TRUSTED_PROXY') === '1');
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || ($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        header(
            "Content-Security-Policy: default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com; " .
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; " .
            "img-src 'self' data: blob: https:; " .
            "connect-src 'self' https://generativelanguage.googleapis.com; " .
            "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'"
        );
    }

    /**
     * Bloqueia user-agents conhecidos de scrapers, WebCopy, crawlers não autorizados
     */
    public static function blockScrapers()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $blockedAgents = [
            'webcopy', 'httrack', 'wget', 'teleport', 'offline explorer',
            'website extractor', 'sitesucker', 'scrapy', 'python-urllib',
            'go-http-client', 'java/', 'libwww-perl', 'autoit', 'ahrefsbot',
            'semrushbot', 'mj12bot', 'ezooms', 'seekport', 'xenu', 'nikto',
            'sqlmap', 'nmap', 'dirbuster'
        ];

        $uaLower = strtolower($userAgent);
        foreach ($blockedAgents as $badAgent) {
            if (strpos($uaLower, $badAgent) !== false) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                exit('Acesso negado: ferramenta de scraping/cópia detectada.');
            }
        }
    }

    /**
     * Obtém o IP do cliente.
     * Por padrão usa REMOTE_ADDR. Headers de proxy só com TRUSTED_PROXY=1.
     */
    public static function getClientIp()
    {
        $trustProxy = (defined('TRUSTED_PROXY') && TRUSTED_PROXY)
            || (getenv('TRUSTED_PROXY') === '1');

        if ($trustProxy) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP);
                if ($ip) {
                    return $ip;
                }
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = filter_var(trim($ips[0]), FILTER_VALIDATE_IP);
                if ($ip) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Carrega tentativas de login registradas
     */
    private static function loadAttempts()
    {
        if (!file_exists(self::$attemptsFile)) {
            return [];
        }
        $fp = @fopen(self::$attemptsFile, 'r');
        if (!$fp) {
            return [];
        }
        @flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Salva tentativas de login
     */
    private static function saveAttempts($data)
    {
        $dir = dirname(self::$attemptsFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fp = @fopen(self::$attemptsFile, 'c+');
        if (!$fp) {
            return;
        }
        if (@flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    /**
     * Executa leitura + modificação + escrita do arquivo de tentativas sob um único
     * lock exclusivo (evita "lost update" quando duas requisições concorrentes
     * registram/limpam tentativas ao mesmo tempo).
     */
    private static function mutateAttempts(callable $mutator): void
    {
        $dir = dirname(self::$attemptsFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fp = @fopen(self::$attemptsFile, 'c+');
        if (!$fp) {
            return;
        }
        if (@flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $attempts = json_decode($content, true);
            $attempts = is_array($attempts) ? $attempts : [];

            self::cleanExpiredAttempts($attempts);
            $attempts = $mutator($attempts);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($attempts, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    /**
     * Limpa tentativas expiradas do arquivo
     */
    private static function cleanExpiredAttempts(&$attempts)
    {
        $now = time();
        foreach ($attempts as $key => $records) {
            $filtered = array_filter($records, function ($timestamp) use ($now) {
                return ($now - $timestamp) < self::$lockoutWindow;
            });
            if (empty($filtered)) {
                unset($attempts[$key]);
            } else {
                $attempts[$key] = array_values($filtered);
            }
        }
    }

    /**
     * Verifica se o IP ou usuário está bloqueado por excesso de tentativas (desativado para validação social)
     */
    public static function isLockedOut($username = '')
    {
        return false;
    }

    /**
     * Registra uma falha de login (lockout desativado a pedido do cliente)
     */
    public static function recordFailedAttempt($username = '')
    {
        return;
    }

    /**
     * Reseta as falhas de login após sucesso
     */
    public static function resetFailedAttempts($username = '')
    {
        return;
    }
}

// Execução automática de headers e bloqueio de scrapers na inclusão do arquivo
SecurityGuard::applySecurityHeaders();
SecurityGuard::blockScrapers();
