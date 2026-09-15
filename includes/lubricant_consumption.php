<?php
/**
 * Consumo individual de lubrificantes por equipamento (dados da planta).
 * Não altera KPIs nem cadastros — apenas leitura.
 */
require_once __DIR__ . '/lubrication_tech_sync.php';

class LubricantConsumption
{
    public static function build(PDO $db, array $options = []): array
    {
        $period = strtolower((string) ($options['period'] ?? 'month'));
        if ($period !== 'year') {
            $period = 'month';
        }

        if (LubricationTechSync::tableExists($db)) {
            try {
                $fromProj = self::buildFromProjection($db, $period);
                if ($fromProj !== null) {
                    return $fromProj;
                }
            } catch (Exception $e) {
                error_log('LubricantConsumption projection: ' . $e->getMessage());
            }
        }

        return self::buildFromJson($db, $period);
    }

    /**
     * Soma por setor/lubrificante em SQL (sem decodificar JSON linha a linha).
     */
    private static function buildFromProjection(PDO $db, string $period): ?array
    {
        $count = (int) $db->query('SELECT COUNT(*) FROM ativos_lubrificacao')->fetchColumn();
        if ($count === 0) {
            return null;
        }

        $assets = [];
        try {
            $assets = $db->query(
                "SELECT id, nome, tag, tipo, pai_id FROM ativos ORDER BY id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return self::emptyResult($period, 'Não foi possível ler os ativos.');
        }
        $byId = [];
        foreach ($assets as $a) {
            $byId[(int) $a['id']] = $a;
        }

        $rows = $db->query(
            "SELECT l.*, a.nome AS ativo_nome, a.tag AS ativo_tag, a.tipo AS ativo_tipo, a.pai_id
             FROM ativos_lubrificacao l
             JOIN ativos a ON a.id = l.ativo_id
             WHERE TRIM(COALESCE(l.lubrificante, '')) != '' AND COALESCE(l.quantidade, 0) > 0"
        )->fetchAll(PDO::FETCH_ASSOC);

        $lines = [];
        foreach ($rows as $r) {
            $id = (int) $r['ativo_id'];
            $qty = (float) $r['quantidade'];
            $qtyMonth = (float) $r['qtd_mensal'];
            $baseMonth = (float) $r['base_mensal'];
            $family = (string) ($r['familia'] ?: 'un');
            $lines[] = [
                'ponto_id' => $id,
                'ponto' => trim((string) ($r['ponto_lub'] ?? $r['ativo_nome'] ?? '')),
                'ponto_tag' => (string) ($r['ativo_tag'] ?? ''),
                'equipamento_id' => (int) ($r['equipamento_id'] ?: $id),
                'equipamento' => (string) ($r['equipamento_nome'] ?? $r['ativo_nome']),
                'equipamento_tag' => '',
                'area' => (string) ($r['setor_nome'] ?? 'Geral'),
                'lubrificante' => (string) $r['lubrificante'],
                'qtd_aplicacao' => round($qty, 4),
                'unidade' => (string) ($r['unidade'] ?: 'g'),
                'frequencia' => (string) ($r['frequencia'] ?: 'Mensal'),
                'consumo_mes' => round($qtyMonth, 4),
                'consumo_ano' => round($qtyMonth * 12.0, 4),
                'base_mes' => round($baseMonth, 4),
                'familia' => $family,
                'rota' => (string) ($r['rota'] ?? ''),
                'sap' => (string) ($r['sap'] ?? ''),
            ];
        }

        return self::finalizeReport($db, $byId, $lines, $period);
    }

    private static function buildFromJson(PDO $db, string $period): array
    {
        $assets = [];
        try {
            $assets = $db->query(
                "SELECT id, nome, tag, tipo, pai_id, dados_tecnicos FROM ativos ORDER BY id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return self::emptyResult($period, 'Não foi possível ler os ativos.');
        }

        $byId = [];
        foreach ($assets as $a) {
            $byId[(int) $a['id']] = $a;
        }

        $lines = [];
        foreach ($assets as $a) {
            $tech = self::parseTech($a['dados_tecnicos'] ?? '');
            $material = trim((string) ($tech['material'] ?? ''));
            $qty = self::parseNumber($tech['qtd_material'] ?? $tech['quantidade'] ?? 0);
            if ($material === '' || $qty <= 0) {
                continue;
            }

            $unid = trim((string) ($tech['unid_material'] ?? $tech['unidade'] ?? 'g'));
            $periodo = trim((string) ($tech['periodo'] ?? $tech['frequencia'] ?? ''));
            $factorMonth = self::monthlyFactor($periodo);
            $qtyMonth = $qty * $factorMonth;
            $qtyYear = $qtyMonth * 12.0;
            [$baseMonth, $family] = self::toBase($qtyMonth, $unid);

            $id = (int) $a['id'];
            $equip = self::findAncestor($id, $byId, 'equipamento') ?: $a;
            $area = self::findAncestor($id, $byId, 'setor')
                ?: self::findAncestor($id, $byId, 'unidade');

            $lines[] = [
                'ponto_id' => $id,
                'ponto' => trim((string) ($tech['ponto_lub'] ?? $a['nome'] ?? '')),
                'ponto_tag' => (string) ($a['tag'] ?? ''),
                'equipamento_id' => (int) ($equip['id'] ?? $id),
                'equipamento' => (string) ($equip['nome'] ?? $a['nome']),
                'equipamento_tag' => (string) ($equip['tag'] ?? ''),
                'area' => (string) ($area['nome'] ?? 'Geral'),
                'lubrificante' => $material,
                'qtd_aplicacao' => round($qty, 4),
                'unidade' => $unid !== '' ? $unid : 'g',
                'frequencia' => $periodo !== '' ? $periodo : 'Mensal',
                'consumo_mes' => round($qtyMonth, 4),
                'consumo_ano' => round($qtyYear, 4),
                'base_mes' => round($baseMonth, 4),
                'familia' => $family,
                'rota' => (string) ($tech['rota'] ?? ''),
                'sap' => (string) ($tech['sap'] ?? ''),
            ];
        }

        return self::finalizeReport($db, $byId, $lines, $period);
    }

    private static function finalizeReport(PDO $db, array $byId, array $lines, string $period): array
    {
        $osByEquip = self::completedOsThisMonth($db, $byId);
        foreach ($lines as &$line) {
            $eid = $line['equipamento_id'];
            $line['os_concluidas_mes'] = (int) ($osByEquip[$eid]['count'] ?? 0);
        }
        unset($line);

        $equipments = self::rollUpEquipment($lines);
        $lubricants = self::rollUpLubricant($lines);
        $factor = $period === 'year' ? 12.0 : 1.0;

        $totKg = 0.0;
        $totL = 0.0;
        foreach ($lines as $ln) {
            if ($ln['familia'] === 'kg') {
                $totKg += $ln['base_mes'] * $factor;
            } elseif ($ln['familia'] === 'l') {
                $totL += $ln['base_mes'] * $factor;
            }
        }

        return [
            'period' => $period,
            'generated_at' => date('c'),
            'period_label' => $period === 'year' ? 'Anual estimado' : 'Mensal estimado',
            'totals' => [
                'equipamentos' => count($equipments),
                'pontos' => count($lines),
                'lubrificantes' => count($lubricants),
                'consumo_kg' => round($totKg, 3),
                'consumo_l' => round($totL, 3),
                'os_concluidas_mes' => array_sum(array_column($osByEquip, 'count')),
            ],
            'equipments' => $equipments,
            'lubricants' => $lubricants,
            'lines' => $lines,
            'has_data' => count($lines) > 0,
        ];
    }

    private static function emptyResult(string $period, string $error): array
    {
        return [
            'period' => $period,
            'generated_at' => date('c'),
            'period_label' => $period === 'year' ? 'Anual estimado' : 'Mensal estimado',
            'error' => $error,
            'totals' => [
                'equipamentos' => 0, 'pontos' => 0, 'lubrificantes' => 0,
                'consumo_kg' => 0, 'consumo_l' => 0, 'os_concluidas_mes' => 0,
            ],
            'equipments' => [],
            'lubricants' => [],
            'lines' => [],
            'has_data' => false,
        ];
    }

    private static function parseTech($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    private static function findAncestor(int $id, array $byId, string $tipo): ?array
    {
        $n = $byId[$id] ?? null;
        $guard = 0;
        while ($n && $guard++ < 50) {
            if (strtolower((string) ($n['tipo'] ?? '')) === $tipo) {
                return $n;
            }
            $pid = (int) ($n['pai_id'] ?? 0);
            if ($pid <= 0 || !isset($byId[$pid])) {
                break;
            }
            $n = $byId[$pid];
        }
        return null;
    }

    private static function completedOsThisMonth(PDO $db, array $byId): array
    {
        $out = [];
        try {
            $rows = $db->query(
                "SELECT ativo_id, COUNT(*) AS c FROM ordens
                 WHERE situacao = 'Concluído'
                 AND (
                    COALESCE(data_conclusao, data_execucao, data_planejada) LIKE strftime('%Y-%m', 'now') || '%'
                    OR date(COALESCE(data_conclusao, data_execucao, data_planejada)) >= date('now', 'start of month')
                 )
                 GROUP BY ativo_id"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return $out;
        }

        foreach ($rows as $r) {
            $aid = (int) ($r['ativo_id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $equip = self::findAncestor($aid, $byId, 'equipamento') ?: ($byId[$aid] ?? null);
            $eid = (int) ($equip['id'] ?? $aid);
            if (!isset($out[$eid])) {
                $out[$eid] = ['count' => 0];
            }
            $out[$eid]['count'] += (int) $r['c'];
        }
        return $out;
    }

    private static function rollUpEquipment(array $lines): array
    {
        $map = [];
        foreach ($lines as $ln) {
            $id = $ln['equipamento_id'];
            if (!isset($map[$id])) {
                $map[$id] = [
                    'id' => $id,
                    'nome' => $ln['equipamento'],
                    'tag' => $ln['equipamento_tag'],
                    'area' => $ln['area'],
                    'pontos' => 0,
                    'lubrificantes' => [],
                    'consumo_kg_mes' => 0.0,
                    'consumo_l_mes' => 0.0,
                    'os_concluidas_mes' => $ln['os_concluidas_mes'],
                ];
            }
            $map[$id]['pontos']++;
            $lub = $ln['lubrificante'];
            if (!isset($map[$id]['lubrificantes'][$lub])) {
                $map[$id]['lubrificantes'][$lub] = [
                    'nome' => $lub,
                    'consumo_mes' => 0.0,
                    'unidade' => $ln['unidade'],
                    'pontos' => 0,
                ];
            }
            $map[$id]['lubrificantes'][$lub]['consumo_mes'] += $ln['consumo_mes'];
            $map[$id]['lubrificantes'][$lub]['pontos']++;
            if ($ln['familia'] === 'kg') {
                $map[$id]['consumo_kg_mes'] += $ln['base_mes'];
            } elseif ($ln['familia'] === 'l') {
                $map[$id]['consumo_l_mes'] += $ln['base_mes'];
            }
        }

        $list = array_values($map);
        foreach ($list as &$eq) {
            $eq['consumo_kg_mes'] = round($eq['consumo_kg_mes'], 3);
            $eq['consumo_l_mes'] = round($eq['consumo_l_mes'], 3);
            $eq['lubrificantes'] = array_values($eq['lubrificantes']);
            foreach ($eq['lubrificantes'] as &$lb) {
                $lb['consumo_mes'] = round($lb['consumo_mes'], 3);
            }
            unset($lb);
        }
        unset($eq);

        usort($list, static function ($a, $b) {
            return strcasecmp($a['area'] . $a['nome'], $b['area'] . $b['nome']);
        });
        return $list;
    }

    private static function rollUpLubricant(array $lines): array
    {
        $map = [];
        foreach ($lines as $ln) {
            $key = (function_exists('mb_strtolower') ? mb_strtolower($ln['lubrificante'], 'UTF-8') : strtolower($ln['lubrificante'])) . '|' . $ln['familia'];
            if (!isset($map[$key])) {
                $map[$key] = [
                    'nome' => $ln['lubrificante'],
                    'unidade_base' => $ln['familia'] === 'l' ? 'L' : ($ln['familia'] === 'kg' ? 'kg' : $ln['unidade']),
                    'consumo_mes' => 0.0,
                    'consumo_ano' => 0.0,
                    'equipamentos' => [],
                    'pontos' => 0,
                ];
            }
            $qty = $ln['familia'] === 'un' ? $ln['consumo_mes'] : $ln['base_mes'];
            $map[$key]['consumo_mes'] += $qty;
            $map[$key]['consumo_ano'] += $qty * 12;
            $map[$key]['pontos']++;
            $map[$key]['equipamentos'][$ln['equipamento_id']] = $ln['equipamento'];
        }

        $list = array_values($map);
        foreach ($list as &$lb) {
            $lb['n_equipamentos'] = count($lb['equipamentos']);
            $lb['equipamentos'] = array_values($lb['equipamentos']);
            $lb['consumo_mes'] = round($lb['consumo_mes'], 3);
            $lb['consumo_ano'] = round($lb['consumo_ano'], 3);
        }
        unset($lb);
        usort($list, static function ($a, $b) {
            return $b['consumo_mes'] <=> $a['consumo_mes'];
        });
        return $list;
    }

    private static function monthlyFactor(string $periodo): float
    {
        $p = function_exists('mb_strtolower') ? mb_strtolower($periodo, 'UTF-8') : strtolower($periodo);
        $p = strtr($p, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ã' => 'a']);
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

    private static function toBase(float $qty, string $unid): array
    {
        $u = function_exists('mb_strtolower') ? mb_strtolower(trim($unid), 'UTF-8') : strtolower(trim($unid));
        $u = strtr($u, ['á' => 'a', 'é' => 'e', 'í' => 'i']);
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

    private static function parseNumber($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^0-9.\-]/', '', $s);
        return is_numeric($s) ? (float) $s : 0.0;
    }
}
