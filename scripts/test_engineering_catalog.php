<?php
require_once __DIR__ . '/../includes/engineering_catalog.php';
require_once __DIR__ . '/../api/EngineeringController.php';

$checks = [
    '6205-2RS' => [25, 52, 15],
    '22210' => [50, 90, 23],
    'NU205' => [25, 52, 15],
    'SKF 6308 C3' => [40, 90, 23],
    'HK2016' => [20, 26, 16],
];
foreach ($checks as $code => $exp) {
    $r = EngineeringCatalog::findBearing($code);
    if (!$r || $r['d'] != $exp[0] || $r['D'] != $exp[1] || $r['B'] != $exp[2]) {
        fwrite(STDERR, "FAIL $code got " . json_encode($r) . "\n");
        exit(1);
    }
    echo "OK $code {$r['d']}x{$r['D']}x{$r['B']}\n";
}
echo 'TOTAL ' . count(EngineeringCatalog::bearings()) . "\n";

$c = new EngineeringController(null, 0, null, [
    'name' => '6205', 'rpm' => 1750, 'temp' => 70, 'horas_dia' => 24,
    'vib' => 'low', 'cont' => 'clean', 'pos' => 'horiz', 'moisture' => 'dry',
]);
$out = $c->handleSuggestLubrication();
echo "GP {$out['grams']} g  hours {$out['hours']}  nu1 {$out['nu1_rec_cst']}  vg {$out['iso_vg_rec']}\n";
if (empty($out['suggestion_only'])) {
    fwrite(STDERR, "FAIL suggestion_only flag\n");
    exit(1);
}

$c2 = new EngineeringController(null, 0, null, ['vg' => 46, 'vi' => 95, 'temp' => 40]);
$v = $c2->handleCalcViscosity();
if (abs($v['viscosity_op'] - 46) > 0.2) {
    fwrite(STDERR, "FAIL visc40 {$v['viscosity_op']}\n");
    exit(1);
}
echo "VISC40 {$v['viscosity_op']} v100 {$v['v100']}\n";

$ck = new EngineeringController(null, 0, null, [
    'code' => '6205', 'rpm' => 1750, 'temp' => 70, 'vg' => 68, 'vi' => 95,
]);
$k = $ck->handleCalcKappa();
if (empty($k['found']) || ($k['kappa'] ?? 0) <= 0) {
    fwrite(STDERR, "FAIL kappa " . json_encode($k) . "\n");
    exit(1);
}
$expectNu1 = 45000 / (pow(1750, 0.83) * pow(38.5, 0.5));
if (abs($k['nu1'] - $expectNu1) > 0.6) {
    fwrite(STDERR, "FAIL nu1 got {$k['nu1']} expect ~$expectNu1\n");
    exit(1);
}
$reK = $k['nu'] / $k['nu1'];
if (abs($k['kappa'] - $reK) > 0.05) {
    fwrite(STDERR, "FAIL kappa {$k['kappa']} != nu/nu1 $reK\n");
    exit(1);
}
echo "KAPPA {$k['kappa']} nu {$k['nu']} nu1 {$k['nu1']} band {$k['band']} gp {$k['grams_relub']}\n";
echo "PASS\n";
