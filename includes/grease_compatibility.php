<?php
/**
 * Matriz de compatibilidade de espessantes (Noria / prática de campo).
 * Primeiro enchimento é livre. Mistura incompatível exige confirmação de purga.
 */
class GreaseCompatibility
{
    /** Pares compatíveis (além do mesmo código). */
    private static function compatiblePairs(): array
    {
        return [
            'LI|LIC' => true,
            'LIC|LI' => true,
            'CS|CSX' => true,
            'CSX|CS' => true,
        ];
    }

    public static function detectFromText(string $text): ?string
    {
        $t = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ã' => 'a']);
        if ($t === '') {
            return null;
        }
        if (strpos($t, 'poliure') !== false || strpos($t, 'polyurea') !== false || strpos($t, 'polyrex') !== false) {
            return 'PUA';
        }
        if (strpos($t, 'complexo de sulfonato') !== false || strpos($t, 'sulfonate complex') !== false || preg_match('/\bcsx\b/', $t)) {
            return 'CSX';
        }
        if (strpos($t, 'sulfonato') !== false || strpos($t, 'calcium sulfonate') !== false) {
            return 'CS';
        }
        if (strpos($t, 'complexo de litio') !== false || strpos($t, 'lithium complex') !== false || preg_match('/\blic\b/', $t)) {
            return 'LIC';
        }
        if (strpos($t, 'litio') !== false || strpos($t, 'lithium') !== false) {
            return 'LI';
        }
        if (strpos($t, 'aluminio') !== false || strpos($t, 'aluminum complex') !== false) {
            return 'ALC';
        }
        if (strpos($t, 'bentonit') !== false || strpos($t, 'argila') !== false) {
            return 'BENT';
        }
        if (strpos($t, 'ptfe') !== false || strpos($t, 'teflon') !== false) {
            return 'PTFE';
        }
        if (strpos($t, 'mos2') !== false || strpos($t, 'molibden') !== false) {
            return 'MOS2';
        }
        return null;
    }

    public static function detectFromTech(array $tech): ?string
    {
        foreach (['espessante', 'thickener', 'thickener_code'] as $k) {
            $v = strtoupper(trim((string) ($tech[$k] ?? '')));
            if (in_array($v, ['CSX', 'CS', 'LI', 'LIC', 'ALC', 'PUA', 'BENT', 'PTFE', 'MOS2'], true)) {
                return $v;
            }
        }
        $blob = trim((string) ($tech['material'] ?? '') . ' ' . ($tech['kit'] ?? ''));
        return self::detectFromText($blob);
    }

    public static function isCompatible(?string $from, ?string $to): bool
    {
        if ($from === null || $to === null || $from === '' || $to === '') {
            return true;
        }
        if ($from === $to) {
            return true;
        }
        if (in_array($from, ['MOS2', 'PTFE'], true) || in_array($to, ['MOS2', 'PTFE'], true)) {
            return true;
        }
        return isset(self::compatiblePairs()[$from . '|' . $to]);
    }

    private static function looksLikeOil(string $text): bool
    {
        $t = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $t = strtr($t, ['ó' => 'o']);
        $isOil = (strpos($t, 'oleo') !== false || strpos($t, 'oil') !== false || strpos($t, 'iso vg') !== false);
        $isGrease = (strpos($t, 'graxa') !== false || strpos($t, 'grease') !== false);
        return $isOil && !$isGrease;
    }

    /**
     * @throws Exception JSON error_code GREASE_INCOMPATIBLE
     */
    public static function guardOnAsset(PDO $db, int $ativoId, array $newTech, bool $confirmPurge, array $user): ?int
    {
        if ($ativoId <= 0) {
            return null;
        }
        $stmt = $db->prepare('SELECT dados_tecnicos FROM ativos WHERE id = ?');
        $stmt->execute([$ativoId]);
        $raw = $stmt->fetchColumn();
        $oldTech = [];
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            $oldTech = is_array($j) ? $j : [];
        }
        return self::guard($db, $ativoId, $oldTech, $newTech, $confirmPurge, $user);
    }

    public static function guardOnOrder(PDO $db, $ativoId, $appliedMaterial, bool $confirmPurge, array $user): ?int
    {
        $ativoId = (int) $ativoId;
        if ($ativoId <= 0) {
            return null;
        }
        $applied = trim((string) $appliedMaterial);
        if ($applied === '') {
            return null;
        }
        $stmt = $db->prepare('SELECT dados_tecnicos FROM ativos WHERE id = ?');
        $stmt->execute([$ativoId]);
        $raw = $stmt->fetchColumn();
        $oldTech = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $newTech = ['material' => $applied];
        return self::guard($db, $ativoId, $oldTech, $newTech, $confirmPurge, $user);
    }

    private static function guard(PDO $db, int $ativoId, array $oldTech, array $newTech, bool $confirmPurge, array $user): ?int
    {
        $fromMat = trim((string) ($oldTech['material'] ?? ''));
        $toMat = trim((string) ($newTech['material'] ?? ''));
        if ($toMat === '' || $fromMat === '' || strcasecmp($fromMat, $toMat) === 0) {
            return null;
        }
        if (self::looksLikeOil($fromMat) && self::looksLikeOil($toMat)) {
            return null;
        }

        $from = self::detectFromTech($oldTech) ?: self::detectFromText($fromMat);
        $to = self::detectFromTech($newTech) ?: self::detectFromText($toMat);
        if ($from === null && $to === null) {
            return null;
        }
        if (self::isCompatible($from, $to)) {
            return null;
        }

        if (!$confirmPurge) {
            throw new Exception(json_encode([
                'error_code' => 'GREASE_INCOMPATIBLE',
                'message' => 'Espessante incompatível com a graxa já no ponto. É obrigatório purgar/lavar o alojamento antes de aplicar o novo produto.',
                'details' => [
                    'from_thickener' => $from,
                    'to_thickener' => $to,
                    'from_material' => $fromMat,
                    'to_material' => $toMat,
                ],
                'suggestion' => 'Confirme a purga completa. O sistema emitirá uma O.S. de lavagem.',
            ], JSON_UNESCAPED_UNICODE));
        }

        $hoje = date('Y-m-d');
        $who = $user['nome'] ?? $user['name'] ?? 'SYSTEM';
        $desc = '[PURGA OBRIGATÓRIA] Lavagem de alojamento — troca de graxa incompatível';
        $obs = "De: {$fromMat} (" . ($from ?: '?') . ") Para: {$toMat} (" . ($to ?: '?') . "). Confirmar remoção total do produto anterior antes da nova carga.";
        $ins = $db->prepare("
            INSERT INTO ordens (descricao, responsavel, data_planejada, prioridade, situacao, observacao, ativo_id, tipo_manutencao, data_emissao, last_sync)
            VALUES (?, ?, ?, 'Alta', 'Pendente', ?, ?, 'Preventiva', ?, datetime('now'))
        ");
        $ins->execute([$desc, $who, $hoje, $obs, $ativoId, $hoje]);
        $osId = (int) $db->lastInsertId();

        try {
            $db->exec("CREATE TABLE IF NOT EXISTS grease_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ativo_id INTEGER,
                from_thickener TEXT,
                to_thickener TEXT,
                from_material TEXT,
                to_material TEXT,
                confirmed INTEGER DEFAULT 0,
                wash_os_id INTEGER,
                usuario TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $ev = $db->prepare('INSERT INTO grease_events (ativo_id, from_thickener, to_thickener, from_material, to_material, confirmed, wash_os_id, usuario) VALUES (?,?,?,?,?,1,?,?)');
            $ev->execute([$ativoId, $from, $to, $fromMat, $toMat, $osId, (string) $who]);
        } catch (Exception $e) {
            error_log('grease_events: ' . $e->getMessage());
        }

        return $osId;
    }
}
