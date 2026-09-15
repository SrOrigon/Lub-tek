<?php
/**
 * Exportação server-side do Plano de Lubrificação (HTML → Impressão/PDF).
 * Suporta plantas com milhares de peças sem limite artificial.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/lubrication_plan_builder.php';
require_once __DIR__ . '/includes/lubrication_plan_image_helper.php';
require_once __DIR__ . '/includes/lubrication_plan_ai_enricher.php';
require_once __DIR__ . '/includes/lubrication_plan_renderer.php';
require_once __DIR__ . '/includes/lubrication_plan_pattern_guard.php';
require_once __DIR__ . '/includes/gemini_service.php';

$auth = new AuthSystem();
$auth->requireLogin();

$currentUser = $auth->getCurrentUser();
if (!$auth->canAccessPage('assets') && !$auth->canAccessPage('reports')) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

$assetId = (int) ($_GET['id'] ?? 0);
$frequency = trim((string) ($_GET['frequency'] ?? ''));
$title = trim((string) ($_GET['title'] ?? 'PLANO DE LUBRIFICAÇÃO'));
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
$useAi = isset($_GET['use_ai']) && $_GET['use_ai'] === '1';

if ($assetId <= 0) {
    http_response_code(400);
    echo 'Parâmetro id é obrigatório.';
    exit;
}

try {
    $db = DB::getInstance();
    $tenant = $currentUser['tenant'] ?? null;
    $companyName = 'LUB-TEK';
    $companyLogo = 'assets/img/system/img_69581d7fcdbbf.jpeg';

    $customName = DB::getSystemMeta('company_name', $tenant);
    $customLogo = DB::getSystemMeta('company_logo', $tenant);
    if ($customName) {
        $companyName = $customName;
    } elseif ($tenant) {
        $companyName = ucfirst((string) $tenant);
    }
    if ($customLogo) {
        $companyLogo = $customLogo;
    }
    if (!empty($currentUser['tenant_label'])) {
        $companyName = (string) $currentUser['tenant_label'];
    }

    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    $builder = new LubricationPlanBuilder($db);
    $plan = $builder->build($assetId, ['frequency' => $frequency]);

    if ($useAi) {
        $enricher = new LubricationPlanAiEnricher($db);
        $plan = $enricher->enrich($plan, ['frequency' => $frequency]);
    }

    $renderer = new LubricationPlanRenderer([
        'name' => $companyName,
        'logo' => $companyLogo,
    ]);

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $html = $renderer->renderFull($plan, [
        'title' => $title,
        'subtitle' => (string) ($plan['meta']['root_name'] ?? ''),
        'auto_print' => $autoPrint,
        'show_toolbar' => true,
        'use_ai' => $useAi,
        'ai_source' => $useAi ? ($plan['ai']['source'] ?? null) : null,
    ]);

    $validation = LubricationPlanPatternGuard::validate($html);
    if (!$validation['ok']) {
        error_log('export_lubrication_plan pattern_guard: ' . implode('; ', $validation['errors']));
    }

    echo $html;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('export_lubrication_plan: ' . $e->getMessage());
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;">';
    echo '<h2>Erro ao gerar plano</h2><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
}
