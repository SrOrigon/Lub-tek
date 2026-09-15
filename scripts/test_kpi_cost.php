<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/KPIController.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ativos (id INTEGER, pai_id INTEGER, tipo TEXT, status TEXT, dados_tecnicos TEXT)");
$pdo->exec("CREATE TABLE catalogo (id INTEGER, nome TEXT, codigo TEXT, specs TEXT, estoque_atual REAL)");
$pdo->exec("CREATE TABLE ordens (id INTEGER, situacao TEXT, data_planejada TEXT, data_conclusao TEXT, data_execucao TEXT, last_sync TEXT, prioridade TEXT, ativo_id INTEGER, materiais TEXT, qtd_real TEXT, materiais_sap TEXT)");
$pdo->exec("CREATE TABLE planos (id INTEGER, ativo_id INTEGER, catalogo_id INTEGER, frequencia_dias INTEGER, quantidade REAL)");
$pdo->exec("CREATE TABLE mercado_ofertas (id INTEGER, catalogo_id INTEGER, price REAL)");
$pdo->exec("CREATE TABLE analises (id INTEGER)");

$tech = json_encode([
    'material' => 'LE 4220 H1 Quinplex Syn FG Gear Oil- ISO VG 220',
    'qtd_material' => '13',
    'unid_material' => 'l',
    'periodo' => 'Mensal',
], JSON_UNESCAPED_UNICODE);
$pdo->exec("INSERT INTO ativos VALUES (1, NULL, 'equipamento', 'OK', NULL)");
$pdo->prepare("INSERT INTO ativos VALUES (2, 1, 'ponto', 'OK', ?)")->execute([$tech]);
$pdo->exec("INSERT INTO catalogo VALUES (1, 'LE 4220 H1 Quinplex Syn FG Gear Oil', 'LE 4220', '{\"custo_medio\":45}', 10)");
$pdo->exec("INSERT INTO ordens (id, situacao, data_planejada, prioridade) VALUES (1, 'Pendente', '2000-01-01', 'Alta')");

$kpi = new KPIController($pdo, 1, true, null);
$res = $kpi->getDashboardKPIs();
$total = $res['kpis']['financial_total'] ?? 0;
echo 'financial_total=' . $total . PHP_EOL;
echo 'hint=' . ($res['kpis']['financial_hint'] ?? '') . PHP_EOL;
if ($total < 40) {
    fwrite(STDERR, "FAIL: esperado custo mensal ~13L * R$45\n");
    exit(1);
}
echo "PASS\n";
