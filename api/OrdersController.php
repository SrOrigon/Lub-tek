<?php
require_once __DIR__ . '/json_shield.php';
/**
 * LUB-TEK - Orders Controller
 * Handles Work Orders CRUD
 */

require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/grease_compatibility.php';
require_once __DIR__ . '/../includes/lubrication_schedule.php';
require_once __DIR__ . '/../includes/lubrication_tech_sync.php';
require_once __DIR__ . '/../includes/work_order_generation_agent.php';
require_once __DIR__ . '/../includes/work_order_agent_briefing.php';
require_once __DIR__ . '/../includes/agent_feedback.php';
require_once __DIR__ . '/../includes/idempotency_guard.php';
require_once __DIR__ . '/../includes/integration_queue.php';

class OrdersController
{
    private $db;
    private $user;
    private $input;

    public function __construct($db, $user, $input)
    {
        $this->db = $db;
        $this->user = $user;
        $this->input = $input;
    }

    public function getTasks()
    {
        // Geração de O.S. de rotina: use generate_scheduled_orders / run_work_order_agent (cron ou gestor).
        // Gestor/developer: todas as OS do banco do tenant
        // Trabalhador: todas as OS (checklist operacional da empresa)
        $sql = "SELECT o.*, a.nome as ativo_nome, a.tag as ativo_tag FROM ordens o LEFT JOIN ativos a ON o.ativo_id = a.id ORDER BY o.rota ASC, a.nome ASC, o.data_planejada DESC, o.prioridade DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetchAll();

        return array_map(function ($r) {
            return [
                'id' => $r['id'],
                'desc' => $r['descricao'],
                'resp' => $r['responsavel'],
                'date' => $r['data_planejada'],
                'prio' => $r['prioridade'],
                'done' => $r['situacao'] === 'Concluído',
                'situacao' => $r['situacao'],
                'ativo_id' => $r['ativo_id'],
                'ip' => $r['ip'] ?? '',
                'cod_serv' => $r['cod_serv'] ?? '',
                'rota' => $r['rota'] ?? '',
                'materiais_sap' => $r['materiais_sap'] ?? '',
                'reserva_almox' => $r['reserva_almox'] ?? '',
                'num_pontos' => $r['num_pontos'] ?? 0,
                'complemento' => $r['complemento'] ?? '',
                'data_emissao' => $r['data_emissao'] ?? '',
                'data_execucao' => $r['data_execucao'] ?? '',
                'horas_exec' => $r['horas_exec'] ?? 0,
                'minutos_exec' => $r['minutos_exec'] ?? 0,
                'cod_exec' => $r['cod_exec'] ?? '',
                'conc_percent' => $r['conc_percent'] ?? 0,
                'ph' => $r['ph'] ?? 0,
                'agua_l' => $r['agua_l'] ?? 0,
                'obs_exec' => $r['obs_exec'] ?? '',
                'motivos' => $r['motivos'] ?? '',
                'condicao_servico' => $r['condicao_servico'] ?? '',
                'ativo_nome' => $r['ativo_nome'] ?? '',
                'ativo_tag' => $r['ativo_tag'] ?? '',
                'qtd_real' => $r['qtd_real'] ?? ''
            ];
        }, $res);
    }

    public function saveTask()
    {
        $input = $this->input;
        $role = $this->user['role'] ?? 'trabalhador';

        if (Permissions::isTrabalhador($role)) {
            return $this->saveTaskExecution($input);
        }

        $isGateway = Permissions::isGatewayRole($role);
        if (!$isGateway && $this->user['role'] !== 'developer' && !Permissions::isGestor($this->user['role'] ?? '')) {
            throw new Exception("Permissão negada para alterar ordens de serviço.");
        }

        $idempotencyKey = $input['idempotency_key'] 
            ?? $_SERVER['HTTP_IDEMPOTENCY_KEY'] 
            ?? $input['client_request_id'] 
            ?? null;

        return IdempotencyGuard::run($this->db, $idempotencyKey, 'orders.save_task', function () use ($input) {
            return $this->executeSaveTask($input);
        });
    }

    private function executeSaveTask(array $input)
    {
        $id = intval($input['id'] ?? 0);
        $situacao = isset($input['situacao']) ? strip_tags(trim((string) $input['situacao'])) : null;
        $now = date('Y-m-d H:i:s');

        // Outbox / Timestamps
        $executedAtRaw = $input['executed_at'] ?? null;
        $executedAt = null;
        if ($executedAtRaw) {
            $ts = strtotime((string)$executedAtRaw);
            if ($ts && $ts > 0) {
                $executedAt = date('Y-m-d H:i:s', $ts);
            }
        }
        $completionDate = $executedAt ?: $now;
        $executionDateOnly = $executedAt ? date('Y-m-d', strtotime($executedAt)) : ($input['data_execucao'] ?? null);
        $syncedAt = $now;
        $idempotencyKey = $input['idempotency_key'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
        $dosageAmount = isset($input['dosage_amount']) && is_numeric($input['dosage_amount']) ? floatval($input['dosage_amount']) : null;
        $dosageUnit = isset($input['dosage_unit']) ? strip_tags(trim((string)$input['dosage_unit'])) : null;

        // 1. ATUALIZAÇÃO PARCIAL RÁPIDA (Vinda do Kanban Drag & Drop ou API enxuta)
        if ($id > 0 && $situacao !== null && count($input) <= 6) {
            $validStatuses = ['Pendente', 'Em Andamento', 'Concluído', 'Cancelada', 'Aguardando Peça'];
            if (!in_array($situacao, $validStatuses, true)) {
                $situacao = 'Pendente';
            }

            return DB::safeExecute(function ($db) use ($id, $situacao, $now, $completionDate, $executionDateOnly, $executedAt, $syncedAt, $idempotencyKey, $dosageAmount, $dosageUnit) {
                // A. Busca os dados atuais da OS antes de atualizar
                $stmtOS = $db->prepare("SELECT situacao, materiais FROM ordens WHERE id = ?");
                $stmtOS->execute([$id]);
                $osAtual = $stmtOS->fetch(PDO::FETCH_ASSOC);

                if (!$osAtual) {
                    throw new Exception("Ordem de serviço não encontrada.");
                }

                $oldStatus = $osAtual['situacao'];

                // B. Atualiza o status da OS, datas separadas (executed_at x synced_at) e idempotência
                $stmtUpdate = $db->prepare("
                    UPDATE ordens SET 
                        situacao = ?, 
                        data_conclusao = CASE WHEN ? = 'Concluído' THEN ? ELSE data_conclusao END, 
                        data_execucao = CASE WHEN ? = 'Concluído' AND (data_execucao IS NULL OR data_execucao = '') THEN ? ELSE data_execucao END,
                        executed_at = COALESCE(?, executed_at),
                        synced_at = ?,
                        idempotency_key = COALESCE(?, idempotency_key),
                        dosage_amount = COALESCE(?, dosage_amount),
                        dosage_unit = COALESCE(?, dosage_unit),
                        last_sync = ? 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $situacao, 
                    $situacao, 
                    $completionDate, 
                    $situacao,
                    $executionDateOnly ?: date('Y-m-d'),
                    $executedAt,
                    $syncedAt,
                    $idempotencyKey,
                    $dosageAmount,
                    $dosageUnit,
                    $now, 
                    $id
                ]);

                DB::log($this->user['name'] ?? $this->user['nome'] ?? 'SYSTEM', 'OS_STATUS_CHANGE', "OS #{$id} -> {$situacao}");

                $lowStock = false;
                // C. GATILHO DE INVENTÁRIO, CICLO DE VIDA DO ATIVO E FILA SAP DESACOPLADA
                if ($situacao === 'Concluído' && $oldStatus !== 'Concluído') {
                    AgentFeedback::setOutcomeForOrder($db, $id, 'completed');
                    $materials = !empty($osAtual['materiais']) ? json_decode($osAtual['materiais'], true) : [];

                    if (is_array($materials) && empty($materials) && $dosageAmount) {
                        $materials = $this->materialsFromDosage($db, $osAtual['ativo_id'] ?? null, $dosageAmount, $dosageUnit);
                    }

                    if (is_array($materials) && !empty($materials)) {
                        $lowStock = $this->deductStockNonBlocking($db, $materials, $id);

                        foreach ($materials as $item) {
                            $qtd = floatval($item['qtd'] ?? 0);
                            $nome = $item['nome'] ?? $item['material'] ?? 'item';
                            if ($qtd > 0) {
                                DB::log('SYSTEM', 'INVENTORY_DEDUCT', "OS #{$id} consumiu {$qtd} de {$nome}");
                            }
                        }
                    }

                    // Enfileira integração SAP de forma não-bloqueante
                    try {
                        IntegrationQueue::enqueue($db, 'SAP', 'order_completion', [
                            'order_id' => $id,
                            'status' => 'Concluído',
                            'executed_at' => $completionDate,
                            'synced_at' => $syncedAt,
                            'idempotency_key' => $idempotencyKey,
                            'materials' => $materials,
                            'dosage_amount' => $dosageAmount,
                            'dosage_unit' => $dosageUnit
                        ], 'ORD-' . $id);
                    } catch (Throwable $e) {
                        error_log('[IntegrationQueue] Falha ao enfileirar: ' . $e->getMessage());
                    }

                    $stmtFull = $db->prepare("SELECT * FROM ordens WHERE id = ?");
                    $stmtFull->execute([$id]);
                    $fullOrder = $stmtFull->fetch(PDO::FETCH_ASSOC);
                    if ($fullOrder && !empty($fullOrder['ativo_id'])) {
                        $this->onOrderCompleted($db, (int)$fullOrder['ativo_id'], $fullOrder);
                    }
                }
                // D. REABERTURA: se a OS estava Concluída e volta para outro status,
                // o estoque baixado precisa ser devolvido
                elseif ($situacao !== 'Concluído' && $oldStatus === 'Concluído' && !empty($osAtual['materiais'])) {
                    AgentFeedback::setOutcomeForOrder($db, $id, 'reopened');
                    $materials = json_decode($osAtual['materiais'], true);
                    if (is_array($materials)) {
                        $this->restoreStockConsumption($db, $materials);

                        foreach ($materials as $item) {
                            $qtd = floatval($item['qtd'] ?? 0);
                            $nome = $item['nome'] ?? $item['material'] ?? 'item';
                            if ($qtd > 0) {
                                DB::log('SYSTEM', 'INVENTORY_RESTORE', "OS #{$id} reaberta, estoque restaurado: {$qtd} de {$nome}");
                            }
                        }
                    }
                } elseif ($situacao !== 'Concluído' && $oldStatus === 'Concluído') {
                    AgentFeedback::setOutcomeForOrder($db, $id, 'reopened');
                }

                return ['success' => true, 'id' => $id, 'status' => $situacao, 'low_stock_alert' => !empty($lowStock)];
            });
        }

        // 2. ATUALIZAÇÃO COMPLETA (Via Formulário e edição tradicional)
        $rawStatus = ($input['done'] ?? false) ? 'Concluído' : ($input['situacao'] ?? 'Pendente');
        $validStatuses = ['Pendente', 'Em Andamento', 'Concluído', 'Cancelada', 'Aguardando Peça'];
        $status = in_array($rawStatus, $validStatuses, true) ? $rawStatus : 'Pendente';

        $rawPrio = trim((string)($input['prio'] ?? $input['prioridade'] ?? 'Média'));
        $validPriorities = ['Baixa', 'Média', 'Alta', 'Crítica', 'Rotina'];
        $prio = in_array($rawPrio, $validPriorities, true) ? $rawPrio : 'Média';

        $desc = strip_tags(trim((string)($input['desc'] ?? $input['descricao'] ?? '')));
        $resp = strip_tags(trim((string)($input['resp'] ?? $input['responsavel'] ?? '')));
        $rawDate = trim((string)($input['date'] ?? $input['data_planejada'] ?? ''));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : date('Y-m-d');

        $materials = $input['materiais'] ?? null;
        $isUpdate = isset($input['id']) && (strpos((string) $input['id'], 'new') === false);

        $oldStatus = null;
        if ($isUpdate) {
            $stmtS = $this->db->prepare("SELECT situacao FROM ordens WHERE id = ?");
            $stmtS->execute([$input['id']]);
            $oldStatus = $stmtS->fetchColumn();

            if ($materials === null) {
                $stmtM = $this->db->prepare("SELECT materiais FROM ordens WHERE id = ?");
                $stmtM->execute([$input['id']]);
                $rawM = $stmtM->fetchColumn();
                $materials = json_decode($rawM ?: '[]', true) ?: [];
            }
        } elseif ($materials === null) {
            $materials = [];
        }

        try {
            $confirmPurge = !empty($input['confirm_purge']);
            $appliedLub = $this->appliedLubricant($input, is_array($materials) ? $materials : []);
            $ativoId = $input['ativo_id'] ?? null;
            $dosageAmount = isset($input['dosage_amount']) && is_numeric($input['dosage_amount']) ? floatval($input['dosage_amount']) : null;
            $dosageUnit = isset($input['dosage_unit']) ? strip_tags(trim((string) $input['dosage_unit'])) : null;
            $lowStock = false;
            return DB::safeExecute(function ($db) use ($input, $isUpdate, $status, $now, $materials, $oldStatus, $confirmPurge, $appliedLub, $ativoId, $dosageAmount, $dosageUnit, &$lowStock) {
                if ($status === 'Concluído' && $oldStatus !== 'Concluído' && $ativoId && $appliedLub !== '') {
                    GreaseCompatibility::guardOnOrder($db, $ativoId, $appliedLub, $confirmPurge, $this->user);
                }
                if ($status === 'Concluído' && $oldStatus !== 'Concluído') {
                    $orderIdToExec = (int) ($input['id'] ?? $lastId ?? 0);
                    if ($orderIdToExec > 0) {
                        AgentFeedback::setOutcomeForOrder($db, $orderIdToExec, 'completed');
                    }
                    if (empty($materials) && $dosageAmount) {
                        $materials = $this->materialsFromDosage($db, $ativoId, $dosageAmount, $dosageUnit);
                    }
                    if (!empty($materials)) {
                        $lowStock = (bool) $this->deductStockNonBlocking($db, $materials, $input['id'] ?? null);
                        foreach ($materials as $item) {
                            $qtd = floatval($item['qtd'] ?? 0);
                            $nome = $item['nome'] ?? $item['material'] ?? 'item';
                            if ($qtd > 0) {
                                DB::log('SYSTEM', 'INVENTORY_DEDUCT', "OS #" . ($input['id'] ?? '') . " consumiu {$qtd} de {$nome}");
                            }
                        }
                    }
                    if ($ativoId) {
                        try {
                            $todayDate = date('Y-m-d');
                            if ($orderIdToExec) {
                                $db->prepare("UPDATE ordens SET data_execucao = ? WHERE id = ?")->execute([$todayDate, $orderIdToExec]);
                            }
                            $stmtFull = $db->prepare("SELECT * FROM ordens WHERE id = ?");
                            $stmtFull->execute([$orderIdToExec]);
                            $fullOrder = $stmtFull->fetch(PDO::FETCH_ASSOC);
                            if ($fullOrder) {
                                $this->onOrderCompleted($db, (int)$ativoId, $fullOrder);
                            }
                        } catch (Exception $schedEx) {
                            error_log('Schedule auto-regeneration exception: ' . $schedEx->getMessage());
                        }
                    }
                }
                // Reabertura: devolve ao estoque o que havia sido baixado, evitando drift
                // permanente de inventário quando uma OS Concluída é reaberta pelo formulário.
                elseif ($status !== 'Concluído' && $oldStatus === 'Concluído') {
                    $orderIdToExec = (int) ($input['id'] ?? 0);
                    if ($orderIdToExec > 0) {
                        AgentFeedback::setOutcomeForOrder($db, $orderIdToExec, 'reopened');
                    }
                    if (!empty($materials)) {
                        $this->restoreStockConsumption($db, $materials);
                        foreach ($materials as $item) {
                            $qtd = floatval($item['qtd'] ?? 0);
                            $nome = $item['nome'] ?? $item['material'] ?? 'item';
                            if ($qtd > 0) {
                                DB::log('SYSTEM', 'INVENTORY_RESTORE', "OS #{$input['id']} reaberta, estoque restaurado: {$qtd} de {$nome}");
                            }
                        }
                    }
                }

                $jsonMateriais = json_encode($materials);
                $cleanIp = isset($input['ip']) ? preg_replace('/[^0-9a-zA-Z._-]/', '', (string)$input['ip']) : null;
                $cleanCodServ = isset($input['cod_serv']) ? strip_tags(trim((string)$input['cod_serv'])) : null;
                $cleanRota = isset($input['rota']) ? strip_tags(trim((string)$input['rota'])) : null;
                $cleanMatSap = isset($input['materiais_sap']) ? strip_tags(trim((string)$input['materiais_sap'])) : null;
                $cleanResAlmox = isset($input['reserva_almox']) ? strip_tags(trim((string)$input['reserva_almox'])) : null;
                $cleanNumPontos = max(0, intval($input['num_pontos'] ?? 0));
                $cleanComplemento = isset($input['complemento']) ? strip_tags(trim((string)$input['complemento'])) : null;
                $cleanDataEmissao = isset($input['data_emissao']) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$input['data_emissao']) ? (string)$input['data_emissao'] : null;
                $cleanDataExec = isset($input['data_execucao']) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$input['data_execucao']) ? (string)$input['data_execucao'] : null;
                $cleanHorasExec = max(0, floatval($input['horas_exec'] ?? 0));
                $cleanMinutosExec = max(0, min(59, intval($input['minutos_exec'] ?? 0)));
                $cleanCodExec = isset($input['cod_exec']) ? strip_tags(trim((string)$input['cod_exec'])) : null;
                $cleanConc = floatval($input['conc_percent'] ?? 0);
                $cleanPh = floatval($input['ph'] ?? 0);
                $cleanAgua = floatval($input['agua_l'] ?? 0);
                $cleanObsExec = isset($input['obs_exec']) ? strip_tags(trim((string)$input['obs_exec'])) : null;
                $cleanMotivos = isset($input['motivos']) ? strip_tags(trim((string)$input['motivos'])) : null;
                $cleanCondicao = isset($input['condicao_servico']) ? strip_tags(trim((string)$input['condicao_servico'])) : null;
                $cleanQtdReal = isset($input['qtd_real']) ? strip_tags(trim((string)$input['qtd_real'])) : null;
                $cleanAtivoId = (!empty($input['ativo_id']) && is_numeric($input['ativo_id'])) ? intval($input['ativo_id']) : null;

                if ($isUpdate) {
                    $db->prepare("UPDATE ordens SET 
                        descricao=?, responsavel=?, data_planejada=?, prioridade=?, situacao=?, last_sync=?, usuarios_id=?, materiais=?,
                        ativo_id=?, ip=?, cod_serv=?, rota=?, materiais_sap=?, reserva_almox=?, num_pontos=?, complemento=?,
                        data_emissao=?, data_execucao=?, horas_exec=?, minutos_exec=?, cod_exec=?,
                        conc_percent=?, ph=?, agua_l=?, obs_exec=?, motivos=?, condicao_servico=?,
                        data_conclusao = CASE WHEN ? = 'Concluído' THEN ? ELSE data_conclusao END,
                        qtd_real = ?,
                        executed_at = COALESCE(?, executed_at),
                        synced_at = ?,
                        idempotency_key = COALESCE(?, idempotency_key),
                        dosage_amount = COALESCE(?, dosage_amount),
                        dosage_unit = COALESCE(?, dosage_unit)
                        WHERE id=?")
                        ->execute([
                            $desc,
                            $resp,
                            $date,
                            $prio,
                            $status,
                            $now,
                            $this->user['id'],
                            $jsonMateriais,
                            $cleanAtivoId,
                            $cleanIp,
                            $cleanCodServ,
                            $cleanRota,
                            $cleanMatSap,
                            $cleanResAlmox,
                            $cleanNumPontos,
                            $cleanComplemento,
                            $cleanDataEmissao,
                            $cleanDataExec ?: ($status === 'Concluído' ? ($executionDateOnly ?: date('Y-m-d')) : null),
                            $cleanHorasExec,
                            $cleanMinutosExec,
                            $cleanCodExec,
                            $cleanConc,
                            $cleanPh,
                            $cleanAgua,
                            $cleanObsExec,
                            $cleanMotivos,
                            $cleanCondicao,
                            $status,
                            $completionDate,
                            $cleanQtdReal,
                            $executedAt,
                            $syncedAt,
                            $idempotencyKey,
                            $dosageAmount,
                            $dosageUnit,
                            $input['id']
                        ]);
                } else {
                    $db->prepare("INSERT INTO ordens (
                        descricao, responsavel, data_planejada, prioridade, situacao, ativo_id, last_sync, usuarios_id, materiais,
                        ip, cod_serv, rota, materiais_sap, reserva_almox, num_pontos, complemento,
                        data_emissao, data_execucao, horas_exec, minutos_exec, cod_exec,
                        conc_percent, ph, agua_l, obs_exec, motivos, condicao_servico, data_conclusao, qtd_real,
                        executed_at, synced_at, idempotency_key, dosage_amount, dosage_unit
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CASE WHEN ? = 'Concluído' THEN ? ELSE NULL END,?,?,?,?,?,?)")
                        ->execute([
                            $desc,
                            $resp,
                            $date,
                            $prio,
                            $status,
                            $cleanAtivoId,
                            $now,
                            $this->user['id'],
                            $jsonMateriais,
                            $cleanIp,
                            $cleanCodServ,
                            $cleanRota,
                            $cleanMatSap,
                            $cleanResAlmox,
                            $cleanNumPontos,
                            $cleanComplemento,
                            $cleanDataEmissao,
                            $cleanDataExec ?: ($status === 'Concluído' ? ($executionDateOnly ?: date('Y-m-d')) : null),
                            $cleanHorasExec,
                            $cleanMinutosExec,
                            $cleanCodExec,
                            $cleanConc,
                            $cleanPh,
                            $cleanAgua,
                            $cleanObsExec,
                            $cleanMotivos,
                            $cleanCondicao,
                            $status,
                            $completionDate,
                            $cleanQtdReal,
                            $executedAt,
                            $syncedAt,
                            $idempotencyKey,
                            $dosageAmount,
                            $dosageUnit
                        ]);
                }
                $lastId = $isUpdate ? $input['id'] : $db->lastInsertId();

                if ($status === 'Concluído') {
                    try {
                        IntegrationQueue::enqueue($db, 'SAP', 'order_completion', [
                            'order_id' => $lastId,
                            'status' => 'Concluído',
                            'executed_at' => $completionDate,
                            'synced_at' => $syncedAt,
                            'idempotency_key' => $idempotencyKey,
                            'materials' => $materials,
                            'dosage_amount' => $dosageAmount,
                            'dosage_unit' => $dosageUnit
                        ], 'ORD-' . $lastId);
                    } catch (Throwable $e) {}
                }

                return ['id' => $lastId, 'new_sync' => $now, 'success' => true, 'low_stock_alert' => !empty($lowStock)];
            });
        } catch (Exception $e) {
            // Rethrow specific stock errors so frontend catches them
            throw $e;
        } finally {
            // Fix 1: Audit Log guaranteed even on error
            DB::log($this->user['id'], $isUpdate ? 'UPDATE_OS_ATTEMPT' : 'CREATE_OS_ATTEMPT', "ordens", null, $input);
        }
    }

    /**
     * Trabalhador: atualiza apenas campos de execução/checklist da OS (sem criar nem apagar).
     */
    private function saveTaskExecution(array $input)
    {
        $idempotencyKey = $input['idempotency_key'] 
            ?? $_SERVER['HTTP_IDEMPOTENCY_KEY'] 
            ?? $input['client_request_id'] 
            ?? null;

        return IdempotencyGuard::run($this->db, $idempotencyKey, 'orders.worker_execution', function () use ($input) {
            return $this->executeWorkerSaveTask($input);
        });
    }

    private function executeWorkerSaveTask(array $input)
    {
        $orderId = intval($input['id'] ?? 0);
        if ($orderId <= 0 || strpos((string) ($input['id'] ?? ''), 'new') !== false) {
            throw new Exception("Trabalhadores não podem criar novas ordens de serviço.");
        }

        $rawStatus = ($input['done'] ?? false) ? 'Concluído' : ($input['situacao'] ?? 'Pendente');
        $validStatuses = ['Pendente', 'Em Andamento', 'Concluído', 'Cancelada', 'Aguardando Peça'];
        $status = in_array($rawStatus, $validStatuses, true) ? $rawStatus : 'Pendente';
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            "SELECT situacao, materiais, usuarios_id, responsavel, ativo_id, descricao, tipo_manutencao FROM ordens WHERE id = ?"
        );
        $stmt->execute([$orderId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new Exception("Ordem de serviço não encontrada.");
        }

        $userId = (int) ($this->user['id'] ?? 0);
        $userName = trim((string) ($this->user['nome'] ?? $this->user['name'] ?? ''));
        if (!$this->workerCanExecuteOrder($existing, $userId, $userName)) {
            throw new Exception("Esta ordem de serviço não está atribuída a você.");
        }

        $golpes = isset($input['golpes_aplicados']) ? intval($input['golpes_aplicados']) : (isset($input['golpes']) ? intval($input['golpes']) : null);
        $toolId = isset($input['engraxadeira_id']) ? intval($input['engraxadeira_id']) : null;
        if ($golpes !== null && $golpes > 0 && empty($input['qtd_real'])) {
            $gPerStroke = 1.5;
            if ($toolId > 0) {
                try {
                    $stTool = $this->db->prepare("SELECT gramas_por_golpe FROM engraxadeiras_calibracao WHERE id = ?");
                    $stTool->execute([$toolId]);
                    $v = $stTool->fetchColumn();
                    if ($v) {
                        $gPerStroke = floatval($v);
                    }
                } catch (Exception $e) {}
            }
            $input['qtd_real'] = round($golpes * $gPerStroke, 2) . ' g';
        }

        $materials = null;
        if (isset($input['qtd_real'])) {
            $materials = json_decode($existing['materiais'] ?: '[]', true) ?: [];
            if (!empty($materials)) {
                $qtyVal = floatval(preg_replace('/[^0-9.]/', '', $input['qtd_real']));
                if ($qtyVal > 0) {
                    $materials[0]['qtd'] = $qtyVal;
                }
            }
        }

        // Sanitização de entradas do trabalhador
        $cleanDataExec = isset($input['data_execucao']) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$input['data_execucao']) ? (string)$input['data_execucao'] : null;
        $cleanHorasExec = isset($input['horas_exec']) ? max(0, floatval($input['horas_exec'])) : null;
        $cleanMinutosExec = isset($input['minutos_exec']) ? max(0, min(59, intval($input['minutos_exec']))) : null;
        $cleanCodExec = isset($input['cod_exec']) ? strip_tags(trim((string)$input['cod_exec'])) : null;
        $cleanConc = isset($input['conc_percent']) ? floatval($input['conc_percent']) : null;
        $cleanPh = isset($input['ph']) ? floatval($input['ph']) : null;
        $cleanAgua = isset($input['agua_l']) ? floatval($input['agua_l']) : null;
        $cleanObsExec = isset($input['obs_exec']) ? strip_tags(trim((string)$input['obs_exec'])) : null;
        $cleanMotivos = isset($input['motivos']) ? strip_tags(trim((string)$input['motivos'])) : null;
        $cleanCondicao = isset($input['condicao_servico']) ? strip_tags(trim((string)$input['condicao_servico'])) : null;
        $cleanQtdReal = isset($input['qtd_real']) ? strip_tags(trim((string)$input['qtd_real'])) : null;

        // Outbox / Timestamps
        $executedAtRaw = $input['executed_at'] ?? null;
        $executedAt = null;
        if ($executedAtRaw) {
            $ts = strtotime((string)$executedAtRaw);
            if ($ts && $ts > 0) {
                $executedAt = date('Y-m-d H:i:s', $ts);
            }
        }
        $completionDate = $executedAt ?: $now;
        $executionDateOnly = $executedAt ? date('Y-m-d', strtotime($executedAt)) : ($cleanDataExec ?: date('Y-m-d'));
        $syncedAt = $now;
        $idempotencyKey = $input['idempotency_key'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
        $dosageAmount = isset($input['dosage_amount']) && is_numeric($input['dosage_amount']) ? floatval($input['dosage_amount']) : null;
        $dosageUnit = isset($input['dosage_unit']) ? strip_tags(trim((string)$input['dosage_unit'])) : null;

        return DB::safeExecute(function ($db) use ($orderId, $input, $status, $now, $existing, $materials, $cleanDataExec, $cleanHorasExec, $cleanMinutosExec, $cleanCodExec, $cleanConc, $cleanPh, $cleanAgua, $cleanObsExec, $cleanMotivos, $cleanCondicao, $cleanQtdReal, $completionDate, $executionDateOnly, $executedAt, $syncedAt, $idempotencyKey, $dosageAmount, $dosageUnit) {
            $db->prepare("UPDATE ordens SET
                situacao = ?,
                last_sync = ?,
                usuarios_id = ?,
                data_execucao = COALESCE(?, data_execucao),
                horas_exec = COALESCE(?, horas_exec),
                minutos_exec = COALESCE(?, minutos_exec),
                cod_exec = COALESCE(?, cod_exec),
                conc_percent = COALESCE(?, conc_percent),
                ph = COALESCE(?, ph),
                agua_l = COALESCE(?, agua_l),
                obs_exec = COALESCE(?, obs_exec),
                motivos = COALESCE(?, motivos),
                condicao_servico = COALESCE(?, condicao_servico),
                data_conclusao = CASE WHEN ? = 'Concluído' THEN ? ELSE data_conclusao END,
                executed_at = COALESCE(?, executed_at),
                synced_at = ?,
                idempotency_key = COALESCE(?, idempotency_key),
                dosage_amount = COALESCE(?, dosage_amount),
                dosage_unit = COALESCE(?, dosage_unit),
                qtd_real = COALESCE(?, qtd_real),
                materiais = COALESCE(?, materiais)
                WHERE id = ?")
                ->execute([
                    $status,
                    $now,
                    $this->user['id'],
                    $cleanDataExec ?: ($status === 'Concluído' ? $executionDateOnly : null),
                    $cleanHorasExec,
                    $cleanMinutosExec,
                    $cleanCodExec,
                    $cleanConc,
                    $cleanPh,
                    $cleanAgua,
                    $cleanObsExec,
                    $cleanMotivos,
                    $cleanCondicao,
                    $status,
                    $completionDate,
                    $executedAt,
                    $syncedAt,
                    $idempotencyKey,
                    $dosageAmount,
                    $dosageUnit,
                    $cleanQtdReal,
                    $materials !== null ? json_encode($materials) : null,
                    $orderId
                ]);

            $lowStock = false;
            if ($status === 'Concluído' && $existing['situacao'] !== 'Concluído') {
                AgentFeedback::setOutcomeForOrder($db, (int) $input['id'], 'completed');
                $finalMaterials = $materials ?: json_decode($existing['materiais'] ?: '[]', true) ?: [];

                try {
                    IntegrationQueue::enqueue($db, 'SAP', 'order_completion', [
                        'order_id' => $orderId,
                        'status' => 'Concluído',
                        'executed_at' => $completionDate,
                        'synced_at' => $syncedAt,
                        'idempotency_key' => $idempotencyKey,
                        'worker_id' => $this->user['id'] ?? 0,
                        'worker_name' => $this->user['nome'] ?? $this->user['name'] ?? '',
                        'materials' => $finalMaterials,
                        'dosage_amount' => $dosageAmount,
                        'dosage_unit' => $dosageUnit
                    ], 'ORD-' . $orderId);
                } catch (Throwable $e) {}
                $applied = $this->appliedLubricant($input, $finalMaterials);
                $aid = $existing['ativo_id'] ?? null;
                if ($aid && $applied !== '') {
                    GreaseCompatibility::guardOnOrder($db, $aid, $applied, !empty($input['confirm_purge']), $this->user);
                }
                if (empty($finalMaterials) && $dosageAmount) {
                    $finalMaterials = $this->materialsFromDosage($db, $aid, $dosageAmount, $dosageUnit);
                }
                $lowStock = false;
                if (!empty($finalMaterials)) {
                    $lowStock = (bool) $this->deductStockNonBlocking($db, $finalMaterials, $input['id']);
                    foreach ($finalMaterials as $item) {
                        $qtd = floatval($item['qtd'] ?? 0);
                        $nome = $item['nome'] ?? $item['material'] ?? 'item';
                        if ($qtd > 0) {
                            DB::log('SYSTEM', 'INVENTORY_DEDUCT', "OS #{$input['id']} consumiu {$qtd} de {$nome}");
                        }
                    }
                }
                if ($aid) {
                    $stmtFull = $db->prepare("SELECT * FROM ordens WHERE id = ?");
                    $stmtFull->execute([$input['id']]);
                    $fullOrder = $stmtFull->fetch(PDO::FETCH_ASSOC);
                    if ($fullOrder) {
                        $this->onOrderCompleted($db, (int)$aid, $fullOrder);
                    }
                }
            }
            // Reabertura pelo trabalhador (ex: desmarcar "concluído"): restaura o estoque
            // baixado anteriormente, mantendo o inventário consistente.
            elseif ($status !== 'Concluído' && $existing['situacao'] === 'Concluído') {
                AgentFeedback::setOutcomeForOrder($db, (int) $input['id'], 'reopened');
                $finalMaterials = $materials ?: json_decode($existing['materiais'] ?: '[]', true) ?: [];
                if (!empty($finalMaterials)) {
                    $this->restoreStockConsumption($db, $finalMaterials);
                    DB::log('SYSTEM', 'INVENTORY_RESTORE', "OS #{$input['id']} reaberta, estoque restaurado.");
                }
            }

            DB::log($this->user['id'], 'EXECUTE_OS', 'ordens:' . $input['id'], $existing, $input);
            return ['id' => $input['id'], 'new_sync' => $now, 'success' => true, 'low_stock_alert' => !empty($lowStock)];
        });
    }

    /**
     * Callback acionado na conclusão de uma O.S. para recálculo do ciclo de vida do ativo.
     */
    private function onOrderCompleted($db, int $ativoId, array $order)
    {
        if ($ativoId <= 0) return;

        $today = date('Y-m-d');

        // 1. Carrega dados atuais do ativo
        $stmtAsset = $db->prepare("SELECT id, dados_tecnicos FROM ativos WHERE id = ?");
        $stmtAsset->execute([$ativoId]);
        $asset = $stmtAsset->fetch(PDO::FETCH_ASSOC);
        if (!$asset) return;

        $tech = json_decode($asset['dados_tecnicos'] ?: '[]', true) ?: [];

        // 2. Registra data da última intervenção
        $tech['data_ultima_intervencao'] = $today;

        // 3. Registra e compara consumo planejado vs real de lubrificante
        $plannedQty = floatval($tech['qtd_material'] ?? $tech['quantidade'] ?? 0);
        $consumedQty = 0;
        if (!empty($order['qtd_real'])) {
            $consumedQty = floatval(preg_replace('/[^0-9.]/', '', $order['qtd_real']));
        }
        if ($consumedQty <= 0 && !empty($order['materiais'])) {
            $materials = is_string($order['materiais']) ? json_decode($order['materiais'], true) : $order['materiais'];
            if (is_array($materials) && !empty($materials[0]['qtd'])) {
                $consumedQty = floatval($materials[0]['qtd']);
            }
        }
        if ($plannedQty <= 0) {
            $plannedQty = $consumedQty;
        }

        $currConsumo = floatval($tech['consumo_acumulado'] ?? 0);
        $tech['consumo_acumulado'] = $currConsumo + $consumedQty;

        // Histórico de consumo planejado x real no ativo
        $historicoConsumo = $tech['historico_consumo'] ?? [];
        if (!is_array($historicoConsumo)) $historicoConsumo = [];
        $historicoConsumo[] = [
            'os_id' => $order['id'] ?? null,
            'data' => $today,
            'planejado' => $plannedQty,
            'real' => $consumedQty,
            'unidade' => $tech['unid_material'] ?? $tech['unidade'] ?? 'g',
            'material' => $tech['material'] ?? 'Lubrificante'
        ];
        // Mantém os últimos 50 registros de execução
        if (count($historicoConsumo) > 50) {
            $historicoConsumo = array_slice($historicoConsumo, -50);
        }
        $tech['historico_consumo'] = $historicoConsumo;

        DB::log('SYSTEM', 'CONSUMO_LUBRIFICANTE', "OS #{$order['id']} Ativo #{$ativoId}: Planejado={$plannedQty}, Real={$consumedQty}");

        // 4. Próxima intervenção conforme período cadastrado no ponto fixo
        $freqDays = LubricationSchedule::frequencyDays($tech);
        if ($freqDays !== null && $freqDays > 0) {
            $tech['data_proxima_intervencao'] = date('Y-m-d', strtotime("+{$freqDays} days"));
        }

        // 5. Recalcula a saúde do ativo ("Excelente", "Atenção", "Crítica") baseada em OSs abertas/atrasadas
        $stmtOverdue = $db->prepare("SELECT COUNT(*) FROM ordens WHERE ativo_id = ? AND situacao != 'Concluído' AND data_planejada < ?");
        $stmtOverdue->execute([$ativoId, $today]);
        $overdueCount = (int)$stmtOverdue->fetchColumn();

        if ($overdueCount == 0) {
            $tech['saude_ativo'] = 'Excelente';
        } elseif ($overdueCount <= 2) {
            $tech['saude_ativo'] = 'Atenção';
        } else {
            $tech['saude_ativo'] = 'Crítica';
        }

        // Atualiza 'dados_tecnicos' no banco de dados
        $updatedTechJson = json_encode($tech);
        $db->prepare("UPDATE ativos SET dados_tecnicos = ? WHERE id = ?")->execute([$updatedTechJson, $ativoId]);

        // Sincroniza tabela 'ativos_lubrificacao'
        if (class_exists('LubricationTechSync')) {
            LubricationTechSync::syncAsset($db, $ativoId);
        }
    }

    private function appliedLubricant(array $input, array $materials = []): string
    {
        $sap = trim((string) ($input['materiais_sap'] ?? ''));
        if ($sap !== '') {
            return $sap;
        }
        if (!empty($materials[0]) && is_array($materials[0])) {
            return trim((string) ($materials[0]['nome'] ?? $materials[0]['material'] ?? ''));
        }
        return trim((string) ($materials[0]['nome'] ?? $materials[0]['material'] ?? ''));
    }

    /**
     * Permite a baixa no inventário sem interromper a conclusão da O.S.,
     * registrando logs de aviso caso o saldo fique zerado ou negativo.
     */
    private function deductStockNonBlocking($db, $materials, $orderId)
    {
        if ($this->stockAlreadyDeducted($db, $orderId)) {
            return false;
        }
        if (empty($materials) || !is_array($materials)) {
            return false;
        }
        $low = false;
        try {
            $low = $this->processStockConsumption($db, $materials, $orderId);
        } catch (Exception $stkEx) {
            $errData = json_decode($stkEx->getMessage(), true);
            if (is_array($errData) && ($errData['error_code'] ?? '') === 'STOCK_LOW') {
                DB::log('SYSTEM', 'INVENTORY_NEGATIVE_ALERT', "OS #{$orderId}: Baixa efetuada com saldo insuficiente. " . ($errData['message'] ?? ''));
                foreach ($materials as $item) {
                    $catId = $item['catalog_id'] ?? $item['id'] ?? null;
                    $qtyNeeded = floatval($item['qtd'] ?? 0);
                    if ($catId && $qtyNeeded > 0) {
                        $db->prepare("UPDATE catalogo SET estoque_atual = estoque_atual - ? WHERE id = ?")->execute([$qtyNeeded, $catId]);
                        $stmtRem = $db->prepare("SELECT nome, estoque_atual FROM catalogo WHERE id = ?");
                        $stmtRem->execute([$catId]);
                        $rem = $stmtRem->fetch(PDO::FETCH_ASSOC);
                        if ($rem && floatval($rem['estoque_atual']) <= 5) {
                            $low = true;
                            DB::log('SYSTEM', 'STOCK_NEGATIVE_WARNING', "Estoque baixo para {$rem['nome']}: saldo atual de {$rem['estoque_atual']}.");
                        }
                    }
                }
            } else {
                throw $stkEx;
            }
        }
        return $low;
    }

    private function stockAlreadyDeducted($db, $orderId): bool
    {
        if (!$orderId) {
            return false;
        }
        require_once __DIR__ . '/InventoryController.php';
        return InventoryController::orderAlreadyDeducted($db, $orderId);
    }

    /** Monta a linha de consumo a partir da dosagem quando a O.S. não traz materiais. */
    private function materialsFromDosage($db, $ativoId, $amount, $unit): array
    {
        $amount = floatval($amount);
        if ($amount <= 0 || !$ativoId) {
            return [];
        }
        $stmt = $db->prepare("SELECT dados_tecnicos FROM ativos WHERE id = ?");
        $stmt->execute([$ativoId]);
        $tech = json_decode((string) $stmt->fetchColumn(), true);
        if (!is_array($tech)) {
            return [];
        }
        $name = trim((string) ($tech['material'] ?? $tech['lubrificante'] ?? ''));
        if ($name === '') {
            return [];
        }
        $find = $db->prepare("SELECT id, nome FROM catalogo WHERE LOWER(nome) LIKE LOWER(?) LIMIT 1");
        $find->execute(['%' . $name . '%']);
        $cat = $find->fetch(PDO::FETCH_ASSOC);
        if (!$cat) {
            return [];
        }
        $qty = $amount;
        $unit = strtolower(trim((string) $unit));
        if (in_array($unit, ['g', 'ml'], true)) {
            $qty = $amount / 1000;
        }
        return [[
            'catalog_id' => $cat['id'],
            'qtd' => $qty,
            'nome' => $cat['nome'],
        ]];
    }

    /**
     * Senior Logic: Process Stock and Validate Availability
     * Throws Exception with Metadata for Marketplace if failed.
     */
    private function processStockConsumption($db, $materials, $orderId)
    {
        // 1. Check if we already deducted for this specific order to avoid double-dipping?
        // For simplicity in this scope: We assume 'Concluído' flow happens once. 
        // Real-world would need a ledger.

        $missing = [];
        $low = false;

        foreach ($materials as $item) {
            $catId = $item['catalog_id'] ?? $item['id'] ?? null; // Resilience
            $qtyNeeded = floatval($item['qtd'] ?? 0);

            if (!$catId || $qtyNeeded <= 0)
                continue;

            // Lock & Check
            $stmt = $db->prepare("SELECT nome, estoque_atual FROM catalogo WHERE id = ?");
            $stmt->execute([$catId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product)
                continue; // Item deleted?

            if ($product['estoque_atual'] < $qtyNeeded) {
                $missing[] = [
                    'name' => $product['nome'],
                    'required' => $qtyNeeded,
                    'available' => $product['estoque_atual']
                ];
            }
        }

        if (!empty($missing)) {
            $msg = "Estoque insuficiente para: " . implode(", ", array_column($missing, 'name'));
            throw new Exception(json_encode([
                'error_code' => 'STOCK_LOW',
                'message' => $msg,
                'details' => $missing,
                'suggestion' => 'Comprar no Marketplace'
            ]));
        }

        // 2. Deduct & Check Low Stock Warning
        foreach ($materials as $item) {
            $catId = $item['catalog_id'] ?? $item['id'] ?? null;
            if (!$catId) continue;
            $qtyNeeded = floatval($item['qtd'] ?? 0);
            if ($qtyNeeded <= 0)
                continue;

            $db->prepare("UPDATE catalogo SET estoque_atual = estoque_atual - ? WHERE id = ?")
                ->execute([$qtyNeeded, $catId]);

            // Check if stock is now below minimum threshold (e.g. 5)
            $stmtRem = $db->prepare("SELECT nome, estoque_atual FROM catalogo WHERE id = ?");
            $stmtRem->execute([$catId]);
            $rem = $stmtRem->fetch(PDO::FETCH_ASSOC);
            if ($rem && floatval($rem['estoque_atual']) <= 5) {
                DB::log('SYSTEM', 'LOW_STOCK_ALERT', "Estoque crítico para {$rem['nome']}: resta(m) apenas {$rem['estoque_atual']} unidade(s).");
                $low = true;
            }
        }
        return $low;
    }

    /**
     * Devolve ao estoque os materiais previamente baixados por uma OS que foi reaberta
     * (situação deixa de ser 'Concluído'). Espelha processStockConsumption() sem as
     * validações de disponibilidade, já que aqui estamos apenas revertendo uma baixa.
     */
    private function restoreStockConsumption($db, $materials)
    {
        foreach ($materials as $item) {
            $catId = $item['catalog_id'] ?? $item['id'] ?? null;
            if (!$catId) continue;
            $qty = floatval($item['qtd'] ?? 0);
            if ($qty <= 0) continue;

            $db->prepare("UPDATE catalogo SET estoque_atual = estoque_atual + ? WHERE id = ?")
                ->execute([$qty, $catId]);
        }
    }

    // Helper to ensure schema (lightweight migration)
    private function ensureColumn($table, $col, $def)
    {
        // Check if exists
        $cols = $this->db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($col, $cols)) {
            $this->db->exec("ALTER TABLE $table ADD COLUMN $col $def");
        }
    }

    /**
     * Trabalhador pode executar: atribuído a ele, fila aberta (sem usuário) ou O.S. de rotina do agente.
     */
    private function workerCanExecuteOrder(array $order, int $userId, string $userName): bool
    {
        $assignedId = (int) ($order['usuarios_id'] ?? 0);
        $resp = trim((string) ($order['responsavel'] ?? ''));
        if ($assignedId > 0 && $assignedId === $userId) {
            return true;
        }
        if ($resp !== '' && $userName !== '' && strcasecmp($resp, $userName) === 0) {
            return true;
        }
        if ($assignedId <= 0) {
            return true;
        }
        return LubricationSchedule::isRoutineOrderRow($order);
    }

    private function upsertSystemMeta(string $key, string $value): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $this->db->prepare(
                'INSERT INTO system_meta (mkey, mval) VALUES (?, ?) ON DUPLICATE KEY UPDATE mval = VALUES(mval)'
            );
        } else {
            $stmt = $this->db->prepare('INSERT OR REPLACE INTO system_meta (mkey, mval) VALUES (?, ?)');
        }
        $stmt->execute([$key, $value]);
    }

    /**
     * Prepara agenda a partir do cadastro e gera O.S. de rotina vencidas (inclui backlog antigo).
     */
    private function ensureRoutineWorkOrders(bool $forceFullSync): array
    {
        $today = date('Y-m-d');
        $this->db->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
        $stmtMeta = $this->db->prepare('SELECT mval FROM system_meta WHERE mkey = ?');
        $stmtMeta->execute(['os_scheduler_full_sync']);
        $lastFull = $stmtMeta->fetchColumn();

        if ($forceFullSync || $lastFull !== $today) {
            LubricationSchedule::prepareTenantSchedule($this->db);
            $this->upsertSystemMeta('os_scheduler_full_sync', $today);
        } else {
            try {
                LubricationTechSync::ensureTable($this->db);
                if ((int) $this->db->query('SELECT COUNT(*) FROM ativos_lubrificacao')->fetchColumn() === 0) {
                    LubricationTechSync::backfill($this->db);
                }
            } catch (Exception $e) {
                error_log('Routine schedule prep: ' . $e->getMessage());
            }
        }

        // Agenda já sincronizada acima quando necessário — evita prepare duplicado no agente.
        return $this->runWorkOrderAgent(false, false);
    }

    /** Botão Preventivas / cron — agente de geração com sync completo. */
    public function syncRoutineWorkOrders()
    {
        LubricationSchedule::prepareTenantSchedule($this->db);
        $today = date('Y-m-d');
        $this->upsertSystemMeta('os_scheduler_full_sync', $today);
        return $this->runWorkOrderAgent(false, false);
    }

    /**
     * Agente de geração de O.S. (rotina). dry_run=1 simula sem gravar.
     */
    public function runWorkOrderAgent(bool $fullSync = true, ?bool $dryRun = null)
    {
        $role = $this->user['role'] ?? '';
        if (Permissions::isTrabalhador($role)) {
            throw new Exception('Trabalhadores não podem executar o agente de geração de O.S.');
        }

        if ($dryRun === null) {
            $dryRun = !empty($this->input['dry_run']) || !empty($this->input['simulate']);
        }

        $userLabel = $this->user['name'] ?? $this->user['nome'] ?? 'SYSTEM';
        $agent = new WorkOrderGenerationAgent($this->db, [
            'user_label' => $userLabel,
            'tenant' => $this->user['tenant'] ?? null,
        ]);

        $report = $agent->run($fullSync, (bool) $dryRun);
        return $this->attachAgentBriefing($report);
    }

    /**
     * Relatório do agente (última execução ou simulação sob demanda).
     */
    public function getWorkOrderAgentReport()
    {
        if (Permissions::isTrabalhador($this->user['role'] ?? '')) {
            throw new Exception('Acesso negado ao relatório do agente.');
        }

        $simulate = !empty($this->input['simulate']) || !empty($this->input['refresh']);
        $aiBrief = !empty($this->input['ai_brief']);

        if ($simulate) {
            $report = $this->runWorkOrderAgent(false, true);
        } else {
            $report = WorkOrderGenerationAgent::loadLastReport($this->db);
            if (!$report) {
                $agent = new WorkOrderGenerationAgent($this->db, [
                    'user_label' => $this->user['name'] ?? $this->user['nome'] ?? 'SYSTEM',
                ]);
                $report = $agent->run(false, true);
                $report = $this->attachAgentBriefing($report, false);
            } else {
                $report = $this->attachAgentBriefing($report, false);
            }
        }

        if ($aiBrief) {
            $report = $this->attachAgentBriefing($report, true);
        }

        return array_merge(['success' => true], $report);
    }

    private function attachAgentBriefing(array $report, ?bool $forceGemini = null): array
    {
        if ($forceGemini === null) {
            $forceGemini = !empty($this->input['ai_brief']);
        }
        if (!$forceGemini && !empty($report['ai_briefing'])) {
            return $report;
        }
        if (!$forceGemini) {
            $report['ai_briefing'] = WorkOrderAgentBriefing::localBriefing($report);
            $report['ai_briefing_source'] = 'local';
            return $report;
        }
        $brief = WorkOrderAgentBriefing::generate($report, true);
        $report['ai_briefing'] = $brief['text'];
        $report['ai_briefing_source'] = $brief['source'];
        if (!empty($brief['error'])) {
            $report['ai_briefing_error'] = $brief['error'];
        }
        return $report;
    }

    /** @deprecated Use runWorkOrderAgent — mantido para compatibilidade interna/cron legado */
    public function generateScheduledOrders(bool $forceRun = true)
    {
        return $this->runWorkOrderAgent($forceRun, false);
    }

    /**
     * Admin global: panorama de O.S. e último relatório do agente por tenant.
     */
    public function getTenantOsOverview()
    {
        if (!Permissions::isDeveloper($this->user['role'] ?? '')) {
            throw new Exception('Visão multi-empresa disponível apenas para administrador global.');
        }

        require_once __DIR__ . '/../includes/tenant.php';
        $tenants = array_merge([null], TenantResolver::listTenantSlugs());
        $rows = [];
        $currentTenant = TenantResolver::getCurrentTenant();

        foreach ($tenants as $slug) {
            try {
                $db = DB::getInstance($slug);
                $pendingStmt = $db->query(
                    "SELECT COUNT(*) FROM ordens WHERE situacao IN ('Pendente', 'Em Andamento', 'Aguardando Material', 'Aberta')"
                );
                $pending = (int) $pendingStmt->fetchColumn();
                $critStmt = $db->query(
                    "SELECT COUNT(*) FROM ordens WHERE situacao IN ('Pendente', 'Em Andamento', 'Aguardando Material')
                     AND (prioridade IN ('Alta', 'Crítica') OR prioridade LIKE '%rit%')"
                );
                $critical = (int) $critStmt->fetchColumn();
                $doneTodayStmt = $db->prepare(
                    "SELECT COUNT(*) FROM ordens WHERE situacao = 'Concluído'
                     AND substr(COALESCE(data_execucao, data_conclusao, last_sync, ''), 1, 10) = ?"
                );
                $doneTodayStmt->execute([date('Y-m-d')]);
                $doneToday = (int) $doneTodayStmt->fetchColumn();

                $report = WorkOrderGenerationAgent::loadLastReport($db);
                $rows[] = [
                    'tenant' => $slug,
                    'tenant_key' => $slug ?? 'admin',
                    'label' => TenantResolver::getTenantLabel($slug),
                    'is_current' => ($slug === $currentTenant) || ($slug === null && $currentTenant === null),
                    'pending_os' => $pending,
                    'critical_os' => $critical,
                    'done_today' => $doneToday,
                    'agent_ran_at' => $report['ran_at'] ?? null,
                    'agent_generated' => (int) ($report['generated_orders_count'] ?? 0),
                    'agent_due' => (int) ($report['due_points'] ?? 0),
                    'agent_scanned' => (int) ($report['scanned_points'] ?? 0),
                    'agent_version' => $report['agent_version'] ?? null,
                ];
            } catch (Exception $e) {
                $rows[] = [
                    'tenant' => $slug,
                    'tenant_key' => $slug ?? 'admin',
                    'label' => TenantResolver::getTenantLabel($slug),
                    'error' => 'Indisponível',
                ];
            }
        }

        return [
            'success' => true,
            'current_tenant' => $currentTenant,
            'tenants' => $rows,
        ];
    }

    public function deleteTask()
    {
        if (Permissions::isTrabalhador($this->user['role'] ?? '')) {
            throw new Exception("Trabalhadores não podem excluir ordens de serviço.");
        }

        if (!Permissions::isDeveloper($this->user['role'] ?? '') && !Permissions::isGestor($this->user['role'] ?? '')) {
            throw new Exception("Permissão negada.");
        }

        $id = intval($this->input['id']);
        $this->db->prepare("DELETE FROM ordens WHERE id=?")->execute([$id]);
        return ['deleted' => $id];
    }
}
