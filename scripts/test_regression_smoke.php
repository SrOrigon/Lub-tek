<?php
/**
 * Smoke de regressão (permisões, rotas, classes críticas).
 */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/includes/permissions.php';
require_once dirname(__DIR__) . '/api/ApiRouteRegistry.php';

$fail = 0;

if (!Permissions::canAccessApiAction('gestor', 'wipe_db')) {
    // ok — gestor não deve apagar DB
} else {
    echo "FAIL gestor wipe_db\n";
    $fail++;
}

if (!Permissions::canAccessApiAction('powerbi_reader', 'save_asset')) {
    // ok
} else {
    echo "FAIL powerbi save_asset\n";
    $fail++;
}

if (!isset(ApiRouteRegistry::routes()['save_agent_feedback'])) {
    echo "FAIL route save_agent_feedback\n";
    $fail++;
}

exit($fail > 0 ? 1 : 0);
