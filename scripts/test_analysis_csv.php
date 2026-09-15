<?php
require_once __DIR__ . '/../includes/analysis_csv.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ativos (id INTEGER, tag TEXT, nome TEXT)");
$pdo->exec("CREATE TABLE analises (id INTEGER PRIMARY KEY AUTOINCREMENT, ativo_id INTEGER, data_coleta TEXT, laboratorio TEXT, iso_4406 TEXT, agua_ppm REAL, fe_ppm REAL, cu_ppm REAL, si_ppm REAL, laudo_geral TEXT, visc40 REAL, visc100 REAL, acidez REAL)");
$pdo->exec("INSERT INTO ativos VALUES (7, 'P-18', 'Redutor')");
$pdo->exec("INSERT INTO analises (ativo_id, data_coleta, laboratorio, iso_4406, agua_ppm, fe_ppm, cu_ppm, si_ppm, laudo_geral) VALUES (7,'2020-01-01','Old','18/16/13',10,5,0,0,'Normal')");

$csv = "tag,date,iso,water,fe,visc40,visc100,tan,lab\nP-18,2026-01-15,19/17/14,80,12,68.1,8.7,0.4,ALS\n";
$parsed = AnalysisCsv::parse($csv, $pdo);
if (($parsed['importable'] ?? 0) !== 1) {
    fwrite(STDERR, "FAIL parse " . json_encode($parsed) . "\n");
    exit(1);
}
$n = AnalysisCsv::insertRows($pdo, $parsed['rows']);
$cnt = (int) $pdo->query('SELECT COUNT(*) FROM analises')->fetchColumn();
if ($n !== 1 || $cnt !== 2) {
    fwrite(STDERR, "FAIL insert n=$n cnt=$cnt\n");
    exit(1);
}
echo "PASS csv import additive cnt=$cnt\n";
