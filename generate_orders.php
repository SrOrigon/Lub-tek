<?php
/**
 * LUB-TEK - CLI Script for Auto-generating preventative work orders
 * 
 * Runs once a day via CRON: php generate_orders.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Acesso negado. Execute via SSH/Cron:\n  php generate_orders.php\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/api/OrdersController.php';

echo "=== INICIANDO GERACAO AUTOMATICA DE ORDENS DE SERVICO ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

function runOrderGenerationForTenant(?string $tenant): void
{
    $label = $tenant ?? 'admin';
    echo "[$label] Processando preventivas...\n";

    try {
        $db = DB::getInstance($tenant);
        
        // Mock a user object with sufficient permission for the controller
        $mockUser = [
            'id' => 1,
            'name' => 'SYSTEM_CRON',
            'role' => 'developer',
            'tenant' => $tenant
        ];

        $ordersCtrl = new OrdersController($db, $mockUser, []);
        $result = $ordersCtrl->generateScheduledOrders();
        
        // Check both possible formats of return
        $count = 0;
        if (is_array($result)) {
            $count = $result['generated_count'] ?? $result['count'] ?? count($result['orders'] ?? []);
        } elseif (is_numeric($result)) {
            $count = (int)$result;
        }
        
        echo "[$label] Concluido! Ordens geradas: $count\n";
    } catch (Exception $e) {
        echo "[$label] [ERRO] Falha ao processar: " . $e->getMessage() . "\n";
        error_log("Cron Order Generation error for tenant $label: " . $e->getMessage());
    }
}

// 1. Process default/admin DB
runOrderGenerationForTenant(null);

// 2. Process all client DBs
foreach (TenantResolver::listTenantSlugs() as $slug) {
    runOrderGenerationForTenant($slug);
}

echo "\n=== PROCESSO FINALIZADO COM SUCESSO ===\n";
