<?php
/**
 * LUB-TEK - Neural Engine Controller (Lúbria)
 * Motor de Inteligência Artificial para diagnóstico preditivo e prescritivo.
 */

require_once __DIR__ . '/../includes/gemini_service.php';
require_once __DIR__ . '/../includes/permissions.php';

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

        if (!$this->gemini->isConfigured()) {
            return ['error' => 'Motor Neural (Gemini) não está configurado neste tenant.'];
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

        $systemInstruction = "Você é a Lúbria, Inteligência Artificial Orquestradora e Engenheira Chefe de Confiabilidade do sistema LUB-TEK. Sua missão é auxiliar engenheiros, gestores e lubrificadores industriais com precisão técnica absoluta. Use terminologia industrial real (fator DN, ISO VG, NLGI, Polichemia, Sulfonato de Cálcio, CBM), focando em prevenção de falhas e diagnósticos práticos em formato Markdown com tópicos claros.";

        $response = $this->gemini->ask($prompt, $systemInstruction);

        if (isset($response['error']) && empty($response['text'])) {
            return ['error' => 'Falha no processamento neural: ' . $response['error']];
        }

        DB::log($this->userLabel(), 'NEURAL_DIAGNOSIS', "Ativo ID: {$assetId}");

        return [
            'asset_name' => $asset['nome'],
            'diagnosis_markdown' => $response['text'] ?? 'Sem resposta da IA.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
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

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN tipo_manutencao = 'Corretiva' OR prioridade IN ('Crítica', 'Alta') THEN 1 ELSE 0 END) as corretivas
             FROM ordens WHERE ativo_id = ? AND COALESCE(data_conclusao, data_emissao, data_planejada) >= date('now', '-90 days')"
        );
        $stmt->execute([$assetId]);
        $hist = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = intval($hist['total'] ?? 0);
        $corretivas = intval($hist['corretivas'] ?? 0);

        $riskScore = 15.0;
        if ($total > 0) {
            $riskScore += ($corretivas / max(1, $total)) * 70;
        }
        if (in_array($ativoRow['status'] ?? '', ['Alerta', 'Danger'], true)) {
            $riskScore = max($riskScore, 45);
        }
        if (($ativoRow['status'] ?? '') === 'Crítico') {
            $riskScore = max($riskScore, 75);
        }

        $riskScore = min(99.0, round($riskScore, 1));
        $health = $riskScore > 60 ? 'Crítico' : ($riskScore > 30 ? 'Atenção' : 'Excelente');

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

        $stmt = $this->db->prepare("
            INSERT INTO ordens (descricao, responsavel, data_planejada, prioridade, situacao, observacao, ativo_id, tipo_manutencao, data_emissao, last_sync)
            VALUES (?, 'Pendente Atribuição', ?, 'Alta', 'Pendente', ?, ?, 'Preditiva', ?, datetime('now'))
        ");

        $stmt->execute([$desc, $hoje, $obs, $assetId, $hoje]);
        $osId = $this->db->lastInsertId();

        DB::log($this->userLabel(), 'NEURAL_OS_APPROVED', "OS #{$osId} aprovada para Ativo ID: {$assetId}");

        return [
            'success' => true,
            'os_id' => $osId,
            'message' => 'Sugestão aceita. Ordem de Serviço Preditiva criada com sucesso.'
        ];
    }
}
