<?php
/**
 * Migrações aditivas versionadas (só CLI / forceMigration).
 * Nunca DROP COLUMN / DROP TABLE de dados de produção.
 */
class SchemaMigrations
{
    public static function apply(PDO $pdo): array
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            filename TEXT PRIMARY KEY,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'migrations';
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        $done = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $done = array_flip($done ?: []);
        $applied = [];

        foreach ($files as $path) {
            $name = basename($path);
            if (isset($done[$name])) {
                continue;
            }
            $fn = require $path;
            if (is_callable($fn)) {
                $fn($pdo);
            }
            $ins = $pdo->prepare('INSERT INTO schema_migrations (filename, applied_at) VALUES (?, datetime(\'now\'))');
            $ins->execute([$name]);
            $applied[] = $name;
        }

        return $applied;
    }
}
