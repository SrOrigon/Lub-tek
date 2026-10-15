<?php
/**
 * LUB-TEK - Idempotency Guard
 * Previne execuções e apontamentos duplicados em reconexões de rede no chão de fábrica.
 */

class IdempotencyGuard
{
    private static $tableEnsured = false;

    /**
     * Garante a existência da tabela de controle de idempotência.
     */
    public static function ensureTable(PDO $pdo): void
    {
        if (self::$tableEnsured) {
            return;
        }

        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS idempotency_log (
                    idempotency_key VARCHAR(191) PRIMARY KEY,
                    scope VARCHAR(64) NOT NULL DEFAULT 'default',
                    response_json MEDIUMTEXT NOT NULL,
                    created_at INT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS idempotency_log (
                    idempotency_key TEXT PRIMARY KEY,
                    scope TEXT NOT NULL DEFAULT 'default',
                    response_json TEXT NOT NULL,
                    created_at INTEGER NOT NULL
                )");
            }
            self::$tableEnsured = true;
        } catch (Throwable $e) {
            error_log('[IdempotencyGuard] Falha ao verificar tabela: ' . $e->getMessage());
        }
    }

    /**
     * Executa uma ação protegida por chave de idempotência.
     * Se a chave já tiver sido processada na janela de TTL, retorna o resultado anterior sem reprocessar.
     *
     * @param PDO $pdo Conexão PDO com o banco
     * @param string|null $key Chave única enviada pelo cliente (UUID / client_request_id)
     * @param string $scope Escopo da operação (ex: 'orders.save_task')
     * @param callable $callback Função que executa a operação real
     * @param int $ttlSeconds Tempo de validade do replay (padrão: 600 segundos / 10 minutos)
     * @return array Resposta resultante
     */
    public static function run(PDO $pdo, ?string $key, string $scope, callable $callback, int $ttlSeconds = 600): array
    {
        $key = trim((string) $key);
        if ($key === '') {
            return $callback();
        }

        self::ensureTable($pdo);

        $now = time();
        $scopedKey = $scope . ':' . $key;

        // 1. Verifica se já existe registro válido
        try {
            $stmt = $pdo->prepare("SELECT response_json, created_at FROM idempotency_log WHERE idempotency_key = ?");
            $stmt->execute([$scopedKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $age = $now - (int) ($row['created_at'] ?? 0);
                if ($age >= 0 && $age <= $ttlSeconds) {
                    $decoded = json_decode($row['response_json'], true);
                    if (is_array($decoded)) {
                        $decoded['idempotent_replay'] = true;
                        $decoded['replayed_at'] = date('Y-m-d H:i:s');
                        return $decoded;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[IdempotencyGuard] Erro ao consultar chave: ' . $e->getMessage());
        }

        // 2. Executa a operação real
        $result = $callback();

        // 3. Persiste o resultado
        try {
            $json = json_encode($result, JSON_UNESCAPED_UNICODE);
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'mysql') {
                $saveStmt = $pdo->prepare("INSERT INTO idempotency_log (idempotency_key, scope, response_json, created_at)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), created_at = VALUES(created_at)");
            } else {
                $saveStmt = $pdo->prepare("INSERT OR REPLACE INTO idempotency_log (idempotency_key, scope, response_json, created_at)
                    VALUES (?, ?, ?, ?)");
            }
            $saveStmt->execute([$scopedKey, $scope, $json, $now]);

            // Limpeza oportunista (1 em cada 20 requisições)
            if (mt_rand(1, 20) === 1) {
                $cutoff = $now - 86400; // Remove registros com mais de 24h
                $cleanStmt = $pdo->prepare("DELETE FROM idempotency_log WHERE created_at < ?");
                $cleanStmt->execute([$cutoff]);
            }
        } catch (Throwable $e) {
            error_log('[IdempotencyGuard] Erro ao salvar chave: ' . $e->getMessage());
        }

        return $result;
    }
}
