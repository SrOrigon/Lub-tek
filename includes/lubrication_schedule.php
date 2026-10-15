<?php
/**
 * Agenda de tarefas fixas de lubrificação (cadastro → datas, fila de O.S. de rotina).
 */
require_once __DIR__ . '/lubrication_plan_builder.php';

class LubricationSchedule
{
    public const MARKER_PREFIX = '[TAREFA FIXA #';

    public static function marker(int $ativoId): string
    {
        return self::MARKER_PREFIX . $ativoId . ']';
    }

    public static function frequencyDays(array $tech): ?int
    {
        $periodo = trim((string) ($tech['periodo'] ?? $tech['frequencia'] ?? ''));
        if ($periodo === '') {
            return null;
        }
        $days = LubricationPlanBuilder::periodoToDays($periodo);
        return ($days !== null && $days > 0) ? $days : null;
    }

    public static function resolveServico(array $tech): string
    {
        $servico = trim((string) ($tech['servico'] ?? ''));
        if ($servico !== '') {
            return $servico;
        }
        $metodo = trim((string) ($tech['metodo'] ?? ''));
        if ($metodo !== '') {
            return $metodo;
        }
        return 'Executar tarefa de lubrificação cadastrada';
    }

    /**
     * Ponto elegível: material + período válidos (serviço inferido se vazio).
     */
    public static function isFixedTaskPoint(array $tech): bool
    {
        $material = trim((string) ($tech['material'] ?? ''));
        $periodo = trim((string) ($tech['periodo'] ?? $tech['frequencia'] ?? ''));
        if ($material === '' || $periodo === '') {
            return false;
        }
        return self::frequencyDays($tech) !== null;
    }

    public static function parseTechFromRow(array $row): array
    {
        $tech = json_decode($row['dados_tecnicos'] ?? '[]', true);
        if (!is_array($tech)) {
            $tech = [];
        }
        if (!empty($row['frequencia'])) {
            $tech['periodo'] = $tech['periodo'] ?? $row['frequencia'];
        }
        if (empty($tech['material']) && !empty($row['lubrificante'])) {
            $tech['material'] = $row['lubrificante'];
        }
        if (empty($tech['qtd_material']) && isset($row['quantidade'])) {
            $tech['qtd_material'] = $row['quantidade'];
        }
        if (empty($tech['unid_material']) && !empty($row['unidade'])) {
            $tech['unid_material'] = $row['unidade'];
        }
        if (empty($tech['ponto_lub']) && !empty($row['ponto_lub'])) {
            $tech['ponto_lub'] = $row['ponto_lub'];
        }
        if (empty($tech['rota']) && !empty($row['rota'])) {
            $tech['rota'] = $row['rota'];
        }
        return $tech;
    }

    /**
     * Lista unificada de pontos a partir do cadastro (ativos) + projeção SQL.
     */
    public static function collectSchedulablePoints(PDO $db): array
    {
        require_once __DIR__ . '/lubrication_tech_sync.php';
        LubricationTechSync::ensureTable($db);

        $byId = [];
        $stmt = $db->query(
            "SELECT id, nome, tag, user_id, tipo, dados_tecnicos FROM ativos
             WHERE dados_tecnicos IS NOT NULL AND TRIM(dados_tecnicos) != '' AND TRIM(dados_tecnicos) != '{}'"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tech = self::parseTechFromRow($row);
            if (!self::isFixedTaskPoint($tech)) {
                continue;
            }
            $id = (int) $row['id'];
            $byId[$id] = [
                'ativo_id' => $id,
                'nome' => $row['nome'] ?? '',
                'tag' => $row['tag'] ?? '',
                'user_id' => $row['user_id'] ?? 1,
                'tipo' => $row['tipo'] ?? '',
                'dados_tecnicos' => $row['dados_tecnicos'],
                'tech' => $tech,
            ];
        }

        $stmtLub = $db->query(
            "SELECT l.*, a.nome, a.tag, a.user_id, a.tipo, a.dados_tecnicos
             FROM ativos_lubrificacao l
             INNER JOIN ativos a ON a.id = l.ativo_id"
        );
        while ($row = $stmtLub->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) $row['ativo_id'];
            $tech = self::parseTechFromRow($row);
            if (!self::isFixedTaskPoint($tech)) {
                continue;
            }
            if (isset($byId[$id])) {
                $byId[$id]['tech'] = array_merge($byId[$id]['tech'], $tech);
                continue;
            }
            $byId[$id] = [
                'ativo_id' => $id,
                'nome' => $row['nome'] ?? '',
                'tag' => $row['tag'] ?? '',
                'user_id' => $row['user_id'] ?? 1,
                'tipo' => $row['tipo'] ?? '',
                'dados_tecnicos' => $row['dados_tecnicos'],
                'tech' => $tech,
                'lubrificante' => $row['lubrificante'] ?? '',
                'quantidade' => $row['quantidade'] ?? null,
                'unidade' => $row['unidade'] ?? '',
                'ponto_lub' => $row['ponto_lub'] ?? '',
                'rota' => $row['rota'] ?? '',
                'frequencia' => $row['frequencia'] ?? '',
            ];
        }

        return array_values($byId);
    }

    /**
     * Enriquece datas com histórico de O.S. concluídas do ponto (cadastro antigo / datas inconsistentes).
     */
    public static function enrichDatesFromOrderHistory(PDO $db, int $ativoId, array $tech): array
    {
        $marker = self::marker($ativoId);
        $stmt = $db->prepare(
            "SELECT data_execucao, data_planejada, data_emissao FROM ordens
             WHERE ativo_id = ? AND situacao = 'Concluído'
             AND (descricao LIKE ? OR descricao LIKE '%[PREVENTIVA AUTOMÁTICA]%' OR tipo_manutencao = 'Preventiva')
             ORDER BY COALESCE(data_execucao, data_planejada, data_emissao) DESC LIMIT 1"
        );
        $stmt->execute([$ativoId, '%' . $marker . '%']);
        $lastOs = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lastOs) {
            return $tech;
        }
        $raw = $lastOs['data_execucao'] ?: $lastOs['data_planejada'] ?: $lastOs['data_emissao'];
        if ($raw && preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $raw, $m)) {
            $tech['data_ultima_intervencao'] = $m[1];
        }
        return $tech;
    }

    public static function computeNextDueDate(array $tech, ?string $today = null): ?string
    {
        $freq = self::frequencyDays($tech);
        if ($freq === null) {
            return null;
        }
        $today = $today ?: date('Y-m-d');

        $explicitNext = trim((string) ($tech['data_proxima_intervencao'] ?? ''));
        if ($explicitNext !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $explicitNext)) {
            $explicitNext = substr($explicitNext, 0, 10);
        } else {
            $explicitNext = '';
        }

        $last = trim((string) ($tech['data_ultima_intervencao'] ?? ''));
        if ($last !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $last)) {
            $last = substr($last, 0, 10);
            $fromLast = date('Y-m-d', strtotime($last) + ($freq * 86400));
            if ($explicitNext === '' || $fromLast <= $explicitNext) {
                return $fromLast;
            }
        }

        if ($explicitNext !== '') {
            return $explicitNext;
        }

        return $today;
    }

    public static function hasCompletedRoutineOrder(PDO $db, int $ativoId): bool
    {
        $marker = self::marker($ativoId);
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM ordens WHERE ativo_id = ? AND situacao = 'Concluído'
             AND (descricao LIKE ? OR descricao LIKE '%[PREVENTIVA AUTOMÁTICA]%' OR descricao LIKE ?
             OR tipo_manutencao = 'Preventiva')"
        );
        $stmt->execute([$ativoId, '%' . $marker . '%', '%' . self::MARKER_PREFIX . $ativoId . '%']);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function isDueForRoutine(PDO $db, int $ativoId, array $tech, ?string $today = null): bool
    {
        $today = $today ?: date('Y-m-d');
        $next = self::computeNextDueDate($tech, $today);
        if ($next !== null && $next <= $today) {
            return true;
        }
        // Backlog: ponto cadastrado que nunca entrou na rotina operacional.
        if (!self::hasCompletedRoutineOrder($db, $ativoId)) {
            return true;
        }
        return false;
    }

    public static function persistNextDueInCadastro(PDO $db, int $ativoId, array $tech): void
    {
        $next = self::computeNextDueDate($tech);
        if ($next === null) {
            return;
        }
        $tech['data_proxima_intervencao'] = $next;
        $db->prepare('UPDATE ativos SET dados_tecnicos = ? WHERE id = ?')
            ->execute([json_encode($tech, JSON_UNESCAPED_UNICODE), $ativoId]);
    }

    /**
     * Sincroniza projeção SQL e datas — não cria ordens.
     */
    public static function prepareTenantSchedule(PDO $db): int
    {
        require_once __DIR__ . '/lubrication_tech_sync.php';
        LubricationTechSync::ensureTable($db);
        $n = LubricationTechSync::backfill($db);
        foreach (self::collectSchedulablePoints($db) as $point) {
            $ativoId = (int) $point['ativo_id'];
            $tech = self::enrichDatesFromOrderHistory($db, $ativoId, $point['tech']);
            self::persistNextDueInCadastro($db, $ativoId, $tech);
            LubricationTechSync::syncAsset($db, $ativoId);
        }
        return $n;
    }

    /**
     * O.S. gerada pelo agente de rotina ou legado preventivo — fila compartilhada da planta.
     */
    public static function isRoutineOrderRow(array $order): bool
    {
        $desc = (string) ($order['descricao'] ?? $order['desc'] ?? '');
        if ($desc !== '' && str_contains($desc, self::MARKER_PREFIX)) {
            return true;
        }
        if (str_contains($desc, '[PREVENTIVA AUTOMÁTICA]')) {
            return true;
        }
        $tipo = (string) ($order['tipo_manutencao'] ?? '');
        if ($tipo === 'Preventiva' && stripos($desc, 'tarefa fixa') !== false) {
            return true;
        }
        $resp = trim((string) ($order['responsavel'] ?? $order['resp'] ?? ''));
        if (strcasecmp($resp, 'Rotina Lubrificação') === 0) {
            return true;
        }
        return false;
    }

    /**
     * Ativos com dados técnicos preenchidos mas sem material/frequência válidos para rotina.
     */
    public static function countIncompleteFixedTaskCadastro(PDO $db): int
    {
        $incomplete = 0;
        try {
            $stmt = $db->query(
                "SELECT dados_tecnicos FROM ativos
                 WHERE dados_tecnicos IS NOT NULL AND TRIM(dados_tecnicos) != '' AND TRIM(dados_tecnicos) != '{}'"
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tech = self::parseTechFromRow($row);
                if (!self::isFixedTaskPoint($tech)) {
                    $incomplete++;
                }
            }
        } catch (Exception $e) {
            return 0;
        }
        return $incomplete;
    }

    public static function refreshCadastroDates(PDO $db, int $ativoId): void
    {
        if ($ativoId <= 0) {
            return;
        }
        $stmt = $db->prepare('SELECT dados_tecnicos FROM ativos WHERE id = ?');
        $stmt->execute([$ativoId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            return;
        }
        $tech = json_decode($raw ?: '[]', true);
        if (!is_array($tech) || !self::isFixedTaskPoint($tech)) {
            return;
        }
        $tech = self::enrichDatesFromOrderHistory($db, $ativoId, $tech);
        self::persistNextDueInCadastro($db, $ativoId, $tech);
        require_once __DIR__ . '/lubrication_tech_sync.php';
        LubricationTechSync::syncAsset($db, $ativoId);
    }
}
