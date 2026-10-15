<?php
/**
 * Resumo operacional do relatório do agente de O.S. (local + Gemini opcional).
 */
require_once __DIR__ . '/gemini_service.php';

class WorkOrderAgentBriefing
{
    public static function localBriefing(array $report): string
    {
        $insight = trim((string) ($report['insight'] ?? ''));
        if ($insight !== '') {
            return $insight;
        }
        $scanned = (int) ($report['scanned_points'] ?? 0);
        $due = (int) ($report['due_points'] ?? 0);
        $created = (int) ($report['generated_orders_count'] ?? 0);
        return "Monitorando {$scanned} ponto(s), {$due} vencido(s), {$created} O.S. gerada(s) na última execução.";
    }

    /**
     * @return array{text:string, source:string, error?:string}
     */
    public static function generate(array $report, bool $forceGemini = false): array
    {
        $local = self::localBriefing($report);
        $gemini = GeminiService::getInstance();

        if (!$forceGemini || !$gemini->isGeminiEnabled()) {
            return [
                'text' => $local,
                'source' => $forceGemini ? 'local_fallback' : 'local',
            ];
        }

        $sample = [];
        foreach ($report['decisions'] ?? [] as $d) {
            $action = $d['action'] ?? '';
            if ($action === 'not_due') {
                continue;
            }
            $sample[] = [
                'ativo_id' => $d['ativo_id'] ?? null,
                'ponto' => $d['asset_name'] ?? '',
                'acao' => $action,
                'prioridade' => $d['priority'] ?? '',
                'score' => $d['score'] ?? 0,
                'atraso_dias' => $d['overdue_days'] ?? 0,
                'rota' => $d['rota'] ?? '',
                'motivo' => $d['reason'] ?? '',
            ];
            if (count($sample) >= 12) {
                break;
            }
        }

        $payload = json_encode([
            'resumo_local' => $local,
            'scanned_points' => $report['scanned_points'] ?? 0,
            'due_points' => $report['due_points'] ?? 0,
            'generated' => $report['generated_orders_count'] ?? 0,
            'skipped' => $report['skipped_count'] ?? 0,
            'amostra_decisoes' => $sample,
        ], JSON_UNESCAPED_UNICODE);

        require_once __DIR__ . '/prompt_registry.php';
        $system = PromptRegistry::get('os_agent_briefing');

        $prompt = "Relatório do agente de geração de ordens de serviço de rotina:\n\n{$payload}";

        $res = $gemini->ask($prompt, $system, false, 320, false, 'os_agent_briefing');
        if (!empty($res['text'])) {
            return [
                'text' => trim($res['text']),
                'source' => !empty($res['cached']) ? 'gemini_cached' : 'gemini',
            ];
        }

        return [
            'text' => $local,
            'source' => 'local_fallback',
            'error' => $res['error'] ?? 'gemini_unavailable',
        ];
    }
}
