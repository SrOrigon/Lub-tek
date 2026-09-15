<?php
require_once __DIR__ . '/../includes/grease_compatibility.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ativos (id INTEGER, dados_tecnicos TEXT)");
$pdo->exec("CREATE TABLE ordens (id INTEGER PRIMARY KEY AUTOINCREMENT, descricao TEXT, responsavel TEXT, data_planejada TEXT, prioridade TEXT, situacao TEXT, observacao TEXT, ativo_id INTEGER, tipo_manutencao TEXT, data_emissao TEXT, last_sync TEXT)");

$old = json_encode(['material' => 'Graxa Poliureia Mobil Polyrex']);
$pdo->prepare('INSERT INTO ativos VALUES (1, ?)')->execute([$old]);

$fail = 0;
try {
    GreaseCompatibility::guardOnAsset($pdo, 1, ['material' => 'Graxa Lítio MP2'], false, ['nome' => 'Tec']);
    echo "FAIL should block\n";
    $fail++;
} catch (Exception $e) {
    $j = json_decode($e->getMessage(), true);
    if (($j['error_code'] ?? '') !== 'GREASE_INCOMPATIBLE') {
        echo "FAIL code\n";
        $fail++;
    } else {
        echo "PASS block incompatible\n";
    }
}

$os = GreaseCompatibility::guardOnAsset($pdo, 1, ['material' => 'Graxa Lítio MP2'], true, ['nome' => 'Tec']);
if (!$os) {
    echo "FAIL wash OS\n";
    $fail++;
} else {
    echo "PASS wash OS #$os\n";
}

$n = (int) $pdo->query('SELECT COUNT(*) FROM ativos')->fetchColumn();
if ($n !== 1) {
    echo "FAIL asset lost\n";
    $fail++;
} else {
    echo "PASS asset preserved\n";
}

exit($fail > 0 ? 1 : 0);
