<?php
/**
 * Agente de Geração de Ordens de Serviço (rotina / tarefas fixas).
 *
 * Motor determinístico (sem depender de LLM): avalia cadastro, prazo, rota,
 * histórico e conflitos antes de criar O.S. Evita duplicidade e documenta cada decisão.
 */
require_once __DIR__ . '/lubrication_schedule.php';
require_once __DIR__ . '/lubrication_tech_sync.php';
require_once __DIR__ . '/agent_weights.php';
require_once __DIR__ . '/agent_eval_scoring.php';
require_once __DIR__ . '/agent_feedback.php';

class WorkOrderGenerationAgent
{
    public const VERSION = '1.0.1';

    private PDO $db;
    private array $context;
    private string $today;
    private string $nowStr;

    /** @var array<int, true> */
    private array $openRoutineByAsset = [];

    private array $weights;

    public function __construct(PDO $db, array $context = [])
    {
        $this->db = $db;
        $this->context = $context;
        $this->today = date('Y-m-d');
        $this->nowStr = date('Y-m-d H:i:s');
        $this->weights = AgentWeights::load($db)['os_agent'] ?? AgentWeights::defaults()['os_agent'];
    }

    /**
     * @return array{success:bool,message:string,generated_orders_count:int,scanned_points:int,due_points:int,skipped_count:int,decisions:array,insight:string,agent_version:string,ran_at:string,dry_run:bool}
     */
    public function run(bool $fullSync = true, bool $dryRun = false): array
    {
        if ($fullSync) {
            LubricationSchedule::prepareTenantSchedule($this->db);
        } else {
            LubricationTechSync::ensureTable($this->db);
            if ((int) $this->db->query('SELECT COUNT(*) FROM ativos_lubrificacao')->fetchColumn() === 0) {
                LubricationTechSync::backfill($this->db);
            }
        }

        $this->warmOpenRoutineCache();

        $points = LubricationSchedule::collectSchedulablePoints($this->db);
        $cadastroIncomplete = LubricationSchedule::countIncompleteFixedTaskCadastro($this->db);
        usort($points, fn ($a, $b) => $this->compareRouteOrder($a, $b));

        $decisions = [];
        $created = 0;
        $due = 0;
        $skipped = 0;

        foreach ($points as $point) {
            $ativoId = (int) $point['ativo_id'];
            $tech = LubricationSchedule::enrichDatesFromOrderHistory($this->db, $ativoId, $point['tech']);
            $evaluation = $this->evaluatePoint($point, $tech);

            if ($evaluation['action'] === 'not_due') {
                $decisions[] = $evaluation;
                continue;
            }

            $due++;
            if ($evaluation['action'] === 'skip') {
                $skipped++;
                $decisions[] = $evaluation;
                continue;
            }

            if ($dryRun) {
                $evaluation['action'] = 'would_create';
                $decisions[] = $evaluation;
                $created++;
                continue;
            }

            $osId = $this->createRoutineOrder($point, $tech, $evaluation);
            if ($osId > 0) {
                $evaluation['action'] = 'created';
                $evaluation['os_id'] = $osId;
                $created++;
                $this->openRoutineByAsset[$ativoId] = true;
            } else {
                $evaluation['action'] = 'skip';
                $evaluation['reason'] = 'insert_failed';
                $skipped++;
            }
            $decisions[] = $evaluation;
        }

        $scanned = count($points);
        $insight = $this->buildOperationalInsight($decisions, $created, $due, $skipped, $scanned, $cadastroIncomplete);
        $message = $this->buildUserMessage($created, $due, $skipped, $scanned, $dryRun);

        if (!$dryRun && $created > 0 && class_exists('DB')) {
            DB::log(
                $this->context['user_label'] ?? 'OS_AGENT',
                'OS_AGENT_RUN',
                json_encode([
                    'created' => $created,
                    'due' => $due,
                    'skipped' => $skipped,
                    'scanned' => $scanned,
                ], JSON_UNESCAPED_UNICODE)
            );
        }

        $result = [
            'success' => true,
            'message' => $message,
            'generated_orders_count' => $created,
            'generated_count' => $created,
            'scanned_points' => $scanned,
            'due_points' => $due,
            'skipped_count' => $skipped,
            'cadastro_incomplete_count' => $cadastroIncomplete,
            'decisions' => $decisions,
            'insight' => $insight,
            'agent_version' => self::VERSION,
            'ran_at' => date('c'),
            'dry_run' => $dryRun,
        ];

        self::persistLastReport($this->db, $result);

        return $result;
    }

    public static function loadLastReport(PDO $db): ?array
    {
        try {
            $db->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
            $stmt = $db->prepare('SELECT mval FROM system_meta WHERE mkey = ?');
            $stmt->execute(['os_agent_last_report']);
            $raw = $stmt->fetchColumn();
            if ($raw === false || $raw === '') {
                return null;
            }
            $data = json_decode((string) $raw, true);
            return is_array($data) ? $data : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function persistLastReport(PDO $db, array $report): void
    {
        try {
            $db->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
            $store = $report;
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $stmt = $db->prepare(
                    'INSERT INTO system_meta (mkey, mval) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE mval = VALUES(mval)'
                );
            } else {
                $stmt = $db->prepare('INSERT OR REPLACE INTO system_meta (mkey, mval) VALUES (?, ?)');
            }
            $stmt->execute(['os_agent_last_report', json_encode($store, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) {
            error_log('OS Agent persist report: ' . $e->getMessage());
        }
    }

    private function compareRouteOrder(array $a, array $b): int
    {
        $ra = (string) ($a['tech']['rota'] ?? $a['rota'] ?? '');
        $rb = (string) ($b['tech']['rota'] ?? $b['rota'] ?? '');
        if ($ra !== $rb) {
            return strcmp($ra, $rb);
        }
        return strcmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
    }

    private function warmOpenRoutineCache(): void
    {
        $this->openRoutineByAsset = [];
        $stmt = $this->db->query(
            "SELECT DISTINCT ativo_id FROM ordens
             WHERE ativo_id IS NOT NULL
               AND situacao IN ('Pendente', 'Em Andamento', 'Aberta', 'Aguardando Material')
               AND (descricao LIKE '%[TAREFA FIXA #%' OR descricao LIKE '%[PREVENTIVA AUTOMÁTICA]%' OR descricao LIKE '%[PLANO #%')"
        );
        while ($id = $stmt->fetchColumn()) {
            $this->openRoutineByAsset[(int) $id] = true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluatePoint(array $point, array $tech): array
    {
        $ativoId = (int) $point['ativo_id'];
        $marker = LubricationSchedule::marker($ativoId);
        $planned = LubricationSchedule::computeNextDueDate($tech, $this->today);
        $freqDays = LubricationSchedule::frequencyDays($tech) ?? 30;

        $base = [
            'ativo_id' => $ativoId,
            'asset_name' => (string) ($point['nome'] ?? ''),
            'tag' => (string) ($point['tag'] ?? ''),
            'marker' => $marker,
            'planned_date' => $planned,
            'frequency_days' => $freqDays,
            'rota' => (string) ($tech['rota'] ?? $point['rota'] ?? ''),
            'servico' => LubricationSchedule::resolveServico($tech),
        ];

        if (!LubricationSchedule::isDueForRoutine($this->db, $ativoId, $tech, $this->today)) {
            return array_merge($base, [
                'action' => 'not_due',
                'reason' => 'not_due_yet',
                'score' => 0,
                'priority' => null,
            ]);
        }

        if ($planned === null || $planned > $this->today) {
            return array_merge($base, [
                'action' => 'not_due',
                'reason' => 'planned_in_future',
                'score' => 0,
                'priority' => null,
            ]);
        }

        $overdueDays = max(0, (int) floor((strtotime($this->today) - strtotime($planned)) / 86400));
        $score = $this->scoreUrgency($tech, $overdueDays, $freqDays);
        $priority = $this->priorityFromScore($tech, $score, $overdueDays);

        if ($freqDays === 1 && $this->hasDailyTaskAlreadyHandled($ativoId, $marker)) {
            return array_merge($base, [
                'action' => 'skip',
                'reason' => 'daily_already_queued_or_done',
                'score' => $score,
                'priority' => $priority,
                'overdue_days' => $overdueDays,
            ]);
        }

        if ($freqDays !== 1 && ($this->openRoutineByAsset[$ativoId] ?? false)) {
            return array_merge($base, [
                'action' => 'skip',
                'reason' => 'open_routine_exists',
                'score' => $score,
                'priority' => $priority,
                'overdue_days' => $overdueDays,
            ]);
        }

        return array_merge($base, [
            'action' => 'create',
            'reason' => $overdueDays > 0 ? 'overdue_backlog' : 'due_today',
            'score' => $score,
            'priority' => $priority,
            'overdue_days' => $overdueDays,
        ]);
    }

    private function scoreUrgency(array $tech, int $overdueDays, int $freqDays): int
    {
        return AgentEvalScoring::osUrgencyScore($tech, $overdueDays, $freqDays, $this->weights);
    }

    private function priorityFromScore(array $tech, int $score, int $overdueDays): string
    {
        return AgentEvalScoring::osPriorityLabel($tech, $score, $overdueDays);
    }

    private function hasDailyTaskAlreadyHandled(int $ativoId, string $marker): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM ordens WHERE ativo_id = ? AND descricao LIKE ?
             AND (
                situacao IN ('Pendente', 'Em Andamento', 'Aberta', 'Aguardando Material')
                OR (situacao = 'Concluído' AND (
                    substr(COALESCE(data_execucao, data_planejada, data_emissao, ''), 1, 10) = ?
                    OR substr(COALESCE(data_planejada, ''), 1, 10) = ?
                ))
             )"
        );
        $stmt->execute([$ativoId, '%' . $marker . '%', $this->today, $this->today]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function createRoutineOrder(array $point, array $tech, array $evaluation): int
    {
        $ativoId = (int) $point['ativo_id'];
        $marker = LubricationSchedule::marker($ativoId);
        $plannedDate = (string) ($evaluation['planned_date'] ?? $this->today);
        $prio = (string) ($evaluation['priority'] ?? 'Baixa');

        $matName = trim((string) ($tech['material'] ?? $point['lubrificante'] ?? 'Lubrificante'));
        $qty = floatval($tech['qtd_material'] ?? $point['quantidade'] ?? 0);
        if ($qty <= 0) {
            $qty = floatval($point['quantidade'] ?? 1);
        }
        $unid = trim((string) ($tech['unid_material'] ?? $point['unidade'] ?? 'g'));
        $servico = LubricationSchedule::resolveServico($tech);
        $periodo = trim((string) ($tech['periodo'] ?? $point['frequencia'] ?? ''));
        $pontoLub = trim((string) ($tech['ponto_lub'] ?? $point['ponto_lub'] ?? $point['nome'] ?? ''));
        $instrucao = trim((string) ($tech['instrucao'] ?? $tech['procedimento'] ?? ''));
        $rota = trim((string) ($tech['rota'] ?? $point['rota'] ?? ''));
        $condicao = trim((string) ($tech['condicao'] ?? ''));
        $score = (int) ($evaluation['score'] ?? 0);
        $overdue = (int) ($evaluation['overdue_days'] ?? 0);

        $catId = $tech['catalogo_id'] ?? null;
        $materialsJson = json_encode([
            [
                'id' => $catId,
                'catalog_id' => $catId,
                'nome' => $matName,
                'qtd' => $qty,
                'unidade' => $unid !== '' ? $unid : 'g',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $pointLabel = $point['nome'] ?? '';
        if ($pontoLub !== '' && stripos($pointLabel, $pontoLub) === false) {
            $pointLabel .= ' — ' . $pontoLub;
        }
        $tag = $point['tag'] ?? '';

        $osDesc = "{$marker} Tarefa fixa de lubrificação\n"
            . "Serviço: {$servico}\n"
            . "Ponto: {$pointLabel}" . ($tag !== '' ? " [TAG: {$tag}]" : '') . "\n"
            . "Frequência cadastrada: {$periodo}\n"
            . "Lubrificante: {$matName} ({$qty} {$unid})\n"
            . ($condicao !== '' ? "Condição máquina: {$condicao}\n" : '')
            . ($instrucao !== '' ? "Instruções: {$instrucao}\n" : '')
            . "Data prevista: " . date('d/m/Y', strtotime($plannedDate)) . "\n"
            . "Score operacional (agente): {$score}/100"
            . ($overdue > 0 ? " · Atraso: {$overdue} dia(s)" : '') . "\n"
            . 'Executar conforme cadastro do ponto e registrar conclusão na rota ou ordem.';

        $insStmt = $this->db->prepare("INSERT INTO ordens (
            descricao, responsavel, data_planejada, prioridade, situacao, ativo_id, last_sync, usuarios_id, materiais,
            data_emissao, materiais_sap, cod_serv, reserva_almox, rota, tipo_manutencao, observacao
        ) VALUES (?, ?, ?, ?, 'Pendente', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Preventiva', ?)");

        $obs = json_encode([
            'agent' => self::VERSION,
            'score' => $score,
            'overdue_days' => $overdue,
            'reason' => $evaluation['reason'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        $ok = $insStmt->execute([
            $osDesc,
            'Rotina Lubrificação',
            $plannedDate,
            $prio,
            $ativoId,
            $this->nowStr,
            null,
            $materialsJson,
            $this->today,
            $tech['sap'] ?? '',
            $tech['cod_serv'] ?? '',
            $tech['almox'] ?? '',
            $rota !== '' ? $rota : null,
            $obs,
        ]);

        if (!$ok) {
            return 0;
        }
        $osId = (int) $this->db->lastInsertId();
        if ($osId > 0) {
            try {
                AgentFeedback::save($this->db, [
                    'agent' => 'os_generation',
                    'decision_id' => $marker,
                    'asset_id' => $ativoId,
                    'order_id' => $osId,
                    'action' => 'generated',
                    'reason' => (string) ($evaluation['reason'] ?? ''),
                    'meta' => [
                        'score' => $score,
                        'priority' => $prio,
                        'overdue_days' => $overdue,
                        'agent_version' => self::VERSION,
                    ],
                    'created_by' => $this->context['user_label'] ?? 'OS_AGENT',
                ]);
            } catch (Exception $e) {
                error_log('OS Agent feedback log: ' . $e->getMessage());
            }
        }
        return $osId;
    }

    /**
     * @param array<int, array<string, mixed>> $decisions
     */
    private function buildOperationalInsight(array $decisions, int $created, int $due, int $skipped, int $scanned, int $cadastroIncomplete = 0): string
    {
        if ($scanned === 0) {
            $extra = $cadastroIncomplete > 0
                ? " Há {$cadastroIncomplete} ativo(s) com cadastro técnico incompleto (faltam material ou frequência)."
                : '';
            return 'Cadastre pontos com material, quantidade e frequência para o agente montar a fila diária de rotina.' . $extra;
        }

        $overdue = 0;
        $high = 0;
        $byRoute = [];
        foreach ($decisions as $d) {
            if (($d['action'] ?? '') === 'not_due') {
                continue;
            }
            $od = (int) ($d['overdue_days'] ?? 0);
            if ($od > 0) {
                $overdue++;
            }
            if (($d['priority'] ?? '') === 'Alta') {
                $high++;
            }
            $r = trim((string) ($d['rota'] ?? ''));
            if ($r === '') {
                $r = 'Sem rota';
            }
            $byRoute[$r] = ($byRoute[$r] ?? 0) + 1;
        }
        arsort($byRoute);
        $topRoute = key($byRoute);
        $topCount = $topRoute !== null ? ($byRoute[$topRoute] ?? 0) : 0;

        $parts = [];
        $parts[] = "Agente v" . self::VERSION . ": {$scanned} ponto(s) monitorados, {$due} vencido(s), {$created} O.S. gerada(s), {$skipped} ignorada(s) por regra.";
        if ($overdue > 0) {
            $parts[] = "{$overdue} tarefa(s) com atraso — priorize conclusão antes de novas emergências.";
        }
        if ($high > 0) {
            $parts[] = "{$high} item(ns) com prioridade alta pelo score operacional.";
        }
        if ($topRoute && $topCount > 1) {
            $parts[] = "Rota «{$topRoute}» concentra {$topCount} tarefa(s) na fila de hoje.";
        }
        if ($cadastroIncomplete > 0) {
            $parts[] = "{$cadastroIncomplete} ativo(s) com cadastro técnico incompleto (revise material e frequência).";
        }
        return implode(' ', $parts);
    }

    private function buildUserMessage(int $created, int $due, int $skipped, int $scanned, bool $dryRun): string
    {
        if ($dryRun) {
            return "Simulação: {$created} O.S. seriam criadas ({$due} vencidas, {$skipped} ignoradas, {$scanned} pontos).";
        }
        if ($created > 0) {
            return "Agente gerou {$created} ordem(ns) de rotina ({$due} vencida(s), {$skipped} já cobertas por regra).";
        }
        if ($due > 0) {
            return "Há {$due} tarefa(s) vencida(s), mas o agente não criou novas O.S. (já existem abertas ou regras diárias).";
        }
        if ($scanned === 0) {
            return 'Nenhum ponto elegível no cadastro (material + frequência).';
        }
        return "Fila em dia: {$scanned} ponto(s) monitorados, nenhuma tarefa vencida agora.";
    }
}
