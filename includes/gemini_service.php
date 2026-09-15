<?php
/**
 * LUB-TEK Gemini AI Service + Motor Neural contextual
 */

require_once __DIR__ . '/neural_knowledge.php';
require_once __DIR__ . '/ai_cache.php';

class GeminiService
{
    private static $instance = null;
    private $apiKey;
    private $model;

    private function __construct()
    {
        if (!defined('GEMINI_API_KEY')) {
            require_once __DIR__ . '/../config.php';
        }
        $this->apiKey = GEMINI_API_KEY;
        $this->model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-2.5-flash';
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isConfigured()
    {
        return !empty($this->apiKey);
    }

    /** IA remota habilitada (chave + flag global). */
    public function isGeminiEnabled(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        if (defined('AI_GEMINI_ENABLED') && !AI_GEMINI_ENABLED) {
            return false;
        }
        return true;
    }

    /**
     * Limite por sessão para proteger quota Gemini com muitos usuários/tenants.
     * Retorna array de erro ou null se OK.
     */
    public static function checkRateLimit(): ?array
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $limit = defined('AI_GEMINI_RATE_LIMIT') ? (int) AI_GEMINI_RATE_LIMIT : 12;
        if ($limit <= 0) {
            return null;
        }

        $now = time();
        if (!isset($_SESSION['gemini_rate_limit']) || ($now - (int) ($_SESSION['gemini_rate_limit']['start'] ?? 0)) >= 60) {
            $_SESSION['gemini_rate_limit'] = ['count' => 0, 'start' => $now];
        }
        $_SESSION['gemini_rate_limit']['count']++;

        if ($_SESSION['gemini_rate_limit']['count'] > $limit) {
            return [
                'error' => 'Limite de requisições IA atingido. Aguarde 1 minuto e tente novamente.',
                'text' => null,
                'source' => 'rate_limit',
                'retry_after' => max(1, 60 - ($now - (int) $_SESSION['gemini_rate_limit']['start'])),
            ];
        }

        return null;
    }

    public function ask($prompt, $systemInstruction = null, $jsonMode = false, $maxTokens = null, $forceRefresh = false)
    {
        if ($systemInstruction === null) {
            $systemInstruction = $this->getDefaultSystemPrompt();
        }

        if (!$this->isGeminiEnabled()) {
            return [
                'error' => 'IA remota desabilitada ou nao configurada',
                'text' => null,
                'source' => 'none'
            ];
        }

        $tokens = $maxTokens ?? ($jsonMode ? 300 : 512);
        $mime = $jsonMode ? 'application/json' : 'text/plain';
        $cacheKey = AiCache::makeKey('gemini', $this->model, $mime, (string) $tokens, $prompt, $systemInstruction);

        if (!$forceRefresh) {
            $hit = AiCache::get($cacheKey);
            if (is_array($hit) && !empty($hit['text'])) {
                $hit['cached'] = true;
                $hit['source'] = $hit['source'] ?? 'gemini';
                return $hit;
            }
        }

        $rateErr = self::checkRateLimit();
        if ($rateErr !== null) {
            return $rateErr;
        }

        $result = $this->request($prompt, $systemInstruction, $mime, $tokens);
        if (!isset($result['error'])) {
            $result['source'] = 'gemini';
            $ttl = $jsonMode
                ? (defined('AI_CACHE_TTL_JSON') ? AI_CACHE_TTL_JSON : AiCache::JSON_TTL)
                : (defined('AI_CACHE_TTL') ? AI_CACHE_TTL : AiCache::DEFAULT_TTL);
            AiCache::set($cacheKey, ['text' => $result['text'], 'source' => 'gemini'], (int) $ttl);
        }
        return $result;
    }

    /**
     * Assistente completo com conhecimento do sistema e contexto do usuário.
     * @param bool $forceAi Quando true, permite chamada Gemini (sob demanda).
     */
    public function askAssistant($prompt, $context = [], $forceAi = false)
    {
        $page = $context['page'] ?? 'home';
        $guide = NeuralKnowledge::getModuleGuide($page);

        // Perguntas operacionais → sempre local (economia de tokens)
        if (NeuralKnowledge::shouldUseLocalFirst($prompt)) {
            $localText = NeuralKnowledge::getLocalAnswer($prompt, $context);
            return [
                'text' => $localText,
                'source' => 'local',
                'module' => $guide['nome'],
                'hint' => null
            ];
        }

        // Sem opt-in explícito → guia local + dica (não chama Gemini automaticamente)
        if (!$forceAi) {
            $localText = NeuralKnowledge::getLocalAnswer($prompt, $context);
            return [
                'text' => $localText,
                'source' => 'local',
                'module' => $guide['nome'],
                'hint' => $this->isGeminiEnabled()
                    ? 'Resposta local. Ative "IA avançada" para análise com Gemini.'
                    : 'IA avançada indisponível — usando guia integrado do sistema.',
            ];
        }

        $system = $this->buildAssistantSystemPrompt($context, $guide);

        // Monta histórico de conversa (máx 4 turnos para economizar tokens)
        $fullPrompt = $prompt;
        if (!empty($context['history']) && is_array($context['history'])) {
            $historyText = "";
            foreach (array_slice($context['history'], -4) as $turn) {
                $role = ($turn['role'] ?? '') === 'user' ? 'Usuário' : 'Assistente';
                $historyText .= "{$role}: " . mb_substr($turn['text'] ?? '', 0, 200) . "\n";
            }
            $fullPrompt = "Histórico recente:\n{$historyText}\nNova pergunta: {$prompt}";
        }

        if ($this->isGeminiEnabled()) {
            $res = $this->ask($fullPrompt, $system, false, 512);
            if (!isset($res['error']) && !empty($res['text'])) {
                $res['source'] = 'gemini';
                $res['module'] = $guide['nome'];
                return $res;
            }
        }

        // Fallback local inteligente — sempre funciona
        $localText = NeuralKnowledge::getLocalAnswer($prompt, $context);
        return [
            'text' => $localText,
            'source' => 'local',
            'module' => $guide['nome'],
            'hint' => $this->isGeminiEnabled() ? 'Resposta via guia integrado (economia de tokens).' : 'IA avançada indisponível — usando guia integrado do sistema.'
        ];
    }

    private function buildAssistantSystemPrompt($context, $guide)
    {
        $steps = implode("\n", array_map(fn($s) => "  - $s", array_slice($guide['como_usar'], 0, 5)));

        $ctx = [];
        $ctx[] = "MÓDULO ATUAL DO USUÁRIO: {$guide['nome']} (page=" . ($context['page'] ?? 'home') . ")";
        $ctx[] = "DESCRIÇÃO DO MÓDULO: {$guide['descricao']}";
        $ctx[] = "AÇÕES PERMITIDAS:\n{$steps}";

        if (!empty($context['selected_asset'])) {
            $ctx[] = "Ativo focado na tela: {$context['selected_asset']}";
        }
        if (isset($context['os_pendentes'])) {
            $ctx[] = "O.S. pendentes globais: {$context['os_pendentes']}, críticas: " . ($context['os_criticas'] ?? 0);
        }
        if (!empty($context['top_ativos'])) {
            $ctx[] = "Top Ativos na planta: " . implode(", ", $context['top_ativos']);
        }
        if (!empty($context['recent_os'])) {
            $ctx[] = "O.S. Recentes: " . $context['recent_os'];
        }

        $contextBlock = implode("\n", $ctx);

        return "Você é a Lúbria, Inteligência Artificial Orquestradora e Engenheira Chefe de Confiabilidade do sistema LUB-TEK. Sua missão é auxiliar engenheiros, gestores e lubrificadores industriais com precisão técnica absoluta.\n\n" .
            "VISÃO MACRO: O LUB-TEK gerencia Ativos (hierarquia técnica de equipamentos), Ordens de Serviço (preventiva/corretiva/preditiva), Estoque (Catálogo), KPIs (OEE) e Telemetria (PI System / Sensores CBM).\n" .
            "Seu conhecimento abrange todo o banco de dados (Homologações, Ativos, OS, Logs) e todo o escopo de engenharia de manutenção (TPM, RCM, Fator DN, Viscosidade, CBM) junto com a API Gemini Flash 2.5.\n\n" .
            "DIRETRIZES DE RESPOSTA E ENGENHARIA AVANÇADA:\n" .
            "1. Use terminologia industrial real (ex: fator DN, viscosidade cinemática ISO VG, índices NLGI, tipos de espessantes como Polichemia, Sulfonato de Cálcio Complexo, Poliureia, etc.).\n" .
            "2. Seja direta, prática e objetiva, focando em manutenções baseadas em condição (CBM) e planos de lubrificação preventiva.\n" .
            "3. Quando o usuário perguntar sobre um ativo ou falha, analise criticamente os riscos de atrito, temperatura e contaminação por água/particulados.\n" .
            "4. Responda sempre em português técnico do Brasil, mantendo um tom profissional, proativo e especialista.\n" .
            "5. FORMATO: Use Markdown, listas, **negrito** e seja impecável na clareza.\n\n" .
            "CONTEXTO DO SISTEMA E DO USUÁRIO NO MOMENTO:\n{$contextBlock}";
    }

    private function getDefaultSystemPrompt()
    {
        return "Você é a Lúbria, Inteligência Artificial Orquestradora e Engenheira Chefe de Confiabilidade do sistema LUB-TEK. Sua missão é auxiliar engenheiros, gestores e lubrificadores industriais com precisão técnica absoluta. Use terminologia industrial real (fator DN, ISO VG, NLGI, Polichemia, Sulfonato de Cálcio, CBM), focando em prevenção de falhas e diagnósticos práticos.";
    }

    public function askWithContext($prompt, $context = [])
    {
        return $this->askAssistant($prompt, $context);
    }

    public function getKPIInsight($data)
    {
        $prompt = "Analise estes KPIs de manutencao industrial: " . json_encode($data, JSON_UNESCAPED_UNICODE);
        $system = "Voce e o Core Neural do LUB-TEK. Retorne APENAS JSON: {\"insight\": \"texto curto\", \"recommendation\": \"acao\", \"severity\": \"optimal|warning|critical\"}";

        $result = $this->ask($prompt, $system, true, 300);

        if (isset($result['error'])) {
            return $this->getKPIFallback($data);
        }

        $parsed = json_decode($result['text'] ?? '{}', true);
        if (!is_array($parsed)) {
            $parsed = self::parseJsonResponse($result['text'] ?? null);
        }
        return is_array($parsed) && isset($parsed['insight']) ? $parsed : $this->getKPIFallback($data);
    }

    private function getKPIFallback($data)
    {
        $oee = $data['oee'] ?? $data['oee_percent'] ?? 85;
        if ($oee < 70) {
            return ['insight' => 'OEE abaixo da meta.', 'recommendation' => 'Priorizar ordens críticas.', 'severity' => 'critical'];
        }
        if ($oee < 85) {
            return ['insight' => 'Desempenho moderado.', 'recommendation' => 'Revisar aderência ao plano preventivo.', 'severity' => 'warning'];
        }
        return ['insight' => 'Sistema estável.', 'recommendation' => 'Manter monitoramento.', 'severity' => 'optimal'];
    }

    private function request($prompt, $systemInstruction, $mimeType, $maxTokens)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'generationConfig' => [
                'temperature' => 0.25,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => $maxTokens,
                'responseMimeType' => $mimeType
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $this->apiKey],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => 'Falha de conexao', 'text' => null];
        }

        if ($httpCode !== 200) {
            $body = json_decode($response, true);
            $msg = $body['error']['message'] ?? "Erro API ($httpCode)";
            return ['error' => $msg, 'text' => null];
        }

        $result = json_decode($response, true);
        $candidate = $result['candidates'][0] ?? null;

        if (!$candidate || empty($candidate['content']['parts'][0]['text'])) {
            return ['error' => 'Resposta vazia', 'text' => null];
        }

        return ['text' => trim($candidate['content']['parts'][0]['text'])];
    }

    /** Extrai JSON mesmo quando a IA envolve em ```json ... ``` ou texto extra. */
    public static function parseJsonResponse(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $clean = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }
}
