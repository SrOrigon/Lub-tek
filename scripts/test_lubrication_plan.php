<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/lubrication_plan_builder.php';
require_once __DIR__ . '/../includes/lubrication_plan_image_helper.php';
require_once __DIR__ . '/../includes/lubrication_plan_ai_enricher.php';
require_once __DIR__ . '/../includes/lubrication_plan_renderer.php';

$pdo = DB::getInstance();
$builder = new LubricationPlanBuilder($pdo);

$root = $pdo->query("SELECT id, nome FROM ativos WHERE id = 18 OR pai_id IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$root) {
    $root = $pdo->query("SELECT id, nome FROM ativos LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

$plan = $builder->build((int) $root['id']);
$renderer = new LubricationPlanRenderer([
    'name' => 'Solar Coca-Cola',
    'logo' => 'assets/img/system/img_69581d7fcdbbf.jpeg',
]);

$html = $renderer->renderFull($plan, [
    'title' => 'PLANO DE LUBRIFICAÇÃO',
    'subtitle' => $plan['meta']['root_name'],
    'show_toolbar' => false,
    'use_ai' => false,
]);

$out = __DIR__ . '/../data/test_plan_preview.html';
file_put_contents($out, $html);

echo "Root: {$root['nome']}\n";
echo "Stations: " . count($plan['meta']['line_stations']) . "\n";
echo "Equipments: {$plan['meta']['stats']['equipments']}\n";
echo "Points: {$plan['meta']['stats']['points']}\n";
echo "Slides est: {$plan['meta']['stats']['slides_estimated']}\n";
echo "Slides real: " . LubricationPlanPatternGuard::countSlides($html) . "\n";

$validation = LubricationPlanPatternGuard::validate($html);
if (!$validation['ok']) {
    echo "Pattern FAIL:\n";
    foreach ($validation['errors'] as $e) {
        echo "  - $e\n";
    }
    exit(1);
}
echo "Pattern: PASS\n";
echo "Written: $out (" . strlen($html) . " bytes)\n";
