<?php
/**
 * LUB-TEK - Integration Queue (ERP / SAP Resiliente)
 * Desacopla chamadas externas síncronas do fluxo operacional de campo.
 */

class IntegrationQueue
{
    private static $tableEnsured = false;

    public static function ensureTable(PDO $pdo): void
    {
        if (self::$tableEnsured) {
            return;
        }

        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS integration_queue (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    external_system VARCHAR(32) NOT NULL DEFAULT 'SAP',
                    action VARCHAR(64) NOT NULL,
                    reference_id VARCHAR(128) NULL,
                    payload MEDIUMTEXT NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                    attempts INT NOT NULL DEFAULT 0,
                    max_attempts INT NOT NULL DEFAULT 3,
                    error_message TEXT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    processed_at DATETIME NULL,
                    INDEX idx_integration_status (status, external_system),
                    INDEX idx_integration_ref (reference_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS integration_queue (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    external_system TEXT NOT NULL DEFAULT 'SAP',
                    action TEXT NOT NULL,
                    reference_id TEXT NULL,
                    payload TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'PENDING',
                    attempts INTEGER NOT NULL DEFAULT 0,
                    max_attempts INTEGER NOT NULL DEFAULT 3,
                    error_message TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    processed_at TEXT NULL
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_integration_status ON integration_queue (status, external_system)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_integration_ref ON integration_queue (reference_id)");
            }
            self::$tableEnsured = true;
        } catch (Throwable $e) {
            error_log('[IntegrationQueue] Erro ao verificar tabela: ' . $e->getMessage());
        }
    }

    /**
     * Enfileira uma mensagem para integração assíncrona com ERP / SAP.
     */
    public static function enqueue(PDO $pdo, string $system, string $action, array $payload, ?string $referenceId = null): int
    {
        self::ensureTable($pdo);
        $now = date('Y-m-d H:i:s');
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("INSERT INTO integration_queue 
            (external_system, action, reference_id, payload, status, attempts, max_attempts, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'PENDING', 0, 3, ?, ?)");
        $stmt->execute([
            strtoupper(trim($system)),
            trim($action),
            $referenceId ? trim($referenceId) : null,
            $jsonPayload,
            $now,
            $now
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Retorna itens da fila para visualização / monitoramento.
     */
    public static function getQueue(PDO $pdo, ?string $status = null, int $limit = 50): array
    {
        self::ensureTable($pdo);
        $sql = "SELECT * FROM integration_queue";
        $params = [];

        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = strtoupper(trim($status));
        }

        $sql .= " ORDER BY id DESC LIMIT " . max(1, min(200, $limit));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['payload'] = json_decode($r['payload'] ?: '{}', true);
        }

        return $rows;
    }

    /**
     * Reprocessa um item que falhou (Retry manual).
     */
    public static function retryItem(PDO $pdo, int $id): bool
    {
        self::ensureTable($pdo);
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE integration_queue 
            SET status = 'PENDING', error_message = NULL, updated_at = ?, max_attempts = max_attempts + 2 
            WHERE id = ?");
        return $stmt->execute([$now, $id]);
    }

    /**
     * Processa itens pendentes da fila (simulando ou enviando para gateway ERP com timeout seguro).
     */
    public static function processPending(PDO $pdo, int $limit = 10, ?callable $customDispatcher = null): array
    {
        self::ensureTable($pdo);
        $stmt = $pdo->prepare("SELECT * FROM integration_queue 
            WHERE status IN ('PENDING', 'RETRY') AND attempts < max_attempts 
            ORDER BY id ASC LIMIT ?");
        $stmt->execute([max(1, min(50, $limit))]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;
        $failed = 0;
        $results = [];

        foreach ($items as $item) {
            $id = (int) $item['id'];
            $attempts = (int) $item['attempts'] + 1;
            $now = date('Y-m-d H:i:s');

            // Marca como PROCESSING
            $pdo->prepare("UPDATE integration_queue SET status = 'PROCESSING', attempts = ?, updated_at = ? WHERE id = ?")
                ->execute([$attempts, $now, $id]);

            try {
                $payload = json_decode($item['payload'] ?: '{}', true);

                if (is_callable($customDispatcher)) {
                    $customDispatcher($item['external_system'], $item['action'], $payload);
                } else {
                    // Dispatcher padrão interno LUB-TEK -> SAP
                    self::defaultDispatch($item['external_system'], $item['action'], $payload);
                }

                // Sucesso
                $pdo->prepare("UPDATE integration_queue SET status = 'SUCCESS', processed_at = ?, updated_at = ?, error_message = NULL WHERE id = ?")
                    ->execute([$now, $now, $id]);
                $processed++;
                $results[] = ['id' => $id, 'status' => 'SUCCESS'];
            } catch (Throwable $e) {
                $failed++;
                $errMsg = substr($e->getMessage(), 0, 500);
                $newStatus = ($attempts >= (int) $item['max_attempts']) ? 'FAILED' : 'RETRY';

                $pdo->prepare("UPDATE integration_queue SET status = ?, error_message = ?, updated_at = ? WHERE id = ?")
                    ->execute([$newStatus, $errMsg, $now, $id]);

                $results[] = ['id' => $id, 'status' => $newStatus, 'error' => $errMsg];
            }
        }

        return [
            'total' => count($items),
            'processed' => $processed,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Dispatcher padrão com simulação resiliente ou envio via cURL com timeout curto de 5s.
     */
    private static function defaultDispatch(string $system, string $action, array $payload): void
    {
        $erpWebhook = getenv('ERP_WEBHOOK_URL') ?: (defined('ERP_WEBHOOK_URL') ? ERP_WEBHOOK_URL : null);

        if ($erpWebhook && filter_var($erpWebhook, FILTER_VALIDATE_URL)) {
            $ch = curl_init($erpWebhook);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'system' => $system,
                    'action' => $action,
                    'timestamp' => date('c'),
                    'payload' => $payload
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5, // Timeout estrito de 5 segundos
                CURLOPT_CONNECTTIMEOUT => 3
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err !== '') {
                throw new Exception("Timeout/Falha de rede com ERP: " . $err);
            }
            if ($httpCode >= 400) {
                throw new Exception("ERP retornou HTTP " . $httpCode . ": " . substr((string) $resp, 0, 150));
            }
        }
        // Se webhook não configurado, simula sucesso para fila interna do PCM
    }
}
