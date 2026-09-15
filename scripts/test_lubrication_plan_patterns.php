<?php
/**
 * Certifica que o contrato visual do Plano PDF se mantém em qualquer contexto.
 * Uso: php scripts/test_lubrication_plan_patterns.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/lubrication_plan_builder.php';
require_once __DIR__ . '/../includes/lubrication_plan_image_helper.php';
require_once __DIR__ . '/../includes/lubrication_plan_ssma_content.php';
require_once __DIR__ . '/../includes/lubrication_plan_renderer.php';

$pdo = DB::getInstance();
$builder = new LubricationPlanBuilder($pdo);
$renderer = new LubricationPlanRenderer(['name' => 'LUB-TEK']);

$scenarios = [
    'completo' => fn() => $builder->build(18, []),
    'mensal' => fn() => $builder->build(18, ['frequency' => 'Mensal']),
    'semanal' => fn() => $builder->build(18, ['frequency' => 'Semanal']),
    'vazio_mock' => fn() => [
        'meta' => [
            'root_name' => 'Linha teste',
            'root_imagem' => '',
            'line_label' => 'LINHA TESTE',
            'line_stations' => [],
            'generated_at' => date('c'),
            'stats' => ['equipments' => 0, 'points' => 0, 'areas' => 0],
            'unique_lubricants' => [],
        ],
        'areas' => [],
    ],
    'minimo_1_ponto' => function () use ($builder) {
        $plan = $builder->build(18, ['frequency' => 'Mensal']);
        $eq = $plan['areas'][0]['equipments'][0] ?? null;
        if (!$eq) {
            return $plan;
        }
        $pts = array_slice($eq['points'] ?? [], 0, 1);
        return [
            'meta' => $plan['meta'],
            'areas' => [[
                'name' => $plan['areas'][0]['name'] ?? 'GERAL',
                'equipments' => [[
                    ...$eq,
                    'points' => $pts,
                    'subsections' => [[
                        'title' => $eq['nome'],
                        'subtitle' => '',
                        'imagem' => $eq['imagem'] ?? '',
                        'obs' => '',
                        'points' => $pts,
                    ]],
                ]],
            ]],
        ];
    },
];

$allOk = true;
echo "=== Certificação padrão Plano PDF (pet160-v2) ===\n\n";

foreach ($scenarios as $name => $factory) {
    $plan = $factory();
    $html = $renderer->renderFull($plan, [
        'subtitle' => (string) ($plan['meta']['root_name'] ?? ''),
        'show_toolbar' => false,
        'use_ai' => false,
    ]);

    $result = LubricationPlanPatternGuard::validate($html);
    $bodyHtml = preg_match('/<main id="plan-document"[^>]*>(.*)<\/main>/s', $html, $m) ? $m[1] : $html;
    $slides = substr_count($bodyHtml, '<section class="slide');
    $pattern = preg_match('/data-plan-pattern="pet160-v2"/', $html) ? 'ok' : 'missing';

    echo "[$name]\n";
    echo "  Slides: $slides | pattern attr: $pattern\n";

    if ($result['ok']) {
        echo "  Status: PASS\n";
    } else {
        $allOk = false;
        echo "  Status: FAIL\n";
        foreach ($result['errors'] as $e) {
            echo "    - $e\n";
        }
    }
    foreach ($result['warnings'] as $w) {
        echo "    aviso: $w\n";
    }
    echo "\n";
}

echo $allOk ? "RESULTADO: CERTIFICADO — todos os cenários passaram.\n" : "RESULTADO: FALHOU — corrigir erros acima.\n";
exit($allOk ? 0 : 1);
