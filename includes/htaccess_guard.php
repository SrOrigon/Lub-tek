<?php
/**
 * Gera .htaccess de negação total (Apache 2.2 + 2.4 + LiteSpeed)
 */
class HtaccessGuard
{
    public static function denyAllContent(): string
    {
        return <<<'HTACCESS'
# LUB-TEK — Acesso HTTP negado (proteção de bancos SQLite e backups)
Require all denied

<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>

Options -Indexes

# Bloqueia download direto de SQLite mesmo se regras acima falharem
<FilesMatch "\.(sqlite|sqlite-wal|sqlite-shm|db)$">
    Require all denied
</FilesMatch>
HTACCESS;
    }

    public static function writeDenyAll(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            error_log("LUB-TEK HtaccessGuard: falha ao criar diretório $directory (permissão negada?)");
            return;
        }
        $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
        // Não confia apenas na existência do arquivo: se estiver vazio/corrompido
        // (ex: gravação anterior interrompida por disco cheio), a proteção
        // silenciosamente deixaria de existir. Regrava sempre que o conteúdo
        // de proteção esperado não estiver presente.
        $needsWrite = true;
        if (is_file($path)) {
            $current = @file_get_contents($path);
            $needsWrite = ($current === false || $current === '' || strpos($current, 'Require all denied') === false);
        }
        if ($needsWrite && @file_put_contents($path, self::denyAllContent(), LOCK_EX) === false) {
            error_log("LUB-TEK HtaccessGuard: falha ao gravar $path (disco cheio ou permissão negada?)");
        }
    }
}
