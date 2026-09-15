<?php
require_once __DIR__ . '/../includes/lubricant_consumption.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ativos (id INTEGER, nome TEXT, tag TEXT, tipo TEXT, pai_id INTEGER, dados_tecnicos TEXT)");
$pdo->exec("CREATE TABLE ordens (id INTEGER, situacao TEXT, ativo_id INTEGER, data_conclusao TEXT, data_execucao TEXT, data_planejada TEXT)");

$tech = json_encode([
    'material' => 'MOBILGEAR 600 XP 220',
    'qtd_material' => '13',
    'unid_material' => 'l',
    'periodo' => 'Mensal',
    'ponto_lub' => 'REDUTOR',
], JSON_UNESCAPED_UNICODE);

$pdo->exec("INSERT INTO ativos VALUES (1,'Linha PET','U1','unidade',NULL,NULL)");
$pdo->exec("INSERT INTO ativos VALUES (2,'Enchedora','EQ-01','equipamento',1,NULL)");
$pdo->prepare("INSERT INTO ativos VALUES (3,'Ponto redutor','P-18','ponto',2,?)")->execute([$tech]);
$pdo->exec("INSERT INTO ordens VALUES (1,'Concluído',3,'" . date('Y-m-d') . "',NULL,NULL)");

$rep = LubricantConsumption::build($pdo, ['period' => 'month']);
if (!$rep['has_data'] || count($rep['equipments']) !== 1) {
    fwrite(STDERR, "FAIL rollup " . json_encode($rep) . "\n");
    exit(1);
}
$eq = $rep['equipments'][0];
if ($eq['nome'] !== 'Enchedora' || abs($eq['consumo_l_mes'] - 13) > 0.01) {
    fwrite(STDERR, "FAIL equip " . json_encode($eq) . "\n");
    exit(1);
}
if ((int) $eq['os_concluidas_mes'] < 1) {
    fwrite(STDERR, "FAIL os count\n");
    exit(1);
}
echo "OK {$eq['nome']} {$eq['consumo_l_mes']} L  pontos {$eq['pontos']}\n";

// Projeção estruturada (SQL) deve bater com o JSON
require_once __DIR__ . '/../includes/lubrication_tech_sync.php';
LubricationTechSync::backfill($pdo);
$rep2 = LubricantConsumption::build($pdo, ['period' => 'month']);
if (abs($rep2['equipments'][0]['consumo_l_mes'] - 13) > 0.01) {
    fwrite(STDERR, "FAIL projection " . json_encode($rep2['equipments'][0]) . "\n");
    exit(1);
}
$sumSql = $pdo->query("SELECT SUM(base_mensal) FROM ativos_lubrificacao WHERE familia='l'")->fetchColumn();
if (abs((float)$sumSql - 13) > 0.01) {
    fwrite(STDERR, "FAIL SQL sum $sumSql\n");
    exit(1);
}
echo "OK projection SQL SUM={$sumSql}\n";
echo "PASS\n";
