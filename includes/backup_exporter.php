<?php
/**
 * Exportação off-site de backups SQLite
 *
 * Configure em config.local.php (não versionado) ou variáveis de ambiente:
 *
 * BACKUP_EXPORT_DIR  — pasta externa/local
 * BACKUP_EMAIL       — e-mail com anexo (bancos < 10MB)
 * BACKUP_FTP_*       — envio FTP/FTPS para outro servidor
 * BACKUP_S3_*        — AWS S3, Cloudflare R2, Backblaze B2, MinIO
 */

require_once __DIR__ . '/backup_s3_client.php';

class BackupExporter
{
    private const MAX_EMAIL_BYTES = 10 * 1024 * 1024;

    /**
     * Copia backup para destinos externos configurados.
     *
     * @return array{
     *   copied: bool,
     *   emailed: bool,
     *   ftp: bool,
     *   s3: bool,
     *   messages: string[]
     * }
     */
    public static function export(string $backupFilePath, string $label): array
    {
        $result = [
            'copied' => false,
            'emailed' => false,
            'ftp' => false,
            's3' => false,
            'messages' => [],
        ];

        if (!is_file($backupFilePath)) {
            $result['messages'][] = 'Arquivo de backup não encontrado.';
            return $result;
        }

        $filename = basename($backupFilePath);
        $remoteName = $label . '/' . $filename;

        $exportDir = self::cfg('BACKUP_EXPORT_DIR');
        if ($exportDir !== '') {
            $result = self::mergeResult($result, self::exportToLocalDir($backupFilePath, $exportDir));
        }

        $email = self::cfg('BACKUP_EMAIL');
        if ($email !== '') {
            $result = self::mergeResult($result, self::exportToEmail($backupFilePath, $label, $email));
        }

        if (self::ftpConfigured()) {
            $result = self::mergeResult($result, self::exportToFtp($backupFilePath, $remoteName));
        }

        if (self::s3Configured()) {
            $result = self::mergeResult($result, self::exportToS3($backupFilePath, $remoteName));
        }

        if (!$result['copied'] && !$result['emailed'] && !$result['ftp'] && !$result['s3']) {
            if ($exportDir === '' && $email === '' && !self::ftpConfigured() && !self::s3Configured()) {
                $result['messages'][] = 'Nenhum destino off-site configurado (BACKUP_EXPORT_DIR, BACKUP_EMAIL, BACKUP_FTP_*, BACKUP_S3_*).';
            }
        }

        return $result;
    }

    /**
     * Testa conectividade FTP/S3 sem gerar backup (CLI: php backup_db.php --test-export)
     *
     * @return array{ok: bool, messages: string[]}
     */
    public static function testConnections(): array
    {
        $messages = [];
        $ok = true;

        if (self::ftpConfigured()) {
            try {
                $conn = self::connectFtp();
                if ($conn) {
                    ftp_close($conn);
                    $messages[] = '[FTP] Conexão OK — ' . self::cfg('BACKUP_FTP_HOST');
                }
            } catch (Exception $e) {
                $ok = false;
                $messages[] = '[FTP] Falha: ' . $e->getMessage();
            }
        } else {
            $messages[] = '[FTP] Não configurado.';
        }

        if (self::s3Configured()) {
            try {
                $client = self::buildS3Client();
                $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lubtek_s3_test_' . uniqid('', true) . '.txt';
                file_put_contents($tmp, 'lubtek-backup-test');
                $client->putObject('lubtek/_connection_test.txt', $tmp);
                @unlink($tmp);
                $messages[] = '[S3] Upload de teste OK — bucket ' . self::cfg('BACKUP_S3_BUCKET');
            } catch (Exception $e) {
                $ok = false;
                $messages[] = '[S3] Falha: ' . $e->getMessage();
            }
        } else {
            $messages[] = '[S3] Não configurado.';
        }

        return ['ok' => $ok, 'messages' => $messages];
    }

    private static function exportToLocalDir(string $filePath, string $exportDir): array
    {
        $result = ['copied' => false, 'emailed' => false, 'ftp' => false, 's3' => false, 'messages' => []];

        if (!is_dir($exportDir)) {
            @mkdir($exportDir, 0755, true);
        }
        if (!is_dir($exportDir) || !is_writable($exportDir)) {
            $result['messages'][] = 'BACKUP_EXPORT_DIR não gravável.';
            return $result;
        }

        $dest = rtrim($exportDir, '/\\') . DIRECTORY_SEPARATOR . basename($filePath);
        if (@copy($filePath, $dest)) {
            $result['copied'] = true;
            $result['messages'][] = "Cópia local externa: $dest";
        } else {
            $result['messages'][] = 'Falha ao copiar para BACKUP_EXPORT_DIR.';
        }

        return $result;
    }

    private static function exportToEmail(string $filePath, string $label, string $email): array
    {
        $result = ['copied' => false, 'emailed' => false, 'ftp' => false, 's3' => false, 'messages' => []];

        $size = @filesize($filePath);
        if ($size === false) {
            $result['messages'][] = 'Falha ao ler tamanho do backup — e-mail ignorado.';
            return $result;
        }
        if ($size > self::MAX_EMAIL_BYTES) {
            $result['messages'][] = 'Backup > 10MB — e-mail ignorado (use FTP ou S3).';
            return $result;
        }

        if (self::sendBackupEmail($email, $filePath, $label)) {
            $result['emailed'] = true;
            $result['messages'][] = "E-mail enviado para $email";
        } else {
            $result['messages'][] = 'Falha ao enviar e-mail (verifique mail() do servidor).';
        }

        return $result;
    }

    private static function exportToFtp(string $filePath, string $remoteName): array
    {
        $result = ['copied' => false, 'emailed' => false, 'ftp' => false, 's3' => false, 'messages' => []];

        try {
            $conn = self::connectFtp();
            $remotePath = self::ftpRemotePath($remoteName);

            self::ftpEnsureDir($conn, dirname(str_replace('\\', '/', $remotePath)));

            if (@ftp_put($conn, $remotePath, $filePath, FTP_BINARY)) {
                $result['ftp'] = true;
                $result['messages'][] = 'FTP enviado: ' . self::cfg('BACKUP_FTP_HOST') . $remotePath;
            } else {
                $result['messages'][] = 'Falha no upload FTP (ftp_put).';
            }

            ftp_close($conn);
        } catch (Exception $e) {
            $result['messages'][] = 'FTP: ' . $e->getMessage();
        }

        return $result;
    }

    private static function exportToS3(string $filePath, string $remoteName): array
    {
        $result = ['copied' => false, 'emailed' => false, 'ftp' => false, 's3' => false, 'messages' => []];

        try {
            $prefix = trim(self::cfg('BACKUP_S3_PREFIX'), '/');
            $key = ($prefix !== '' ? $prefix . '/' : '') . $remoteName;

            $client = self::buildS3Client();
            $client->putObject($key, $filePath);

            $result['s3'] = true;
            $result['messages'][] = 'S3 enviado: s3://' . self::cfg('BACKUP_S3_BUCKET') . '/' . $key;
        } catch (Exception $e) {
            $result['messages'][] = 'S3: ' . $e->getMessage();
        }

        return $result;
    }

    private static function connectFtp()
    {
        $host = self::cfg('BACKUP_FTP_HOST');
        $user = self::cfg('BACKUP_FTP_USER');
        $pass = self::cfg('BACKUP_FTP_PASS');
        $port = (int) (self::cfg('BACKUP_FTP_PORT') ?: '21');
        $useSsl = filter_var(self::cfg('BACKUP_FTP_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN);

        if ($host === '' || $user === '') {
            throw new RuntimeException('BACKUP_FTP_HOST e BACKUP_FTP_USER são obrigatórios.');
        }

        $conn = $useSsl
            ? @ftp_ssl_connect($host, $port, 30)
            : @ftp_connect($host, $port, 30);

        if (!$conn) {
            throw new RuntimeException('Não foi possível conectar ao servidor FTP.');
        }

        if (!@ftp_login($conn, $user, $pass)) {
            ftp_close($conn);
            throw new RuntimeException('Login FTP rejeitado (usuário/senha).');
        }

        @ftp_pasv($conn, true);

        return $conn;
    }

    private static function ftpRemotePath(string $remoteName): string
    {
        $base = str_replace('\\', '/', self::cfg('BACKUP_FTP_PATH'));
        $base = rtrim($base, '/');
        $remoteName = str_replace('\\', '/', $remoteName);

        return ($base !== '' ? $base . '/' : '/') . ltrim($remoteName, '/');
    }

    /**
     * Cria diretórios remotos recursivamente (ex: /backups/cocacola/)
     */
    private static function ftpEnsureDir($conn, string $dir): void
    {
        $dir = str_replace('\\', '/', $dir);
        $parts = array_filter(explode('/', ltrim($dir, '/')));
        $path = '';
        foreach ($parts as $part) {
            $path .= '/' . $part;
            @ftp_mkdir($conn, $path);
        }
    }

    private static function buildS3Client(): BackupS3Client
    {
        return new BackupS3Client(
            self::cfg('BACKUP_S3_ACCESS_KEY'),
            self::cfg('BACKUP_S3_SECRET_KEY'),
            self::cfg('BACKUP_S3_BUCKET'),
            self::cfg('BACKUP_S3_REGION') ?: 'us-east-1',
            self::cfg('BACKUP_S3_ENDPOINT'),
            filter_var(self::cfg('BACKUP_S3_PATH_STYLE') ?: 'false', FILTER_VALIDATE_BOOLEAN)
        );
    }

    private static function ftpConfigured(): bool
    {
        return self::cfg('BACKUP_FTP_HOST') !== '' && self::cfg('BACKUP_FTP_USER') !== '';
    }

    private static function s3Configured(): bool
    {
        return self::cfg('BACKUP_S3_BUCKET') !== ''
            && self::cfg('BACKUP_S3_ACCESS_KEY') !== ''
            && self::cfg('BACKUP_S3_SECRET_KEY') !== '';
    }

    private static function cfg(string $name, string $default = ''): string
    {
        if (defined($name)) {
            $value = constant($name);
            return is_string($value) ? $value : (string) $value;
        }

        $env = getenv($name);
        return $env !== false ? $env : $default;
    }

    /**
     * @param array{copied: bool, emailed: bool, ftp: bool, s3: bool, messages: string[]} $base
     * @param array{copied: bool, emailed: bool, ftp: bool, s3: bool, messages: string[]} $add
     * @return array{copied: bool, emailed: bool, ftp: bool, s3: bool, messages: string[]}
     */
    private static function mergeResult(array $base, array $add): array
    {
        $base['copied'] = $base['copied'] || $add['copied'];
        $base['emailed'] = $base['emailed'] || $add['emailed'];
        $base['ftp'] = $base['ftp'] || $add['ftp'];
        $base['s3'] = $base['s3'] || $add['s3'];
        $base['messages'] = array_merge($base['messages'], $add['messages']);
        return $base;
    }

    private static function sendBackupEmail(string $to, string $filePath, string $label): bool
    {
        $filename = basename($filePath);
        $boundary = '=_LUBTEK_' . md5((string) microtime(true));
        $subject = '[LUB-TEK] Backup ' . $label . ' — ' . date('Y-m-d H:i');
        $body = "Backup automático LUB-TEK\nTenant: $label\nData: " . date('c') . "\n";
        $fileData = file_get_contents($filePath);
        if ($fileData === false) {
            return false;
        }

        $headers = [
            'From: LUB-TEK Backup <noreply@' . (gethostname() ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $body . "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
        $message .= chunk_split(base64_encode($fileData)) . "\r\n";
        $message .= "--$boundary--";

        return @mail($to, $subject, $message, implode("\r\n", $headers));
    }

    /**
     * Atualiza o registro off-site do último manifesto de um arquivo.
     */
    public static function updateManifestOffsite(string $backupDir, string $filePath, array $export): void
    {
        $manifestPath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!file_exists($manifestPath)) {
            return;
        }

        $raw = @file_get_contents($manifestPath);
        if ($raw === false) {
            error_log("LUB-TEK BackupExporter: falha ao ler manifest.json em $manifestPath (permissão negada?)");
            return;
        }

        $entries = json_decode($raw, true);
        if (!is_array($entries)) {
            return;
        }

        $basename = basename($filePath);
        for ($i = count($entries) - 1; $i >= 0; $i--) {
            if (($entries[$i]['file'] ?? '') === $basename) {
                $entries[$i]['offsite'] = [
                    'local' => !empty($export['copied']),
                    'email' => !empty($export['emailed']),
                    'ftp' => !empty($export['ftp']),
                    's3' => !empty($export['s3']),
                ];
                if (@file_put_contents($manifestPath, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
                    error_log("LUB-TEK BackupExporter: falha ao gravar manifest.json em $manifestPath (disco cheio ou permissão negada?)");
                }
                return;
            }
        }
    }

    /**
     * Registra manifesto JSON dos backups (auditoria / recuperação).
     */
    public static function appendManifest(string $backupDir, string $filePath, string $label, string $method): void
    {
        $manifestPath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json';
        $entries = [];
        if (file_exists($manifestPath)) {
            $raw = @file_get_contents($manifestPath);
            if ($raw === false) {
                error_log("LUB-TEK BackupExporter: falha ao ler manifest.json em $manifestPath (permissão negada?)");
            } else {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $entries = $decoded;
                }
            }
        }

        $entries[] = [
            'file' => basename($filePath),
            'tenant' => $label,
            'method' => $method,
            'size' => is_file($filePath) ? filesize($filePath) : 0,
            'sha256' => is_file($filePath) ? hash_file('sha256', $filePath) : null,
            'created_at' => date('c'),
            'offsite' => [
                'local' => false,
                'email' => false,
                'ftp' => false,
                's3' => false,
            ],
        ];

        if (count($entries) > 500) {
            $entries = array_slice($entries, -500);
        }

        if (@file_put_contents($manifestPath, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            error_log("LUB-TEK BackupExporter: falha ao gravar manifest.json em $manifestPath (disco cheio ou permissão negada?)");
        }
    }
}
