<?php
/**
 * Monta dados estruturados do Plano de Lubrificação (padrão executivo Pet/KHS).
 */
class LubricationPlanBuilder
{
    /** @var PDO */
    private $db;

    /** @var array<int, array> */
    private $assetMap = [];

    /** @var array<int, int[]> */
    private $childrenMap = [];

    /** @var array<string, array> */
    private $catalogByName = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array{frequency?:string,include_empty_equipment?:bool} $options
     */
    public function build(int $rootAssetId, array $options = []): array
    {
        $frequency = trim((string) ($options['frequency'] ?? ''));
        $includeEmptyEquipment = (bool) ($options['include_empty_equipment'] ?? false);

        $root = $this->fetchAsset($rootAssetId);
        if (!$root) {
            throw new InvalidArgumentException('Ativo raiz não encontrado.');
        }

        $this->loadAssets();
        $this->loadCatalogIndex();

        $lineStations = $this->buildLineStations($rootAssetId);
        $descendantIds = $this->collectDescendants($rootAssetId);

        $areas = [];
        $stats = [
            'equipments' => 0,
            'components' => 0,
            'points' => 0,
            'areas' => 0,
            'slides_estimated' => 0,
        ];

        $equipmentBuckets = $this->groupByEquipment($descendantIds, $frequency);

        foreach ($equipmentBuckets as $bucket) {
            if (!$includeEmptyEquipment && empty($bucket['points']) && empty($bucket['components'])) {
                continue;
            }

            $areaName = $bucket['area'] ?: 'GERAL';
            if (!isset($areas[$areaName])) {
                $areas[$areaName] = ['name' => $areaName, 'equipments' => []];
                $stats['areas']++;
            }

            $subsections = $this->buildSubsections($bucket);
            $eq = $bucket['equipment'];

            $stats['equipments']++;
            $stats['components'] += count($bucket['components']);
            $stats['points'] += count($bucket['points']);

            $areas[$areaName]['equipments'][] = [
                'id' => (int) ($eq['id'] ?? 0),
                'nome' => (string) ($eq['nome'] ?? ''),
                'tag' => (string) ($eq['tag'] ?? ''),
                'tipo' => (string) ($eq['tipo'] ?? 'equipamento'),
                'imagem' => (string) ($eq['imagem'] ?? ''),
                'imagem_3d' => (string) ($eq['imagem_3d'] ?? ''),
                'fabricante' => (string) ($eq['fabricante'] ?? ''),
                'modelo' => (string) ($eq['modelo'] ?? ''),
                'num_serie' => (string) ($eq['num_serie'] ?? ''),
                'obs' => (string) ($eq['obs'] ?? ''),
                'local_instalacao' => $this->buildLocalInstalacao((int) ($eq['id'] ?? 0)),
                'components' => $bucket['components'],
                'points' => $bucket['points'],
                'subsections' => $subsections,
            ];
        }

        $stats['slides_estimated'] = $this->estimateSlideCount(
            $areas,
            trim((string) ($root['imagem'] ?? '')) !== ''
        );

        $uniqueLubs = [];
        foreach ($areas as $area) {
            foreach ($area['equipments'] as $eq) {
                foreach ($eq['points'] ?? [] as $pt) {
                    $m = trim((string) ($pt['material'] ?? ''));
                    if ($m !== '') {
                        $uniqueLubs[$m] = true;
                    }
                }
            }
        }

        return [
            'meta' => [
                'root_id' => $rootAssetId,
                'root_name' => (string) ($root['nome'] ?? ''),
                'root_tag' => (string) ($root['tag'] ?? ''),
                'root_imagem' => (string) ($root['imagem'] ?? ''),
                'root_obs' => (string) ($root['obs'] ?? ''),
                'frequency_filter' => $frequency,
                'generated_at' => date('c'),
                'stats' => $stats,
                'line_stations' => $lineStations,
                'line_label' => $this->extractLineLabel((string) ($root['nome'] ?? '')),
                'unique_lubricants' => array_keys($uniqueLubs),
            ],
            'areas' => array_values($areas),
        ];
    }

    public function buildEquipmentSlice(int $rootAssetId, int $offset, int $limit, array $options = []): array
    {
        $plan = $this->build($rootAssetId, $options);
        $allEquipments = [];
        foreach ($plan['areas'] as $area) {
            foreach ($area['equipments'] as $eq) {
                $allEquipments[] = ['area' => $area['name'], 'equipment' => $eq];
            }
        }

        $total = count($allEquipments);
        $slice = array_slice($allEquipments, max(0, $offset), max(1, min(50, $limit)));

        return [
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'slice' => $slice,
            'done' => ($offset + count($slice)) >= $total,
            'meta' => $plan['meta'],
        ];
    }

    /** @return array<int, array{num:int,nome:string,tag:string,imagem:string}> */
    private function buildLineStations(int $rootId): array
    {
        $stations = [];
        $children = $this->childrenMap[$rootId] ?? [];
        $n = 0;
        foreach ($children as $childId) {
            $node = $this->assetMap[$childId] ?? null;
            if (!$node) {
                continue;
            }
            $tipo = (string) ($node['tipo'] ?? '');
            if (!in_array($tipo, ['equipamento', 'setor', 'unidade'], true)) {
                continue;
            }
            $n++;
            $stations[] = [
                'num' => $n,
                'nome' => (string) ($node['nome'] ?? ''),
                'tag' => (string) ($node['tag'] ?? ''),
                'imagem' => (string) ($node['imagem'] ?? ''),
                'tipo' => $tipo,
            ];
        }
        return $stations;
    }

    private function buildSubsections(array $bucket): array
    {
        $byKey = [];
        $eqName = (string) ($bucket['equipment']['nome'] ?? '');

        foreach ($bucket['points'] as $pt) {
            $compName = trim((string) ($pt['component'] ?? ''));
            $key = $compName !== '' ? $compName : '_geral_';
            if (!isset($byKey[$key])) {
                $comp = null;
                foreach ($bucket['components'] as $c) {
                    if ((string) ($c['nome'] ?? '') === $compName) {
                        $comp = $c;
                        break;
                    }
                }
                $byKey[$key] = [
                    'title' => $compName !== '' ? $compName : $eqName,
                    'subtitle' => $compName !== '' ? $eqName : '',
                    'imagem' => (string) ($comp['imagem'] ?? $pt['imagem'] ?? $bucket['equipment']['imagem'] ?? ''),
                    'obs' => (string) ($comp['obs'] ?? ''),
                    'points' => [],
                ];
            }
            $byKey[$key]['points'][] = $pt;
        }

        if (empty($byKey) && !empty($bucket['components'])) {
            foreach ($bucket['components'] as $comp) {
                $byKey[(string) $comp['nome']] = [
                    'title' => (string) ($comp['nome'] ?? ''),
                    'subtitle' => $eqName,
                    'imagem' => (string) ($comp['imagem'] ?? ''),
                    'obs' => '',
                    'points' => [],
                ];
            }
        }

        return array_values($byKey);
    }

    private function buildLocalInstalacao(int $nodeId): string
    {
        $parts = [];
        $visited = [];
        $currentId = $nodeId;
        while ($currentId && !in_array($currentId, $visited, true)) {
            $visited[] = $currentId;
            $node = $this->assetMap[$currentId] ?? null;
            if (!$node) {
                break;
            }
            array_unshift($parts, (string) ($node['nome'] ?? ''));
            $parentId = (int) ($node['pai_id'] ?? 0);
            if ($parentId <= 0) {
                break;
            }
            $currentId = $parentId;
        }
        return implode(' / ', array_filter($parts));
    }

    private function loadAssets(): void
    {
        $rows = $this->db->query('SELECT * FROM ativos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assetMap = [];
        $this->childrenMap = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->assetMap[$id] = $row;
            $parentId = (int) ($row['pai_id'] ?? 0);
            if ($parentId > 0) {
                $this->childrenMap[$parentId][] = $id;
            }
        }
    }

    private function loadCatalogIndex(): void
    {
        $this->catalogByName = [];
        try {
            $rows = $this->db->query('SELECT nome, imagem, codigo, fabricante, tipo, descricao FROM catalogo')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $key = mb_strtolower(trim((string) ($row['nome'] ?? '')));
                if ($key !== '') {
                    $this->catalogByName[$key] = $row;
                }
            }
        } catch (Throwable $e) {
        }
    }

    private function fetchAsset(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ativos WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return int[] */
    private function collectDescendants(int $rootId): array
    {
        $result = [];
        $stack = [$rootId];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!isset($this->childrenMap[$current])) {
                continue;
            }
            foreach ($this->childrenMap[$current] as $childId) {
                $result[] = $childId;
                $stack[] = $childId;
            }
        }
        return $result;
    }

    /** @param int[] $descendantIds */
    private function groupByEquipment(array $descendantIds, string $frequency): array
    {
        $buckets = [];
        foreach ($descendantIds as $nodeId) {
            $node = $this->assetMap[$nodeId] ?? null;
            if (!$node) {
                continue;
            }

            $tech = $this->parseTech($node['dados_tecnicos'] ?? '');
            $tipo = (string) ($node['tipo'] ?? '');
            $ancestors = $this->resolveAncestors($nodeId);
            $equipment = $ancestors['equipment'] ?? $node;
            $equipmentId = (int) ($equipment['id'] ?? $nodeId);

            if (!isset($buckets[$equipmentId])) {
                $buckets[$equipmentId] = [
                    'area' => $ancestors['area'] ?? '',
                    'equipment' => $equipment,
                    'components' => [],
                    'points' => [],
                ];
            }

            if ($tipo === 'componente') {
                $buckets[$equipmentId]['components'][] = $this->formatComponent($node, $tech);
            }

            $hasLubData = !empty($tech['material']) || $tipo === 'ponto';
            if (!$hasLubData) {
                continue;
            }
            if ($frequency !== '' && !$this->matchesFrequency($tech['periodo'] ?? '', $frequency)) {
                continue;
            }

            $buckets[$equipmentId]['points'][] = $this->formatPoint($node, $tech, $ancestors);
        }
        return $buckets;
    }

    private function resolveAncestors(int $nodeId): array
    {
        $area = '';
        $equipment = null;
        $component = null;
        $visited = [];
        $currentId = $nodeId;

        while ($currentId && !in_array($currentId, $visited, true)) {
            $visited[] = $currentId;
            $node = $this->assetMap[$currentId] ?? null;
            if (!$node) {
                break;
            }
            $tipo = (string) ($node['tipo'] ?? '');
            if ($component === null && $tipo === 'componente') {
                $component = $node;
            }
            if ($equipment === null && $tipo === 'equipamento') {
                $equipment = $node;
            }
            if ($area === '' && ($tipo === 'setor' || $tipo === 'unidade')) {
                $area = (string) ($node['nome'] ?? '');
            }
            $parentId = (int) ($node['pai_id'] ?? 0);
            if ($parentId <= 0) {
                break;
            }
            $currentId = $parentId;
        }

        if ($equipment === null) {
            $node = $this->assetMap[$nodeId] ?? null;
            if ($node && in_array($node['tipo'] ?? '', ['equipamento', 'componente', 'ponto'], true)) {
                $equipment = $node;
            }
        }

        return ['area' => $area, 'equipment' => $equipment, 'component' => $component];
    }

    private function parseTech($raw): array
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

    private function matchesFrequency(string $periodo, string $filter): bool
    {
        if ($filter === '') {
            return true;
        }
        return stripos($periodo, $filter) !== false;
    }

    public static function periodoToDays(string $periodo): ?int
    {
        $p = mb_strtolower(trim($periodo));
        if ($p === '') {
            return null;
        }
        if (preg_match('/(\d+)\s*dia/', $p, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(\d+)\s*mes/', $p, $m)) {
            return (int) $m[1] * 30;
        }
        if (preg_match('/(\d+)\s*ano/', $p, $m)) {
            return (int) $m[1] * 365;
        }
        if (mb_strpos($p, 'di') !== false && mb_strpos($p, 'quinzen') === false) {
            return 1;
        }
        if (mb_strpos($p, 'quinzen') !== false) {
            return 15;
        }
        if (mb_strpos($p, 'seman') !== false) {
            return 7;
        }
        if (mb_strpos($p, 'mens') !== false) {
            return 30;
        }
        if (mb_strpos($p, 'trimest') !== false) {
            return 90;
        }
        if (mb_strpos($p, 'semest') !== false) {
            return 180;
        }
        if (mb_strpos($p, 'an') !== false) {
            return 365;
        }
        return null;
    }

    private function formatComponent(array $node, array $tech): array
    {
        return [
            'id' => (int) $node['id'],
            'nome' => (string) ($node['nome'] ?? ''),
            'tag' => (string) ($node['tag'] ?? ''),
            'imagem' => (string) ($node['imagem'] ?? ''),
            'fabricante' => (string) ($node['fabricante'] ?? ''),
            'modelo' => (string) ($node['modelo'] ?? ''),
            'obs' => (string) ($node['obs'] ?? ''),
            'ip' => (string) ($tech['ip'] ?? $tech['cip'] ?? ''),
        ];
    }

    private function formatPoint(array $node, array $tech, array $ancestors): array
    {
        $material = (string) ($tech['material'] ?? '');
        $catalog = $this->catalogByName[mb_strtolower(trim($material))] ?? null;
        $periodo = (string) ($tech['periodo'] ?? '');
        $freqTroca = self::periodoToDays($periodo);
        $freqInspecao = null;
        if (isset($tech['freq_inspecao_dias']) && $tech['freq_inspecao_dias'] !== '') {
            $freqInspecao = (int) $tech['freq_inspecao_dias'];
        } elseif ($freqTroca !== null) {
            $freqInspecao = $freqTroca >= 60 ? (int) round($freqTroca / 2) : max(7, (int) round($freqTroca / 2));
        }

        $equip = $ancestors['equipment'] ?? [];
        $comp = $ancestors['component'] ?? null;
        $localParts = array_filter([
            (string) ($equip['nome'] ?? ''),
            $comp ? (string) ($comp['nome'] ?? '') : '',
            (string) ($tech['ponto_lub'] ?? $node['nome'] ?? ''),
        ]);

        return [
            'id' => (int) $node['id'],
            'nome' => (string) ($node['nome'] ?? ''),
            'tag' => (string) ($node['tag'] ?? ''),
            'ponto_lub' => (string) ($tech['ponto_lub'] ?? $node['nome'] ?? ''),
            'ponto_num' => (string) ($tech['ip'] ?? $tech['cip'] ?? ''),
            'material' => $material,
            'qtd_material' => (string) ($tech['qtd_material'] ?? ''),
            'unid_material' => (string) ($tech['unid_material'] ?? ''),
            'periodo' => $periodo,
            'freq_troca_dias' => $freqTroca,
            'freq_inspecao_dias' => $freqInspecao,
            'servico' => (string) ($tech['servico'] ?? ''),
            'metodo' => (string) ($tech['metodo'] ?? ''),
            'condicao' => (string) ($tech['condicao'] ?? ''),
            'sap' => (string) ($tech['sap'] ?? ''),
            'almox' => (string) ($tech['almox'] ?? ''),
            'cod_serv' => (string) ($tech['cod_serv'] ?? ''),
            'rota' => (string) ($tech['rota'] ?? ''),
            'complemento' => (string) ($tech['complemento'] ?? ''),
            'procedimento' => (string) ($tech['procedimento'] ?? ''),
            'instrucao' => (string) ($tech['instrucao'] ?? ''),
            'sistema_lub' => (string) ($tech['sistema_lub'] ?? ''),
            'prioridade' => (string) ($tech['prioridade'] ?? ''),
            'duracao_m' => (string) ($tech['duracao_m'] ?? ''),
            'ip' => (string) ($tech['ip'] ?? $tech['cip'] ?? ''),
            'imagem' => (string) ($node['imagem'] ?? ''),
            'component' => $comp ? (string) ($comp['nome'] ?? '') : '',
            'local_instalacao' => implode(' / ', $localParts),
            'descricao_ponto' => (string) ($tech['ponto_lub'] ?? $node['nome'] ?? ''),
            'tipo_lubrificante' => $this->inferLubricantType($material, $tech),
            'khs_recomendacao' => $this->inferKhsRecommendation($material, $tech),
            'lub_image' => (string) ($catalog['imagem'] ?? ''),
            'lub_codigo' => (string) ($catalog['codigo'] ?? ''),
            'lub_fabricante' => (string) ($catalog['fabricante'] ?? ''),
            'marker_x' => $tech['marker_x'] ?? $tech['pos_x'] ?? $tech['pin_x'] ?? null,
            'marker_y' => $tech['marker_y'] ?? $tech['pos_y'] ?? $tech['pin_y'] ?? null,
        ];
    }

    private function inferLubricantType(string $material, array $tech): string
    {
        if (!empty($tech['sistema_lub'])) {
            return (string) $tech['sistema_lub'];
        }
        $m = mb_strtolower($material);
        if (mb_strpos($m, 'spray') !== false) {
            return 'Spray';
        }
        if (mb_strpos($m, 'graxa') !== false || mb_strpos($m, 'grease') !== false || mb_strpos($m, '4024') !== false || mb_strpos($m, '3752') !== false) {
            return 'Graxa';
        }
        if (mb_strpos($m, 'gear') !== false || mb_strpos($m, 'óleo') !== false || mb_strpos($m, 'oleo') !== false || mb_strpos($m, 'oil') !== false) {
            return 'Óleo';
        }
        return (string) ($tech['metodo'] ?? 'Óleo');
    }

    private function inferKhsRecommendation(string $material, array $tech): string
    {
        $m = mb_strtolower($material);
        if (mb_strpos($m, '4024') !== false || mb_strpos($m, '4025') !== false) {
            return 'KHS Multi Grease 01';
        }
        if (mb_strpos($m, '3752') !== false) {
            return 'KHS Multi Grease 02';
        }
        if (mb_strpos($m, '4059') !== false) {
            return 'Spray NSF-H1';
        }
        if (mb_strpos($m, '4220') !== false || mb_strpos($m, '4460') !== false) {
            return 'KHS Gear Fluid';
        }
        return (string) ($tech['metodo'] ?? '-');
    }

    private function extractLineLabel(string $rootName): string
    {
        if (preg_match('/LINH[A-Z\s]*([A-Z0-9\.\-\s]+)/iu', $rootName, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]) ?? '');
        }
        if (preg_match('/KHS|K-?\d+/i', $rootName, $m)) {
            return 'LINHA ' . strtoupper(trim($m[0]));
        }
        return 'LINHA DE PRODUÇÃO';
    }

    /**
     * Estimativa alinhada ao renderer (consolidação reduz slides reais).
     *
     * @param array<string, array> $areas
     */
    private function estimateSlideCount(array $areas, bool $hasRootImage): int
    {
        $slides = 3 + 4; // capa, linha, índice SSMA + 4 treinamento
        if ($hasRootImage) {
            $slides++;
        }

        foreach ($areas as $area) {
            foreach ($area['equipments'] ?? [] as $eq) {
                $ptCount = count($eq['points'] ?? []);
                if ($ptCount === 0) {
                    continue;
                }
                $hasImg = trim((string) ($eq['imagem'] ?? '')) !== '';
                if ($hasImg && $ptCount <= 4) {
                    $slides++;
                    continue;
                }
                if ($hasImg) {
                    $slides++;
                }
                $slides += $ptCount <= 5 ? 1 : (int) max(1, ceil($ptCount / 12));
            }
        }

        return $slides;
    }
}
