<?php
/**
 * Cache persistente da IA (Gemini) e revisão da planta.
 * Evita gastar tokens enquanto ativos, O.S. e catálogo não mudarem.
 */

class AiCache
{
    const DEFAULT_TTL = 21600; // 6 horas
    const JSON_TTL = 14400;    // 4 horas (insights estruturados)
    const ASSISTANT_TTL = 43200; // 12 horas
    const MAX_FILES = 500;

    public static function makeKey(string $namespace, ...$parts): string
    {
        $raw = $namespace . "\n" . implode("\n", array_map(static function ($p) {
            if (is_array($p) || is_object($p)) {
                return json_encode($p, JSON_UNESCAPED_UNICODE);
            }
            return (string) $p;
        }, $parts));

        return hash('sha256', $raw);
    }

    public static function get(string $key): ?array
    {
        $path = self::filePath($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $row = json_decode($raw, true);
        if (!is_array($row) || empty($row['expires_at']) || (int) $row['expires_at'] < time()) {
            @unlink($path);
            return null;
        }

        $payload = $row['payload'] ?? null;
        return is_array($payload) ? $payload : null;
    }

    public static function set(string $key, array $payload, int $ttl = self::DEFAULT_TTL): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $row = [
            'created_at' => time(),
            'expires_at' => time() + max(60, $ttl),
            'payload' => $payload,
        ];

        $path = self::filePath($key);
        $tmp = $path . '.tmp';
        $json = json_encode($row, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
            @rename($tmp, $path);
        } else {
            @unlink($tmp);
        }

        self::maybePrune($dir);
    }

    /**
     * Revisão barata do estado operacional (sem telemetria PI).
     * Muda só quando ativos, O.S., catálogo ou análises mudam.
     */
    public static function plantRevision($pdo): string
    {
        $bits = [
            self::scalar($pdo, "SELECT COUNT(*) || ':' || COALESCE(MAX(id),0) || ':' || COALESCE(SUM(length(COALESCE(dados_tecnicos,''))),0) || ':' || COALESCE(SUM(length(COALESCE(status,''))),0) FROM ativos"),
            self::scalar($pdo, "SELECT COUNT(*) || ':' || COALESCE(MAX(id),0) || ':' || COALESCE(MAX(last_sync),'') || ':' || SUM(CASE WHEN situacao = 'Concluído' THEN 1 ELSE 0 END) FROM ordens"),
            self::scalar($pdo, "SELECT COUNT(*) || ':' || COALESCE(MAX(id),0) || ':' || COALESCE(SUM(length(COALESCE(specs,''))),0) || ':' || COALESCE(SUM(estoque_atual),0) FROM catalogo"),
            self::scalar($pdo, "SELECT COUNT(*) || ':' || COALESCE(MAX(id),0) || ':' || COALESCE(SUM(quantidade),0) FROM planos"),
            self::scalar($pdo, "SELECT COUNT(*) || ':' || COALESCE(MAX(id),0) || ':' || COALESCE(SUM(price),0) FROM mercado_ofertas"),
            self::scalar($pdo, "SELECT COUNT(*) || ':' || COALESCE(MAX(id),0) FROM analises"),
        ];

        return hash('sha256', implode('|', $bits));
    }

    /**
     * Revisão da telemetria PI — isolada para o simulador não invalidar o restante.
     */
    public static function piRevision($pdo): string
    {
        $bits = [
            self::scalar($pdo, "SELECT COUNT(*) || ':' || COALESCE(MAX(id),0) FROM pi_tags"),
            self::scalar($pdo, "SELECT COUNT(*) || ':' || COALESCE(MAX(id),0) FROM pi_telemetry"),
        ];

        return hash('sha256', implode('|', $bits));
    }

    private static function scalar($pdo, string $sql): string
    {
        try {
            $v = $pdo->query($sql)->fetchColumn();
            return (string) ($v ?? '0');
        } catch (Throwable $e) {
            return '0';
        }
    }

    private static function tenantSlug(): string
    {
        $tenant = $_SESSION['tenant'] ?? null;
        if (is_string($tenant) && preg_match('/^[a-z0-9-]+$/', $tenant)) {
            return $tenant;
        }
        return 'default';
    }

    private static function dir(): string
    {
        $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ai_cache' . DIRECTORY_SEPARATOR . self::tenantSlug();
        return $root;
    }

    private static function filePath(string $key): string
    {
        $safe = preg_replace('/[^a-f0-9]/', '', strtolower($key));
        if (strlen($safe) < 16) {
            $safe = hash('sha256', $key);
        }
        return self::dir() . DIRECTORY_SEPARATOR . $safe . '.json';
    }

    private static function maybePrune(string $dir): void
    {
        static $lastPrune = 0;
        if ((time() - $lastPrune) < 120) {
            return;
        }
        $lastPrune = time();

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
        if (!is_array($files) || count($files) <= self::MAX_FILES) {
            return;
        }

        $now = time();
        $meta = [];
        foreach ($files as $file) {
            $mtime = @filemtime($file) ?: 0;
            $expired = false;
            $raw = @file_get_contents($file);
            $row = $raw ? json_decode($raw, true) : null;
            if (is_array($row) && !empty($row['expires_at']) && (int) $row['expires_at'] < $now) {
                @unlink($file);
                $expired = true;
            }
            if (!$expired) {
                $meta[] = ['file' => $file, 'mtime' => $mtime];
            }
        }

        usort($meta, static function ($a, $b) {
            return $a['mtime'] <=> $b['mtime'];
        });

        $overflow = count($meta) - self::MAX_FILES;
        for ($i = 0; $i < $overflow; $i++) {
            @unlink($meta[$i]['file']);
        }
    }
}
