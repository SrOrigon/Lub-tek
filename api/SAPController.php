<?php
/**
 * LUB-TEK - SAP Integration Controller
 * Manages imports and exports for SAP PM (Plant Maintenance) and SAP MM (Materials Management)
 */

class SAPController
{
    private $db;
    private $user;
    private $input;

    /** @param mixed $raw */
    private function jsonToArray($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array{ok:bool,items?:array,error?:string} */
    private function parseItemsInput(string $field = 'items'): array
    {
        $raw = $this->input[$field] ?? [];
        if (!is_array($raw)) {
            return ['ok' => false, 'error' => "Formato inválido: '{$field}' deve ser um array."];
        }
        return ['ok' => true, 'items' => $raw];
    }

    public function __construct($db, $user, $input)
    {
        $this->db = $db;
        $this->user = $user;
        $this->input = $input;
    }

    /**
     * IMPORT: SAP PM Work Orders
     */
    public function importOrders()
    {
        $parsed = $this->parseItemsInput('items');
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => $parsed['error']];
        }
        $items = $parsed['items'];
        if (empty($items)) {
            return ['ok' => true, 'count' => 0, 'message' => 'Nenhum item fornecido.'];
        }

        $inserted = 0;
        $updated = 0;
        $errors = [];

        DB::safeExecute(function ($db) use ($items, &$inserted, &$updated, &$errors) {
            $stmtCheck = $db->prepare("SELECT id FROM ordens WHERE materiais_sap = ? AND materiais_sap IS NOT NULL AND materiais_sap != '' LIMIT 1");
            $stmtAsset = $db->prepare("SELECT id FROM ativos WHERE tag = ? OR nome = ? OR ip = ? LIMIT 1");
            $stmtCurrentAsset = $db->prepare("SELECT ativo_id FROM ordens WHERE id = ?");
            $stmtInsert = $db->prepare("INSERT INTO ordens (descricao, responsavel, data_planejada, prioridade, situacao, ativo_id, materiais_sap, reserva_almox, ip, cod_serv, complemento, last_sync, usuarios_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtUpdate = $db->prepare("UPDATE ordens SET descricao=?, responsavel=?, data_planejada=?, prioridade=?, situacao=?, ativo_id=?, reserva_almox=?, ip=?, cod_serv=?, complemento=?, last_sync=?, usuarios_id=? WHERE id=?");

            foreach ($items as $idx => $item) {
                try {
                    $sapOrder = trim($item['sap_order'] ?? $item['AUFNR'] ?? '');
                    if (empty($sapOrder)) {
                        throw new Exception("Número da ordem SAP (AUFNR) ausente ou inválido.");
                    }

                    // Look up asset by tag, name, or IP
                    $assetId = null;
                    $assetRef = trim($item['asset_ref'] ?? $item['TPLNR'] ?? $item['EQUNR'] ?? '');
                    if (!empty($assetRef)) {
                        $stmtAsset->execute([$assetRef, $assetRef, intval($assetRef)]);
                        $assetId = $stmtAsset->fetchColumn() ?: null;
                    }

                    $desc = trim($item['desc'] ?? $item['descricao'] ?? $item['KTEXT'] ?? 'Ordem SAP ' . $sapOrder);
                    $resp = trim($item['resp'] ?? $item['responsavel'] ?? $item['INGRPR'] ?? 'PCM SAP');
                    $date = trim($item['date'] ?? $item['data_planejada'] ?? $item['GSTRP'] ?? date('Y-m-d'));
                    $prio = trim($item['prio'] ?? $item['prioridade'] ?? $item['PRIOK'] ?? 'Média');
                    $sit = trim($item['situation'] ?? $item['situacao'] ?? 'Pendente');
                    $reserva = trim($item['reserva'] ?? $item['reserva_almox'] ?? $item['RSNUM'] ?? '');
                    $ip = trim($item['ip'] ?? $item['IP'] ?? $item['cip'] ?? $item['CIP'] ?? '');
                    $codServ = trim($item['cod_serv'] ?? $item['STEUS'] ?? '');
                    $compl = trim($item['complemento'] ?? $item['LTXA1'] ?? '');
                    $now = date('Y-m-d H:i:s');
                    $userId = $this->user['id'] ?? 1;

                    // Normalize Priority
                    if ($prio === '1' || stripos($prio, 'imedi') !== false || stripos($prio, 'crit') !== false) {
                        $prio = 'Crítica';
                    } elseif ($prio === '2' || stripos($prio, 'alt') !== false) {
                        $prio = 'Alta';
                    } elseif ($prio === '3' || stripos($prio, 'med') !== false || stripos($prio, 'mid') !== false) {
                        $prio = 'Média';
                    } else {
                        $prio = 'Baixa';
                    }

                    // Check if already exists in LUB-TEK by SAP order number
                    $stmtCheck->execute([$sapOrder]);
                    $existingId = $stmtCheck->fetchColumn();

                    if ($existingId) {
                        // Preserva o ativo já vinculado quando esta linha do SAP não trouxe
                        // asset_ref/TPLNR/EQUNR — sem isso, um resync que só atualiza status
                        // desvincularia o ativo (ativo_id = NULL) por engano.
                        if (empty($assetRef)) {
                            $stmtCurrentAsset->execute([$existingId]);
                            $assetId = $stmtCurrentAsset->fetchColumn() ?: null;
                        }
                        // Update
                        $stmtUpdate->execute([
                            $desc,
                            $resp,
                            $date,
                            $prio,
                            $sit,
                            $assetId,
                            $reserva,
                            $ip,
                            $codServ,
                            $compl,
                            $now,
                            $userId,
                            $existingId
                        ]);
                        $updated++;
                    } else {
                        // Insert
                        $stmtInsert->execute([
                            $desc,
                            $resp,
                            $date,
                            $prio,
                            $sit,
                            $assetId,
                            $sapOrder, // materials_sap acts as SAP order code
                            $reserva,
                            $ip,
                            $codServ,
                            $compl,
                            $now,
                            $userId
                        ]);
                        $inserted++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Linha " . ($idx + 2) . ": " . $e->getMessage();
                }
            }
        });

        return [
            'ok' => true,
            'count' => $inserted + $updated,
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors
        ];
    }

    /**
     * IMPORT: SAP MM Catalog / Materials (MM60)
     */
    public function importMaterials()
    {
        $parsed = $this->parseItemsInput('items');
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => $parsed['error']];
        }
        $items = $parsed['items'];
        if (empty($items)) {
            return ['ok' => true, 'count' => 0, 'message' => 'Nenhum material fornecido.'];
        }

        $inserted = 0;
        $updated = 0;
        $errors = [];

        DB::safeExecute(function ($db) use ($items, &$inserted, &$updated, &$errors) {
            $stmtCheck = $db->prepare("SELECT id FROM catalogo WHERE codigo = ? AND codigo IS NOT NULL AND codigo != '' LIMIT 1");
            $stmtInsert = $db->prepare("INSERT INTO catalogo (nome, tipo, codigo, fabricante, estoque_atual, localizacao, specs, descricao) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtUpdate = $db->prepare("UPDATE catalogo SET nome=?, tipo=?, fabricante=?, estoque_atual=?, localizacao=?, specs=?, descricao=? WHERE id=?");

            foreach ($items as $idx => $item) {
                try {
                    $sapCode = trim($item['codigo'] ?? $item['MATNR'] ?? '');
                    if (empty($sapCode)) {
                        throw new Exception("Código do Material SAP (MATNR) ausente ou inválido.");
                    }

                    $nome = trim($item['nome'] ?? $item['MAKTX'] ?? 'Material ' . $sapCode);
                    $tipo = trim($item['tipo'] ?? $item['MTART'] ?? 'Lubrificante');
                    $fab = trim($item['fabricante'] ?? $item['HERST'] ?? 'Indefinido');
                    $estoque = floatval($item['estoque_atual'] ?? $item['LABST'] ?? 0);
                    $loc = trim($item['localizacao'] ?? $item['LGPBE'] ?? 'Almoxarifado');
                    $desc = trim($item['descricao'] ?? $item['WGBEZ'] ?? '');
                    
                    // Specs
                    $rawSpecs = $item['specs'] ?? [];
                    $specsArray = is_array($rawSpecs) ? $rawSpecs : $this->jsonToArray($rawSpecs);
                    $specsJson = json_encode($specsArray);

                    // Check if exists
                    $stmtCheck->execute([$sapCode]);
                    $existingId = $stmtCheck->fetchColumn();

                    if ($existingId) {
                        $stmtUpdate->execute([
                            $nome,
                            $tipo,
                            $fab,
                            $estoque,
                            $loc,
                            $specsJson,
                            $desc,
                            $existingId
                        ]);
                        $updated++;
                    } else {
                        $stmtInsert->execute([
                            $nome,
                            $tipo,
                            $sapCode,
                            $fab,
                            $estoque,
                            $loc,
                            $specsJson,
                            $desc
                        ]);
                        $inserted++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Linha " . ($idx + 2) . ": " . $e->getMessage();
                }
            }
        });

        return [
            'ok' => true,
            'count' => $inserted + $updated,
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors
        ];
    }

    /**
     * EXPORT: Work Orders (SAP PM format)
     */
    public function exportOrders()
    {
        $startDate = $this->input['start_date'] ?? null;
        $endDate = $this->input['end_date'] ?? null;
        $situacao = $this->input['situacao'] ?? null;

        $sql = "SELECT o.*, a.nome as ativo_nome, a.tag as ativo_tag, a.ip as ativo_ip 
                FROM ordens o 
                LEFT JOIN ativos a ON o.ativo_id = a.id 
                WHERE 1=1";
        $params = [];

        if (!empty($startDate)) {
            $sql .= " AND o.data_planejada >= ?";
            $params[] = $startDate;
        }
        if (!empty($endDate)) {
            $sql .= " AND o.data_planejada <= ?";
            $params[] = $endDate;
        }
        if (!empty($situacao)) {
            $sql .= " AND o.situacao = ?";
            $params[] = $situacao;
        }

        $sql .= " ORDER BY o.data_planejada DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = [];
        foreach ($orders as $o) {
            // Process materials array
            $matsRaw = $o['materiais'] ?? '';
            $matsArray = $this->jsonToArray($matsRaw ?: '[]');
            $matsText = [];
            foreach ($matsArray as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $code = $m['codigo'] ?? '';
                $qty = $m['quantidade'] ?? $m['qtd'] ?? 0;
                $unit = $m['unidade'] ?? 'g';
                if (!empty($code)) {
                    $matsText[] = "$code ($qty $unit)";
                }
            }

            $formatted[] = [
                'ID_LOCAL' => $o['id'],
                'ORDEM_SAP' => $o['materiais_sap'] ?: '', // mapped to materials_sap in platform
                'DESCRICAO' => $o['descricao'],
                'RESPONSAVEL' => $o['responsavel'],
                'DATA_PLANEJADA' => $o['data_planejada'],
                'DATA_EXECUCAO' => $o['data_execucao'] ?: '',
                'PRIORIDADE' => $o['prioridade'],
                'SITUACAO' => $o['situacao'],
                'ATIVO_TAG' => $o['ativo_tag'] ?: '',
                'ATIVO_NOME' => $o['ativo_nome'] ?: '',
                'ATIVO_IP' => $o['ativo_ip'] ?: '',
                'RESERVA_ALMOX' => $o['reserva_almox'] ?: '',
                'IP' => $o['ip'] ?: '',
                'COD_SERVICO' => $o['cod_serv'] ?: '',
                'COMPLEMENTO' => $o['complemento'] ?: '',
                'HORAS_EXEC' => $o['horas_exec'] ?? 0,
                'MINUTOS_EXEC' => $o['minutos_exec'] ?? 0,
                'CONSUMOS_SAP' => implode(', ', $matsText),
                'OBSERVACOES_TECNICAS' => $o['obs_exec'] ?: ''
            ];
        }

        return $formatted;
    }

    /**
     * EXPORT: Assets (Functional Locations Layout)
     */
    public function exportAssets()
    {
        $stmt = $this->db->query("SELECT a1.*, a2.nome as pai_nome, a2.tag as pai_tag 
                                  FROM ativos a1 
                                  LEFT JOIN ativos a2 ON a1.pai_id = a2.id 
                                  ORDER BY a1.tipo ASC, a1.nome ASC");
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = [];
        foreach ($assets as $a) {
            $formatted[] = [
                'ID' => $a['id'],
                'TAG' => $a['tag'] ?: '',
                'NOME' => $a['nome'],
                'TIPO' => ucfirst($a['tipo']),
                'PAI_ID' => $a['pai_id'] ?: '',
                'PAI_TAG' => $a['pai_tag'] ?: '',
                'PAI_NOME' => $a['pai_nome'] ?: '',
                'IP' => $a['ip'] ?: '',
                'FABRICANTE' => $a['fabricante'] ?: '',
                'MODELO' => $a['modelo'] ?: '',
                'NUM_SERIE' => $a['num_serie'] ?: '',
                'OBSERVACOES' => $a['obs'] ?: ''
            ];
        }
        return $formatted;
    }

    /**
     * EXPORT: Materials (MM60 layout)
     */
    public function exportMaterials()
    {
        $stmt = $this->db->query("SELECT * FROM catalogo ORDER BY nome ASC");
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = [];
        foreach ($materials as $m) {
            $specsArray = $this->jsonToArray($m['specs'] ?? '{}');
            $specsText = [];
            foreach ($specsArray as $k => $v) {
                $specsText[] = "$k: $v";
            }

            $formatted[] = [
                'ID' => $m['id'],
                'CODIGO_SAP' => $m['codigo'] ?: '',
                'NOME' => $m['nome'],
                'TIPO' => $m['tipo'] ?: '',
                'FABRICANTE' => $m['fabricante'] ?: '',
                'ESTOQUE_ATUAL' => $m['estoque_atual'] ?? 0,
                'LOCALIZACAO' => $m['localizacao'] ?: '',
                'DESCRICAO' => $m['descricao'] ?: '',
                'ESPECIFICACOES' => implode(' | ', $specsText)
            ];
        }
        return $formatted;
    }
}
