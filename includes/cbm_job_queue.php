<?php
/**
 * Fila de CBM: o sensor grava telemetria e enfileira o alerta.
 * OS e e-mail rodam depois da resposta HTTP (ou via cron).
 */
class CbmJobQueue
{
    public static function ensureTable(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS cbm_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kind TEXT NOT NULL,
            payload TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            attempts INTEGER NOT NULL DEFAULT 0,
            last_error TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_cbm_jobs_status ON cbm_jobs(status, id)');
    }

    public static function enqueue(PDO $db, string $kind, array $payload): void
    {
        self::ensureTable($db);
        $stmt = $db->prepare(
            "INSERT INTO cbm_jobs (kind, payload, status, created_at) VALUES (?, ?, 'pending', datetime('now'))"
        );
        $stmt->execute([$kind, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]);
    }

    public static function processPending(PDO $db, int $limit = 8): int
    {
        self::ensureTable($db);
        $limit = max(1, min(50, $limit));
        $stmt = $db->prepare(
            "SELECT id, kind, payload, attempts FROM cbm_jobs WHERE status = 'pending' ORDER BY id ASC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $done = 0;

        foreach ($jobs as $job) {
            $id = (int) $job['id'];
            $claim = $db->prepare("UPDATE cbm_jobs SET status = 'running', attempts = attempts + 1 WHERE id = ? AND status = 'pending'");
            $claim->execute([$id]);
            if ($claim->rowCount() === 0) {
                continue;
            }

            try {
                $payload = json_decode((string) $job['payload'], true);
                if (!is_array($payload)) {
                    throw new RuntimeException('Payload CBM inválido');
                }
                if (($job['kind'] ?? '') === 'telemetry.critical') {
                    self::handleCritical($db, $payload);
                }
                $db->prepare("UPDATE cbm_jobs SET status = 'done', processed_at = datetime('now'), last_error = NULL WHERE id = ?")
                    ->execute([$id]);
                $done++;
            } catch (Throwable $e) {
                $attempts = (int) ($job['attempts'] ?? 0) + 1;
                $status = $attempts >= 5 ? 'failed' : 'pending';
                $db->prepare('UPDATE cbm_jobs SET status = ?, last_error = ? WHERE id = ?')
                    ->execute([$status, $e->getMessage(), $id]);
                error_log('[CBM JOB] #' . $id . ' ' . $e->getMessage());
            }
        }

        return $done;
    }

    private static function handleCritical(PDO $db, array $payload): void
    {
        $tag = $payload['tag'] ?? [];
        $value = $payload['value'] ?? 0.0;
        $unit = $tag['unit'] ?? '';
        $label = $tag['label'] ?? '';
        $tagName = $tag['tag_name'] ?? '';
        $ativoId = $tag['ativo_id'] ?? null;
        $threshold = $tag['critical_threshold'] ?? 'N/A';

        if (!$ativoId) {
            return;
        }

        $checkStmt = $db->prepare("SELECT id FROM ordens WHERE ativo_id = ? AND situacao = 'Pendente' AND prioridade = 'Crítica'");
        $checkStmt->execute([$ativoId]);
        $existingOs = $checkStmt->fetchColumn();
        $osId = $existingOs ? (int) $existingOs : null;

        if (!$osId) {
            $desc = "[CBM AUTOMÁTICO] Alerta Crítico do PI Sensor: {$label}";
            $obs = "Leitura registrada: {$value} {$unit} (Limite Crítico: {$threshold} {$unit}). Ação Requerida: Executar inspeção imediata de lubrificação, vazamento e temperatura. Avaliar ruído e vibração no local.";
            $hoje = date('Y-m-d');
            $insStmt = $db->prepare("
                INSERT INTO ordens (descricao, responsavel, data_planejada, prioridade, situacao, observacao, ativo_id, tipo_manutencao, data_emissao, last_sync)
                VALUES (?, 'Equipe de Confiabilidade (PI)', ?, 'Crítica', 'Pendente', ?, ?, 'Corretiva', ?, datetime('now'))
            ");
            $insStmt->execute([$desc, $hoje, $obs, $ativoId, $hoje]);
            $osId = (int) $db->lastInsertId();
            error_log("[ALERT] Telemetria crítica {$label} ({$tagName}): {$value} {$unit}. O.S. #{$osId}");
        } else {
            error_log("[CBM] Alerta mantido para {$tagName}. OS #{$osId} já existe.");
        }

        self::sendAlertEmail($tagName, $label, $value, $unit, $threshold, $osId);
    }

    private static function sendAlertEmail($tagName, $label, $value, $unit, $threshold, $osId): void
    {
        $to = defined('CBM_ALERT_EMAIL') ? CBM_ALERT_EMAIL : (getenv('CBM_ALERT_EMAIL') ?: 'manutencao@lubteksystem.com');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $subject = "ALERTA INDUSTRIAL CRITICO - Sensor {$tagName}";
        $message = "Alerta de Telemetria Crítica no LUB-TEK:\n\n" .
            "Sensor: {$label} ({$tagName})\n" .
            "Valor Lido: {$value} {$unit}\n" .
            "Limite Crítico: {$threshold} {$unit}\n" .
            "Ordem de Serviço Corretiva: #{$osId}\n\n" .
            "Acesse o painel Kanban para iniciar o atendimento.";
        $headers = "From: no-reply@lubteksystem.com\r\nReply-To: no-reply@lubteksystem.com\r\nX-Mailer: PHP/" . phpversion();
        @mail($to, $subject, $message, $headers);
    }
}
