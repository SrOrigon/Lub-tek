<?php
/**
 * Projeção estruturada de dados de lubrificação (JSON -> colunas).
 * Mantém dados_tecnicos como fonte da UI; a tabela serve analytics SQL.
 */
class LubricationTechSync
{
    public static function ensureTable(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS ativos_lubrificacao (
            ativo_id INTEGER PRIMARY KEY REFERENCES ativos(id) ON DELETE CASCADE,
            lubrificante TEXT,
            quantidade REAL,
            unidade TEXT,
            frequencia TEXT,
            qtd_mensal REAL,
            familia TEXT,
            base_mensal REAL,
            ponto_lub TEXT,
            rota TEXT,
            sap TEXT,
            d_int REAL,
            d_ext REAL,
            largura_b REAL,
            rpm REAL,
            setor_id INTEGER,
            setor_nome TEXT,
            equipamento_id INTEGER,
            equipamento_nome TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_lub_material ON ativos_lubrificacao(lubrificante)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_lub_setor ON ativos_lubrificacao(setor_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_lub_freq ON ativos_lubrificacao(frequencia)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_lub_familia ON ativos_lubrificacao(familia)');
    }

    public static function tableExists(PDO $db): bool
    {
        try {
            $n = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ativos_lubrificacao'")
                ->fetchColumn();
            return (bool) $n;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function syncAsset(PDO $db, int $ativoId, ?array $byId = null): void
    {
        if ($ativoId <= 0) {
            return;
        }
        self::ensureTable($db);

        $stmt = $db->prepare('SELECT id, nome, tag, tipo, pai_id, dados_tecnicos FROM ativos WHERE id = ?');
        $stmt->execute([$ativoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $db->prepare('DELETE FROM ativos_lubrificacao WHERE ativo_id = ?')->execute([$ativoId]);
            return;
        }

        $tech = self::parseTech($row['dados_tecnicos'] ?? '');
        $material = trim((string) ($tech['material'] ?? ''));
        $qty = self::parseNumber($tech['qtd_material'] ?? $tech['quantidade'] ?? 0);
        if ($material === '' || $qty <= 0) {
            $db->prepare('DELETE FROM ativos_lubrificacao WHERE ativo_id = ?')->execute([$ativoId]);
            return;
        }

        $unid = trim((string) ($tech['unid_material'] ?? $tech['unidade'] ?? 'g'));
        $periodo = trim((string) ($tech['periodo'] ?? $tech['frequencia'] ?? ''));
        $factorMonth = self::monthlyFactor($periodo);
        $qtyMonth = $qty * $factorMonth;
        [$baseMonth, $family] = self::toBase($qtyMonth, $unid);

        $equip = null;
        $area = null;
        if (is_array($byId) && isset($byId[$ativoId])) {
            $equip = self::findAncestorInMap($ativoId, $byId, 'equipamento') ?: $row;
            $area = self::findAncestorInMap($ativoId, $byId, 'setor')
                ?: self::findAncestorInMap($ativoId, $byId, 'unidade');
        } else {
            $chain = self::loadAncestorChain($db, $ativoId);
            $equip = self::findInChain($chain, 'equipamento') ?: $row;
            $area = self::findInChain($chain, 'setor') ?: self::findInChain($chain, 'unidade');
        }

        $ins = $db->prepare(
            'INSERT OR REPLACE INTO ativos_lubrificacao (
                ativo_id, lubrificante, quantidade, unidade, frequencia, qtd_mensal, familia, base_mensal,
                ponto_lub, rota, sap, d_int, d_ext, largura_b, rpm, setor_id, setor_nome, equipamento_id, equipamento_nome, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, datetime(\'now\'))'
        );
        $ins->execute([
            $ativoId,
            $material,
            $qty,
            $unid !== '' ? $unid : 'g',
            $periodo !== '' ? $periodo : 'Mensal',
            round($qtyMonth, 6),
            $family,
            round($baseMonth, 6),
            trim((string) ($tech['ponto_lub'] ?? $row['nome'] ?? '')),
            (string) ($tech['rota'] ?? ''),
            (string) ($tech['sap'] ?? ''),
            self::parseNumber($tech['d'] ?? $tech['bearing_d'] ?? 0) ?: null,
            self::parseNumber($tech['D'] ?? $tech['bearing_D'] ?? 0) ?: null,
            self::parseNumber($tech['B'] ?? 0) ?: null,
            self::parseNumber($tech['rpm'] ?? 0) ?: null,
            isset($area['id']) ? (int) $area['id'] : null,
            (string) ($area['nome'] ?? 'Geral'),
            isset($equip['id']) ? (int) $equip['id'] : $ativoId,
            (string) ($equip['nome'] ?? $row['nome']),
        ]);
    }

    public static function backfill(PDO $db): int
    {
        self::ensureTable($db);
        $assets = $db->query('SELECT id, nome, tag, tipo, pai_id, dados_tecnicos FROM ativos ORDER BY id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($assets as $a) {
            $byId[(int) $a['id']] = $a;
        }
        $n = 0;
        foreach ($assets as $a) {
            self::syncAsset($db, (int) $a['id'], $byId);
            $n++;
        }
        return $n;
    }

    public static function parseTech($raw): array
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

    public static function monthlyFactor(string $periodo): float
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

    public static function toBase(float $qty, string $unid): array
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

    public static function parseNumber($value): float
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

    private static function loadAncestorChain(PDO $db, int $id): array
    {
        $chain = [];
        $current = $id;
        $guard = 0;
        $stmt = $db->prepare('SELECT id, nome, tipo, pai_id FROM ativos WHERE id = ?');
        while ($current && $guard++ < 50) {
            $stmt->execute([$current]);
            $n = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$n) {
                break;
            }
            $chain[] = $n;
            $current = (int) ($n['pai_id'] ?? 0);
        }
        return $chain;
    }

    private static function findInChain(array $chain, string $tipo): ?array
    {
        foreach ($chain as $n) {
            if (strtolower((string) ($n['tipo'] ?? '')) === $tipo) {
                return $n;
            }
        }
        return null;
    }

    private static function findAncestorInMap(int $id, array $byId, string $tipo): ?array
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
}
