<?php
/**
 * Logger JSON estruturado (tenant + request).
 */
class Logger
{
    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('error', $event, $context);
    }

    private static function write(string $level, string $event, array $context): void
    {
        $tenant = null;
        try {
            if (class_exists('TenantResolver')) {
                $tenant = TenantResolver::getCurrentTenant();
            }
        } catch (Throwable $e) {
            $tenant = null;
        }

        $payload = [
            'ts' => date('c'),
            'level' => $level,
            'event' => $event,
            'tenant' => $tenant,
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? substr(bin2hex(random_bytes(8)), 0, 16),
            'context' => $context,
        ];
        error_log(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
