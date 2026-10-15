<?php
require_once __DIR__ . '/json_shield.php';
/**
 * LUB-TEK - Neural Engine Controller (Lúbria)
 * Motor de Inteligência Artificial para diagnóstico preditivo e prescritivo.
 */

require_once __DIR__ . '/../includes/gemini_service.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/agent_feedback.php';
require_once __DIR__ . '/../includes/prompt_registry.php';
require_once __DIR__ . '/../includes/asset_rag_context.php';
require_once __DIR__ . '/../includes/agent_weights.php';
require_once __DIR__ . '/../includes/reliability_metrics.php';
require_once __DIR__ . '/../includes/agent_eval_scoring.php';

class NeuralEngineController {
    private $db;
    private $user;
    private $input;
    private $gemini;

    public function __construct($db, $user, $input) {
        $this->db = $db;
        $this->user = $user;
        $this->input = $input;
        $this->gemini = GeminiService::getInstance();
    }

    private function userLabel(): string
    {
        return $this->user['name'] ?? $this->user['nome'] ?? 'SYSTEM';
    }

    /**
     * Diagnóstico de Sistema baseado em Análises de Óleo e Telemetria
     */
    public function diagnoseSystem() {
        $assetId = intval($this->input['asset_id'] ?? 0);
        if (!$assetId) {
            return ['error' => 'ID do ativo é obrigatório para o diagnóstico.'];
        }

        $stmt = $this->db->prepare("SELECT nome, tipo, fabricante, modelo, dados_tecnicos FROM ativos WHERE id = ?");
        $stmt->execute([$assetId]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$asset) {
            return ['error' => 'Ativo não encontrado.'];
        }

        $stmtAnalises = $this->db->prepare("SELECT data_coleta, iso_4406, agua_ppm, fe_ppm, visc40, laudo_geral FROM analises WHERE ativo_id = ? ORDER BY data_coleta DESC LIMIT 3");
        $stmtAnalises->execute([$assetId]);
        $analises = $stmtAnalises->fetchAll(PDO::FETCH_ASSOC);

        $stmtAlertas = $this->db->prepare("SELECT descricao, data_emissao FROM ordens WHERE ativo_id = ? AND responsavel = 'Equipe de Confiabilidade (PI)' ORDER BY data_emissao DESC LIMIT 3");
        $stmtAlertas->execute([$assetId]);
        $alertas = $stmtAlertas->fetchAll(PDO::FETCH_ASSOC);

        $prompt = "Atue como Lúbria, Engenheira de Confiabilidade Sênior. Analise os seguintes dados do ativo e forneça um diagnóstico técnico direto ao ponto.\n\n";
        $prompt .= "Ativo: {$asset['nome']} ({$asset['tipo']}) - {$asset['fabricante']} {$asset['modelo']}\n";
        $prompt .= "Últimas Análises de Óleo:\n" . json_encode($analises) . "\n\n";
        $prompt .= "Últimos Alertas de Sensores (CBM):\n" . json_encode($alertas) . "\n\n";
        $prompt .= "Com base nisso, identifique a causa raiz provável de desgaste ou anomalia e sugira 3 passos de ação imediata.";

        $ragBlock = AssetRagContext::buildForAsset($this->db, $assetId);
        if ($ragBlock !== '') {
            $prompt .= "\n\nContexto adicional do cadastro:\n" . $ragBlock;
        }

        $systemInstruction = PromptRegistry::get('neural_diagnose_system');

        $response = $this->gemini->isConfigured()
            ? $this->gemini->ask($prompt, $systemInstruction, false, 768, false, 'neural_diagnose')
            : ['error' => 'offline'];

        if (isset($response['error']) && empty($response['text'])) {
            $local = $this->buildLocalDiagnosisFallback($asset, $analises, $alertas);
            return [
                'asset_name' => $asset['nome'],
                'diagnosis_markdown' => $local,
                'timestamp' => date('Y-m-d H:i:s'),
                'source' => 'local',
            ];
        }

        DB::log($this->userLabel(), 'NEURAL_DIAGNOSIS', "Ativo ID: {$assetId}");

        return [
            'asset_name' => $asset['nome'],
            'diagnosis_markdown' => $response['text'] ?? 'Sem resposta da IA.',
            'timestamp' => date('Y-m-d H:i:s'),
            'source' => $response['source'] ?? 'gemini',
        ];
    }

    private function buildLocalDiagnosisFallback(array $asset, array $analises, array $alertas): string
    {
        $lines = [
            '**Diagnóstico local (sem Gemini)**',
            '',
            'Ativo: **' . ($asset['nome'] ?? '') . '**',
        ];
        if (!empty($analises[0])) {
            $a = $analises[0];
            $lines[] = '- Última análise: ISO ' . ($a['iso_4406'] ?? '—') . ', água ' . ($a['agua_ppm'] ?? '—') . ' ppm, Fe ' . ($a['fe_ppm'] ?? '—') . ' ppm.';
        } else {
            $lines[] = '- Sem análises de óleo recentes no cadastro.';
        }
        if (!empty($alertas)) {
            $lines[] = '- Alertas CBM recentes: ' . count($alertas) . '.';
        }
        $lines[] = '';
        $lines[] = '**Ações sugeridas:**';
        $lines[] = '1. Coletar nova amostra de óleo se a última análise tiver mais de 90 dias.';
        $lines[] = '2. Inspecionar vedações e pontos de contaminação.';
        $lines[] = '3. Abrir O.S. preventiva se houver desvio de partículas ou umidade.';
        return implode("\n", $lines);
    }

    public function saveAgentFeedback(): array
    {
        $action = strtolower(trim((string) ($this->input['action'] ?? '')));
        if (!in_array($action, ['accept', 'reject'], true)) {
            return ['success' => false, 'error' => 'Ação inválida (accept/reject).'];
        }

        $id = AgentFeedback::save($this->db, [
            'agent' => trim((string) ($this->input['agent'] ?? 'neural_engine')),
            'decision_id' => $this->input['decision_id'] ?? null,
            'asset_id' => (int) ($this->input['asset_id'] ?? 0) ?: null,
            'order_id' => (int) ($this->input['order_id'] ?? 0) ?: null,
            'action' => $action,
            'reason' => trim((string) ($this->input['reason'] ?? '')),
            'meta' => is_array($this->input['meta'] ?? null) ? $this->input['meta'] : [
                'risk' => $this->input['risk'] ?? null,
                'suggestion' => $this->input['suggestion'] ?? null,
            ],
            'created_by' => $this->userLabel(),
        ]);

        return ['success' => true, 'feedback_id' => $id];
    }

    /**
     * Predição de Falha — com asset_id: score único; sem asset_id: lista de insights p/ dashboard.
     */
    public function predictAsset() {
        $assetId = intval($this->input['asset_id'] ?? 0);

        if ($assetId > 0) {
            $score = $this->computeRiskForAsset($assetId);
            if ($score === null) {
                return ['error' => 'Ativo não encontrado.'];
            }
            return [
                'risk_percentage' => $score['risk_percentage'],
                'health_status' => $score['health_status'],
                'data_points_analyzed' => $score['data_points_analyzed'],
                'insights' => [$this->scoreToInsight($score)],
            ];
        }

        // Dashboard: scan dos ativos com maior risco / alerta
        try {
            $ativos = $this->db->query(
                "SELECT id, nome, status FROM ativos
                 WHERE COALESCE(tipo, '') NOT IN ('setor', 'equipamento', 'planta')
                 ORDER BY CASE WHEN status IN ('Alerta', 'Crítico', 'Danger') THEN 0 ELSE 1 END, id DESC
                 LIMIT 40"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $ativos = $this->db->query("SELECT id, nome, status FROM ativos ORDER BY id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
        }

        $insights = [];
        foreach ($ativos as $ativo) {
            $score = $this->computeRiskForAsset((int) $ativo['id'], $ativo);
            if ($score === null) {
                continue;
            }
            // Mostra risco médio/alto ou status de alerta; limita volume
            if ($score['risk_percentage'] >= 30 || in_array($ativo['status'] ?? '', ['Alerta', 'Crítico', 'Danger'], true)) {
                $insights[] = $this->scoreToInsight($score);
            }
            if (count($insights) >= 12) {
                break;
            }
        }

        usort($insights, function ($a, $b) {
            $order = ['alto' => 0, 'medio' => 1, 'baixo' => 2, 'error' => 3];
            return ($order[$a['risk']] ?? 9) <=> ($order[$b['risk']] ?? 9);
        });

        return [
            'insights' => $insights,
            'message' => empty($insights)
                ? 'Nenhuma ação recomendada no momento. Ativos sem histórico de corretivas relevantes.'
                : null,
        ];
    }

    private function computeRiskForAsset(int $assetId, ?array $ativoRow = null): ?array
    {
        if ($ativoRow === null) {
            $stmtA = $this->db->prepare("SELECT id, nome, status FROM ativos WHERE id = ?");
            $stmtA->execute([$assetId]);
            $ativoRow = $stmtA->fetch(PDO::FETCH_ASSOC);
            if (!$ativoRow) {
                return null;
            }
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sinceExpr = $driver === 'mysql'
            ? 'DATE_SUB(CURDATE(), INTERVAL 90 DAY)'
            : "date('now', '-90 days')";
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN tipo_manutencao = 'Corretiva' OR prioridade IN ('Crítica', 'Alta') THEN 1 ELSE 0 END) as corretivas
             FROM ordens WHERE ativo_id = ? AND COALESCE(data_conclusao, data_emissao, data_planejada) >= {$sinceExpr}"
        );
        $stmt->execute([$assetId]);
        $hist = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = intval($hist['total'] ?? 0);
        $corretivas = intval($hist['corretivas'] ?? 0);

        $w = AgentWeights::load($this->db)['neural_risk'] ?? AgentWeights::defaults()['neural_risk'];
        $signals = [
            'total' => $total,
            'corretivas' => $corretivas,
            'status' => $ativoRow['status'] ?? '',
        ];

        try {
            $rel = ReliabilityMetrics::compute($this->db, $assetId);
            $mtbf = (float) ($rel['mtbf_hours'] ?? 0);
            if ($mtbf > 0) {
                $signals['mtbf_hours'] = $mtbf;
            }
        } catch (Exception $e) {
            // métricas opcionais
        }

        try {
            $oil = $this->db->prepare(
                'SELECT laudo_geral, agua_ppm FROM analises WHERE ativo_id = ? ORDER BY data_coleta DESC LIMIT 1'
            );
            $oil->execute([$assetId]);
            $lastOil = $oil->fetch(PDO::FETCH_ASSOC);
            if ($lastOil) {
                $signals['laudo_geral'] = $lastOil['laudo_geral'] ?? null;
                $signals['agua_ppm'] = $lastOil['agua_ppm'] ?? null;
            }
        } catch (Exception $e) {
            // tabela analises opcional
        }

        $riskScore = AgentEvalScoring::neuralRiskPercentage($signals, $w);
        $health = AgentEvalScoring::neuralHealthLabel($riskScore);

        return [
            'asset_id' => $assetId,
            'nome' => $ativoRow['nome'] ?? ('Ativo #' . $assetId),
            'status' => $ativoRow['status'] ?? '',
            'risk_percentage' => $riskScore,
            'health_status' => $health,
            'data_points_analyzed' => $total,
            'corretivas' => $corretivas,
        ];
    }

    private function scoreToInsight(array $score): array
    {
        $pct = $score['risk_percentage'];
        if ($pct > 60) {
            $risk = 'alto';
            $suggestion = "Risco {$pct}% ({$score['health_status']}): priorizar inspeção preditiva e verificar lubrificação / sensores nos últimos {$score['data_points_analyzed']} eventos.";
        } elseif ($pct > 30) {
            $risk = 'medio';
            $suggestion = "Risco {$pct}% ({$score['health_status']}): agendar relubrificação e revisar histórico de corretivas.";
        } else {
            $risk = 'baixo';
            $suggestion = "Risco {$pct}% ({$score['health_status']}): manter plano preventivo atual.";
        }

        $assetId = (int) $score['asset_id'];

        return [
            'asset_id' => $assetId,
            'nome' => $score['nome'],
            'risk' => $risk,
            'suggestion' => $suggestion,
            'risk_percentage' => $pct,
            'health_status' => $score['health_status'],
            'has_pending_os' => $this->hasPendingNeuralOS($assetId),
        ];
    }

    /** Evita sugerir/criar de novo se já houver O.S. preditiva pendente da Lúbria. */
    private function hasPendingNeuralOS(int $assetId): bool
    {
        if ($assetId <= 0) {
            return false;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT id FROM ordens
                 WHERE ativo_id = ?
                   AND situacao = 'Pendente'
                   AND (descricao LIKE '[LÚBRIA IA]%' OR observacao LIKE '%Motor Neural%' OR observacao LIKE '%Lúbria%')
                 LIMIT 1"
            );
            $stmt->execute([$assetId]);
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Cria O.S. a partir de sugestão da Lúbria — somente após aceite manual do gestor.
     */
    public function generateOS() {
        // Usa Permissions::isGestor() (inclui 'supervisor', alias de gestor em todo o sistema)
        // em vez de uma lista fixa que deixava supervisores de fora indevidamente.
        if (!Permissions::isGestor($this->user['role'] ?? '')) {
            return ['error' => 'Apenas gestores podem autorizar O.S. sugeridas pela I.A.'];
        }

        $assetId = intval($this->input['asset_id'] ?? 0);
        $aiRecommendation = trim(
            (string) ($this->input['ai_recommendation'] ?? $this->input['suggestion'] ?? '')
        );

        if (!$assetId || $aiRecommendation === '') {
            return ['error' => 'Ativo e recomendação neural são necessários.'];
        }

        if ($this->hasPendingNeuralOS($assetId)) {
            return ['error' => 'Já existe uma O.S. preditiva pendente para este ativo. Aceite/conclua a existente antes de criar outra.'];
        }

        $hoje = date('Y-m-d');
        $gestor = $this->userLabel();
        $desc = "[LÚBRIA IA] " . (function_exists('mb_substr') ? mb_substr($aiRecommendation, 0, 80) : substr($aiRecommendation, 0, 80));
        $obs = "Ordem de Serviço aprovada manualmente a partir de sugestão da Lúbria.\nAprovado por: {$gestor}\n\nPrescrição sugerida:\n" . $aiRecommendation;

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ordens (descricao, responsavel, data_planejada, prioridade, situacao, observacao, ativo_id, tipo_manutencao, data_emissao, last_sync)
                VALUES (?, 'Pendente Atribuição', ?, 'Alta', 'Pendente', ?, ?, 'Preditiva', ?, {$nowExpr})
            ");
            $stmt->execute([$desc, $hoje, $obs, $assetId, $hoje]);
        } catch (Exception $e) {
            return ['error' => 'Não foi possível gravar a O.S. sugerida. Tente de novo em instantes.'];
        }
        $osId = $this->db->lastInsertId();

        DB::log($this->userLabel(), 'NEURAL_OS_APPROVED', "OS #{$osId} aprovada para Ativo ID: {$assetId}");

        AgentFeedback::save($this->db, [
            'agent' => 'neural_engine',
            'decision_id' => 'asset:' . $assetId,
            'asset_id' => $assetId,
            'order_id' => (int) $osId,
            'action' => 'accept',
            'reason' => null,
            'meta' => ['suggestion' => $aiRecommendation],
            'created_by' => $this->userLabel(),
        ]);

        return [
            'success' => true,
            'os_id' => $osId,
            'message' => 'Sugestão aceita. Ordem de Serviço Preditiva criada com sucesso.'
        ];
    }
}
