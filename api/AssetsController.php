<?php
/**
 * LUB-TEK - Asset Controller
 * Handles Asset CRUD, Tree Generation, and Analysis
 */

require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/lubrication_tech_sync.php';
require_once __DIR__ . '/../includes/grease_compatibility.php';
require_once __DIR__ . '/../includes/lubrication_schedule.php';

class AssetsController
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

    private static $seeding = false;

    /** @var array<int,int|null>|null id => pai_id */
    private ?array $ativoParentMap = null;

    private function getAtivoParentMap(): array
    {
        if ($this->ativoParentMap !== null) {
            return $this->ativoParentMap;
        }
        $map = [];
        try {
            $stmt = $this->db->query('SELECT id, pai_id FROM ativos');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = (int) $row['id'];
                $pai = $row['pai_id'];
                $map[$id] = ($pai === null || $pai === '') ? null : (int) $pai;
            }
        } catch (Exception $e) {
            DB::logError($e);
        }
        $this->ativoParentMap = $map;
        return $this->ativoParentMap;
    }

    public function getTree()
    {
        // 1. Fetch Aggregated Stats (Optimized Query)
        try {
            $today = date('Y-m-d');
            $sqlStats = "SELECT ativo_id, 
                               COUNT(*) as os_count,
                               MAX(CASE 
                                   WHEN (prioridade = 'Crítica') OR (data_planejada < ?) THEN 2 
                                   WHEN (prioridade IN ('Alta', 'Média')) THEN 1 
                                   ELSE 0 
                               END) as max_score 
                        FROM ordens 
                        WHERE situacao != 'Concluído' 
                        GROUP BY ativo_id";

            $stmtStats = $this->db->prepare($sqlStats);
            $stmtStats->execute([$today]);
            $statsRaw = $stmtStats->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (Exception $e) {
            DB::logError($e);
            $statsRaw = [];
        }

        $assetStats = [];
        foreach ($statsRaw as $row) {
            $assetStats[$row['ativo_id']] = [
                'count' => (int)$row['os_count'],
                'score' => (int)$row['max_score']
            ];
        }

        // 2. Fetch Assets (ISOLATION LOGIC)
        if (!$this->user)
            return [];

        $sqlOrder = "ORDER BY CASE tipo 
            WHEN 'unidade' THEN 0 
            WHEN 'setor' THEN 1 
            WHEN 'equipamento' THEN 2 
            WHEN 'componente' THEN 3 
            WHEN 'ponto' THEN 4 
            ELSE 99 END, nome ASC";

        // Fetch all assets (unrestricted view for single-enterprise factory model)
        $all = $this->db->query("SELECT * FROM ativos WHERE (nome IS NOT NULL AND nome != '' AND nome != 'Sem Nome') $sqlOrder")->fetchAll();

        // Auto-seed default Digital Twin factory hierarchy if empty (apenas modo admin/demo, nunca para tenants)
        if (empty($all) && TenantResolver::getCurrentTenant() === null) {
            $seedFile = __DIR__ . '/../seed.php';
            if (file_exists($seedFile)) {
                require_once $seedFile;
                if (class_exists('Seeder')) {
                    Seeder::seedAssets($this->db);
                    $all = $this->db->query("SELECT * FROM ativos WHERE (nome IS NOT NULL AND nome != '' AND nome != 'Sem Nome') $sqlOrder")->fetchAll();
                }
            }
        }

        $map = [];
        $tree = [];
        
        foreach ($all as &$node) {
            $node['children'] = [];
            $stat = $assetStats[$node['id']] ?? ['count' => 0, 'score' => 0];
            $node['os_direct'] = $stat['count'];
            $node['health_direct'] = $stat['score'];

            // Se apontar para arquivo local inexistente, limpa para evitar requisições 404
            if (!empty($node['imagem']) && !preg_match('#^https?://#i', $node['imagem'])) {
                $cleanPath = ltrim(str_replace('\\', '/', $node['imagem']), '/');
                $fullPath = __DIR__ . '/../' . $cleanPath;
                if (!file_exists($fullPath)) {
                    $node['imagem'] = '';
                }
            }

            $map[$node['id']] = & $node;
        }
        unset($node);

        foreach ($all as &$node) {
            if ($node['pai_id'] && isset($map[$node['pai_id']])) {
                $map[$node['pai_id']]['children'][] = & $node;
            }
            else {
                $tree[] = & $node;
            }
        }
        unset($node);

        // Recursive Processing (With Cycle Detection)
        $processNode = function (&$node, $depth = 0, $visited = []) use (&$processNode) {
            if ($depth > 50 || in_array($node['id'], $visited))
                return ['children_count' => 0, 'os_count' => 0, 'health' => 0]; 

            $visited[] = $node['id'];
            $myChildrenCount = 0;
            $myOSCount = $node['os_direct'] ?? 0;
            $myHealth = $node['health_direct'] ?? 0;

            foreach ($node['children'] as &$child) {
                $stats = $processNode($child, $depth + 1, $visited);
                $myChildrenCount += 1 + $stats['children_count'];
                $myOSCount += $stats['os_count'];
                if ($stats['health'] > $myHealth)
                    $myHealth = $stats['health'];
            }

            $node['total_children'] = $myChildrenCount;
            $node['os_recursive'] = $myOSCount;
            $node['health_recursive'] = $myHealth;

            return ['children_count' => $myChildrenCount, 'os_count' => $myOSCount, 'health' => $myHealth];
        };

        foreach ($tree as &$root) {
            $processNode($root, 0, []);
        }

        return $tree;
    }

    private function validateAssetInput($input)
    {
        if (empty($input['nome']) || $input['nome'] === 'Sem Nome')
            throw new Exception('O nome do ativo é obrigatório.');
        if (empty($input['tipo']))
            throw new Exception('Selecione um Tipo válido.');
    }

    private function validateHierarchy($id, $parentId)
    {
        if ($parentId && $id) {
            if ($id == $parentId) {
                throw new Exception("Um ativo não pode ser pai de si mesmo.");
            }
            if ($this->isDescendant($id, $parentId)) {
                throw new Exception("Ciclo detectado: O novo pai é um descendente deste ativo.");
            }
        }
    }

    // Fix 1 Helper: Check for Cycle
    private function isDescendant($targetId, $potentialAncestorId)
    {
        $map = $this->getAtivoParentMap();
        $current = $potentialAncestorId;
        $steps = 0;
        while ($current && $steps < 100) {
            if ((int) $current === (int) $targetId) {
                return true;
            }
            $current = $map[(int) $current] ?? null;
            $steps++;
        }
        return false;
    }

    public function saveAsset()
    {
        if (Permissions::isTrabalhador($this->user['role'] ?? '')) {
            throw new Exception("Trabalhadores não podem alterar ativos.");
        }

        $input = $this->input;
        $this->validateAssetInput($input);

        $specsArr = $this->parseBearingSpecs($input['nome']);
        // PROTECT EXISTING SPECS / MEDIA
        $existingSpecs = null;
        $prevRow = null;
        if (isset($input['id']) && !empty($input['id'])) {
            $prevStmt = $this->db->prepare("SELECT imagem, imagem_3d, dados_tecnicos, json_specs, qr_hash, is_food_grade_required FROM ativos WHERE id = ?");
            $prevStmt->execute([$input['id']]);
            $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $existingSpecs = $prevRow['json_specs'] ?? null;
        }

        $rawDt = $input['dados_tecnicos'] ?? [];
        $decodedDt = is_array($rawDt) ? $rawDt : (json_decode($rawDt, true) ?: []);
        if (is_string($decodedDt)) {
            $decodedDt = json_decode($decodedDt, true) ?: [];
        }

        // MERGE-PATCH: preserva chaves de dados_tecnicos não reenviadas por este formulário
        // (ex: 'model_3d' anexado via visualizador 3D, que usa endpoint dedicado). Sem isso,
        // qualquer salvamento básico do ativo apagaria a referência do modelo 3D.
        if ($prevRow && !empty($prevRow['dados_tecnicos'])) {
            $existingDt = json_decode($prevRow['dados_tecnicos'], true);
            if (is_array($existingDt)) {
                $decodedDt = array_merge($existingDt, $decodedDt);
            }
        }

        if ($specsArr) {
            $decodedDt = array_merge($decodedDt, [
                'bearing_model' => $specsArr['model'],
                'bearing_d' => $specsArr['d'],
                'bearing_D' => $specsArr['D'],
                'bearing_dm' => $specsArr['dm']
            ]);
            $json_specs = json_encode($specsArr);
        }
        else {
            $json_specs = $existingSpecs ?: '{}';
        }

        $dados = json_encode($decodedDt);
        $currentUser = $this->user;
        $newId = $input['id'] ?? null;
        $previousMediaPaths = [];

        if ($prevRow) {
            $previousMediaPaths = UploadHelper::collectAssetMediaPaths($prevRow);
        }

        try {
            $confirmPurge = !empty($input['confirm_purge']);
            $userRef = $currentUser;
            DB::safeExecute(function ($db) use ($input, $dados, $json_specs, $currentUser, &$newId, $decodedDt, $confirmPurge, $userRef, $prevRow) {
                $parentId = (!empty($input['pai_id']) && $input['pai_id'] !== 'null') ? intval($input['pai_id']) : NULL;

                // Hierarchy Validation
                $this->validateHierarchy($input['id'] ?? null, $parentId);

                if (isset($input['id']) && !empty($input['id'])) {
                    GreaseCompatibility::guardOnAsset($db, (int) $input['id'], $decodedDt, $confirmPurge, $userRef);
                    // Update Logic ...
                    if ($currentUser['role'] !== 'developer') {
                    /* Access Check Omitted for Brevity */
                    }

                    // IMMUTABLE IP LOGIC
                    // 1. Fetch current IP state from DB
                    $currStmt = $db->prepare("SELECT ip FROM ativos WHERE id = ?");
                    $currStmt->execute([$input['id']]);
                    $currentDbIp = $currStmt->fetchColumn();

                    $ipToSave = null;
                    $dtArr = json_decode($dados, true) ?: [];

                    if ($currentDbIp && $currentDbIp > 0) {
                        // IMMUTABLE: Keep existing DB IP, ignore input
                        $ipToSave = intval($currentDbIp);

                        // Force JSON to match Reality (Anti-tamper)
                        $dtArr['ip'] = $ipToSave;
                        $dados = json_encode($dtArr);
                    }
                    else {
                        // ASSIGNABLE: If DB has no IP, take from input or AUTO-GENERATE
                        if (isset($dtArr['ip']) && is_numeric($dtArr['ip']) && $dtArr['ip'] > 0) {
                            $ipToSave = intval($dtArr['ip']);
                        }
                        else {
                            // AUTO-GENERATE SEQUENCE
                            $maxSeq = $db->query("SELECT MAX(ip) FROM ativos")->fetchColumn();
                            $ipToSave = ($maxSeq && $maxSeq > 0) ? $maxSeq + 1 : 1000;

                            // Sync into JSON
                            $dtArr['ip'] = $ipToSave;
                            $dados = json_encode($dtArr);
                        }
                    }

                    // QR Code Desacoplado & Metadados de Auditoria Grau Alimentício (H1)
                    $qrHashToSave = trim($input['qr_hash'] ?? ($prevRow['qr_hash'] ?? ''));
                    if (empty($qrHashToSave)) {
                        $qrHashToSave = bin2hex(random_bytes(16));
                    }
                    $isFoodGrade = (!empty($input['is_food_grade_required']) || !empty($decodedDt['is_food_grade_required'])) ? 1 : 0;
                    $dtArr['qr_hash'] = $qrHashToSave;
                    $dtArr['is_food_grade_required'] = (bool) $isFoodGrade;
                    $dados = json_encode($dtArr);

                    // 2. Perform Update ensuring IP, QR and Food-Grade sync
                    $imagemToSave = array_key_exists('imagem', $input) ? ($input['imagem'] ?? '') : ($prevRow['imagem'] ?? '');
                    $stmt = $db->prepare("UPDATE ativos SET nome=?, tag=?, tipo=?, pai_id=?, obs=?, dados_tecnicos=?, imagem=?, json_specs=?, fabricante=?, modelo=?, num_serie=?, ip=?, qr_hash=?, is_food_grade_required=? WHERE id=?");
                    $stmt->execute([
                        $input['nome'],
                        $input['tag'] ?? '',
                        $input['tipo'],
                        $parentId,
                        $input['obs'] ?? '',
                        $dados, // Updated JSON (Force-synced)
                        $imagemToSave,
                        $json_specs,
                        $input['fabricante'] ?? '',
                        $input['modelo'] ?? '',
                        $input['num_serie'] ?? '',
                        $ipToSave, // Synced Column
                        $qrHashToSave,
                        $isFoodGrade,
                        $input['id']
                    ]);
                    $newId = $input['id'];
                }
                else {
                    // Insert Logic ...
                    // STRATEGY: Global Sequential IP (Avoids fragmentation)
                    $max = $db->query("SELECT MAX(ip) FROM ativos")->fetchColumn();
                    $newIp = ($max && $max > 0) ? $max + 1 : 1000;

                    $qrHashToSave = trim($input['qr_hash'] ?? '');
                    if (empty($qrHashToSave)) {
                        $qrHashToSave = bin2hex(random_bytes(16));
                    }
                    $isFoodGrade = (!empty($input['is_food_grade_required']) || !empty($decodedDt['is_food_grade_required'])) ? 1 : 0;

                    // Sync IP, QR and Food Grade into Technical Data JSON for consistency
                    $dtArr = json_decode($dados, true) ?: [];
                    $dtArr['ip'] = $newIp;
                    $dtArr['qr_hash'] = $qrHashToSave;
                    $dtArr['is_food_grade_required'] = (bool) $isFoodGrade;
                    $dados = json_encode($dtArr);

                    $stmt = $db->prepare("INSERT INTO ativos (nome, tag, tipo, pai_id, obs, dados_tecnicos, imagem, ip, json_specs, fabricante, modelo, num_serie, user_id, qr_hash, is_food_grade_required) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([
                        $input['nome'],
                        $input['tag'] ?? '',
                        $input['tipo'],
                        $parentId,
                        $input['obs'] ?? '',
                        $dados,
                        $input['imagem'] ?? '',
                        $newIp,
                        $json_specs,
                        $input['fabricante'] ?? '',
                        $input['modelo'] ?? '',
                        $input['num_serie'] ?? '',
                        $currentUser['id'],
                        $qrHashToSave,
                        $isFoodGrade
                    ]);
                    $newId = $db->lastInsertId();
                }
                if ($newId) {
                    LubricationTechSync::syncAsset($db, (int) $newId);
                    LubricationSchedule::refreshCadastroDates($db, (int) $newId);
                }
            });
        }
        catch (PDOException $e) {
            throw new Exception("Erro de Banco de Dados: " . $e->getMessage());
        }

        // Audit...

        // Audit check outside transaction to avoid lock
        $action = isset($input['id']) ? 'UPDATE' : 'INSERT';
        DB::log($currentUser['id'], $action, "ativos:" . ($newId ?? 'new'), null, $input);

        if (!empty($previousMediaPaths)) {
            // 'imagem_3d' (coluna) e 'dados_tecnicos.model_3d' não são editáveis por este
            // formulário — são preservados via merge acima — então precisam ser incluídos
            // aqui para não serem apagados do disco como "mídia órfã".
            $currentSavedImg = array_key_exists('imagem', $input) ? ($input['imagem'] ?? '') : ($prevRow['imagem'] ?? '');
            $newMedia = array_filter([
                $currentSavedImg,
                $prevRow['imagem_3d'] ?? '',
                $decodedDt['model_3d'] ?? '',
            ]);
            foreach ($previousMediaPaths as $oldPath) {
                if (!in_array($oldPath, $newMedia, true)) {
                    UploadHelper::deleteIfUploaded($oldPath);
                }
            }
        }

        return ['id' => $newId];
    }

    public function moveAsset()
    {
        $id = intval($this->input['id']);
        $newParent = (!empty($this->input['pai_id']) && $this->input['pai_id'] !== 'null') ? intval($this->input['pai_id']) : NULL;

        if ($id == $newParent) {
            throw new Exception("Um ativo não pode ser pai de si mesmo.");
        }
        if ($newParent && $this->isDescendant($id, $newParent)) {
            throw new Exception("Ciclo detectado: O novo pai é um descendente deste ativo.");
        }

        $stmt = $this->db->prepare("UPDATE ativos SET pai_id = ? WHERE id = ?");
        $stmt->execute([$newParent, $id]);
        $this->ativoParentMap = null;
        return ['ok' => true];
    }

    public function deleteRecursive($id, &$mediaPaths = [])
    {
        $stmt = $this->db->prepare("SELECT imagem, imagem_3d, dados_tecnicos FROM ativos WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $mediaPaths = array_merge($mediaPaths, UploadHelper::collectAssetMediaPaths($row));
        }

        // Find all children
        $stmt = $this->db->prepare("SELECT id FROM ativos WHERE pai_id = ?");
        $stmt->execute([$id]);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($children as $childId) {
            $this->deleteRecursive($childId, $mediaPaths);
        }
        // Delete self
        $this->db->prepare("DELETE FROM ativos WHERE id=?")->execute([$id]);
    }

    private function collectSubtreeIds(int $id, array &$ids): void
    {
        if (in_array($id, $ids, true)) {
            return;
        }
        $ids[] = $id;
        $stmt = $this->db->prepare('SELECT id FROM ativos WHERE pai_id = ?');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $this->collectSubtreeIds((int) $childId, $ids);
        }
    }

    public function deleteAsset()
    {
        if (Permissions::isTrabalhador($this->user['role'] ?? '')) {
            throw new Exception("Trabalhadores não podem excluir ativos.");
        }

        $id = intval($this->input['id']);
        // Check permissions
        if ($this->user['role'] !== 'developer' && $this->user['role'] !== 'admin') {
        // Optional: Add logic here if you want to restrict deletion of root nodes for non-admins
        }

        $mediaPaths = [];
        try {
            DB::safeExecute(function ($db) use ($id, &$mediaPaths) {
                $this->deleteRecursive($id, $mediaPaths);
            });
        } catch (PDOException $e) {
            $db = $this->db;
            $db->exec('PRAGMA foreign_keys = OFF');
            try {
                $db->beginTransaction();
                $ids = [];
                $this->collectSubtreeIds($id, $ids);
                require_once __DIR__ . '/../includes/db_dialect.php';
                [$inPh, $idList] = DbDialect::placeholdersIn($ids);
                if ($inPh !== '') {
                    try {
                        $db->prepare("DELETE FROM pi_telemetry WHERE tag_id IN (SELECT id FROM pi_tags WHERE ativo_id IN ($inPh))")->execute($idList);
                    } catch (Exception $x) {}
                    try {
                        $db->prepare("DELETE FROM pi_tags WHERE ativo_id IN ($inPh)")->execute($idList);
                    } catch (Exception $x) {}
                    try {
                        $db->prepare("DELETE FROM ativos_lubrificacao WHERE ativo_id IN ($inPh)")->execute($idList);
                    } catch (Exception $x) {}
                    try {
                        $db->prepare("UPDATE ordens SET ativo_id = NULL WHERE ativo_id IN ($inPh)")->execute($idList);
                    } catch (Exception $x) {}
                    $db->prepare("DELETE FROM ativos WHERE id IN ($inPh)")->execute($idList);
                }
                $db->commit();
            } catch (Exception $fallbackEx) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $fallbackEx;
            } finally {
                $db->exec('PRAGMA foreign_keys = ON');
            }
        }

        UploadHelper::deleteMany(array_unique($mediaPaths));

        return ['deleted' => $id];
    }

    public function saveBatch()
    {
        $items = $this->input['items'] ?? [];
        if (empty($items))
            return ['count' => 0];

        $count = 0;
        $errors = [];
        $virtualMap = []; // Map: virtual_id -> real_id

        DB::safeExecute(function ($db) use ($items, &$count, &$errors, &$virtualMap) {
            $maxIpStmt = $db->prepare("SELECT MAX(ip) FROM ativos");
            $maxIpStmt->execute();
            $nextIp = (int)$maxIpStmt->fetchColumn();
            if ($nextIp < 1000)
                $nextIp = 1000;

            foreach ($items as $idx => $item) {
                try {
                    // 1. Resolve Parent ID
                    $parentId = $item['pai_id'] ?? null;
                    if (isset($item['pai_virtual_id']) && isset($virtualMap[$item['pai_virtual_id']])) {
                        $parentId = $virtualMap[$item['pai_virtual_id']];
                    } elseif (isset($item['pai_ref']) && !empty($item['pai_ref'])) {
                        // Resolução de pai preexistente no banco de dados para evitar orfandade
                        $stmtParent = $db->prepare("SELECT id FROM ativos WHERE tag = ? OR nome = ? LIMIT 1");
                        $stmtParent->execute([$item['pai_ref'], $item['pai_ref']]);
                        $parentId = $stmtParent->fetchColumn() ?: null;
                    }

                    // Validation: Skip meaningless items
                    if (empty($item['nome']) || $item['nome'] === 'Sem Nome') {
                        continue;
                    }

                    // 2. Prepare Tech & Specs
                    $tech = $item['tech'] ?? [];
                    if (is_string($tech))
                        $tech = json_decode($tech, true) ?: [];

                    $specs = $item['specs'] ?? [];
                    if (is_string($specs))
                        $specs = json_decode($specs, true) ?: [];
                    $json_specs = json_encode($specs);

                    // 3. Execution (Insert Only for Import Batch typically)
                    if (isset($item['id']) && $item['id']) {
                        // Update not standard for this batch but supported.
                        // IMUTÁVEL: preserva o IP já atribuído (coluna 'ip' + dados_tecnicos.ip)
                        // em vez de sobrescrever com um número de sequência novo, o que causava
                        // desincronização entre a coluna 'ip' (não tocada pelo UPDATE) e o JSON.
                        $currStmt = $db->prepare("SELECT ip, dados_tecnicos FROM ativos WHERE id = ?");
                        $currStmt->execute([$item['id']]);
                        $currRow = $currStmt->fetch(PDO::FETCH_ASSOC);
                        $existingTech = [];
                        if ($currRow && !empty($currRow['dados_tecnicos'])) {
                            $existingTech = json_decode($currRow['dados_tecnicos'], true) ?: [];
                        }
                        $tech = array_merge($existingTech, $tech);
                        if ($currRow && !empty($currRow['ip'])) {
                            $tech['ip'] = (int)$currRow['ip'];
                        }
                        $dados = json_encode($tech);

                        $db->prepare("UPDATE ativos SET nome=?, tipo=?, pai_id=?, tag=?, obs=?, dados_tecnicos=?, imagem=?, fabricante=?, modelo=?, num_serie=? WHERE id=?")
                            ->execute([
                                $item['nome'],
                                $item['tipo'],
                                $parentId,
                                $item['tag'] ?? '',
                                $item['obs'] ?? '',
                                $dados,
                                $item['imagem'] ?? '',
                                $item['fabricante'] ?? '',
                                $item['modelo'] ?? '',
                                $item['num_serie'] ?? '',
                                $item['id']
                            ]);
                        $realId = $item['id'];
                    }
                    else {
                        // Assign Next IP (apenas para itens realmente novos)
                        $nextIp++;
                        $tech['ip'] = $nextIp;
                        $dados = json_encode($tech);
                        $stmt = $db->prepare("INSERT INTO ativos (nome, tipo, pai_id, tag, obs, dados_tecnicos, imagem, ip, json_specs, fabricante, modelo, num_serie, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->execute([
                            $item['nome'],
                            $item['tipo'],
                            $parentId,
                            $item['tag'] ?? '',
                            $item['obs'] ?? '',
                            $dados,
                            $item['imagem'] ?? '',
                            $nextIp,
                            $json_specs,
                            $item['fabricante'] ?? '',
                            $item['modelo'] ?? '',
                            $item['num_serie'] ?? '',
                            $this->user ? ($this->user['id'] ?? 1) : 1
                        ]);
                        $realId = $db->lastInsertId();
                    }

                    // 4. Update Virtual Map
                    if (isset($item['virtual_id'])) {
                        $virtualMap[$item['virtual_id']] = $realId;
                    }
                    if ($realId) {
                        LubricationTechSync::syncAsset($db, (int) $realId);
                        LubricationSchedule::refreshCadastroDates($db, (int) $realId);
                    }
                    $count++;

                }
                catch (Exception $e) {
                    $errors[] = "Row $idx: " . $e->getMessage();
                }
            }
        });

        return ['count' => $count, 'errors' => $errors, 'virtual_map' => $virtualMap];
    }

    // Internal Helper
    private function parseBearingSpecs($name)
    {
        $name = strtoupper($name);
        if (preg_match('/((?:6[0234]|222|223|213|230|231|232|240|241|NU\s?[23]|NJ\s?[23])\d{2})/', $name, $matches)) {
            $code = str_replace([' ', 'NU', 'NJ'], '', $matches[1]);
            $last2 = intval(substr($code, -2));
            $d = ($last2 < 4) ? ([10, 12, 15, 17][$last2] ?? 10) : ($last2 * 5);
            $D = $d * 2;
            if (substr($code, 0, 2) == '62')
                $D = $d * 1.7 + 12; // Estimation
            return ['model' => $matches[1], 'd' => $d, 'D' => round($D, 1), 'dm' => ($d + $D) / 2];
        }
        return null;
    }

    public function getTimeline()
    {
        $id = $this->input['id'] ?? 0;
        if (!$id)
            return ['timeline' => []];

        $timeline = [];

        // 1. Creation Log (Simple approximation if no log exists)
        // Try to find INSERT in audit logs if table exists
        // Converting simple query for now.

        // 2. Completed Tasks
        try {
            // Check if ordens table has data_conclusao
            $stmt = $this->db->prepare("SELECT * FROM ordens WHERE ativo_id = ? AND situacao = 'Concluído' ORDER BY data_planejada DESC LIMIT 20");
            $stmt->execute([$id]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tasks as $t) {
                $timeline[] = [
                    'date' => $t['data_conclusao'] ?? $t['data_planejada'],
                    'type' => 'task',
                    'title' => 'OS Concluída #' . $t['id'],
                    'subtitle' => $t['tipo_manutencao'] ?? 'Manutenção',
                    'details' => $t['descricao'],
                    'icon' => 'check-circle',
                    'color' => '#10b981'
                ];
            }

            // 3. Pending/Running Tasks
            $stmt = $this->db->prepare("SELECT * FROM ordens WHERE ativo_id = ? AND situacao != 'Concluído' ORDER BY data_planejada ASC LIMIT 10");
            $stmt->execute([$id]);
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pending as $p) {
                $timeline[] = [
                    'date' => $p['data_planejada'],
                    'type' => 'task',
                    'title' => 'OS Pendente #' . $p['id'],
                    'subtitle' => $p['situacao'],
                    'details' => $p['descricao'],
                    'icon' => 'clock',
                    'color' => ($p['prioridade'] === 'Crítica') ? '#ef4444' : '#f59e0b'
                ];
            }

        }
        catch (Exception $e) { /* Ignore table errors */
        }

        // Sort by Date Descending
        usort($timeline, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return ['timeline' => $timeline];
    }

    /**
     * Resolução desacoplada de QR Code por identificador público imutável (qr_hash ou tag)
     */
    public function resolveByQrCode()
    {
        $code = trim($this->input['code'] ?? $this->input['qr_hash'] ?? $this->input['tag'] ?? $this->input['id'] ?? '');
        if (empty($code)) {
            throw new Exception("Identificador de QR Code não informado.");
        }

        // 1. Busca por qr_hash público imutável
        $stmt = $this->db->prepare("SELECT * FROM ativos WHERE qr_hash = ? LIMIT 1");
        $stmt->execute([$code]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Fallback por TAG industrial
        if (!$asset) {
            $stmt = $this->db->prepare("SELECT * FROM ativos WHERE tag = ? LIMIT 1");
            $stmt->execute([$code]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 3. Fallback por ID sequencial
        if (!$asset && ctype_digit((string) $code)) {
            $stmt = $this->db->prepare("SELECT * FROM ativos WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $code]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 4. Fallback por IP hierárquico
        if (!$asset && ctype_digit((string) $code)) {
            $stmt = $this->db->prepare("SELECT * FROM ativos WHERE ip = ? LIMIT 1");
            $stmt->execute([(int) $code]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$asset) {
            throw new Exception("Ativo não localizado para o QR Code fornecido.");
        }

        // Garante que o ativo possua qr_hash se for legado
        if (empty($asset['qr_hash'])) {
            $newQr = bin2hex(random_bytes(16));
            $this->db->prepare("UPDATE ativos SET qr_hash = ? WHERE id = ?")->execute([$newQr, $asset['id']]);
            $asset['qr_hash'] = $newQr;
        }

        // Dados técnicos
        $dt = json_decode($asset['dados_tecnicos'] ?: '{}', true) ?: [];

        // Monta breadcrumb e descobre linha / setor pai
        $breadcrumb = [];
        $curr = $asset;
        $lineId = null;
        $lineName = null;
        $maxHops = 10;

        while ($curr && $maxHops-- > 0) {
            array_unshift($breadcrumb, [
                'id' => (int) $curr['id'],
                'nome' => $curr['nome'],
                'tipo' => $curr['tipo'],
                'tag' => $curr['tag'] ?? ''
            ]);

            if (in_array($curr['tipo'], ['setor', 'unidade']) && !$lineId) {
                $lineId = (int) $curr['id'];
                $lineName = $curr['nome'];
            }

            if (!empty($curr['pai_id'])) {
                $pStmt = $this->db->prepare("SELECT id, nome, tipo, tag, pai_id FROM ativos WHERE id = ?");
                $pStmt->execute([$curr['pai_id']]);
                $curr = $pStmt->fetch(PDO::FETCH_ASSOC);
            } else {
                break;
            }
        }

        // Se não achou unidade/setor, usa o próprio ativo ou pai imediato
        if (!$lineId) {
            $lineId = !empty($breadcrumb[0]['id']) ? $breadcrumb[0]['id'] : (int) $asset['id'];
            $lineName = !empty($breadcrumb[0]['nome']) ? $breadcrumb[0]['nome'] : $asset['nome'];
        }

        // Ordens ativas para este ponto
        $stmtOrdens = $this->db->prepare("SELECT id, descricao, tipo_manutencao, prioridade, situacao, data_planejada, materiais, rota, cod_serv, reserva_almox, materiais_sap
            FROM ordens 
            WHERE ativo_id = ? AND situacao != 'Concluído' 
            ORDER BY CASE prioridade WHEN 'Crítica' THEN 1 WHEN 'Alta' THEN 2 WHEN 'Média' THEN 3 ELSE 4 END, data_planejada ASC");
        $stmtOrdens->execute([$asset['id']]);
        $activeOrders = $stmtOrdens->fetchAll(PDO::FETCH_ASSOC);

        // Calibração de engraxadeiras disponível no sistema
        $greaseGuns = [];
        try {
            $gStmt = $this->db->query("SELECT id, codigo, modelo, tipo, g_por_golpe FROM engraxadeiras_calibracao WHERE ativo = 1");
            $greaseGuns = $gStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        return [
            'ok' => true,
            'asset' => [
                'id' => (int) $asset['id'],
                'nome' => $asset['nome'],
                'tag' => $asset['tag'],
                'qr_hash' => $asset['qr_hash'],
                'tipo' => $asset['tipo'],
                'pai_id' => $asset['pai_id'],
                'fabricante' => $asset['fabricante'],
                'modelo' => $asset['modelo'],
                'num_serie' => $asset['num_serie'],
                'imagem' => $asset['imagem'],
                'imagem_3d' => $asset['imagem_3d'] ?? null,
                'is_food_grade_required' => (bool) ($asset['is_food_grade_required'] ?? false),
                'dados_tecnicos' => $dt
            ],
            'line' => [
                'id' => $lineId,
                'nome' => $lineName,
                'prefetch_url' => 'api.php?action=get_line_lubrication_points&line_id=' . $lineId
            ],
            'breadcrumb' => $breadcrumb,
            'active_orders' => $activeOrders,
            'grease_guns' => $greaseGuns
        ];
    }

    /**
     * Retorna todos os pontos de lubrificação, insumos e instruções de uma linha inteira em payload enxuto para pre-fetching/cache offline do PWA
     */
    public function getLineLubricationPoints()
    {
        $lineId = isset($this->input['line_id']) ? (int) $this->input['line_id'] : (isset($this->input['id']) ? (int) $this->input['id'] : null);
        $tag = trim($this->input['tag'] ?? '');

        if (!$lineId && !empty($tag)) {
            $s = $this->db->prepare("SELECT id FROM ativos WHERE tag = ? OR qr_hash = ? LIMIT 1");
            $s->execute([$tag, $tag]);
            $lineId = $s->fetchColumn() ?: null;
        }

        if (!$lineId) {
            throw new Exception("Parâmetro 'line_id' obrigatório para pre-fetching de linha.");
        }

        // Coleta todos os IDs da subárvore pertencente a esta linha
        $subtreeIds = [$lineId];
        $this->collectSubtreeIds($lineId, $subtreeIds);

        // Informações da linha/setor
        $lineStmt = $this->db->prepare("SELECT id, nome, tag, tipo FROM ativos WHERE id = ?");
        $lineStmt->execute([$lineId]);
        $lineInfo = $lineStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $lineId, 'nome' => 'Linha ' . $lineId];

        // Tenta buscar da tabela de projeção de lubrificação para máxima performance
        $inClause = implode(',', array_map('intval', $subtreeIds));
        $sql = "SELECT l.*, a.nome, a.tag, a.tipo, a.imagem, a.qr_hash, a.is_food_grade_required, a.dados_tecnicos, a.pai_id
                FROM ativos a
                LEFT JOIN ativos_lubrificacao l ON l.ativo_id = a.id
                WHERE a.id IN ($inClause) AND (a.tipo IN ('ponto', 'componente') OR l.ativo_id IS NOT NULL)
                ORDER BY a.pai_id ASC, a.nome ASC";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $points = [];
        $lubricants = [];

        foreach ($rows as $row) {
            $dt = json_decode($row['dados_tecnicos'] ?: '{}', true) ?: [];
            $lub = $row['lubrificante'] ?: ($dt['lubrificante'] ?? $dt['graxa'] ?? $dt['oleo'] ?? 'Graxa Padrão');
            $qtd = $row['quantidade'] !== null ? (float) $row['quantidade'] : (float) ($dt['quantidade'] ?? $dt['qtd'] ?? 0);
            $unidade = $row['unidade'] ?: ($dt['unidade'] ?? 'g');
            $freq = $row['frequencia'] ?: ($dt['frequencia'] ?? 'Mensal');

            $pointData = [
                'id' => (int) $row['id'],
                'nome' => $row['nome'],
                'tag' => $row['tag'] ?: '',
                'qr_hash' => $row['qr_hash'] ?: null,
                'tipo' => $row['tipo'],
                'pai_id' => $row['pai_id'],
                'equipamento' => $row['equipamento_nome'] ?: '',
                'setor' => $row['setor_nome'] ?: $lineInfo['nome'],
                'lubrificante' => $lub,
                'quantidade' => $qtd,
                'unidade' => $unidade,
                'frequencia' => $freq,
                'proxima_intervencao' => $row['data_proxima_intervencao'] ?? null,
                'ultima_intervencao' => $row['data_ultima_intervencao'] ?? null,
                'is_food_grade_required' => (bool) ($row['is_food_grade_required'] ?? false),
                'condicao_maquina' => $dt['condicao'] ?? $dt['condicao_servico'] ?? 'Em Marcha',
                'ponto_especifico' => $dt['ponto_lubrificacao'] ?? $row['ponto_lub'] ?? '',
                'metodo' => $dt['metodo'] ?? 'Manual',
                'imagem' => $row['imagem'] ?: null,
                'sap_code' => $row['sap'] ?: ($dt['sap'] ?? '')
            ];

            $points[] = $pointData;
            if ($lub && !in_array($lub, $lubricants, true)) {
                $lubricants[] = $lub;
            }
        }

        return [
            'ok' => true,
            'line' => [
                'id' => (int) $lineInfo['id'],
                'nome' => $lineInfo['nome'],
                'tag' => $lineInfo['tag'] ?? '',
                'tipo' => $lineInfo['tipo'] ?? 'setor'
            ],
            'total_points' => count($points),
            'points' => $points,
            'required_lubricants' => $lubricants,
            'fetched_at' => date('Y-m-d H:i:s')
        ];
    }
}
