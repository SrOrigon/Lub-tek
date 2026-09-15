<?php
/**
 * MTBF / MTTR a partir das O.S. reais (sem números inventados).
 */
class ReliabilityMetrics
{
    public static function compute(PDO $db, ?int $assetId = null): array
    {
        $empty = [
            'failures_12m' => 0,
            'mtbf_hours' => null,
            'mttr_hours' => null,
            'sample_ok' => false,
            'hint' => 'Sem falhas corretivas concluídas nos últimos 12 meses para calcular MTBF/MTTR.',
        ];

        $assetSql = $assetId ? ' AND ativo_id = ?' : '';
        $params = $assetId ? [$assetId] : [];

        try {
            $nEquip = 0;
            $eqSql = "SELECT COUNT(*) FROM ativos WHERE tipo IN ('equipamento', 'ponto')";
            if ($assetId) {
                $st = $db->prepare($eqSql . ' AND (id = ? OR pai_id = ?)');
                $st->execute([$assetId, $assetId]);
                $nEquip = (int) $st->fetchColumn();
            } else {
                $nEquip = (int) $db->query($eqSql)->fetchColumn();
            }
            $nEquip = max(1, $nEquip);

            $sql = "SELECT tipo_manutencao, prioridade,
                    COALESCE(NULLIF(data_conclusao,''), NULLIF(data_execucao,''), last_sync) AS fim,
                    COALESCE(NULLIF(data_emissao,''), NULLIF(data_planejada,''), last_sync) AS ini
                    FROM ordens
                    WHERE situacao = 'Concluído'
                    AND date(COALESCE(NULLIF(data_conclusao,''), NULLIF(data_execucao,''), last_sync, data_planejada))
                        >= date('now', '-365 days')" . $assetSql;
            $st = $db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return $empty;
        }

        $mttrSum = 0.0;
        $mttrN = 0;
        $failures = 0;
        foreach ($rows as $r) {
            if (!self::isFailure($r)) {
                continue;
            }
            $failures++;
            $ini = strtotime((string) ($r['ini'] ?? ''));
            $fim = strtotime((string) ($r['fim'] ?? ''));
            if ($ini && $fim && $fim >= $ini) {
                $h = ($fim - $ini) / 3600.0;
                if ($h > 0 && $h < 24 * 60) {
                    $mttrSum += $h;
                    $mttrN++;
                }
            }
        }

        if ($failures <= 0) {
            return $empty;
        }

        $windowH = 365.0 * 24.0;
        $mtbf = ($nEquip * $windowH) / $failures;
        $mttr = $mttrN > 0 ? ($mttrSum / $mttrN) : null;

        return [
            'failures_12m' => $failures,
            'mtbf_hours' => round($mtbf, 1),
            'mttr_hours' => $mttr !== null ? round($mttr, 2) : null,
            'sample_ok' => true,
            'equipamentos' => $nEquip,
            'hint' => $failures . ' falha(s) corretiva(s)/crítica(s) em 12 meses · MTBF = horas-planta / falhas.',
        ];
    }

    private static function isFailure(array $r): bool
    {
        $tipo = function_exists('mb_strtolower')
            ? mb_strtolower((string) ($r['tipo_manutencao'] ?? ''), 'UTF-8')
            : strtolower((string) ($r['tipo_manutencao'] ?? ''));
        $prio = function_exists('mb_strtolower')
            ? mb_strtolower((string) ($r['prioridade'] ?? ''), 'UTF-8')
            : strtolower((string) ($r['prioridade'] ?? ''));
        if (strpos($tipo, 'corret') !== false) {
            return true;
        }
        return strpos($prio, 'crit') !== false;
    }
}
