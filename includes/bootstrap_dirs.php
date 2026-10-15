<?php
/**
 * Garante pastas graváveis e proteção HTTP em diretórios sensíveis.
 */
require_once __DIR__ . '/htaccess_guard.php';

class BootstrapDirs
{
    public static function ensure(): void
    {
        $root = dirname(__DIR__);
        $writable = [
            $root . '/uploads',
            $root . '/backups',
            $root . '/databases',
            $root . '/data/sessions',
            $root . '/data/ai_cache',
        ];

        foreach ($writable as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        HtaccessGuard::writeDenyAll($root . '/backups');
        HtaccessGuard::writeDenyAll($root . '/databases');

        $sessionsDir = $root . '/data/sessions';
        if (is_dir($sessionsDir)) {
            HtaccessGuard::writeDenyAll($sessionsDir);
        }

        $aiCacheDir = $root . '/data/ai_cache';
        if (is_dir($aiCacheDir)) {
            HtaccessGuard::writeDenyAll($aiCacheDir);
        }

        $legacyDb = $root . '/banco de dados';
        if (is_dir($legacyDb)) {
            HtaccessGuard::writeDenyAll($legacyDb);
        }
    }
}
