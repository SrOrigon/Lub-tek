<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/lubrication_plan_builder.php';
require_once __DIR__ . '/../includes/lubrication_plan_image_helper.php';
require_once __DIR__ . '/../includes/lubrication_plan_ssma_content.php';
require_once __DIR__ . '/../includes/lubrication_plan_renderer.php';

$pdo = DB::getInstance();
$plan = (new LubricationPlanBuilder($pdo))->build(18, ['frequency' => 'Mensal']);
$r = new LubricationPlanRenderer(['name' => 'Solar']);
$html = $r->renderFull($plan, [
    'subtitle' => $plan['meta']['root_name'],
    'show_toolbar' => false,
    'use_ai' => false,
]);
$validation = LubricationPlanPatternGuard::validate($html);
$slides = LubricationPlanPatternGuard::countSlides($html);
$out = __DIR__ . '/../data/test_mensal.html';
file_put_contents($out, $html);
echo 'Points: ' . ($plan['meta']['stats']['points'] ?? 0) . "\n";
echo 'Slides: ' . $slides . "\n";
echo 'Slides est: ' . ($plan['meta']['stats']['slides_estimated'] ?? 0) . "\n";
echo 'Merged: ' . substr_count($html, 'merged-tables-slide') . "\n";
echo 'Photo+table: ' . substr_count($html, 'photo-table-combo') . "\n";
echo 'Pattern: ' . ($validation['ok'] ? 'PASS' : 'FAIL') . "\n";
if (!$validation['ok']) {
    foreach ($validation['errors'] as $e) {
        echo "  - $e\n";
    }
    exit(1);
}
echo "Written: $out\n";
