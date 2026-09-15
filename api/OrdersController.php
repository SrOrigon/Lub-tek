<?php
/**
 * LUB-TEK - Orders Controller
 * Handles Work Orders CRUD
 */

require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/grease_compatibility.php';

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

        $id = intval($input['id'] ?? 0);
        $situacao = $input['situacao'] ?? null;
        $now = date('Y-m-d H:i:s');

        // 1. ATUALIZAÇÃO PARCIAL RÁPIDA (Vinda do Kanban Drag & Drop ou API enxuta)
        if ($id > 0 && $situacao !== null && count($input) <= 3) {
            return DB::safeExecute(function ($db) use ($id, $situacao, $now) {
                // A. Busca os dados atuais da OS antes de atualizar
                $stmtOS = $db->prepare("SELECT situacao, materiais FROM ordens WHERE id = ?");
                $stmtOS->execute([$id]);
                $osAtual = $stmtOS->fetch(PDO::FETCH_ASSOC);

                if (!$osAtual) {
                    throw new Exception("Ordem de serviço não encontrada.");
                }

                $oldStatus = $osAtual['situacao'];

                // B. Atualiza o status da OS e data_conclusao se aplicável
                $stmtUpdate = $db->prepare("
                    UPDATE ordens SET 
                        situacao = ?, 
                        data_conclusao = CASE WHEN ? = 'Concluído' THEN ? ELSE data_conclusao END, 
                        last_sync = ? 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$situacao, $situacao, $now, $now, $id]);

                DB::log($this->user['name'] ?? $this->user['nome'] ?? 'SYSTEM', 'OS_STATUS_CHANGE', "OS #{$id} -> {$situacao}");

                // C. GATILHO DE INVENTÁRIO (Apenas se mudando para Concluído agora)
                if ($situacao === 'Concluído' && $oldStatus !== 'Concluído' && !empty($osAtual['materiais'])) {
                    $materials = json_decode($osAtual['materiais'], true);
                    if (is_array($materials)) {
                        $this->processStockConsumption($db, $materials, $id);
                        
                        // Auditoria detalhada da baixa de estoque
                        foreach ($materials as $item) {
                            $qtd = floatval($item['qtd'] ?? 0);
                            $nome = $item['nome'] ?? $item['material'] ?? 'item';
                            if ($qtd > 0) {
                                DB::log('SYSTEM', 'INVENTORY_DEDUCT', "OS #{$id} consumiu {$qtd} de {$nome}");
                            }
                        }
                    }
                }
                // D. REABERTURA: se a OS estava Concluída e volta para outro status,
                // o estoque baixado precisa ser devolvido (senão o inventário fica
                // permanentemente incorreto após qualquer reabertura de OS).
                elseif ($situacao !== 'Concluído' && $oldStatus === 'Concluído' && !empty($osAtual['materiais'])) {
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
                }

                return ['success' => true, 'id' => $id, 'status' => $situacao];
            });
        }

        // 2. ATUALIZAÇÃO COMPLETA (Via Formulário e edição tradicional)
        $status = ($input['done'] ?? false) ? 'Concluído' : ($input['situacao'] ?? 'Pendente');
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
            return DB::safeExecute(function ($db) use ($input, $isUpdate, $status, $now, $materials, $oldStatus, $confirmPurge, $appliedLub, $ativoId) {
                if ($status === 'Concluído' && $oldStatus !== 'Concluído' && $ativoId && $appliedLub !== '') {
                    GreaseCompatibility::guardOnOrder($db, $ativoId, $appliedLub, $confirmPurge, $this->user);
                }
                if ($status === 'Concluído' && !empty($materials) && $oldStatus !== 'Concluído') {
                    $this->processStockConsumption($db, $materials, $input['id'] ?? null);
                }
                // Reabertura: devolve ao estoque o que havia sido baixado, evitando drift
                // permanente de inventário quando uma OS Concluída é reaberta pelo formulário.
                elseif ($status !== 'Concluído' && $oldStatus === 'Concluído' && !empty($materials)) {
                    $this->restoreStockConsumption($db, $materials);
                    foreach ($materials as $item) {
                        $qtd = floatval($item['qtd'] ?? 0);
                        $nome = $item['nome'] ?? $item['material'] ?? 'item';
                        if ($qtd > 0) {
                            DB::log('SYSTEM', 'INVENTORY_RESTORE', "OS #{$input['id']} reaberta, estoque restaurado: {$qtd} de {$nome}");
                        }
                    }
                }

                $jsonMateriais = json_encode($materials);

                if ($isUpdate) {
                    $db->prepare("UPDATE ordens SET 
                        descricao=?, responsavel=?, data_planejada=?, prioridade=?, situacao=?, last_sync=?, usuarios_id=?, materiais=?,
                        ativo_id=?, ip=?, cod_serv=?, rota=?, materiais_sap=?, reserva_almox=?, num_pontos=?, complemento=?,
                        data_emissao=?, data_execucao=?, horas_exec=?, minutos_exec=?, cod_exec=?,
                        conc_percent=?, ph=?, agua_l=?, obs_exec=?, motivos=?, condicao_servico=?,
                        data_conclusao = CASE WHEN ? = 'Concluído' THEN ? ELSE data_conclusao END,
                        qtd_real = ?
                        WHERE id=?")
                        ->execute([
                            $input['desc'],
                            $input['resp'],
                            $input['date'],
                            $input['prio'],
                            $status,
                            $now,
                            $this->user['id'],
                            $jsonMateriais,
                            $input['ativo_id'] ?? null,
                            $input['ip'] ?? null,
                            $input['cod_serv'] ?? null,
                            $input['rota'] ?? null,
                            $input['materiais_sap'] ?? null,
                            $input['reserva_almox'] ?? null,
                            $input['num_pontos'] ?? 0,
                            $input['complemento'] ?? null,
                            $input['data_emissao'] ?? null,
                            $input['data_execucao'] ?? null,
                            $input['horas_exec'] ?? 0,
                            $input['minutos_exec'] ?? 0,
                            $input['cod_exec'] ?? null,
                            $input['conc_percent'] ?? 0,
                            $input['ph'] ?? 0,
                            $input['agua_l'] ?? 0,
                            $input['obs_exec'] ?? null,
                            $input['motivos'] ?? null,
                            $input['condicao_servico'] ?? null,
                            $status,
                            $now,
                            $input['qtd_real'] ?? null,
                            $input['id']
                        ]);
                } else {
                    $db->prepare("INSERT INTO ordens (
                        descricao, responsavel, data_planejada, prioridade, situacao, ativo_id, last_sync, usuarios_id, materiais,
                        ip, cod_serv, rota, materiais_sap, reserva_almox, num_pontos, complemento,
                        data_emissao, data_execucao, horas_exec, minutos_exec, cod_exec,
                        conc_percent, ph, agua_l, obs_exec, motivos, condicao_servico, data_conclusao, qtd_real
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CASE WHEN ? = 'Concluído' THEN ? ELSE NULL END,?)")
                        ->execute([
                            $input['desc'],
                            $input['resp'],
                            $input['date'],
                            $input['prio'],
                            $status,
                            $input['ativo_id'] ?? null,
                            $now,
                            $this->user['id'],
                            $jsonMateriais,
                            $input['ip'] ?? null,
                            $input['cod_serv'] ?? null,
                            $input['rota'] ?? null,
                            $input['materiais_sap'] ?? null,
                            $input['reserva_almox'] ?? null,
                            $input['num_pontos'] ?? 0,
                            $input['complemento'] ?? null,
                            $input['data_emissao'] ?? null,
                            $input['data_execucao'] ?? null,
                            $input['horas_exec'] ?? 0,
                            $input['minutos_exec'] ?? 0,
                            $input['cod_exec'] ?? null,
                            $input['conc_percent'] ?? 0,
                            $input['ph'] ?? 0,
                            $input['agua_l'] ?? 0,
                            $input['obs_exec'] ?? null,
                            $input['motivos'] ?? null,
                            $input['condicao_servico'] ?? null,
                            $status,
                            $now,
                            $input['qtd_real'] ?? null
                        ]);
                }
                $lastId = $isUpdate ? $input['id'] : $db->lastInsertId();
                return ['id' => $lastId, 'new_sync' => $now, 'success' => true];
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
        if (empty($input['id']) || strpos((string) $input['id'], 'new') !== false) {
            throw new Exception("Trabalhadores não podem criar novas ordens de serviço.");
        }

        $status = ($input['done'] ?? false) ? 'Concluído' : ($input['situacao'] ?? 'Pendente');
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("SELECT situacao, materiais, usuarios_id, responsavel, ativo_id FROM ordens WHERE id = ?");
        $stmt->execute([$input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new Exception("Ordem de serviço não encontrada.");
        }

        $userId = (int) ($this->user['id'] ?? 0);
        $userName = trim((string) ($this->user['nome'] ?? $this->user['name'] ?? ''));
        $assignedId = (int) ($existing['usuarios_id'] ?? 0);
        $resp = trim((string) ($existing['responsavel'] ?? ''));
        if ($assignedId > 0 && $assignedId !== $userId && strcasecmp($resp, $userName) !== 0) {
            throw new Exception("Esta ordem de serviço não está atribuída a você.");
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

        return DB::safeExecute(function ($db) use ($input, $status, $now, $existing, $materials) {
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
                qtd_real = COALESCE(?, qtd_real),
                materiais = COALESCE(?, materiais)
                WHERE id = ?")
                ->execute([
                    $status,
                    $now,
                    $this->user['id'],
                    $input['data_execucao'] ?? null,
                    $input['horas_exec'] ?? null,
                    $input['minutos_exec'] ?? null,
                    $input['cod_exec'] ?? null,
                    $input['conc_percent'] ?? null,
                    $input['ph'] ?? null,
                    $input['agua_l'] ?? null,
                    $input['obs_exec'] ?? null,
                    $input['motivos'] ?? null,
                    $input['condicao_servico'] ?? null,
                    $status,
                    $now,
                    $input['qtd_real'] ?? null,
                    $materials !== null ? json_encode($materials) : null,
                    $input['id']
                ]);

            if ($status === 'Concluído' && $existing['situacao'] !== 'Concluído') {
                $finalMaterials = $materials ?: json_decode($existing['materiais'] ?: '[]', true) ?: [];
                $applied = $this->appliedLubricant($input, $finalMaterials);
                $aid = $existing['ativo_id'] ?? null;
                if ($aid && $applied !== '') {
                    GreaseCompatibility::guardOnOrder($db, $aid, $applied, !empty($input['confirm_purge']), $this->user);
                }
                if (!empty($finalMaterials)) {
                    $this->processStockConsumption($db, $finalMaterials, $input['id']);
                }
            }
            // Reabertura pelo trabalhador (ex: desmarcar "concluído"): restaura o estoque
            // baixado anteriormente, mantendo o inventário consistente.
            elseif ($status !== 'Concluído' && $existing['situacao'] === 'Concluído') {
                $finalMaterials = $materials ?: json_decode($existing['materiais'] ?: '[]', true) ?: [];
                if (!empty($finalMaterials)) {
                    $this->restoreStockConsumption($db, $finalMaterials);
                    DB::log('SYSTEM', 'INVENTORY_RESTORE', "OS #{$input['id']} reaberta, estoque restaurado.");
                }
            }

            DB::log($this->user['id'], 'EXECUTE_OS', 'ordens:' . $input['id'], $existing, $input);
            return ['id' => $input['id'], 'new_sync' => $now, 'success' => true];
        });
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
     * Senior Logic: Process Stock and Validate Availability
     * Throws Exception with Metadata for Marketplace if failed.
     */
    private function processStockConsumption($db, $materials, $orderId)
    {
        // 1. Check if we already deducted for this specific order to avoid double-dipping?
        // For simplicity in this scope: We assume 'Concluído' flow happens once. 
        // Real-world would need a ledger.

        $missing = [];

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
            }
        }
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

    public function generateScheduledOrders()
    {
        if (Permissions::isTrabalhador($this->user['role'] ?? '')) {
            throw new Exception("Trabalhadores não podem gerar ordens preventivas.");
        }

        // 1. Fetch all plans
        $stmt = $this->db->query("SELECT p.*, a.nome as ativo_nome, a.tag as ativo_tag, a.user_id as asset_user_id 
                                  FROM planos p 
                                  JOIN ativos a ON p.ativo_id = a.id");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $createdCount = 0;
        $nowStr = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        foreach ($plans as $plan) {
            $planId = $plan['id'];
            $ativoId = $plan['ativo_id'];
            $freq = intval($plan['frequencia_dias']);
            $proc = $plan['procedimento'] ?? '';
            $metodo = $plan['metodo'] ?? '';
            $qty = floatval($plan['quantidade'] ?? 0);
            $catId = $plan['catalogo_id'] ?? null;
            $assetUserId = $plan['asset_user_id'] ?? 1;

            if ($freq <= 0) continue;

            // Identifier to look for in orders: [PLANO #id]
            $identifier = "[PLANO #{$planId}]";

            // 2. Check if there is an open (pending/in progress) order for this plan
            $checkOpen = $this->db->prepare("SELECT COUNT(*) FROM ordens WHERE ativo_id = ? AND situacao IN ('Pendente', 'Em Andamento') AND descricao LIKE ?");
            $checkOpen->execute([$ativoId, "%{$identifier}%"]);
            $hasOpen = $checkOpen->fetchColumn() > 0;

            if ($hasOpen) {
                // Already has a pending order, skip
                continue;
            }

            // 3. Find the last completed order for this plan
            $checkLastCompleted = $this->db->prepare("SELECT data_execucao, data_planejada FROM ordens WHERE ativo_id = ? AND situacao = 'Concluído' AND descricao LIKE ? ORDER BY data_planejada DESC, id DESC LIMIT 1");
            $checkLastCompleted->execute([$ativoId, "%{$identifier}%"]);
            $lastCompleted = $checkLastCompleted->fetch(PDO::FETCH_ASSOC);

            $lastDate = null;
            if ($lastCompleted) {
                // Use data_execucao if set, otherwise data_planejada
                $lastDate = !empty($lastCompleted['data_execucao']) ? $lastCompleted['data_execucao'] : $lastCompleted['data_planejada'];
            }

            // If no completed order exists, we check if there are ANY completed orders for this asset containing this procedure, or default to asset creation or simply today.
            $shouldGenerate = false;
            $nextPlannedDate = $today;

            if ($lastDate) {
                // Calculate next planned date
                $lastTime = strtotime($lastDate);
                if ($lastTime) {
                    $nextPlannedTime = $lastTime + ($freq * 86400);
                    $nextPlannedDate = date('Y-m-d', $nextPlannedTime);
                    if ($nextPlannedDate <= $today) {
                        $shouldGenerate = true;
                    }
                } else {
                    $shouldGenerate = true;
                }
            } else {
                // No completed order exists, generate one
                $shouldGenerate = true;
            }

            if ($shouldGenerate) {
                // Get catalog item details if linked
                $materialsJson = '[]';
                $materialSapCode = '';
                $servCode = '';
                $resAlmox = '';
                
                if ($catId) {
                    $catStmt = $this->db->prepare("SELECT nome, codigo, localizacao FROM catalogo WHERE id = ?");
                    $catStmt->execute([$catId]);
                    $catItem = $catStmt->fetch(PDO::FETCH_ASSOC);
                    if ($catItem) {
                        $materialsJson = json_encode([
                            [
                                'id' => $catId,
                                'catalog_id' => $catId,
                                'nome' => $catItem['nome'],
                                'qtd' => $qty
                            ]
                        ]);
                        $materialSapCode = $catItem['codigo'] ?? '';
                        $resAlmox = $catItem['localizacao'] ?? '';
                    }
                }

                // If asset has dados_tecnicos, we can pull additional SAP codes!
                $astStmt = $this->db->prepare("SELECT dados_tecnicos FROM ativos WHERE id = ?");
                $astStmt->execute([$ativoId]);
                $astTech = $astStmt->fetchColumn();
                if ($astTech) {
                    try {
                        $tech = json_decode($astTech, true);
                        if ($tech) {
                            if (empty($materialSapCode)) $materialSapCode = $tech['sap'] ?? '';
                            $servCode = $tech['cod_serv'] ?? '';
                            if (empty($resAlmox)) $resAlmox = $tech['almox'] ?? '';
                        }
                    } catch (Exception $ex) {}
                }

                // Build a premium work order description
                $osDesc = "{$identifier} [PREVENTIVA AUTOMÁTICA] Manutenção Planejada de Lubrificação\n" .
                          "Equipamento: {$plan['ativo_nome']} [TAG: {$plan['ativo_tag']}]\n" .
                          "Procedimento: {$proc}\n" .
                          "Método: {$metodo}\n" .
                          "Quantidade: {$qty} " . ($catId ? "unidades" : "") . "\n" .
                          "Frequência Cadastrada: {$freq} dias\n" .
                          "Data de Geração: " . date('d/m/Y') . "\n" .
                          "Ação Requerida: Executar conforme procedimento operacional padrão de lubrificação de ativos.";

                // Insert into ordens
                $insStmt = $this->db->prepare("INSERT INTO ordens (
                    descricao, responsavel, data_planejada, prioridade, situacao, ativo_id, last_sync, usuarios_id, materiais,
                    data_emissao, materiais_sap, cod_serv, reserva_almox
                ) VALUES (?, 'Manutenção Preventiva', ?, 'Média', 'Pendente', ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $insStmt->execute([
                    $osDesc,
                    $nextPlannedDate,
                    $ativoId,
                    $nowStr,
                    $assetUserId,
                    $materialsJson,
                    $today,
                    $materialSapCode,
                    $servCode,
                    $resAlmox
                ]);

                $createdCount++;
            }
        }

        return [
            'success' => true,
            'message' => "Processamento concluído. {$createdCount} novas ordens de serviço geradas.",
            'generated_orders_count' => $createdCount
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
