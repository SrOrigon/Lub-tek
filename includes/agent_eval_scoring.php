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

        $prio = strtolower((string) ($tech['prioridade'] ?? ''));
        $saude = strtolower((string) ($tech['saude_ativo'] ?? ''));

        $isCritical = strpos($prio, 'crit') !== false || strpos($prio, 'crít') !== false;
        $isSaudeCritical = strpos($saude, 'crit') !== false || strpos($saude, 'crít') !== false;
        $isAltaPrio = strpos($prio, 'alta') !== false;

        $overdueCap = 40;

        // Dynamically adjust overdue scale and cap based on frequency
        if ($freqDays <= 1) {
            $overdueMult = 8.0;
            $overdueCap = 60;
            $score += 15;
        } elseif ($freqDays <= 7) {
            $overdueMult = 6.0;
            $overdueCap = 50;
            $score += 8;
        } elseif ($freqDays >= 30) {
            $overdueMult = 4.0;
            if ($overdueDays >= 14) $overdueCap = 60;
        }

        if ($isCritical || $isSaudeCritical) {
            $overdueCap = 100;
        }

        $score += (int) min($overdueCap, $overdueDays * $overdueMult);

        if ($isCritical) {
            $score += (int) ($osAgentWeights['critical_bonus'] ?? 25);
        } elseif ($isAltaPrio) {
            $score += 10;
        } elseif (strpos($prio, 'méd') !== false || strpos($prio, 'med') !== false) {
             $score += 5;
        }

        if (stripos((string) ($tech['condicao'] ?? ''), 'parada') !== false) {
            $score += 5;
        }

        if ($isSaudeCritical) {
            $score += 20;
        } elseif (strpos($saude, 'atenc') !== false || strpos($saude, 'atenç') !== false) {
            $score += 10;
            if ($overdueCap < 80) $overdueCap = 80;
        }

        return min(100, max(1, $score));
    }

    public static function osPriorityLabel(array $tech, int $score, int $overdueDays): string
    {
        $prio = strtolower((string) ($tech['prioridade'] ?? ''));

        $isCritical = strpos($prio, 'crit') !== false || strpos($prio, 'crít') !== false;

        if ($isCritical) return 'Alta';

        if ($score >= 85) {
            if (stripos((string) ($tech['condicao'] ?? ''), 'parada programada') !== false) return 'Média';

            // If it reached high score just by overdue without explicit 'alta'/'critica' attributes, cap at Média
            if (strpos($prio, 'alta') === false && !$isCritical && empty($tech['saude_ativo']) && empty($tech['condicao'])) {
                return 'Média';
            }

            return 'Alta';
        }

        if ($score >= 65 || $overdueDays >= 10) {
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
            $penaltyScale = ($total > 5 && $corretivas > 0) ? 1.5 : 1.0;
            $penalty = min((float) ($neuralWeights['mtbf_penalty_max'] ?? 20.0), ((500 - $mtbf) / 25) * $penaltyScale);
            $riskScore += $penalty;
        }

        $laudo = strtolower((string) ($signals['laudo_geral'] ?? ''));
        if ($laudo !== '') {
            if (str_contains($laudo, 'crit') || str_contains($laudo, 'alert')) {
                $riskScore += (float) ($neuralWeights['oil_alert_bonus'] ?? 10.0);
            }
            if (str_contains($laudo, 'partículas')) {
                $riskScore += 10.0;
            }
        }

        $agua = (float) ($signals['agua_ppm'] ?? 0);
        if ($agua > 500) {
            $riskScore += (float) ($neuralWeights['oil_alert_bonus'] ?? 10.0) * 0.5;
            if ($agua >= 1000) $riskScore += 5.0; // severe water penalty
        }

        if (in_array($status, ['Alerta', 'Danger'], true)) {
            $riskScore = max($riskScore, 45.0);
            if ($status === 'Danger') {
                $riskScore += ($corretivas > 2) ? 15.0 : 0.0;
            }
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
