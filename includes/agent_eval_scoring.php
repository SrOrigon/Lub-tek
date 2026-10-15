<?php
/**
 * Fórmulas heurísticas dos agentes — fonte única para runtime e eval (fixtures).
 */
class AgentEvalScoring
{
    public static function osUrgencyScore(array $tech, int $overdueDays, int $freqDays, array $osAgentWeights): int
    {
        $overdueMult = (float) ($osAgentWeights['overdue_day_score'] ?? 4.0);
        $score = 40;
        $score += (int) min(40, $overdueDays * $overdueMult);
        if ($freqDays <= 1) {
            $score += 15;
        } elseif ($freqDays <= 7) {
            $score += 8;
        }
        if (stripos((string) ($tech['prioridade'] ?? ''), 'crit') !== false) {
            $score += (int) ($osAgentWeights['critical_bonus'] ?? 25);
        }
        if (stripos((string) ($tech['condicao'] ?? ''), 'parada') !== false) {
            $score += 5;
        }
        $saude = strtolower((string) ($tech['saude_ativo'] ?? ''));
        if ($saude === 'crítica' || $saude === 'critica') {
            $score += 20;
        } elseif ($saude === 'atenção' || $saude === 'atencao') {
            $score += 10;
        }
        return min(100, max(1, $score));
    }

    public static function osPriorityLabel(array $tech, int $score, int $overdueDays): string
    {
        if (stripos((string) ($tech['prioridade'] ?? ''), 'crit') !== false || $score >= 85) {
            return 'Alta';
        }
        if ($overdueDays >= 14 || $score >= 65) {
            return 'Média';
        }
        return 'Baixa';
    }

    /**
     * @param array{total?:int,corretivas?:int,status?:string,mtbf_hours?:float|null,laudo_geral?:string|null,agua_ppm?:float|null} $signals
     */
    public static function neuralRiskPercentage(array $signals, array $neuralWeights): float
    {
        $total = (int) ($signals['total'] ?? 0);
        $corretivas = (int) ($signals['corretivas'] ?? 0);
        $status = (string) ($signals['status'] ?? '');

        $riskScore = (float) ($neuralWeights['base'] ?? 15.0);
        if ($total > 0) {
            $riskScore += ($corretivas / max(1, $total)) * (float) ($neuralWeights['corrective_ratio_multiplier'] ?? 70.0);
        }

        $mtbf = isset($signals['mtbf_hours']) ? (float) $signals['mtbf_hours'] : null;
        if ($mtbf !== null && $mtbf > 0 && $mtbf < 500) {
            $penalty = min((float) ($neuralWeights['mtbf_penalty_max'] ?? 20.0), (500 - $mtbf) / 25);
            $riskScore += $penalty;
        }

        $laudo = strtolower((string) ($signals['laudo_geral'] ?? ''));
        if ($laudo !== '' && (str_contains($laudo, 'crit') || str_contains($laudo, 'alert'))) {
            $riskScore += (float) ($neuralWeights['oil_alert_bonus'] ?? 10.0);
        }
        $agua = (float) ($signals['agua_ppm'] ?? 0);
        if ($agua > 500) {
            $riskScore += (float) ($neuralWeights['oil_alert_bonus'] ?? 10.0) * 0.5;
        }

        if (in_array($status, ['Alerta', 'Danger'], true)) {
            $riskScore = max($riskScore, 45.0);
        }
        if ($status === 'Crítico') {
            $riskScore = max($riskScore, 75.0);
        }

        return min(99.0, round($riskScore, 1));
    }

    public static function neuralHealthLabel(float $riskPercentage): string
    {
        if ($riskPercentage > 60) {
            return 'Crítico';
        }
        if ($riskPercentage > 30) {
            return 'Atenção';
        }
        return 'Excelente';
    }
}
