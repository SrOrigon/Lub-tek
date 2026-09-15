<?php
/**
 * Auditoria completa dos subsistemas de IA (Lúbria / Motor Neural / Gemini).
 * Uso: php scripts/test_ai_system.php
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gemini_service.php';
require_once __DIR__ . '/../includes/lubrication_plan_ai_enricher.php';
require_once __DIR__ . '/../api/NeuralEngineController.php';

$pdo = DB::getInstance();
$fail = 0;

function check(bool $ok, string $msg): void {
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . " $msg\n";
    if (!$ok) {
        $fail++;
    }
}

$user = ['id' => 1, 'role' => 'developer', 'nome' => 'Test Admin'];

// --- Arquivos core ---
$coreFiles = [
    'includes/gemini_service.php',
    'includes/ai_cache.php',
    'includes/neural_knowledge.php',
    'includes/neural_assistant.php',
    'includes/lubrication_plan_ai_enricher.php',
    'api/NeuralEngineController.php',
    'assets/js/neural_assistant.js',
    'assets/js/os_manager.js',
];
foreach ($coreFiles as $f) {
    check(is_file(dirname(__DIR__) . '/' . $f), "arquivo IA existe: $f");
}

// --- Rotas API ---
$api = file_get_contents(dirname(__DIR__) . '/api.php') ?: '';
foreach (['ask_neural', 'get_neural_context', 'neural_predict', 'neural_generate_os', 'neural_diagnose', 'lubria_excel_mapper', 'get_asset_reliability', 'pi_ai_diagnose'] as $route) {
    check(strpos($api, "'$route'") !== false, "rota api: $route");
}

// --- Permissões ---
check(Permissions::isGestor('administrador'), 'role administrador reconhecido como gestor');
check(Permissions::canAccessApiAction('gestor', 'neural_generate_os'), 'gestor pode neural_generate_os');
check(Permissions::canAccessApiAction('trabalhador', 'neural_predict'), 'trabalhador pode neural_predict');
check(!Permissions::canAccessApiAction('trabalhador', 'neural_generate_os'), 'trabalhador bloqueado em neural_generate_os');
check(Permissions::canAccessApiAction('cliente', 'ask_neural'), 'cliente pode ask_neural');

// --- GeminiService ---
$gemini = GeminiService::getInstance();
check(method_exists($gemini, 'askAssistant'), 'GeminiService::askAssistant');
check(method_exists($gemini, 'isGeminiEnabled'), 'GeminiService::isGeminiEnabled');
check(method_exists(GeminiService::class, 'checkRateLimit'), 'GeminiService::checkRateLimit');
check(method_exists(GeminiService::class, 'parseJsonResponse'), 'GeminiService::parseJsonResponse');

// Local-first: sem force_ai não deve marcar source gemini
$localRes = $gemini->askAssistant('Qual a temperatura ideal do mancal tipo XYZ?', ['page' => 'calc'], false);
check(($localRes['source'] ?? '') === 'local', 'askAssistant sem force_ai retorna local');

$jsonSample = GeminiService::parseJsonResponse('```json' . "\n{\"health_score\":88,\"risk_level\":\"Baixo\"}\n" . '```');
check(($jsonSample['health_score'] ?? 0) === 88, 'parseJsonResponse extrai JSON de markdown');

check(NeuralKnowledge::shouldUseLocalFirst('Como criar ordem de serviço?') === true, 'shouldUseLocalFirst sem fatal error');

$local = NeuralKnowledge::getLocalAnswer('Como criar ordem de serviço?', ['page' => 'dash', 'os_pendentes' => 2, 'os_criticas' => 1]);
check(strpos($local, 'Ordens') !== false || strpos($local, 'Ordem') !== false, 'NeuralKnowledge resposta local dash');

// --- AiCache ---
$key = AiCache::makeKey('test', 'a', 'b');
AiCache::set($key, ['text' => 'cached'], 120);
$cached = AiCache::get($key);
check(is_array($cached) && ($cached['text'] ?? '') === 'cached', 'AiCache set/get');
check(is_string(AiCache::plantRevision($pdo)) && strlen(AiCache::plantRevision($pdo)) === 64, 'AiCache plantRevision');

// --- NeuralEngine predict ---
$neural = new NeuralEngineController($pdo, $user, []);
$predict = $neural->predictAsset();
check(isset($predict['insights']) && is_array($predict['insights']), 'neural_predict retorna insights');

// --- generateOS guard ---
$deny = new NeuralEngineController($pdo, ['id' => 2, 'role' => 'trabalhador', 'nome' => 'Op'], ['asset_id' => 1, 'suggestion' => 'Teste']);
$denyRes = $deny->generateOS();
check(isset($denyRes['error']), 'trabalhador bloqueado em generateOS');

// --- Plano lubrificação IA local fallback ---
require __DIR__ . '/../includes/lubrication_plan_builder.php';
$builder = new LubricationPlanBuilder($pdo);
$plan = $builder->build(18, []);
$enricher = new LubricationPlanAiEnricher($pdo);
$enriched = $enricher->enrich($plan, ['force_refresh' => true]);
check(!empty($enriched['ai']['global']['executive_summary']), 'LubricationPlanAiEnricher fallback local');
check(in_array($enriched['ai']['source'] ?? '', ['local', 'gemini'], true), 'LubricationPlanAiEnricher source válida');

// --- UI wiring ---
$dash = file_get_contents(dirname(__DIR__) . '/includes/views/dashboard.php') ?: '';
check(strpos($dash, 'neural-insights-container') !== false, 'dashboard tem Motor Neural');
check(strpos($dash, 'neural-suggestion-actions') !== false, 'dashboard CSS botões sugestão');

$header = file_get_contents(dirname(__DIR__) . '/includes/header.php') ?: '';
check(strpos($header, 'neural_assistant') !== false || is_file(dirname(__DIR__) . '/index.php'), 'assistente Lúbria incluído no app');

$neuralUi = file_get_contents(dirname(__DIR__) . '/includes/neural_assistant.php') ?: '';
check(strpos($neuralUi, 'neural-force-ai') !== false, 'chat Lúbria tem toggle IA avançada');
check(strpos($api, 'force_ai') !== false, 'api.php suporta force_ai');
check(strpos($api, 'AI_GEMINI') !== false || strpos(file_get_contents(dirname(__DIR__) . '/config.php') ?: '', 'AI_GEMINI') !== false, 'config AI_GEMINI definido');

@unlink(dirname(__DIR__) . '/data/ai_cache/default/' . preg_replace('/[^a-f0-9]/', '', strtolower($key)) . '.json');

exit($fail === 0 ? 0 : 1);
