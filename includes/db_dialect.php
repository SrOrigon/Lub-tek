<?php
/**
 * Helpers de SQL compatíveis SQLite / MySQL.
 */
class DbDialect
{
    public static function isMysql(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    /** Concatenação de expressões SQL (não escapa — use só literais/colunas fixas). */
    public static function concat(PDO $pdo, array $parts): string
    {
        if (self::isMysql($pdo)) {
            return 'CONCAT(' . implode(', ', $parts) . ')';
        }
        return implode(' || ', $parts);
    }

    public static function placeholdersIn(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
        if ($ids === []) {
            return ['', []];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return [$ph, $ids];
    }
}
