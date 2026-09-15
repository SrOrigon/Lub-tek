<?php
// Bootstrap: sessão e config ANTES de enviar headers HTTP
require_once __DIR__ . '/config.php';
ini_set('display_errors', defined('DEBUG_MODE') && DEBUG_MODE ? 1 : 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'db.php';
require_once 'data.php';
require_once 'includes/gemini_service.php';
require_once 'includes/ai_cache.php';
require_once 'includes/upload_helper.php';
require_once 'includes/event_dispatcher.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-KEY');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

// CORS: nunca usar *. Apenas origins explícitos (ALLOWED_ORIGINS) ou same-origin.
$corsOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [];
if (defined('ALLOWED_ORIGINS') && ALLOWED_ORIGINS !== '') {
    $allowedOrigins = array_filter(array_map('trim', explode(',', ALLOWED_ORIGINS)));
}
if ($corsOrigin !== '' && in_array($corsOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $corsOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}



// Basic error handling for API
register_shutdown_function(['RodrigoAPI', 'fatalHandler']);

class RodrigoAPI
{
    private $db;
    private $input;
    private $action;
    private $user; // Current user object
    private $isStatelessIoT = false;
    private $iotApiKeyProvided = false;

    public function __construct()
    {
        // 0. GLOBAL EXCEPTION HANDLER
        set_exception_handler([$this, 'handleException']);

        // 0.5. REGISTER DEFAULT EVENT LISTENERS
        EventDispatcher::addListener('telemetry.critical', function ($payload) {
            try {
                $pdo = DB::getInstance();
                $isObj = is_object($payload);
                $tag = $isObj ? ($payload->tag ?? []) : ($payload['tag'] ?? []);
                $value = $isObj ? ($payload->value ?? 0.0) : ($payload['value'] ?? 0.0);
                CbmJobQueue::enqueue($pdo, 'telemetry.critical', ['tag' => $tag, 'value' => $value]);
            } catch (Throwable $e) {
                error_log('[CBM] enqueue via event failed: ' . $e->getMessage());
            }
        });

        // 1. AUTH SYSTEM INTIALIZATION
        $auth = new AuthSystem();
        $this->user = $auth->getCurrentUser();
        $this->action = $_GET['action'] ?? '';

        // Compat Power BI Desktop: chamada sem "action" mas com format=flat/&flat
        // deve equivaler a 'powerbi_orders' — precisa ocorrer ANTES da validação
        // do X-API-KEY do gateway, senão clientes autenticados só por API-key
        // (sem sessão) recebem 401 antes mesmo de chegar ao dispatch().
        if ($this->action === '' && (isset($_GET['format']) || isset($_GET['flat']))) {
            $this->action = 'powerbi_orders';
        }

        // 1.5. GATEWAY IoT / Power BI — SOMENTE com X-API-KEY válida (sem bypass anônimo)
        // Query string aceita só para actions do gateway (compat Power BI Desktop).
        $iotActions = ['receive_pi_telemetry', 'pi_ai_diagnose', 'save_asset', 'save_order', 'update_order_status', 'save_task'];
        $powerbiActions = ['powerbi_kpis', 'powerbi_orders', 'powerbi_telemetry', 'powerbi_assets', 'powerbi_audit', 'powerbi', 'powerbi_all'];
        $gatewayActions = array_merge($iotActions, $powerbiActions);

        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['X_API_KEY'] ?? '';
        if ($apiKey === '' && !empty($_GET['api_key'])) {
            $this->error('API key via query string não é permitida. Envie o header X-API-KEY.', 401);
        }
        $this->iotApiKeyProvided = ($apiKey !== '');

        if ($this->iotApiKeyProvided && in_array($this->action, $gatewayActions, true)) {
            try {
                $masterPdo = DB::getMaster();
                $stmt = $masterPdo->prepare("SELECT tenant_slug FROM tenant_api_keys WHERE api_key = ?");
                $stmt->execute([$apiKey]);
                $tenantSlug = $stmt->fetchColumn();

                if ($tenantSlug) {
                    $this->isStatelessIoT = true;
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }

                    $actualTenant = $tenantSlug === 'admin' ? null : $tenantSlug;
                    TenantResolver::forceTenant($actualTenant);

                    // Role de privilégio mínimo (não promove a developer)
                    $gatewayRole = in_array($this->action, $powerbiActions, true) ? 'powerbi_reader' : 'iot_gateway';
                    $this->user = [
                        'id' => 0,
                        'nome' => 'IoT / PowerBI Gateway',
                        'name' => 'IoT / PowerBI Gateway',
                        'email' => 'gateway@' . ($actualTenant ? $actualTenant : 'lubtek') . '.com',
                        'role' => $gatewayRole,
                        'tenant' => $actualTenant
                    ];
                }
            } catch (Exception $e) {
                error_log('IoT/PowerBI API key validation failed: ' . $e->getMessage());
            }
        }

        // 2. SECURITY: RATE LIMITING (sessão)
        if (!$this->isStatelessIoT) {
            $this->checkRateLimitSession();
        }

        // 3. Rate limit por IP — adia conexão SQLite em login público (evita travamento no 1º acesso)
        $publicLightActions = ['login', 'logout', 'get_tenant_branding'];
        if (!in_array($this->action, $publicLightActions, true)) {
            $this->checkRateLimitIp();
        }

        // 4. SESSION CONCURRENCY HANDLING — change_password precisa gravar $_SESSION
        $sessionOpenActions = ['login', 'logout', 'ask_neural', 'get_tenant_branding', 'change_password'];
        if (!$this->isStatelessIoT && !in_array($this->action, $sessionOpenActions, true)) {
            session_write_close();
        }

        // 5. SECURITY: SESSION VALIDATION — Power BI NÃO é público
        $publicActions = ['login', 'logout', 'get_tenant_branding'];
        if (!$this->user && !in_array($this->action, $publicActions, true)) {
            if (in_array($this->action, $gatewayActions, true)) {
                if ($this->iotApiKeyProvided) {
                    $this->error('Chave X-API-KEY inválida ou empresa não encontrada.', 403);
                }
                $this->error('Acesso ao gateway requer header X-API-KEY válido.', 401);
            }
            $this->error('Sessão expirada ou não autenticada.', 401);
        }

        // 6. INPUT PARSING (early returns for login/logout)
        if ($this->action === 'login' || $this->action === 'logout') {
            $this->parseInput();
            return;
        }

        // 7. DATABASE CONNECTION
        try {
            define('API_CONTEXT', true); // Signal to DB class
            $this->db = DB::getInstance();
        }
        catch (Exception $e) {
            $this->error('Database Critical Error', 500, $e->getMessage());
        }

        // 8. INPUT PARSING FOR AUTHENTICATED ACTIONS
        $this->parseInput();
    }

    public function handleException($e)
    {
        DB::logError($e);

        // Erros de negócio estruturados (ex: OrdersController::processStockConsumption lança
        // Exception cuja mensagem é um JSON {error_code, message, details, suggestion}) eram
        // sempre mascarados como "Erro interno do servidor" (500) genérico aqui, escondendo do
        // usuário o motivo real (ex: estoque insuficiente) e quebrando o contrato esperado
        // pelo front (res.error_code / res.details / res.suggestion).
        $decoded = json_decode($e->getMessage(), true);
        if (is_array($decoded) && isset($decoded['error_code'])) {
            $this->cleanBuffer();
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'error' => $decoded['message'] ?? 'Erro de negócio.',
                'error_code' => $decoded['error_code'],
                'details' => $decoded['details'] ?? null,
                'suggestion' => $decoded['suggestion'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $this->error("Erro interno do servidor.", 500, $e->getMessage()); // Debug msg only for Dev
    }

    private function getClientIp()
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function checkRateLimitSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $now = time();
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = ['count' => 0, 'start' => $now];
        }

        if ($now - $_SESSION['rate_limit']['start'] < 60) {
            $_SESSION['rate_limit']['count']++;
        }
        else {
            $_SESSION['rate_limit'] = ['count' => 1, 'start' => $now];
        }

        if ($_SESSION['rate_limit']['count'] > 180) {
            $this->error('Muitas requisições. Aguarde um momento.', 429);
        }
    }

    private function checkRateLimitIp()
    {
        $ip = $this->getClientIp();
        $now = time();

        try {
            // Conecta ao banco master centralizado para armazenar logs de rate limits globais
            $masterPdo = DB::getMaster();
            
            // Migração defensiva: se a tabela legado existir com coluna 'ip' em vez de 'ip_key', ajusta o esquema
            $cols = $masterPdo->query("PRAGMA table_info(rate_limits)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!empty($cols) && !in_array('ip_key', $cols, true)) {
                $masterPdo->exec("DROP TABLE rate_limits");
            }

            $masterPdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                ip_key TEXT PRIMARY KEY,
                count INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL
            )");

            $isAuth = !empty($this->user);
            $userId = $this->user['id'] ?? 'guest';
            $limit = $isAuth ? 2000 : 100;
            
            // Se logado, limita por ID de usuário (evita bloquear o IP de NAT da fábrica inteira)
            // Se visitante, limita pelo IP público do roteador
            $rateKey = $isAuth ? 'user_' . $userId : 'ip_' . $ip;

            $stmt = $masterPdo->prepare("SELECT count, window_start FROM rate_limits WHERE ip_key = ?");
            $stmt->execute([$rateKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && ($now - (int) $row['window_start']) < 60) {
                $count = (int) $row['count'] + 1;
                $windowStart = (int) $row['window_start'];
            }
            else {
                $count = 1;
                $windowStart = $now;
            }

            $masterPdo->prepare("INSERT OR REPLACE INTO rate_limits (ip_key, count, window_start) VALUES (?, ?, ?)")
                ->execute([$rateKey, $count, $windowStart]);

            if ($count > $limit) {
                $this->error('Muitas requisições. Aguarde um momento.', 429);
            }

            // Limpeza probabilística no banco central
            if (mt_rand(1, 200) === 1) {
                $masterPdo->prepare("DELETE FROM rate_limits WHERE window_start < ?")
                    ->execute([$now - 120]);
            }
        }
        catch (Exception $e) {
            error_log('Rate limit IP check failed: ' . $e->getMessage());
            $this->checkRateLimitSessionFallback();
        }
    }

    /** Fallback quando master DB indisponível — fail-closed com limite menor. */
    private function checkRateLimitSessionFallback(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $now = time();
        if (!isset($_SESSION['rate_limit_fallback'])) {
            $_SESSION['rate_limit_fallback'] = ['count' => 0, 'start' => $now];
        }
        if ($now - (int) $_SESSION['rate_limit_fallback']['start'] >= 60) {
            $_SESSION['rate_limit_fallback'] = ['count' => 1, 'start' => $now];
            return;
        }
        $_SESSION['rate_limit_fallback']['count']++;
        if ($_SESSION['rate_limit_fallback']['count'] > 90) {
            $this->error('Muitas requisições. Aguarde um momento.', 429);
        }
    }

    private function parseInput()
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $this->input = array_merge($_GET, $json);
        }
        else {
            $this->input = array_merge($_GET, $_POST);
        }

        // Normalização leve — sanitização de output ocorre no frontend (htmlspecialchars/escapeHtml)
        // Prepared statements protegem contra SQL injection; strip_tags no input corrompe senhas e dados técnicos.
        // Senhas NÃO são trimadas aqui: espaços nas pontas já quebraram login de contas reais.
        $sensitiveKeys = [
            'password', 'senha', 'old_password', 'new_password',
            'current_password', 'confirm_password', 'pass',
        ];
        if ($this->action !== 'sap_import_orders' && $this->action !== 'sap_import_materials' && $this->action !== 'save_asset_batch') {
            array_walk_recursive($this->input, function (&$item, $key) use ($sensitiveKeys) {
                if (!is_string($item)) {
                    return;
                }
                if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                    return;
                }
                $item = str_replace("\r\n", "\n", trim($item));
            });
        }
    }

    public static function fatalHandler()
    {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            http_response_code(500);
            $payload = ['ok' => false, 'error' => 'Erro interno do servidor.'];
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $payload['debug'] = $error['message'];
            }
            echo json_encode($payload);
        }
    }

    public function run()
    {
        $this->dispatch();
    }

    private function dispatch()
    {
        // ROUTING TABLE: Map actions to [ControllerFile, ControllerClass, Method]
        $routes = [
            // Assets
            'get_tree' => ['api/AssetsController.php', 'AssetsController', 'getTree'],
            'save_asset' => ['api/AssetsController.php', 'AssetsController', 'saveAsset'],
            'save_asset_batch' => ['api/AssetsController.php', 'AssetsController', 'saveBatch'],
            'move_asset' => ['api/AssetsController.php', 'AssetsController', 'moveAsset'],
            'delete_asset' => ['api/AssetsController.php', 'AssetsController', 'deleteAsset'],
            'get_asset_timeline' => ['api/AssetsController.php', 'AssetsController', 'getTimeline'],

            // Inventory / Catalog
            'get_catalog' => ['api/InventoryController.php', 'InventoryController', 'getCatalog'],
            'get_cross_equivalents' => ['api/InventoryController.php', 'InventoryController', 'getCrossEquivalents'],
            'save_catalog_item' => ['api/InventoryController.php', 'InventoryController', 'saveItem'],
            'delete_catalog_item' => ['api/InventoryController.php', 'InventoryController', 'deleteItem'],

            // Orders / Tasks
            'get_tasks' => ['api/OrdersController.php', 'OrdersController', 'getTasks'],
            'save_task' => ['api/OrdersController.php', 'OrdersController', 'saveTask'],
            'save_order' => ['api/OrdersController.php', 'OrdersController', 'saveTask'], // Alias IoT
            'update_order_status' => ['api/OrdersController.php', 'OrdersController', 'saveTask'], // Alias IoT
            'delete_task' => ['api/OrdersController.php', 'OrdersController', 'deleteTask'],
            'generate_scheduled_orders' => ['api/OrdersController.php', 'OrdersController', 'generateScheduledOrders'],

            // Users
            'get_users' => ['api/UsersController.php', 'UsersController', 'getAll'],
            'save_user' => ['api/UsersController.php', 'UsersController', 'saveUser'],
            'delete_user' => ['api/UsersController.php', 'UsersController', 'deleteUser'],

            // Plans
            'get_plans' => ['api/PlansController.php', 'PlansController', 'getPlans'],
            'save_plan' => ['api/PlansController.php', 'PlansController', 'savePlan'],
            'delete_plan' => ['api/PlansController.php', 'PlansController', 'deletePlan'],

            // Marketplace
            'get_market_data' => ['api/MarketController.php', 'MarketController', 'getMarketData'],
            'save_market_offer' => ['api/MarketController.php', 'MarketController', 'saveOffer'],
            'delete_market_offer' => ['api/MarketController.php', 'MarketController', 'deleteOffer'],
            'get_market_stats' => ['api/MarketController.php', 'MarketController', 'getStats'],

            // KPI & Engineering
            'get_kpis' => ['api/KPIController.php', 'KPIController', 'getDashboardKPIs'],
            'suggest_lubrication' => ['api/EngineeringController.php', 'EngineeringController', 'handleSuggestLubrication'],
            'calc_bearing' => ['api/EngineeringController.php', 'EngineeringController', 'handleSuggestLubrication'], // Alias
            'calc_dn' => ['api/EngineeringController.php', 'EngineeringController', 'handleCalcDN'],
            'calc_viscosity' => ['api/EngineeringController.php', 'EngineeringController', 'handleCalcViscosity'],
            'calc_kappa' => ['api/EngineeringController.php', 'EngineeringController', 'handleCalcKappa'],
            'calc_bushing' => ['api/EngineeringController.php', 'EngineeringController', 'handleCalcBushing'],
            'calc_filtering' => ['api/EngineeringController.php', 'EngineeringController', 'handleCalcFiltering'],
            'search_engineering_catalog' => ['api/EngineeringController.php', 'EngineeringController', 'handleSearchCatalog'],
            'get_lubricant_consumption' => ['api/ReportsController.php', 'ReportsController', 'getLubricantConsumption'],

            // Plano de Lubrificação (PDF/HTML)
            'get_lubrication_plan_meta' => ['api/PdfController.php', 'PdfController', 'getLubricationPlanMeta'],
            'get_lubrication_plan_chunk' => ['api/PdfController.php', 'PdfController', 'getLubricationPlanChunk'],
            'render_lubrication_plan_cover' => ['api/PdfController.php', 'PdfController', 'renderLubricationPlanCover'],

            // PI System (Telemetry & CBM)
            'get_pi_tags' => ['api/PIController.php', 'PIController', 'getPITags'],
            'save_pi_tag' => ['api/PIController.php', 'PIController', 'savePITag'],
            'delete_pi_tag' => ['api/PIController.php', 'PIController', 'deletePITag'],
            'get_pi_telemetry' => ['api/PIController.php', 'PIController', 'getTelemetry'],
            'receive_pi_telemetry' => ['api/PIController.php', 'PIController', 'receiveTelemetry'],
            'pi_ai_diagnose' => ['api/PIController.php', 'PIController', 'aiDiagnose'],

            // SAP ERP Integration Actions
            'sap_import_orders' => ['api/SAPController.php', 'SAPController', 'importOrders'],
            'sap_import_materials' => ['api/SAPController.php', 'SAPController', 'importMaterials'],
            'sap_export_orders' => ['api/SAPController.php', 'SAPController', 'exportOrders'],
            'sap_export_assets' => ['api/SAPController.php', 'SAPController', 'exportAssets'],
            'sap_export_materials' => ['api/SAPController.php', 'SAPController', 'exportMaterials'],

            // Power BI Integration Actions
            'powerbi' => ['api/PowerBIController.php', 'PowerBIController', 'getOrders'],
            'powerbi_all' => ['api/PowerBIController.php', 'PowerBIController', 'getOrders'],
            'powerbi_kpis' => ['api/PowerBIController.php', 'PowerBIController', 'getKPIs'],
            'powerbi_orders' => ['api/PowerBIController.php', 'PowerBIController', 'getOrders'],
            'powerbi_telemetry' => ['api/PowerBIController.php', 'PowerBIController', 'getTelemetry'],
            'powerbi_assets' => ['api/PowerBIController.php', 'PowerBIController', 'getAssets'],
            'powerbi_audit' => ['api/PowerBIController.php', 'PowerBIController', 'getAudit'],

            // Neural Engine
            'neural_predict' => ['api/NeuralEngineController.php', 'NeuralEngineController', 'predictAsset'],
            'neural_generate_os' => ['api/NeuralEngineController.php', 'NeuralEngineController', 'generateOS'],
            'neural_diagnose' => ['api/NeuralEngineController.php', 'NeuralEngineController', 'diagnoseSystem'],

            // Internal / Legacy (Keep method binding)
            'save_branding' => [null, null, 'handle_save_branding'],
            'get_tenant_branding' => [null, null, 'handle_get_tenant_branding'],
            'get_audit_logs' => [null, null, 'handle_get_audit_logs'],
            'get_tenant_api_key' => [null, null, 'handle_get_tenant_api_key'],
            'rotate_tenant_api_key' => [null, null, 'handle_rotate_tenant_api_key'],
            'login' => [null, null, 'handle_login'],
            'logout' => [null, null, 'handle_logout'],
            'change_password' => [null, null, 'handle_change_password'],
            'get_stats' => [null, null, 'handle_get_stats'],
            'get_dash_stats' => [null, null, 'handle_get_stats'], // Alias
            'get_sync_revision' => [null, null, 'handle_get_sync_revision'],
            'get_asset_reliability' => [null, null, 'handle_get_asset_reliability'], // AI/Reliability
            'wipe_db' => [null, null, 'handle_wipe_db'],
            'save_snapshot' => [null, null, 'handle_save_snapshot'],
            'list_mockups' => [null, null, 'handle_list_mockups'], // New 3D Mockup Route
            'cleanup_sem_nome' => [null, null, 'handle_cleanup_sem_nome'],
            'save_3d_view' => [null, null, 'handle_save_3d_view'],
            'upload_model' => [null, null, 'handle_upload_model'],
            'upload_image' => [null, null, 'handle_upload_image'],
            'update_asset_image' => [null, null, 'handle_update_asset_image'],
            'set_asset_status' => [null, null, 'handle_set_asset_status'],
            'report_route_alert' => [null, null, 'handle_report_route_alert'],
            'get_thickener' => [null, null, 'handle_get_thickener'],
            'apply_machine_template' => [null, null, 'handle_apply_machine_template'],
            'migrate' => [null, null, 'handle_migrate'],
            'ask_neural' => [null, null, 'handle_ask_neural'],
            'lubria_excel_mapper' => [null, null, 'handle_lubria_excel_mapper'],
            'get_neural_context' => [null, null, 'handle_get_neural_context'],
            'save_analysis' => [null, null, 'handle_save_analysis'],
            'import_analysis_csv' => [null, null, 'handle_import_analysis_csv'],
            'get_analysis_history' => [null, null, 'handle_get_analysis_history'],
            'generate_checklist_report' => [null, null, 'handle_generate_checklist_report'],
        ];

        try {
            if (empty($this->action)) {
                if (isset($_GET['format']) || isset($_GET['flat'])) {
                    $this->action = 'powerbi_orders';
                } else {
                    $this->success([
                        'message' => 'LUB-TEK API Operational',
                        'version' => '3.2.0',
                        'auth' => 'session or X-API-KEY required',
                    ]);
                }
            }

            if (!isset($routes[$this->action])) {
                $this->error("Ação '{$this->action}' desconhecida ou não permitida.", 400);
            }

            // RBAC: bloqueia actions não permitidas para o role
            $userRole = $this->user['role'] ?? 'trabalhador';
            if ($this->action !== 'get_tenant_branding' && $this->user && !Permissions::canAccessApiAction($userRole, $this->action)) {
                $this->error('Permissão negada para esta operação.', 403);
            }

            [$file, $class, $method] = $routes[$this->action];

            // 1. Controller-based Dispatch
            if ($file && $class) {
                $this->requireController($file, $class);

                // Specific constructor signature for KPI
                if ($class === 'KPIController') {
                    $isDev = in_array($this->user['role'] ?? '', ['developer', 'admin'], true);
                    $c = new $class($this->db, $this->user['id'] ?? 1, $isDev, $this->input['asset_id'] ?? null);
                }
                // Specific constructor for Engineering
                else if ($class === 'EngineeringController') {
                    $c = new $class($this->db, $this->user['id'] ?? 0, $this->input['asset_id'] ?? null, $this->input);
                }
                // Standard Controller
                else {
                    $c = new $class($this->db, $this->user, $this->input);
                }

                if (method_exists($c, $method)) {
                    $result = $c->$method();
                    // Erros reais de controller → HTTP 4xx (exceto payloads de negócio com found/insights)
                    if (
                        is_array($result)
                        && isset($result['error'])
                        && empty($result['success'])
                        && !array_key_exists('found', $result)
                        && !array_key_exists('insights', $result)
                    ) {
                        $this->error((string) $result['error'], 400);
                    }
                    $this->success($result);
                }
                else {
                    throw new Exception("Method '$method' missing in $class");
                }
            }
            // 2. Internal Method Dispatch
            else {
                if (method_exists($this, $method)) {
                    $this->$method(); // Function handles success/error itself
                }
                else {
                    throw new Exception("Internal handler '$method' missing.");
                }
            }

        }
        catch (Throwable $e) {
            DB::logError($e);

            // Erros de negócio estruturados (ex: OrdersController::processStockConsumption
            // lança Exception cuja mensagem é um JSON {error_code, message, details, suggestion})
            // eram sempre mascarados como "Erro interno do servidor" (500) genérico aqui, já
            // que este catch cobre toda a execução dos controllers via $c->$method(). Isso
            // escondia do usuário o motivo real (ex: estoque insuficiente) e quebrava o
            // contrato esperado pelo front (res.error_code / res.details / res.suggestion).
            $decoded = json_decode($e->getMessage(), true);
            if (is_array($decoded) && isset($decoded['error_code'])) {
                $this->cleanBuffer();
                http_response_code(409);
                echo json_encode([
                    'ok' => false,
                    'error' => $decoded['message'] ?? 'Erro de negócio.',
                    'error_code' => $decoded['error_code'],
                    'details' => $decoded['details'] ?? null,
                    'suggestion' => $decoded['suggestion'] ?? null,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $this->error('Erro interno do servidor.', 500, $e->getMessage());
        }
    }

    // --- UTILITIES ---

    private function requireController(string $file, string $class): void
    {
        $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
        if (!is_file($absolutePath)) {
            $dir = dirname($absolutePath);
            $base = basename($absolutePath);
            if (is_dir($dir)) {
                foreach (scandir($dir) ?: [] as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    if (strcasecmp($entry, $base) === 0) {
                        $absolutePath = $dir . DIRECTORY_SEPARATOR . $entry;
                        break;
                    }
                }
            }
        }
        if (!is_file($absolutePath)) {
            throw new Exception("Controller file '$file' not found. Verifique o upload no Hostinger (Linux diferencia maiúsculas).");
        }

        require_once $absolutePath;
        if (!class_exists($class, false) && !class_exists($class)) {
            throw new Exception("Controller class '$class' not found in '$file'. Confira o nome da classe no arquivo enviado.");
        }
    }

    private function success($data = [], $extra = [])
    {
        $this->cleanBuffer();

        if ((isset($_GET['format']) && strtolower($_GET['format']) === 'flat') || isset($_GET['flat']) || isset($_GET['raw'])) {
            echo json_encode($data);
            $this->finishIoTAndDrainCbm();
            exit;
        }

        $isList = false;
        if (is_array($data)) {
            if (function_exists('array_is_list')) {
                $isList = array_is_list($data);
            }
            else {
                $isList = ($data === [] || array_keys($data) === range(0, count($data) - 1));
            }
        }

        $response = ['ok' => true];
        if ($isList) {
            $response['data'] = $data;
        } elseif (!empty($data)) {
            $response = array_merge($response, $data);
        }

        if (!empty($extra))
            $response = array_merge($response, $extra);

        $json = json_encode($response);
        if ($this->action === 'receive_pi_telemetry' || $this->isStatelessIoT) {
            header('Connection: close');
            header('Content-Length: ' . strlen($json));
        }
        echo $json;
        $this->finishIoTAndDrainCbm();
        exit;
    }

    /** Libera o sensor IoT antes de processar OS/e-mail da fila CBM. */
    private function finishIoTAndDrainCbm(): void
    {
        if ($this->action !== 'receive_pi_telemetry' && !$this->isStatelessIoT) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
            }
            @flush();
        }
        if ($this->action === 'receive_pi_telemetry' && $this->db instanceof PDO) {
            try {
                CbmJobQueue::processPending($this->db, 8);
            } catch (Throwable $e) {
                error_log('[CBM] drain após telemetria: ' . $e->getMessage());
            }
        }
    }

    private function error($message, $code = 400, $debug = null)
    {
        $this->cleanBuffer();
        http_response_code($code);
        $payload = ['ok' => false, 'error' => $message];
        if (defined('DEBUG_MODE') && DEBUG_MODE && $debug !== null) {
            $payload['debug'] = $debug;
        }
        echo json_encode($payload);
        exit;
    }

    private function cleanBuffer()
    {
        while (ob_get_level() > 0)
            ob_end_clean();
    }

    /**
     * Impede que um usuário de um tenant apague/afete arquivo de upload de OUTRO tenant
     * informando manualmente um "old_path"/path alheio. Admin global (sem tenant) mantém
     * acesso total (comportamento já existente para o painel developer).
     */
    private function isUploadPathOwnedByCurrentUser(string $path): bool
    {
        $tenant = $this->user['tenant'] ?? null;
        if (!$tenant) {
            return true; // Admin global — sem restrição de pasta por tenant.
        }
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        return strpos($normalized, 'uploads/' . $tenant . '/') === 0;
    }

    // --- HANDLERS ---

    /**
     * Auth Login
     */
    private function handle_login()
    {
        try {
            $username = trim($this->input['username'] ?? $_POST['username'] ?? '');
            $password = (string) ($this->input['password'] ?? $_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'success' => false, 'message' => 'Usuário e senha são obrigatórios.']);
                exit;
            }

            $auth = new AuthSystem();
            $result = $auth->login($username, $password);

            if (!$result['success']) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'success' => false, 'message' => $result['message'] ?? 'Credenciais inválidas.']);
                exit;
            }

            // Garante persistência da sessão antes de responder ao navegador
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $this->success($result);
        } catch (Throwable $e) {
            error_log('handle_login error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'success' => false,
                'message' => 'Erro interno ao autenticar. Tente novamente em instantes.',
            ]);
            exit;
        }
    }

    private function handle_logout()
    {
        $auth = new AuthSystem();
        $auth->logout();
        $this->success(['success' => true, 'message' => 'Sessao encerrada.']);
    }

    private function handle_change_password()
    {
        if (empty($this->user)) {
            $this->error('Não autenticado.', 401);
        }

        $oldPassword = $this->input['old_password'] ?? '';
        $newPassword = $this->input['new_password'] ?? '';

        if ($newPassword === '') {
            $this->error('A nova senha não pode ser vazia.');
        }

        $userId = $this->user['id'];
        $tenant = $this->user['tenant'];

        if ($tenant !== null) {
            // Tenant User
            $pdo = DB::getInstance($tenant);
            $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $currentHash = $stmt->fetchColumn();

            if (!$currentHash || !AuthSystem::verifyPassword($oldPassword, $currentHash)) {
                $this->error('Senha atual incorreta.');
            }

            // Update password and clear reset flag
            $newHash = AuthSystem::hashPassword($newPassword);
            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET senha = ?, password_reset_required = 0 WHERE id = ?");
            $stmtUpdate->execute([$newHash, $userId]);
        } else {
            // Admin JSON ou colaborador no SQLite master
            $usersFile = __DIR__ . '/data/users.json';
            $data = (file_exists($usersFile))
                ? (json_decode(file_get_contents($usersFile), true) ?: ['users' => []])
                : ['users' => []];
            $foundInJson = false;

            foreach ($data['users'] as &$u) {
                if ($u['id'] == $userId) {
                    if (!AuthSystem::verifyPassword($oldPassword, $u['password'])) {
                        $this->error('Senha atual incorreta.');
                    }
                    $u['password'] = AuthSystem::hashPassword($newPassword);
                    if (isset($u['password_reset_required'])) {
                        $u['password_reset_required'] = 0;
                    }
                    $foundInJson = true;
                    break;
                }
            }
            unset($u);

            if ($foundInJson) {
                file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                try {
                    $pdo = DB::getMaster();
                    $stmt = $pdo->prepare("SELECT id, senha FROM usuarios WHERE id = ?");
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row || empty($row['senha']) || !AuthSystem::verifyPassword($oldPassword, $row['senha'])) {
                        $this->error('Usuário não encontrado ou senha atual incorreta.');
                    }
                    $newHash = AuthSystem::hashPassword($newPassword);
                    $pdo->prepare("UPDATE usuarios SET senha = ?, password_reset_required = 0 WHERE id = ?")
                        ->execute([$newHash, $userId]);
                } catch (Exception $e) {
                    $this->error('Usuário não encontrado.');
                }
            }
        }

        // Update session flag
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['password_reset_required'] = false;

        $this->success(['success' => true, 'message' => 'Senha alterada com sucesso!']);
    }

    private function handle_save_branding()
    {
        $role = $this->user['role'] ?? '';
        if ($role !== 'developer' && $role !== 'admin' && $role !== 'gestor') {
            $this->error('Acesso negado para esta operação.', 403);
        }

        $name = trim($this->input['company_name'] ?? '');
        $logo = trim($this->input['company_logo'] ?? '');

        if ($name === '') {
            $this->error('O nome da empresa não pode ser vazio.');
        }

        DB::setSystemMeta('company_name', $name);
        if ($logo !== '') {
            DB::setSystemMeta('company_logo', $logo);
        }

        $this->success(['message' => 'Branding atualizado com sucesso!', 'company_name' => $name, 'company_logo' => $logo]);
    }

    private function handle_get_tenant_branding()
    {
        $tenant = $this->input['tenant'] ?? '';
        $slug = TenantResolver::sanitizeSlug($tenant);

        if ($slug && TenantResolver::tenantExists($slug)) {
            $companyName = DB::getSystemMeta('company_name', $slug);
            $companyLogo = DB::getSystemMeta('company_logo', $slug);

            $this->success([
                'company_name' => $companyName ? $companyName : ucfirst($slug),
                'company_logo' => $companyLogo ? $companyLogo : 'assets/img/system/img_69581d7fcdbbf.jpeg'
            ]);
        }

        $this->success([
            'company_name' => 'LUB-TEK',
            'company_logo' => 'assets/img/system/img_69581d7fcdbbf.jpeg'
        ]);
    }

    private function handle_get_audit_logs()
    {
        $role = $this->user['role'] ?? '';
        if ($role !== 'developer' && $role !== 'admin' && $role !== 'gestor') {
            $this->error('Acesso negado para esta operação.', 403);
        }

        $userFilter = trim($this->input['user'] ?? '');
        $startDate = trim($this->input['start_date'] ?? '');
        $endDate = trim($this->input['end_date'] ?? '');

        $sql = "SELECT id, usuario, acao, alvo, dados_antigos, dados_novos, detalhes_erro, data 
                FROM logs_auditoria WHERE 1=1";
        $params = [];

        if ($userFilter !== '') {
            $sql .= " AND LOWER(usuario) LIKE LOWER(?)";
            $params[] = '%' . $userFilter . '%';
        }
        if ($startDate !== '') {
            $sql .= " AND data >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate !== '') {
            $sql .= " AND data <= ?";
            $params[] = $endDate . ' 23:59:59';
        }

        $sql .= " ORDER BY data DESC LIMIT 500";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($logs) && $userFilter === '' && $startDate === '' && $endDate === '' && TenantResolver::getCurrentTenant() === null) {
                if (class_exists('Seeder')) {
                    Seeder::seedAuditLogs($this->db);
                } else {
                    $seederPath = __DIR__ . '/seed.php';
                    if (file_exists($seederPath)) {
                        require_once $seederPath;
                        Seeder::seedAuditLogs($this->db);
                    }
                }
                $stmt->execute($params);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            foreach ($logs as &$log) {
                $log['usuario'] = htmlspecialchars($log['usuario'] ?? '');
                $log['acao'] = htmlspecialchars($log['acao'] ?? '');
                $log['alvo'] = htmlspecialchars($log['alvo'] ?? '');
            }

            $this->success($logs);
        } catch (Exception $e) {
            error_log('handle_get_audit_logs failed: ' . $e->getMessage());
            $this->error('Erro ao carregar logs.', 500, $e->getMessage());
        }
    }

    private function handle_get_tenant_api_key()
    {
        $role = $this->user['role'] ?? '';
        if ($role !== 'developer' && $role !== 'admin' && $role !== 'gestor') {
            $this->error('Acesso negado para esta operação.', 403);
        }

        $tenant = TenantResolver::getCurrentTenant();
        $slug = $tenant ? $tenant : 'admin';

        try {
            $masterPdo = DB::getMaster();
            $stmt = $masterPdo->prepare("SELECT api_key FROM tenant_api_keys WHERE tenant_slug = ?");
            $stmt->execute([$slug]);
            $key = $stmt->fetchColumn();

            if (!$key) {
                // Gera a primeira chave de API se ainda não existir
                $key = 'lt_key_' . bin2hex(random_bytes(16));
                $ins = $masterPdo->prepare("INSERT INTO tenant_api_keys (api_key, tenant_slug) VALUES (?, ?)");
                $ins->execute([$key, $slug]);
            }

            $this->success(['api_key' => $key]);
        } catch (Exception $e) {
            error_log('handle_get_tenant_api_key failed: ' . $e->getMessage());
            $this->error('Erro ao recuperar chave de API.', 500, $e->getMessage());
        }
    }

    private function handle_rotate_tenant_api_key()
    {
        $role = $this->user['role'] ?? '';
        if ($role !== 'developer' && $role !== 'admin' && $role !== 'gestor') {
            $this->error('Acesso negado para esta operação.', 403);
        }

        $tenant = TenantResolver::getCurrentTenant();
        $slug = $tenant ? $tenant : 'admin';
        $newKey = 'lt_key_' . bin2hex(random_bytes(16));

        try {
            $masterPdo = DB::getMaster();
            $stmt = $masterPdo->prepare("INSERT OR REPLACE INTO tenant_api_keys (api_key, tenant_slug) VALUES (?, ?)");
            $stmt->execute([$newKey, $slug]);
            
            DB::log($this->user['name'] ?? $this->user['nome'] ?? 'SYSTEM', 'ROTATE_API_KEY', 'tenant_api_keys:' . $slug);
            
            $this->success(['api_key' => $newKey]);
        } catch (Exception $e) {
            error_log('handle_rotate_tenant_api_key failed: ' . $e->getMessage());
            $this->error('Erro ao rotacionar chave de API.', 500, $e->getMessage());
        }
    }

    private function handle_get_dash_stats()
    {
        $this->handle_get_stats();
    }

    /**
     * Dashboard Statistics
     */
    private function handle_get_stats()
    {
        // One-shot aggregation for common stats to reduce database roundtrips
        $uid = $this->user ? ($this->user['id'] ?? null) : null;
        $isDev = ($this->user['role'] ?? '') === 'developer';

        // Base counts
        $items = $this->db->query("SELECT COUNT(*) FROM catalogo")->fetchColumn();
        $doneCount = $this->db->query("SELECT COUNT(*) FROM ordens WHERE situacao='Concluído'")->fetchColumn();

        // Fetch global stats (unrestricted view for single-enterprise factory model)
        $assets = $this->db->query("SELECT COUNT(*) FROM ativos WHERE (nome IS NOT NULL AND nome != '' AND nome != 'Sem Nome')")->fetchColumn();
        $pending = $this->db->query("SELECT COUNT(*) FROM ordens WHERE situacao='Pendente'")->fetchColumn();
        $alerts = $this->db->query("SELECT COUNT(*) FROM ordens WHERE situacao='Pendente' AND prioridade='Crítica'")->fetchColumn();
        $recent = $this->db->query("SELECT * FROM ordens ORDER BY data_planejada DESC LIMIT 5")->fetchAll();

        $this->success([
            'assets' => (int)$assets,
            'total_ativos' => (int)$assets,
            'items' => (int)$items,
            'pending' => (int)$pending,
            'done' => (int)$doneCount,
            'alerts' => (int)$alerts,
            'recent' => $recent
        ]);
    }

    /**
     * Revisão barata do estado da planta — o front só recarrega listas quando muda.
     */
    private function handle_get_sync_revision()
    {
        $this->success([
            'revision' => AiCache::plantRevision($this->db),
            'pi_revision' => AiCache::piRevision($this->db),
        ]);
    }

    /**
     * Asset Reliability (AI-Powered)
     */
    private function handle_get_asset_reliability()
    {
        $id = $this->input['id'] ?? $this->input['asset_id'] ?? null;
        if (!$id) $this->error("ID do ativo obrigatório.");

        // 1. Get Asset Context
        $asset = $this->db->prepare("SELECT * FROM ativos WHERE id = ?");
        $asset->execute([$id]);
        $assetData = $asset->fetch(PDO::FETCH_ASSOC);

        if (!$assetData) $this->error("Ativo não encontrado.");

        $useAi = !empty($this->input['use_ai']) || !empty($this->input['ai_requested']);
        $gemini = GeminiService::getInstance();

        $healthScore = 0;
        $riskLevel = 'Indefinido';
        $summary = 'Sem histórico suficiente para avaliar este ativo.';
        $nextAction = 'Cadastre inspeções ou conclua rotas de lubrificação para gerar indicadores.';
        $partialData = false;

        $orders = $this->db->prepare("SELECT descricao as desc, situacao, data_execucao, prioridade, obs_exec FROM ordens WHERE ativo_id = ? ORDER BY data_planejada DESC LIMIT 5");
        $orders->execute([$id]);
        $history = $orders->fetchAll(PDO::FETCH_ASSOC);

        if ($useAi && $gemini->isConfigured()) {
            $prompt = "Analise o Ativo: {$assetData['nome']} ({$assetData['tipo']}). \n" .
                      "Dados Técnicos: {$assetData['dados_tecnicos']}\n" .
                      "Histórico recente: " . json_encode($history) . "\n" .
                      "Forneça um score de saúde (0-100), nível de risco atual e uma ação recomendada.";

            $system = "Você é o Core Neural do LUB-TEK. Analise a confiabilidade deste ativo específico. " .
                      "Retorne APENAS um JSON: {\"health_score\": 85, \"risk_level\": \"Baixo|Médio|Alto\", \"summary\": \"texto curto\", \"next_action\": \"texto curto\"}";

            $res = $gemini->ask($prompt, $system, true);
            $json = GeminiService::parseJsonResponse($res['text'] ?? null);
            if ($json && isset($json['health_score'])) {
                $healthScore = intval($json['health_score']);
                $riskLevel = $json['risk_level'] ?? 'Baixo';
                $summary = $json['summary'] ?? $summary;
                $nextAction = $json['next_action'] ?? $nextAction;
            } else {
                $useAi = false;
            }
        }

        if (!$useAi || $healthScore <= 0) {
            $st = trim((string) ($assetData['status'] ?? ''));
            $score = intval($assetData['health_score'] ?? 0);
            $critCount = 0;
            $partialData = false;
            try {
                $stmtCrit = $this->db->prepare("SELECT COUNT(*) FROM ordens WHERE ativo_id = ? AND situacao IN ('Pendente','Em Andamento') AND prioridade IN ('Crítica','Alta')");
                $stmtCrit->execute([$id]);
                $critCount = (int) $stmtCrit->fetchColumn();
            } catch (Exception $e) {
                error_log('handle_get_asset_reliability critCount: ' . $e->getMessage());
                $partialData = true;
            }

            $hasSignal = $score > 0 || $st !== '' || $critCount > 0 || !empty($history);

            if ($hasSignal) {
                if ($score > 0) {
                    $healthScore = $score;
                } elseif ($st === 'Crítico') {
                    $healthScore = 42;
                } elseif ($st === 'Alerta' || $st === 'Danger') {
                    $healthScore = 65;
                } elseif ($st === 'Atenção') {
                    $healthScore = 78;
                } elseif ($st === 'OK' || $st === 'Concluido' || $st === 'Concluído') {
                    $healthScore = 90;
                } else {
                    $healthScore = 70;
                }

                if ($critCount > 0) {
                    $healthScore = max(25, $healthScore - ($critCount * 15));
                }

                if ($healthScore < 50) {
                    $riskLevel = 'Alto';
                    $summary = 'Ativo com pendências de alta prioridade.';
                    $nextAction = 'Priorize inspeção e conclusão das O.S. críticas.';
                } elseif ($healthScore < 75) {
                    $riskLevel = 'Médio';
                    $summary = 'Há sinais de atenção no histórico recente.';
                    $nextAction = 'Revise o plano de lubrificação e as pendências.';
                } else {
                    $riskLevel = 'Baixo';
                    $summary = 'Indicadores disponíveis sem alertas críticos.';
                    $nextAction = 'Mantenha o plano preventivo atual.';
                }
            }
        }

        // Map to what the frontend expects
        $reliability = $healthScore;
        if ($healthScore <= 0) {
            $status = 'Sem dados';
            $color = '#94a3b8';
            $nextFailureDays = null;
            $co2Impact = null;
        } elseif ($healthScore < 50) {
            $status = 'Crítico';
            $color = '#ef4444';
            $nextFailureDays = round($healthScore * 1.2);
            $co2Impact = round((100 - $healthScore) * 0.45, 1);
        } elseif ($healthScore < 75) {
            $status = 'Atenção';
            $color = '#f59e0b';
            $nextFailureDays = round($healthScore * 1.2);
            $co2Impact = round((100 - $healthScore) * 0.45, 1);
        } elseif ($healthScore < 90) {
            $status = 'Bom';
            $color = '#3b82f6';
            $nextFailureDays = round($healthScore * 1.2);
            $co2Impact = round((100 - $healthScore) * 0.45, 1);
        } else {
            $status = 'Excelente';
            $color = '#10b981';
            $nextFailureDays = round($healthScore * 1.2);
            $co2Impact = round((100 - $healthScore) * 0.45, 1);
        }

        $enrichedResponse = [
            'reliability' => $reliability,
            'status' => $status,
            'color' => $color,
            'next_failure_days' => $nextFailureDays,
            'co2_impact' => $co2Impact,
            'timestamp' => round(microtime(true) * 1000),
            'breakdown' => [
                'status_score' => $healthScore,
                'maintenance_score' => $healthScore > 0 ? round($healthScore * 0.98) : 0,
                'oil_score' => $healthScore > 0 ? min(100, round($healthScore * 1.02)) : 0,
            ],
            'health_score' => $healthScore,
            'risk_level' => $riskLevel,
            'summary' => $summary,
            'next_action' => $nextAction,
            'partial_data' => $partialData,
        ];

        $this->success($enrichedResponse);
    }

    /**
     * Asset Tree Structure (Calculated & Recursive)
     */


    private function handle_cleanup_sem_nome()
    {
        // One-time utility to clean bad imports
        if ($this->user['role'] !== 'developer' && $this->user['role'] !== 'admin') {
            $this->error("Permissão negada.");
        }

        $count = (int) $this->db->query("SELECT COUNT(*) FROM ativos WHERE nome = 'Sem Nome' OR nome IS NULL OR nome = ''")->fetchColumn();
        if ($count > 0) {
            $this->db->exec("DELETE FROM ativos WHERE nome = 'Sem Nome' OR nome IS NULL OR nome = ''");
        }

        require_once __DIR__ . '/includes/sap_test_plant_cleanup.php';
        $sap = SapTestPlantCleanup::purge($this->db);

        $this->success(['deleted' => $count + (int) $sap['deleted'], 'sap_test' => $sap['deleted']]);
    }



    /**
     * 3D Image Linking
     */
    private function handle_save_3d_view()
    {
        if ($this->user['role'] !== 'developer' && $this->user['role'] !== 'admin') {
            $this->error("Permissão negada.", 403);
        }

        $id = (int) ($this->input['id'] ?? 0);
        $newPath = $this->input['path'] ?? '';

        $stmt = $this->db->prepare("SELECT imagem_3d FROM ativos WHERE id = ?");
        $stmt->execute([$id]);
        $oldPath = $stmt->fetchColumn();

        $this->db->prepare("UPDATE ativos SET imagem_3d = ? WHERE id = ?")->execute([$newPath, $id]);

        if ($oldPath && $oldPath !== $newPath && $this->isUploadPathOwnedByCurrentUser($oldPath)) {
            UploadHelper::deleteIfUploaded($oldPath);
        }

        $this->success();
    }

    private function handle_update_asset_image()
    {
        $id = (int) ($this->input['id'] ?? 0);
        $imagem = trim((string) ($this->input['imagem'] ?? ''));
        if ($id <= 0) {
            $this->error('ID do ativo é obrigatório.', 400);
        }
        if ($imagem === '') {
            $this->error('Caminho da imagem é obrigatório.', 400);
        }
        $this->db->prepare('UPDATE ativos SET imagem = ? WHERE id = ?')->execute([$imagem, $id]);
        $this->success();
    }

    /**
     * Retorna contexto e sugestões do Motor Neural para o módulo atual.
     */
    private function handle_get_neural_context()
    {
        require_once __DIR__ . '/includes/neural_knowledge.php';
        $page = $this->input['page'] ?? 'home';
        $guide = NeuralKnowledge::getModuleGuide($page);

        $gemini = GeminiService::getInstance();
        $this->success([
            'page' => $page,
            'module_name' => $guide['nome'],
            'description' => $guide['descricao'],
            'prompts' => NeuralKnowledge::getPagePrompts($page),
            'ia_configured' => $gemini->isConfigured(),
            'ia_gemini_enabled' => $gemini->isGeminiEnabled(),
            'ai_strategy' => 'local-first',
        ]);
    }

    /**
     * Motor Neural — Assistente contextual do sistema
     */
    private function handle_ask_neural()
    {
        require_once __DIR__ . '/includes/neural_knowledge.php';

        $prompt = trim($this->input['prompt'] ?? '');
        if (empty($prompt)) {
            $this->error("Pergunta vazia.");
        }

        $page = $this->input['page'] ?? 'home';
        $history = $this->input['history'] ?? [];
        if (is_string($history)) {
            $history = json_decode($history, true) ?: [];
        }

        $context = [
            'page' => $page,
            'user_role' => $this->user['role'] ?? 'client',
            'user_name' => $this->user['name'] ?? $this->user['username'] ?? 'Usuário',
            'selected_asset' => $this->input['selected_asset'] ?? ($this->input['asset_name'] ?? null),
            'history' => is_array($history) ? $history : []
        ];

        try {
            $context['total_ativos'] = (int) $this->db->query("SELECT COUNT(*) FROM ativos WHERE nome IS NOT NULL AND nome != ''")->fetchColumn();
            $context['os_pendentes'] = (int) $this->db->query("SELECT COUNT(*) FROM ordens WHERE situacao != 'Concluído'")->fetchColumn();
            $context['os_criticas'] = (int) $this->db->query("SELECT COUNT(*) FROM ordens WHERE situacao != 'Concluído' AND prioridade = 'Crítica'")->fetchColumn();

            $topAtivos = $this->db->query("SELECT nome FROM ativos WHERE tipo IN ('equipamento','componente') ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
            $context['top_ativos'] = $topAtivos;

            $recentOs = $this->db->query("SELECT id, descricao, situacao FROM ordens ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
            if ($recentOs) {
                $parts = [];
                foreach ($recentOs as $o) {
                    $parts[] = "#{$o['id']} " . mb_substr($o['descricao'] ?? '', 0, 40) . " ({$o['situacao']})";
                }
                $context['recent_os'] = implode('; ', $parts);
            }
        } catch (Exception $e) {
            // Continua com contexto mínimo
        }

        $gemini = GeminiService::getInstance();
        $forceRefresh = !empty($this->input['force_refresh']);
        $forceAi = !empty($this->input['force_ai']);
        $plantRev = AiCache::plantRevision($this->db);
        $histSig = md5(json_encode(array_slice(is_array($history) ? $history : [], -4)));
        $cacheKey = AiCache::makeKey(
            'ask_neural',
            $page,
            mb_strtolower($prompt, 'UTF-8'),
            $histSig,
            $plantRev,
            (string) ($context['selected_asset'] ?? ''),
            $forceAi ? 'gemini' : 'local'
        );

        if (!$forceRefresh) {
            $cached = AiCache::get($cacheKey);
            if (is_array($cached) && !empty($cached['text'])) {
                $this->success(array_merge($cached, ['cached' => true]));
                return;
            }
        }

        $res = $gemini->askAssistant($prompt, $context, $forceAi);
        if (empty($res['text'])) {
            $res['text'] = NeuralKnowledge::getLocalAnswer($prompt, $context);
            $res['source'] = 'local';
        }

        $store = [
            'text' => $res['text'],
            'source' => $res['source'] ?? 'local',
            'module' => $res['module'] ?? null,
            'hint' => $res['hint'] ?? null
        ];
        $ttl = ($store['source'] === 'gemini')
            ? (defined('AI_CACHE_TTL_ASSISTANT') ? AI_CACHE_TTL_ASSISTANT : AiCache::ASSISTANT_TTL)
            : 86400;
        AiCache::set($cacheKey, $store, (int) $ttl);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->success($res);
    }

    /**
     * Lúbria Excel Cognitive Mapper
     * Receives an Excel sample and asks Lúbria to map columns to the asset hierarchy.
     */
    private function handle_lubria_excel_mapper()
    {
        $sample = $this->input['sample'] ?? [];
        if (empty($sample)) {
            $this->error("Nenhuma amostra de Excel recebida para análise.");
        }

        if (empty($this->input['force_ai'])) {
            $this->error('Mapeamento cognitivo requer confirmação (force_ai=1). O import funciona sem IA.', 400);
        }

        require_once __DIR__ . '/includes/gemini_service.php';
        $gemini = GeminiService::getInstance();
        if (!$gemini->isGeminiEnabled()) {
            $this->error("IA avançada (Gemini) não está disponível neste ambiente.");
        }

        $prompt = "Você é a Lúbria, Inteligência Orquestradora do LUB-TEK.
O usuário quer importar uma planilha de Excel com seus Ativos Industriais. 
Abaixo está uma amostra JSON contendo o cabeçalho e as primeiras linhas dessa planilha.
Sua missão é analisar semanticamente os nomes das colunas e os dados da amostra e gerar um mapa apontando EXATAMENTE qual coluna do Excel corresponde a qual nível da hierarquia de Ativos no LUB-TEK.

**Campos Estruturais (Retorne o NOME DA COLUNA correspondente, ou null se não existir):**
- 'fabrica_col': A coluna que representa a Fábrica/Unidade/Empresa mãe.
- 'setor_col': A coluna que representa a Área/Setor dentro da fábrica.
- 'equip_col': A coluna do Equipamento/Máquina.
- 'comp_col': A coluna do Componente (opcional).
- 'ponto_col': A coluna do Ponto de Lubrificação (opcional).

**Metadados (Retorne o NOME DA COLUNA correspondente, ou null):**
- 'tag_col': Coluna que contém a TAG única do ativo/ponto.
- 'obs_col': Coluna de observações ou descrição livre.
- 'tech_cols': Array simples com os NOMES das colunas restantes que contêm dados técnicos (ex: 'Viscosidade', 'Frequência').

Amostra da Planilha (JSON):
" . json_encode($sample, JSON_UNESCAPED_UNICODE) . "

**RETORNE EXATAMENTE E APENAS UM JSON VÁLIDO. EXEMPLO:**
{
  \"fabrica_col\": \"Empresa\",
  \"setor_col\": \"Local\",
  \"equip_col\": \"Ativo Pai\",
  \"comp_col\": null,
  \"ponto_col\": \"Ponto Lubrificante\",
  \"tag_col\": \"Código do Ativo\",
  \"obs_col\": \"Notas\",
  \"tech_cols\": [\"Graxa Utilizada\", \"Intervalo\"]
}";

        $system = "Você é Lúbria, IA orquestradora. Retorne APENAS JSON estruturado.";
        $res = $gemini->ask($prompt, $system, true, 800);

        if (isset($res['error'])) {
            error_log('handle_lubria_excel_mapper Gemini error: ' . $res['error']);
            $this->error('Falha na análise da Lúbria. Tente novamente em instantes.', 502, $res['error']);
        }

        $map = GeminiService::parseJsonResponse($res['text'] ?? null);
        if (!$map) {
            $this->error("Lúbria retornou um formato inválido.");
        }

        $this->success(['map' => $map]);
    }

    /**
     * Handle 3D Model Upload
     */
    private function handle_upload_model()
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->error('Upload falhou ou nenhum arquivo enviado.');
        }

        $assetId = intval($_POST['asset_id'] ?? 0);
        if (!$assetId) {
            $this->error('ID do ativo não fornecido.');
        }

        $f = $_FILES['file'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['glb', 'gltf', 'obj', 'stl'];

        if (!in_array($ext, $allowed))
            $this->error('Formato inválido. Permitido: .glb, .gltf, .obj, .stl');

        // Security check: Magic Bytes / Content Validation for 3D files
        $tmpPath = $f['tmp_name'];
        $fp = fopen($tmpPath, 'rb');
        // 84 bytes: 80 do header STL binário + 4 do contador de triângulos (offset 80-83)
        $bytes = fread($fp, 84);
        fclose($fp);

        if ($ext === 'glb') {
            // GLB file must start with "glTF"
            if (substr($bytes, 0, 4) !== 'glTF') {
                $this->error('Arquivo GLB inválido ou corrompido (Assinatura glTF ausente).');
            }
        } elseif ($ext === 'gltf') {
            // glTF is a JSON file
            $json = json_decode(file_get_contents($tmpPath), true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($json['asset'])) {
                $this->error('Arquivo GLTF inválido. Deve ser um JSON estruturado com o nó "asset".');
            }
        } elseif ($ext === 'obj') {
            // OBJ is plain text, check for executable tags and basic OBJ structure
            $content = file_get_contents($tmpPath, false, null, 0, 10240); // check first 10KB
            if (strpos($content, '<?php') !== false || strpos($content, '<script') !== false) {
                $this->error('Arquivo OBJ inválido (Contém código executável suspeito).');
            }
            if (!preg_match('/^\s*(?:#|v\s+|f\s+|g\s+|usemtl\s+|mtllib\s+)/mi', $content)) {
                $this->error('Arquivo OBJ inválido (Estrutura Wavefront OBJ não reconhecida).');
            }
        } elseif ($ext === 'stl') {
            // STL: can be ASCII or Binary
            if (substr($bytes, 0, 6) === 'solid ') {
                // ASCII STL
                $content = file_get_contents($tmpPath, false, null, 0, 10240);
                if (strpos($content, '<?php') !== false || strpos($content, '<script') !== false) {
                    $this->error('Arquivo STL inválido (Contém código executável suspeito).');
                }
                if (stripos($content, 'endsolid') === false) {
                    $this->error('Arquivo STL ASCII inválido (Estrutura solid/endsolid incompleta).');
                }
            } else {
                // Binary STL
                $fileSize = filesize($tmpPath);
                if ($fileSize < 84) {
                    $this->error('Arquivo STL binário muito curto.');
                }
                $triCountBytes = substr($bytes, 80, 4);
                $unpacked = strlen($triCountBytes) === 4 ? unpack('V', $triCountBytes) : false;
                if ($unpacked === false) {
                    $this->error('Arquivo STL binário corrompido ou com tamanho inconsistente.');
                }
                $triangles = $unpacked[1];
                $expectedSize = 84 + ($triangles * 50);
                if (abs($fileSize - $expectedSize) > 1024) {
                    $this->error('Arquivo STL binário corrompido ou com tamanho inconsistente.');
                }
            }
        }

        if ($f['size'] > 50 * 1024 * 1024)
            $this->error('Arquivo muito grande (Max 50MB).');

        $tenant = $this->user['tenant'] ?? null;
        $dir = __DIR__ . '/uploads';
        if ($tenant) {
            $dir .= '/' . $tenant;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Secure Filename
        $name = 'model_' . $assetId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $dir . '/' . $name;
        $publicPath = 'uploads/' . ($tenant ? $tenant . '/' : '') . $name;

        if (move_uploaded_file($f['tmp_name'], $target)) {
            // Update Asset Logic
            // We need to update the 'dados_tecnicos' JSON to include "model_3d": "path"

            $stmt = $this->db->prepare("SELECT dados_tecnicos FROM ativos WHERE id = ?");
            $stmt->execute([$assetId]);
            $currentData = $stmt->fetchColumn();

            $json = json_decode($currentData ?: '{}', true);
            if (!is_array($json))
                $json = [];

            $json['model_3d'] = $publicPath; // Save the path

            $newJson = json_encode($json);

            $update = $this->db->prepare("UPDATE ativos SET dados_tecnicos = ? WHERE id = ?");
            $update->execute([$newJson, $assetId]);

            $this->success(['path' => $publicPath, 'msg' => 'Modelo 3D vinculado com sucesso!']);
        }
        $this->error('Falha ao mover arquivo.');
    }

    /**
     * File Upload with Security Checks
     */
    private function handle_upload_image()
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->error('Upload falhou ou nenhum arquivo enviado.');
        }

        $f = $_FILES['file'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        // ... (original function content) ...

        if (!in_array($ext, $allowed))
            $this->error('Formato inválido. Apenas imagens permitidas.');
        if ($f['size'] > 10 * 1024 * 1024)
            $this->error('Arquivo muito grande (Max 10MB).');

        // SECURITY: Real MIME Check
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($f['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mime, $allowedMimes)) {
            $this->error("Arquivo inválido (MIME suspeito: $mime).");
        }

        $tenant = $this->user['tenant'] ?? null;
        $dir = __DIR__ . '/uploads';
        if ($tenant) {
            $dir .= '/' . $tenant;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // FIX 3: Secure Filename & WebP Compression On-The-Fly
        // Ignora a extensão original e força para .webp
        $name = bin2hex(random_bytes(16)) . '.webp';
        $target = $dir . '/' . $name;
        $publicPath = 'uploads/' . ($tenant ? $tenant . '/' : '') . $name;

        // Tenta comprimir e converter a imagem
        $imageProcessed = false;
        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $img = null;
            if ($mime === 'image/jpeg') {
                $img = @imagecreatefromjpeg($f['tmp_name']);
            } elseif ($mime === 'image/png') {
                $img = @imagecreatefrompng($f['tmp_name']);
                if ($img) {
                    imagepalettetotruecolor($img);
                    imagealphablending($img, true);
                    imagesavealpha($img, true);
                }
            } elseif ($mime === 'image/webp') {
                $img = @imagecreatefromwebp($f['tmp_name']);
            }

            if ($img !== false && $img !== null) {
                $wantMark = !empty($this->input['watermark']) || !empty($_POST['watermark'])
                    || intval($this->input['os_id'] ?? $_POST['os_id'] ?? 0) > 0;
                if ($wantMark) {
                    require_once __DIR__ . '/includes/image_watermark.php';
                    $tagMark = (string) ($this->input['watermark_tag'] ?? $_POST['watermark_tag'] ?? '');
                    $userMark = (string) ($this->input['watermark_user'] ?? $_POST['watermark_user'] ?? ($this->user['nome'] ?? $this->user['name'] ?? ''));
                    ImageWatermark::apply($img, ['tag' => $tagMark, 'user' => $userMark]);
                }
                $imageProcessed = imagewebp($img, $target, 75);
                imagedestroy($img);
            }
        }

        // Fallback: se a compressão falhar (GD desativado ou erro), move o original
        if (!$imageProcessed) {
            $name = bin2hex(random_bytes(16)) . '.' . $ext;
            $target = $dir . '/' . $name;
            $publicPath = 'uploads/' . ($tenant ? $tenant . '/' : '') . $name;
            if (!move_uploaded_file($f['tmp_name'], $target)) {
                $this->error('Falha ao salvar arquivo no servidor.', 500);
            }
        }

        $oldPath = $this->input['old_path'] ?? $_POST['old_path'] ?? null;
        if ($oldPath && $this->isUploadPathOwnedByCurrentUser($oldPath)) {
            UploadHelper::deleteIfUploaded($oldPath);
        }

        // Foto opcional do alerta de rota: vincula path à O.S. criada
        $osId = intval($this->input['os_id'] ?? $_POST['os_id'] ?? 0);
        if ($osId > 0) {
            try {
                $stmt = $this->db->prepare("SELECT obs_exec FROM ordens WHERE id = ?");
                $stmt->execute([$osId]);
                $obs = (string) ($stmt->fetchColumn() ?: '');
                if (strpos($obs, $publicPath) === false) {
                    $obs = trim($obs . "\nFoto: " . $publicPath);
                    $this->db->prepare("UPDATE ordens SET obs_exec = ? WHERE id = ?")->execute([$obs, $osId]);
                }
            } catch (Exception $e) {
                error_log('upload_image os link failed: ' . $e->getMessage());
            }
        }

        $this->success(['path' => $publicPath, 'os_id' => $osId > 0 ? $osId : null]);
    }

    /**
     * Tasks / Work Orders
     */


    /**
     * Reports & Checklist
     */


    /**
     * Catalog CRUD (See InventoryController)
     */

    /**
     * Logic: Suggest Lubrication (Advanced Physics Model)
     * Implements correction factors for Temp, Contamination, Vibration, Position, Moisture.
     */


    /**
     * New Feature: Viscosity Calculator
     * Calculates required viscosity (v1) and operating viscosity (v) based on VG, Temp, and VI.
     */







    private function handle_set_asset_status()
    {
        $status = trim($this->input['status'] ?? '');
        $id = intval($this->input['id'] ?? 0);

        // Normaliza variantes sem acento
        $statusAliases = ['Critico' => 'Crítico', 'Ok' => 'OK', 'Concluido' => 'Concluido'];
        if (isset($statusAliases[$status])) {
            $status = $statusAliases[$status];
        }

        if (!$id || $status === '') {
            $this->error("ID e status são obrigatórios.");
        }

        $allowedStatuses = ['OK', 'Concluido', 'Concluído', 'Alerta', 'Crítico', 'Pendente', 'Em Andamento'];
        if (!in_array($status, $allowedStatuses, true)) {
            $this->error("Status inválido: $status");
        }

        // Rotas de campo: qualquer usuário autenticado pode marcar OK/Alerta/Concluído
        $fieldStatuses = ['Concluido', 'Concluído', 'Alerta', 'OK'];
        if (!in_array($status, $fieldStatuses, true)) {
            if (!Permissions::isGestor($this->user['role'] ?? '') && !Permissions::isDeveloper($this->user['role'] ?? '')) {
                $this->error("Permissão negada.", 403);
            }
        }

        $this->db->prepare("UPDATE ativos SET status = ? WHERE id = ?")
            ->execute([$status, $id]);

        $qr = trim((string) ($this->input['qr_tag'] ?? ''));
        $qrOk = null;
        if ($qr !== '') {
            $tagStmt = $this->db->prepare('SELECT tag FROM ativos WHERE id = ?');
            $tagStmt->execute([$id]);
            $assetTag = trim((string) $tagStmt->fetchColumn());
            $qrOk = ($assetTag !== '' && strcasecmp($assetTag, $qr) === 0);
        }

        $this->success(['updated' => true, 'id' => $id, 'status' => $status, 'qr_ok' => $qrOk]);
    }

    /**
     * Alerta de campo: qualquer usuário autenticado pode reportar anomalia
     * e gerar O.S. corretiva automaticamente (sem obrigar foto).
     */
    private function handle_report_route_alert()
    {
        $id = intval($this->input['id'] ?? $this->input['ativo_id'] ?? 0);
        $motivo = trim($this->input['motivo'] ?? $this->input['desc'] ?? '');

        if (!$id || $motivo === '') {
            $this->error('Informe o ponto e descreva o problema encontrado.');
        }

        $stmt = $this->db->prepare("SELECT id, nome, tag FROM ativos WHERE id = ?");
        $stmt->execute([$id]);
        $ativo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ativo) {
            $this->error('Ponto de lubrificação não encontrado.');
        }

        $nomePonto = $ativo['nome'];
        $equipamento = trim($this->input['equipamento'] ?? '');
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $resp = $this->user['name'] ?? $this->user['nome'] ?? 'Lubrificador de Campo';
        $userId = $this->user['id'] ?? 1;

        $this->db->prepare("UPDATE ativos SET status = ? WHERE id = ?")
            ->execute(['Alerta', $id]);

        $descricao = '[ALERTA DE CAMPO] ' . $motivo;
        $obsExec = 'Alerta gerado pela Rota de Lubrificação. Ponto: ' . $nomePonto
            . ($equipamento ? ' (' . $equipamento . ')' : '')
            . '. Problema: ' . $motivo;

        $photoPath = trim((string) ($this->input['photo_path'] ?? ''));
        if ($photoPath !== '' && strpos($photoPath, 'uploads/') === 0 && strpos($photoPath, '..') === false) {
            $obsExec .= "\nFoto: " . $photoPath;
        }

        $this->db->prepare("INSERT INTO ordens (
            descricao, responsavel, data_planejada, prioridade, situacao, ativo_id,
            last_sync, usuarios_id, data_emissao, obs_exec, tipo_manutencao, complemento
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $descricao,
                $resp,
                $today,
                'Alta',
                'Pendente',
                $id,
                $now,
                $userId,
                $today,
                $obsExec,
                'Corretiva',
                'Rota: ' . $nomePonto
            ]);

        $osId = $this->db->lastInsertId();
        DB::log($this->user['name'] ?? $this->user['nome'] ?? 'SYSTEM', 'ROUTE_ALERT_OS', 'ordens:' . $osId, null, [
            'ativo_id' => $id,
            'motivo' => $motivo,
            'photo_path' => $photoPath !== '' ? $photoPath : null
        ]);

        $this->success([
            'updated' => true,
            'os_id' => $osId,
            'id' => $id,
            'status' => 'Alerta',
            'success' => true
        ]);
    }









    private function handle_get_thickener()
    {
        global $THICKENER_DB;
        $t = trim((string) ($this->input['type'] ?? ''));
        if ($t === '') {
            $this->error('Parâmetro type é obrigatório.', 400);
        }
        $this->success($THICKENER_DB[$t] ?? ['title' => 'Desconhecido']);
    }



    // --- ENGINEERING & AI ACTIONS ---



    // Removed legacy analysis methods to use modular controller


    private function handle_apply_machine_template()
    {
        $templateName = $this->input['template'] ?? '';
        $parentId = $this->input['parent_id'] ?? null;

        $jsonPath = __DIR__ . '/data/templates.json';
        if (!file_exists($jsonPath)) {
            $this->error("Arquivo de templates não encontrado.");
        }

        $templates = json_decode(file_get_contents($jsonPath), true);
        if (!isset($templates[$templateName])) {
            $this->error("Template '$templateName' não existe.");
        }

        $tData = $templates[$templateName];

        DB::safeExecute(function ($db) use ($tData, $parentId, $templateName) {
            $max = $db->query("SELECT MAX(ip) FROM ativos")->fetchColumn();
            $ip = ($max && $max > 0) ? $max + 1 : 2000;
            $stmt = $db->prepare("INSERT INTO ativos (nome, tipo, pai_id, dados_tecnicos, tag, ip, user_id) VALUES (?,?,?,?,?,?,?)");

            // 1. Machine
            $stmt->execute([$tData['name'], 'equipamento', $parentId, '{}', $tData['tag_prefix'] . '-01', $ip++, $this->user['id']]);
            $mId = $db->lastInsertId();

            // 2. Sections
            foreach ($tData['sections'] as $s) {
                $stmt->execute([$s, 'setor', $mId, '{}', $tData['tag_prefix'] . '-' . substr($s, 0, 3), $ip++, $this->user['id']]);
            }
        });
        $this->success();
    }

    private function handle_migrate()
    {
        if (!in_array($this->user['role'] ?? '', ['developer', 'admin'], true)) {
            $this->error("Permissão negada. Apenas administradores podem executar migrações.", 403);
        }

        $all = !empty($this->input['all']);
        if ($all) {
            $this->error(
                'Migração em massa deve ser executada via CLI/Cron (evita timeout parcial): php migrate_all_tenants.php',
                400
            );
        }

        DB::forceMigration();
        $this->success(['msg' => 'Schema migrado com sucesso', 'tenant' => TenantResolver::getCurrentTenant()]);
    }

    // --- INTERNAL HELPERS ---



    private function parseBearingSpecs($name)
    {
        $name = strtoupper($name);
        if (preg_match('/((?:6[0234]|222|223|213|230|231|232|240|241|NU\s?[23]|NJ\s?[23])\d{2})/', $name, $matches)) {
            $code = str_replace([' ', 'NU', 'NJ'], '', $matches[1]);
            $last2 = intval(substr($code, -2));
            $d = ($last2 < 4) ? ([10, 12, 15, 17][$last2] ?? 10) : ($last2 * 5);
            $series = substr($code, 0, strlen($code) - 2);

            // Estimates (simplified for logic)
            $D = $d * 2;
            $B = $d * 0.5;
            // Refine a bit
            if ($series == '62') {
                $D = $d * 1.7 + 12;
                $B = ($D - $d) * 0.35;
            }

            return [
                'model' => $matches[1],
                'd' => $d,
                'D' => round($D, 1),
                'B' => round($B, 1),
                'dm' => ($d + $D) / 2
            ];
        }
        return null;
    }

    private function validateLubricant($tech)
    {
        global $THICKENER_DB;
        if (isset($tech['temp'], $tech['material'])) {
        // Simplified Logic for brevity in re-write
        // ... (Checks would go here)
        }
    }

    private function ensureTable($table, $schema)
    {
        // Simple caching via static var to avoid repeated DESCRIBE queries
        static $checked = [];
        if (isset($checked[$table]))
            return;

        $this->db->exec("CREATE TABLE IF NOT EXISTS $table ($schema)");
        $checked[$table] = true;
    }

    private function ensureColumn($table, $col, $def)
    {
        $cols = $this->db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($col, $cols))
            $this->db->exec("ALTER TABLE $table ADD COLUMN $col $def");
    }

    // analyzeBearingCode has been moved to api/engineering_controller.php



    /**
     * --- OIL ANALYSIS & CONDITION MONITORING (ISO 4406 / Wear) ---
     * Allows saving lab reports and auto-detects critical conditions.
     */
    private function handle_save_analysis()
    {
        $in = $this->input;
        $iso = $in['iso'] ?? ''; // Format 18/16/13
        $water = floatval($in['water'] ?? 0);
        $iron = floatval($in['fe'] ?? 0);
        $ativoId = $in['ativo_id'] ?? null;
        $dataColeta = $in['date'] ?? null;

        if (empty($ativoId) || empty($dataColeta)) {
            $this->error('Ativo e data da coleta são obrigatórios.');
        }

        // --- 1. Save Data ---
        DB::safeExecute(function ($db) use ($in, $iso, $ativoId, $dataColeta) {
            $db->prepare("INSERT INTO analises (ativo_id, data_coleta, laboratorio, iso_4406, agua_ppm, fe_ppm, cu_ppm, si_ppm, laudo_geral, visc40, visc100, acidez) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                $ativoId,
                $dataColeta,
                $in['lab'] ?? 'Interno',
                $iso,
                $in['water'] ?? 0,
                $in['fe'] ?? 0,
                $in['cu'] ?? 0,
                $in['si'] ?? 0,
                $in['laudo'] ?? 'Normal',
                $in['visc40'] ?? null,
                $in['visc100'] ?? null,
                $in['acidez'] ?? null
            ]);
        });

        // --- 2. Auto-Trigger Logic (Industry 4.0) ---
        // Analyze ISO Code (4/6/14)
        $status = 'OK';
        $alertMsg = [];

        if (!empty($iso)) {
            $parts = explode('/', $iso);
            if (count($parts) == 3) {
                if (intval($parts[0]) > 21 || intval($parts[1]) > 19) {
                    $status = 'Crítico';
                    $alertMsg[] = "Contaminação Sólida Crítica (ISO $iso)";
                }
                elseif (intval($parts[0]) > 19) {
                    $status = ($status === 'Crítico') ? 'Crítico' : 'Alerta';
                    $alertMsg[] = "Alerta de Contaminação (ISO $iso)";
                }
            }
        }

        if ($water > 500) {
            $status = 'Crítico';
            $alertMsg[] = "Contaminação por Água Severa ({$water}ppm)";
        }

        if ($iron > 100) {
            $status = ($status === 'Crítico') ? 'Crítico' : 'Alerta';
            $alertMsg[] = "Desgaste Acelerado (Ferro {$iron}ppm)";
        }

        // If critical/alert, update asset status automatically
        if ($status !== 'OK') {
            $this->ensureColumn('ativos', 'status', "TEXT DEFAULT 'Ok'");
            $this->db->prepare("UPDATE ativos SET status = ? WHERE id = ?")->execute([$status, $ativoId]);

        // Generate Automatic "Check" Work Order? 
        // Optional: for now just return the alert
        }

        $this->success(['new_status' => $status, 'alerts' => $alertMsg]);
    }

    private function handle_import_analysis_csv()
    {
        require_once __DIR__ . '/includes/analysis_csv.php';
        $confirm = !empty($this->input['confirm']) || !empty($_POST['confirm']);
        $csv = '';
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
        } elseif (!empty($this->input['csv'])) {
            $csv = (string) $this->input['csv'];
        }
        if (trim($csv) === '') {
            $this->error('Envie um arquivo CSV de laudo.');
        }
        $parsed = AnalysisCsv::parse($csv, $this->db);
        if (empty($parsed['ok'])) {
            $this->error($parsed['error'] ?? 'CSV inválido.');
        }
        if (!$confirm) {
            $this->success([
                'preview' => true,
                'rows' => $parsed['rows'],
                'errors' => $parsed['errors'],
                'importable' => $parsed['importable'],
                'message' => 'Revise as linhas. Envie confirm=1 para gravar (não apaga laudos existentes).',
            ]);
        }
        $n = AnalysisCsv::insertRows($this->db, $parsed['rows']);
        $this->success(['imported' => $n, 'skipped' => count($parsed['rows']) - $n, 'errors' => $parsed['errors']]);
    }

    private function handle_get_analysis_history()
    {
        $aid = (int) ($this->input['ativo_id'] ?? 0);
        if ($aid <= 0) {
            $this->error('ativo_id é obrigatório.', 400);
        }
        $stmt = $this->db->prepare("SELECT * FROM analises WHERE ativo_id = ? ORDER BY data_coleta ASC LIMIT 12");
        $stmt->execute([$aid]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format for Chart.js
        $labels = [];
        $feData = [];
        $waterData = [];
        $visc40 = [];
        $visc100 = [];
        $tan = [];

        foreach ($res as $r) {
            $labels[] = date('d/m', strtotime($r['data_coleta']));
            $feData[] = $r['fe_ppm'];
            $waterData[] = $r['agua_ppm'];
            $visc40[] = $r['visc40'] ?? null;
            $visc100[] = $r['visc100'] ?? null;
            $tan[] = $r['acidez'] ?? null;
        }

        $this->success([
            'raw' => $res,
            'chart' => [
                'labels' => $labels,
                'fe' => $feData,
                'water' => $waterData,
                'visc40' => $visc40,
                'visc100' => $visc100,
                'tan' => $tan,
            ]
        ]);
    }

    private function handle_generate_checklist_report()
    {
        $id = intval($this->input['id'] ?? 0);
        $frequency = trim($this->input['frequency'] ?? '');

        if (!$id) {
            $this->error("ID do ativo é obrigatório.");
        }

        // 1. Fetch the parent asset details
        $stmt = $this->db->prepare("SELECT * FROM ativos WHERE id = ?");
        $stmt->execute([$id]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            $this->error("Ativo pai não encontrado.");
        }

        // 2. Fetch ALL assets to build recursive relationships in memory
        $all = $this->db->query("SELECT * FROM ativos")->fetchAll(PDO::FETCH_ASSOC);

        // Build a map of parent -> children and asset by ID
        $childrenMap = [];
        $assetMap = [];
        foreach ($all as $asset) {
            $assetMap[$asset['id']] = $asset;
            $pId = $asset['pai_id'];
            if ($pId) {
                $childrenMap[$pId][] = $asset['id'];
            }
        }

        // 3. Find all descendant IDs of the parent asset recursively
        $descendants = [];
        $toVisit = [$id];
        while (!empty($toVisit)) {
            $curr = array_pop($toVisit);
            if (isset($childrenMap[$curr])) {
                foreach ($childrenMap[$curr] as $childId) {
                    $descendants[] = $childId;
                    $toVisit[] = $childId;
                }
            }
        }

        // 4. Retrieve matched points
        $checklistRows = [];
        foreach ($descendants as $descId) {
            $node = $assetMap[$descId];
            
            // Parse technical data
            $tech = [];
            if (!empty($node['dados_tecnicos'])) {
                $tech = json_decode($node['dados_tecnicos'], true) ?: [];
            }

            $freq = $tech['periodo'] ?? '';
            $match = false;
            if (!empty($frequency) && !empty($freq)) {
                $match = (strpos(mb_strtolower($freq), mb_strtolower($frequency)) !== false);
            }

            if ($match && ($node['tipo'] === 'ponto' || !empty($tech['material']))) {
                // Find area/setor and equipment ancestors for context
                $area = '';
                $equipment = '';
                
                $currParentId = $node['pai_id'];
                $visitedParents = [];
                while ($currParentId && !in_array($currParentId, $visitedParents)) {
                    $visitedParents[] = $currParentId;
                    if (!isset($assetMap[$currParentId])) break;
                    $pNode = $assetMap[$currParentId];
                    if (empty($area) && ($pNode['tipo'] === 'setor' || $pNode['tipo'] === 'unidade')) {
                        $area = $pNode['nome'];
                    }
                    if (empty($equipment) && $pNode['tipo'] === 'equipamento') {
                        $equipment = $pNode['nome'];
                    }
                    $currParentId = $pNode['pai_id'];
                }

                $checklistRows[] = [
                    'area' => empty($area) ? 'GERAL' : $area,
                    'equipment' => empty($equipment) ? $node['nome'] : $equipment,
                    'tag' => empty($node['tag']) ? '-' : $node['tag'],
                    'ponto' => $tech['ponto_lub'] ?? $node['nome'],
                    'lubrificante' => $tech['material'] ?? '-',
                    'quantidade' => ($tech['qtd_material'] ?? '') . ' ' . ($tech['unid_material'] ?? ''),
                    'frequencia' => $freq,
                ];
            }
        }

        if (empty($checklistRows)) {
            $this->success([
                'parent_name' => $parent['nome'],
                'frequency' => $frequency,
                'rows' => [],
                'generated_at' => date('c'),
                'empty' => true
            ]);
        }

        $this->success([
            'parent_name' => $parent['nome'],
            'frequency' => $frequency,
            'rows' => $checklistRows,
            'generated_at' => date('c'),
            'empty' => false
        ]);
    }
    // --- LEGACY/DUPLICATED METHODS REMOVED FOR OPTIMIZATION ---

    // --- LEGACY/DUPLICATED METHODS REMOVED FOR OPTIMIZATION ---

    /**
     * ADVANCED KPIs (Real Data)
     */



    private function handle_wipe_db()
    {
        // Require Authentication — reforço explícito (defesa em profundidade) além do
        // RBAC (Permissions::canAccessApiAction já bloqueia gestor/trabalhador para esta action).
        if (!isset($this->user['id']) || !Permissions::isDeveloper($this->user['role'] ?? '')) {
            $this->error("Permissão negada para redefinir o banco de dados.", 403);
        }

        try {
            $this->db->beginTransaction();
            $this->db->exec("DELETE FROM planos");
            $this->db->exec("DELETE FROM ativos");
            $this->db->exec("DELETE FROM ordens");
            $this->db->exec("DELETE FROM pi_telemetry");
            $this->db->exec("DELETE FROM pi_tags");
            $this->db->exec("DELETE FROM sqlite_sequence WHERE name IN ('planos','ativos','ordens','pi_tags','pi_telemetry')");
            $this->db->commit();

            $this->success(['message' => 'Banco de Dados Reinicializado com Sucesso!']);
        }
        catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('handle_wipe_db failed: ' . $e->getMessage());
            $this->error('Erro ao redefinir o banco de dados.', 500, $e->getMessage());
        }
    }

    private function handle_save_snapshot()
    {
        // O snapshot é um arquivo ÚNICO/global (data/snapshot_latest.json), usado como
        // template de seed para novos tenants (seed.php::seedFromSnapshot). Por isso só o
        // Admin Global (developer) pode gravá-lo — um gestor de tenant não pode sobrescrever
        // o template do sistema com os dados (potencialmente confidenciais) da própria empresa.
        if (!isset($this->user['id']) || !Permissions::isDeveloper($this->user['role'] ?? '')) {
            $this->error("Permissão negada para salvar snapshot.", 403);
        }

        try {
            // 1. Fetch ALL Assets
            $stmt = $this->db->query("SELECT * FROM ativos ORDER BY pai_id ASC, id ASC");
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Fetch ALL Catalog Items
            $stmt = $this->db->query("SELECT * FROM catalogo");
            $catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Construct Payload
            $snapshot = [
                'meta' => [
                    'generated_at' => date('Y-m-d H:i:s'),
                    'user' => $this->user['name'] ?? 'System',
                    'version' => '3.0'
                ],
                'assets' => $assets,
                'catalog' => $catalog
            ];

            // 4. Save to File
            $path = __DIR__ . '/data/snapshot_latest.json';

            // Ensure data dir exists
            if (!is_dir(__DIR__ . '/data')) {
                mkdir(__DIR__ . '/data', 0777, true);
            }

            $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new Exception("Falha ao codificar JSON: " . json_last_error_msg());
            }

            if (file_put_contents($path, $json) === false) {
                throw new Exception("Falha ao escrever no arquivo de snapshot.");
            }

            $this->success([
                'msg' => 'Snapshot salvo com sucesso! O estado atual agora é a nova referência.',
                'path' => 'data/snapshot_latest.json',
                'assets_count' => count($assets),
                'catalog_count' => count($catalog)
            ]);

        }
        catch (Exception $e) {
            error_log('handle_save_snapshot failed: ' . $e->getMessage());
            $this->error('Erro ao salvar snapshot.', 500, $e->getMessage());
        }
    }

    private function handle_list_mockups()
    {
        try {
            // Check table existence first to avoid crash if migration failed
            $cols = $this->db->query("PRAGMA table_info(ativos)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('imagem_3d', $cols)) {
                $this->success(['mockups' => []]);
                return;
            }

            $stmt = $this->db->query("SELECT id, nome, tag, imagem, imagem_3d, dados_tecnicos FROM ativos WHERE imagem_3d IS NOT NULL AND imagem_3d != ''");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = array_map(function ($item) {
                $specs = json_decode($item['dados_tecnicos'] ?? '{}', true);
                return [
                'id' => $item['id'],
                'name' => $item['nome'],
                'tag' => $item['tag'] ?? 'N/A',
                'image' => $item['imagem'] ?? 'assets/img/system/img_69581d7fcdbbf.jpeg',
                'model_path' => $item['imagem_3d'],
                'location' => $specs['localizacao'] ?? 'Geral'
                ];
            }, $data);

            $this->success(['mockups' => $results]);
        }
        catch (Exception $e) {
            error_log('handle_list_mockups failed: ' . $e->getMessage());
            $this->error('Erro ao listar mockups 3D.', 500, $e->getMessage());
        }
    }
}


// EXECUTE
$api = new RodrigoAPI();
$api->run();
