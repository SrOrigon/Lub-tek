<?php
/**
 * Índice central (master DB): e-mail → tenant_slug para login sem varrer todos os SQLite.
 */
class TenantLoginIndex
{
    public static function ensureTable(PDO $masterPdo): void
    {
        $masterPdo->exec("CREATE TABLE IF NOT EXISTS tenant_login_index (
            email_lower TEXT PRIMARY KEY,
            tenant_slug TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function upsert(string $email, string $tenantSlug): void
    {
        if (strpos($email, '@') === false) {
            return;
        }
        $normalized = self::normalizeEmail($email);
        if ($normalized === '' || $tenantSlug === '') {
            return;
        }
        $pdo = DB::getMaster();
        self::ensureTable($pdo);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare(
                'INSERT INTO tenant_login_index (email_lower, tenant_slug, updated_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE tenant_slug = VALUES(tenant_slug), updated_at = NOW()'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT OR REPLACE INTO tenant_login_index (email_lower, tenant_slug, updated_at)
                 VALUES (?, ?, datetime(\'now\'))'
            );
        }
        $stmt->execute([$normalized, $tenantSlug]);
    }

    public static function remove(string $email): void
    {
        if (strpos($email, '@') === false) {
            return;
        }
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return;
        }
        $pdo = DB::getMaster();
        self::ensureTable($pdo);
        $stmt = $pdo->prepare('DELETE FROM tenant_login_index WHERE email_lower = ?');
        $stmt->execute([$normalized]);
    }

    public static function lookupTenant(string $email): ?string
    {
        if (strpos($email, '@') === false) {
            return null;
        }
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }
        $pdo = DB::getMaster();
        self::ensureTable($pdo);
        $stmt = $pdo->prepare('SELECT tenant_slug FROM tenant_login_index WHERE email_lower = ? LIMIT 1');
        $stmt->execute([$normalized]);
        $slug = $stmt->fetchColumn();
        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
