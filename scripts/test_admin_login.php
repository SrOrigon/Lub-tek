<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = DB::getMaster();
$row = $pdo->query("SELECT email,senha FROM usuarios WHERE email='admin@rodrigo.com'")->fetch(PDO::FETCH_ASSOC);
echo "Master admin@rodrigo.com\n";
foreach (['changeme', '123456', 'admin', 'Admin123', 'Senha123', 'password', 'rodrigo', 'Ambevadmin'] as $p) {
    $ok = AuthSystem::verifyPassword($p, $row['senha']);
    echo ($ok ? 'PASS' : 'fail') . " [$p]\n";
}

$auth = new AuthSystem();
foreach (['admin@rodrigo.com', 'Admin', 'admin@lubtek.com.br'] as $login) {
    $_SESSION = [];
    $r = $auth->login($login, 'changeme');
    echo 'login ' . $login . ' changeme => ' . ($r['success'] ? 'OK' : ($r['message'] ?? 'fail')) . "\n";
}

echo "\nTenant cocacola Funcionario:\n";
foreach (['Cocacola.Funcionario', 'funcionario@cocacola', 'funcionario.cocacola', 'Funcionario'] as $login) {
    $_SESSION = [];
    $r = $auth->login($login, 'changeme');
    echo 'login ' . $login . ' => ' . ($r['success'] ? 'OK role=' . ($r['user']['role'] ?? '') : ($r['message'] ?? 'fail')) . "\n";
}
