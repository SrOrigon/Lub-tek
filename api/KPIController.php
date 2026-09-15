<?php
/**
 * LUB-TEK - Controlador de Indicadores de Desempenho (KPIs)
 * Retorna dados estruturados para o painel "Resumo da Fábrica".
 */

class KPIController {
    private $db;
    private $userId;
    private $isDev;
    private $assetId;

    public function __construct($db, $userId, $isDev, $assetId = null) {
        $this->db = $db;
        $this->userId = $userId;
        $this->isDev = $isDev;
        $this->assetId = $assetId;
    }

    public function getDashboardKPIs() {
        $ordensAssetSql = $this->assetId ? ' AND ativo_id = ?' : '';
        $ordensParams = $this->assetId ? [(int)$this->assetId] : [];

        // Saúde dos equipamentos/pontos por status
        $health = ['Em Dia' => 0, 'Atenção' => 0, 'Crítico' => 0];
        try {
            $sql = "SELECT COALESCE(NULLIF(TRIM(status), ''), 'OK') AS st, COUNT(*) AS c
                    FROM ativos WHERE tipo IN ('equipamento', 'ponto') GROUP BY st";
            $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                // mb_strtoupper (não strtoupper) é obrigatório aqui: strtoupper() não
                // trata corretamente acentos em UTF-8 (ex.: "Concluído" virava "CONCLUíDO"
                // em vez de "CONCLUÍDO"), fazendo ativos concluídos serem contados
                // incorretamente como "Crítico" no card de saúde do dashboard.
                $st = mb_strtoupper($r['st'] ?? '', 'UTF-8');
                if (in_array($st, ['OK', 'CONCLUIDO', 'CONCLUÍDO', 'NORMAL'], true)) {
                    $health['Em Dia'] += (int)$r['c'];
                } elseif (in_array($st, ['ALERTA', 'ATENÇÃO', 'ATENCAO', 'PENDENTE'], true)) {
                    $health['Atenção'] += (int)$r['c'];
                } else {
                    $health['Crítico'] += (int)$r['c'];
                }
            }
        } catch (Exception $e) {
            // mantém zeros
        }

        $totalAssets = array_sum($health);
        $healthPct = $totalAssets > 0 ? round(($health['Em Dia'] / $totalAssets) * 100) : 0;

        // Pendências e atrasadas
        $pendingToday = 0;
        $pendingOverdue = 0;
        $backlog = [];
        try {
            $today = date('Y-m-d');
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM ordens WHERE situacao != 'Concluído' AND date(data_planejada) = ?" . $ordensAssetSql);
            $stmt->execute(array_merge([$today], $ordensParams));
            $pendingToday = (int)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM ordens WHERE situacao != 'Concluído' AND data_planejada < ? AND data_planejada IS NOT NULL AND data_planejada != ''" . $ordensAssetSql);
            $stmt->execute(array_merge([$today], $ordensParams));
            $pendingOverdue = (int)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COALESCE(NULLIF(TRIM(prioridade), ''), 'Normal') AS p, COUNT(*) AS c
                FROM ordens WHERE situacao != 'Concluído'" . $ordensAssetSql . " GROUP BY p");
            $stmt->execute($ordensParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $backlog[$r['p']] = (int)$r['c'];
            }
        } catch (Exception $e) {
            // mantém zeros
        }

        // Tarefas feitas no prazo (últimos 30 dias)
        $adherence = 0;
        $completedMonth = 0;
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM ordens WHERE situacao = 'Concluído'
                AND data_conclusao >= datetime('now', '-30 days')" . $ordensAssetSql);
            $stmt->execute($ordensParams);
            $completedMonth = (int)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT
                COUNT(CASE WHEN situacao = 'Concluído' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) AS compliance
                FROM ordens WHERE data_planejada >= date('now', '-30 days')" . $ordensAssetSql);
            $stmt->execute($ordensParams);
            $val = $stmt->fetchColumn();
            $adherence = $val !== false && $val !== null ? (int)round((float)$val) : 0;
        } catch (Exception $e) {
            // fallback: se não houver coluna data_conclusao, usa preventiva_compliance legado
            try {
                $val = $this->db->query("SELECT COUNT(CASE WHEN situacao = 'Concluído' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) FROM ordens")->fetchColumn();
                $adherence = $val ? (int)round((float)$val) : 0;
            } catch (Exception $e2) {}
        }

        $costPack = $this->estimateMonthlyLubricantCost($ordensAssetSql, $ordensParams);
        $financialTotal = $costPack['total'];
        $consumption = $costPack['consumption'];
        $financialHint = $costPack['hint'];
        $financialActual = $costPack['actual'];

        require_once __DIR__ . '/../includes/reliability_metrics.php';
        $rel = ReliabilityMetrics::compute($this->db, $this->assetId ? (int) $this->assetId : null);

        // Causas — agrupa por prioridade/situação das ordens não concluídas
        $causes = [];
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(NULLIF(TRIM(prioridade), ''), 'Sem classificação') AS c, COUNT(*) AS n
                FROM ordens WHERE situacao != 'Concluído'" . $ordensAssetSql . " GROUP BY c ORDER BY n DESC LIMIT 6");
            $stmt->execute($ordensParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ((int)$r['n'] > 0) {
                    $causes[$r['c']] = (int)$r['n'];
                }
            }
        } catch (Exception $e) {}

        $hasData = $totalAssets > 0 || $completedMonth > 0 || array_sum($backlog) > 0 || $adherence > 0 || $financialTotal > 0;

        $kpis = [
            'adherence' => $adherence,
            'financial_total' => $financialTotal,
            'financial_actual' => $financialActual,
            'financial_hint' => $financialHint,
            'health_pct' => $healthPct,
            'pending_today' => $pendingToday,
            'pending_overdue' => $pendingOverdue,
            'total_equipamentos' => $totalAssets,
            'completed_month' => $completedMonth,
            'mtbf_hours' => $rel['mtbf_hours'],
            'mttr_hours' => $rel['mttr_hours'],
            'failures_12m' => $rel['failures_12m'],
            'reliability_hint' => $rel['hint'],
        ];

        $distribution = [
            'health' => $health,
            'backlog' => $backlog,
            'consumption' => $consumption,
            'causes' => $causes,
        ];

        $aiAdvisor = $this->buildAdvisor($kpis, $distribution, $hasData);

        return [
            'kpis' => $kpis,
            'distribution' => $distribution,
            'has_data' => $hasData,
            'ai_advisor' => $aiAdvisor,
        ];
    }

    private function buildAdvisor(array $kpis, array $dist, bool $hasData): ?array {
        if (!$hasData) {
            return [
                'insight' => 'Ainda não há dados suficientes para análise.',
                'recommendation' => 'Conclua lubrificações nas Rotas de Campo ou registre ordens de serviço.',
                'severity' => 'optimal',
                'trend' => 'Estável',
            ];
        }

        $overdue = (int)($kpis['pending_overdue'] ?? 0);
        $healthPct = (int)($kpis['health_pct'] ?? 0);

        if ($overdue > 0) {
            return [
                'insight' => "{$overdue} tarefa(s) atrasada(s) precisam de atenção.",
                'recommendation' => 'Abra o painel de Ordens de Serviço e priorize as pendências em vermelho.',
                'severity' => 'warning',
                'trend' => 'Queda',
            ];
        }

        if ($healthPct >= 90) {
            return [
                'insight' => "{$healthPct}% dos equipamentos estão em dia.",
                'recommendation' => 'Continue seguindo as rotas de lubrificação diárias.',
                'severity' => 'optimal',
                'trend' => 'Melhoria',
            ];
        }

        return [
            'insight' => "Saúde da planta em {$healthPct}%.",
            'recommendation' => 'Revise pontos com alerta em Meus Ativos.',
            'severity' => 'warning',
            'trend' => 'Estável',
        ];
    }

    /**
     * Custo estimado do mês: pontos injetados (qtd × frequência × preço) e O.S. do mês.
     */
    private function estimateMonthlyLubricantCost(string $ordensAssetSql, array $ordensParams): array
    {
        $prices = $this->buildPriceBook();
        $consumption = [];
        $plantTotal = 0.0;
        $usedRef = false;
        $scopeIds = $this->scopedAssetIds();

        $sqlAtivos = "SELECT id, dados_tecnicos FROM ativos WHERE dados_tecnicos IS NOT NULL AND TRIM(dados_tecnicos) != ''";
        $paramsAtivos = [];
        if ($scopeIds !== null) {
            if ($scopeIds === []) {
                $sqlAtivos .= ' AND 0';
            } else {
                $sqlAtivos .= ' AND id IN (' . implode(',', array_fill(0, count($scopeIds), '?')) . ')';
                $paramsAtivos = $scopeIds;
            }
        }

        try {
            $stmt = $this->db->prepare($sqlAtivos);
            $stmt->execute($paramsAtivos);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $tech = json_decode((string) $row['dados_tecnicos'], true);
                if (!is_array($tech)) {
                    continue;
                }
                $material = trim((string) ($tech['material'] ?? ''));
                $qty = $this->parseNumber($tech['qtd_material'] ?? $tech['quantidade'] ?? 0);
                if ($material === '' || $qty <= 0) {
                    continue;
                }
                $unid = (string) ($tech['unid_material'] ?? $tech['unidade'] ?? 'kg');
                $factor = $this->monthlyFactor((string) ($tech['periodo'] ?? $tech['frequencia'] ?? ''));
                $monthlyQty = $qty * $factor;
                $priced = $this->priceQuantity($material, $monthlyQty, $unid, $prices);
                $plantTotal += $priced['cost'];
                if ($priced['used_ref']) {
                    $usedRef = true;
                }
                $key = mb_substr($material, 0, 48);
                $consumption[$key] = ($consumption[$key] ?? 0) + $monthlyQty;
            }
        } catch (Exception $e) {
        }

        try {
            $planSql = "SELECT p.quantidade, p.frequencia_dias, p.catalogo_id, p.ativo_id, c.nome AS cat_nome, c.specs
                FROM planos p LEFT JOIN catalogo c ON c.id = p.catalogo_id
                WHERE p.quantidade IS NOT NULL AND p.quantidade > 0";
            $planParams = [];
            if ($scopeIds !== null) {
                if ($scopeIds === []) {
                    $planSql .= ' AND 0';
                } else {
                    $planSql .= ' AND p.ativo_id IN (' . implode(',', array_fill(0, count($scopeIds), '?')) . ')';
                    $planParams = $scopeIds;
                }
            }
            $stmt = $this->db->prepare($planSql);
            $stmt->execute($planParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $plan) {
                $days = max(1, (int) ($plan['frequencia_dias'] ?? 30));
                $monthlyQty = (float) $plan['quantidade'] * (30 / $days);
                $name = trim((string) ($plan['cat_nome'] ?? 'Plano de lubrificação'));
                $this->ingestSpecPrices($name, $plan['specs'] ?? '', $prices);
                $priced = $this->priceQuantity($name, $monthlyQty, 'kg', $prices);
                $plantTotal += $priced['cost'];
                if ($priced['used_ref']) {
                    $usedRef = true;
                }
                $key = mb_substr($name !== '' ? $name : 'Plano', 0, 48);
                $consumption[$key] = ($consumption[$key] ?? 0) + $monthlyQty;
            }
        } catch (Exception $e) {
        }

        $actual = 0.0;
        try {
            $stmt = $this->db->prepare(
                "SELECT materiais, qtd_real, materiais_sap FROM ordens
                 WHERE situacao = 'Concluído'
                 AND (
                    COALESCE(data_conclusao, data_execucao, data_planejada) LIKE strftime('%Y-%m', 'now') || '%'
                    OR date(COALESCE(data_conclusao, data_execucao, data_planejada)) >= date('now', 'start of month')
                 )" . $ordensAssetSql
            );
            $stmt->execute($ordensParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $actual += $this->extractOrderCost($row, $prices);
                $label = trim((string) ($row['materiais'] ?? $row['materiais_sap'] ?? ''));
                if ($label !== '' && $label[0] !== '{' && $label[0] !== '[') {
                    $key = mb_substr($label, 0, 48);
                    $consumption[$key] = ($consumption[$key] ?? 0) + 1;
                }
            }
        } catch (Exception $e) {
            $actual = 0.0;
        }

        $total = round(max($plantTotal, $actual), 2);
        if ($consumption) {
            arsort($consumption);
            $consumption = array_slice($consumption, 0, 8, true);
            foreach ($consumption as $k => $v) {
                $consumption[$k] = round((float) $v, 2);
            }
        }

        $hint = 'Estimativa pelos pontos cadastrados';
        if ($total <= 0) {
            $hint = 'Cadastre quantidade e lubrificante nos pontos';
        } elseif ($usedRef && $plantTotal >= $actual) {
            $hint = 'Estimativa da planta (preço de catálogo/referência)';
        } elseif ($actual > $plantTotal) {
            $hint = 'Consumo das O.S. concluídas no mês';
        }

        return [
            'total' => $total,
            'actual' => round($actual, 2),
            'consumption' => $consumption,
            'hint' => $hint,
        ];
    }

    private function scopedAssetIds(): ?array
    {
        $root = (int) $this->assetId;
        if ($root <= 0) {
            return null;
        }
        try {
            $rows = $this->db->query('SELECT id, pai_id FROM ativos')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [$root];
        }
        $children = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['pai_id'] ?? 0);
            $children[$pid][] = (int) $r['id'];
        }
        $ids = [];
        $stack = [$root];
        while ($stack) {
            $id = array_pop($stack);
            if (isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;
            foreach ($children[$id] ?? [] as $child) {
                $stack[] = $child;
            }
        }
        return array_map('intval', array_keys($ids));
    }

    private function buildPriceBook(): array
    {
        $book = ['by_name' => [], 'avg_l' => 0.0, 'avg_kg' => 0.0];
        try {
            $rows = $this->db->query('SELECT id, nome, codigo, specs FROM catalogo')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $this->ingestSpecPrices((string) ($row['nome'] ?? ''), $row['specs'] ?? '', $book);
                if (!empty($row['codigo'])) {
                    $this->ingestSpecPrices((string) $row['codigo'], $row['specs'] ?? '', $book);
                }
            }
        } catch (Exception $e) {
        }

        try {
            $sql = "SELECT c.nome, AVG(o.price) AS p
                    FROM mercado_ofertas o
                    JOIN catalogo c ON c.id = o.catalogo_id
                    WHERE o.price > 0
                    GROUP BY c.nome";
            foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->setPrice($book, (string) $row['nome'], (float) $row['p'], 'un');
            }
        } catch (Exception $e) {
        }

        try {
            $stmt = $this->db->query("SELECT materiais FROM ordens WHERE materiais IS NOT NULL AND materiais != ''");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $decoded = json_decode((string) $row['materiais'], true);
                if (!is_array($decoded)) {
                    continue;
                }
                $items = isset($decoded[0]) ? $decoded : [$decoded];
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $name = (string) ($item['material'] ?? $item['nome'] ?? '');
                    $custo = (float) ($item['custo'] ?? $item['valor'] ?? $item['total'] ?? 0);
                    $unit = (float) ($item['custo_unitario'] ?? $item['preco'] ?? $item['price'] ?? 0);
                    $qty = $this->parseNumber($item['qtd'] ?? $item['quantidade'] ?? $item['qty'] ?? 0);
                    $unid = (string) ($item['unid'] ?? $item['unidade'] ?? 'l');
                    if ($name === '') {
                        continue;
                    }
                    if ($unit > 0) {
                        [$baseQty, $baseUnit] = $this->toBaseUnit(1, $unid);
                        $this->setPrice($book, $name, $unit / max($baseQty, 0.0001), $baseUnit);
                    } elseif ($custo > 0 && $qty > 0) {
                        [$baseQty, $baseUnit] = $this->toBaseUnit($qty, $unid);
                        if ($baseQty > 0) {
                            $this->setPrice($book, $name, $custo / $baseQty, $baseUnit);
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }

        $sumL = 0;
        $nL = 0;
        $sumKg = 0;
        $nKg = 0;
        foreach ($book['by_name'] as $units) {
            if (!empty($units['l'])) {
                $sumL += $units['l'];
                $nL++;
            }
            if (!empty($units['kg'])) {
                $sumKg += $units['kg'];
                $nKg++;
            }
        }
        $refL = defined('LUBE_REF_PRICE_PER_LITER') ? (float) LUBE_REF_PRICE_PER_LITER : 48.0;
        $refKg = defined('LUBE_REF_PRICE_PER_KG') ? (float) LUBE_REF_PRICE_PER_KG : 86.0;
        $book['avg_l'] = $nL ? $sumL / $nL : $refL;
        $book['avg_kg'] = $nKg ? $sumKg / $nKg : $refKg;

        return $book;
    }

    private function ingestSpecPrices(string $name, $specsRaw, array &$book): void
    {
        if ($name === '' || $specsRaw === null || $specsRaw === '') {
            return;
        }
        $specs = is_array($specsRaw) ? $specsRaw : json_decode((string) $specsRaw, true);
        if (!is_array($specs)) {
            return;
        }
        $price = 0.0;
        foreach (['custo_medio', 'custo', 'preco', 'preço', 'price', 'valor', 'custo_unitario'] as $k) {
            if (isset($specs[$k]) && is_numeric($specs[$k]) && (float) $specs[$k] > 0) {
                $price = (float) $specs[$k];
                break;
            }
        }
        if ($price > 0) {
            $this->setPrice($book, $name, $price, 'un');
        }
    }

    private function setPrice(array &$book, string $name, float $price, string $unit): void
    {
        if ($price <= 0) {
            return;
        }
        $key = $this->normName($name);
        if ($key === '') {
            return;
        }
        $u = ($unit === 'l' || $unit === 'kg') ? $unit : 'un';
        if (!isset($book['by_name'][$key])) {
            $book['by_name'][$key] = [];
        }
        $book['by_name'][$key][$u] = $price;
    }

    private function priceQuantity(string $material, float $qty, string $unid, array $prices): array
    {
        [$baseQty, $baseUnit] = $this->toBaseUnit($qty, $unid);
        $key = $this->normName($material);
        $unitPrice = 0.0;
        $usedRef = false;
        $entry = $prices['by_name'][$key] ?? null;

        if (!$entry) {
            foreach ($prices['by_name'] as $name => $units) {
                if (strlen($name) < 8 || strlen($key) < 5) {
                    continue;
                }
                if (strpos($key, $name) !== false || strpos($name, $key) !== false) {
                    $entry = $units;
                    break;
                }
            }
        }

        if ($entry) {
            $unitPrice = (float) ($entry[$baseUnit] ?? $entry['un'] ?? $entry['l'] ?? $entry['kg'] ?? 0);
        }

        if ($unitPrice <= 0) {
            $usedRef = true;
            $unitPrice = $baseUnit === 'l'
                ? (float) ($prices['avg_l'] ?? 48)
                : (float) ($prices['avg_kg'] ?? 86);
        }

        return [
            'cost' => max(0, $baseQty) * $unitPrice,
            'used_ref' => $usedRef,
        ];
    }

    private function monthlyFactor(string $periodo): float
    {
        $p = mb_strtolower($periodo, 'UTF-8');
        $p = strtr($p, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ã' => 'a', 'õ' => 'o']);
        if ($p === '') {
            return 1.0;
        }
        if (strpos($p, 'diar') !== false) {
            return 30.0;
        }
        if (strpos($p, 'quinzen') !== false) {
            return 2.0;
        }
        if (strpos($p, 'seman') !== false) {
            return 4.345;
        }
        if (strpos($p, 'mens') !== false) {
            return 1.0;
        }
        if (strpos($p, 'bimes') !== false) {
            return 0.5;
        }
        if (strpos($p, 'trimes') !== false) {
            return 1 / 3;
        }
        if (strpos($p, 'semestr') !== false) {
            return 1 / 6;
        }
        if (strpos($p, 'anual') !== false || strpos($p, 'ano') !== false) {
            return 1 / 12;
        }
        return 1.0;
    }

    private function toBaseUnit(float $qty, string $unid): array
    {
        $u = mb_strtolower(trim($unid), 'UTF-8');
        $u = strtr($u, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        if (in_array($u, ['g', 'gr', 'grama', 'gramas'], true)) {
            return [$qty / 1000.0, 'kg'];
        }
        if (in_array($u, ['ml', 'mls'], true)) {
            return [$qty / 1000.0, 'l'];
        }
        if (in_array($u, ['kg', 'kgs', 'quilo', 'quilos'], true)) {
            return [$qty, 'kg'];
        }
        if (in_array($u, ['l', 'lt', 'lts', 'litro', 'litros'], true)) {
            return [$qty, 'l'];
        }
        return [$qty, 'un'];
    }

    private function parseNumber($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        $s = preg_replace('/[^0-9.\-]/', '', $s);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function normName(string $name): string
    {
        $n = mb_strtolower($name, 'UTF-8');
        $n = strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ã' => 'a', 'õ' => 'o', 'ç' => 'c']);
        $n = str_replace(['®', '™', '*'], '', $n);
        $n = preg_replace('/\s+/', ' ', $n);
        return trim((string) $n);
    }

    private function extractOrderCost(array $row, array $prices = []): float
    {
        foreach (['materiais', 'materiais_sap', 'qtd_real'] as $field) {
            $raw = $row[$field] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            if (is_numeric($raw)) {
                $label = trim((string) ($row['materiais'] ?? $row['materiais_sap'] ?? ''));
                if ($label !== '' && $prices) {
                    return $this->priceQuantity($label, (float) $raw, 'un', $prices)['cost'];
                }
                continue;
            }
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                if ($prices && $field === 'materiais') {
                    $qty = $this->parseNumber($row['qtd_real'] ?? 0);
                    if ($qty > 0) {
                        return $this->priceQuantity((string) $raw, $qty, 'un', $prices)['cost'];
                    }
                }
                continue;
            }
            $sum = 0.0;
            $items = isset($decoded[0]) ? $decoded : [$decoded];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $qty = $this->parseNumber($item['qtd'] ?? $item['quantidade'] ?? $item['qty'] ?? 1);
                $name = (string) ($item['material'] ?? $item['nome'] ?? '');
                $unid = (string) ($item['unid'] ?? $item['unidade'] ?? 'un');
                $lineTotal = (float) ($item['custo'] ?? $item['valor'] ?? $item['total'] ?? 0);
                $unit = (float) ($item['custo_unitario'] ?? $item['preco'] ?? $item['price'] ?? 0);
                if ($lineTotal > 0) {
                    $sum += $lineTotal;
                    continue;
                }
                if ($unit > 0) {
                    $sum += $unit * max($qty, 0);
                    continue;
                }
                if ($name !== '' && $qty > 0 && $prices) {
                    $sum += $this->priceQuantity($name, $qty, $unid, $prices)['cost'];
                }
            }
            if ($sum > 0) {
                return $sum;
            }
        }
        return 0.0;
    }
}
