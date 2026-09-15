<?php
/**
 * Testa criação de usuário + login (admin e tenant).
 * Uso: php scripts/test_user_create_login.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../api/UsersController.php';

$fail = 0;

function tryLogin(string $login, string $pass): bool {
    $_SESSION = [];
    $auth = new AuthSystem();
    $r = $auth->login($login, $pass);
    return !empty($r['success']);
}

// --- Admin mode (usuário temporário, não altera id=1) ---
TenantResolver::forceTenant(null);
$pdo = DB::getMaster();
$passAdmin = 'NovaSenhaAdmin!1';
$adminLogin = 'teste.admin.' . time() . '@rodrigo.com';
$ctrl = new UsersController($pdo, ['id' => 1, 'role' => 'developer', 'nome' => 'Test'], [
    'nome' => 'Admin Teste',
    'username' => $adminLogin,
    'senha' => $passAdmin,
    'nivel' => 3,
]);
$res = $ctrl->saveUser();
$okAdmin = tryLogin($adminLogin, $passAdmin) || tryLogin(str_replace('@', '.', $adminLogin), $passAdmin);
echo ($okAdmin ? 'PASS' : 'FAIL') . " admin master login após criar conta\n";
if (!$okAdmin) $fail++;
$pdo->prepare('DELETE FROM usuarios WHERE email = ?')->execute([$res['email'] ?? $adminLogin]);

// --- Tenant short login ---
TenantResolver::forceTenant('cocacola');
$pdoT = DB::getInstance('cocacola');
$passT = 'SenhaCampo!77';
$short = 'campo.' . time();
$ctrlT = new UsersController($pdoT, ['id' => 1, 'role' => 'gestor', 'nome' => 'Admin'], [
    'nome' => 'Campo Teste',
    'username' => $short,
    'senha' => $passT,
    'nivel' => 1,
]);
$saveT = $ctrlT->saveUser();
$hint = $saveT['login_hint'] ?? ('Cocacola.' . $short);
$okT = tryLogin($hint, $passT) || tryLogin($saveT['email'] ?? '', $passT);
echo ($okT ? 'PASS' : 'FAIL') . " tenant login curto [$hint]\n";
if (!$okT) $fail++;
$pdoT->prepare('DELETE FROM usuarios WHERE email = ?')->execute([$saveT['email'] ?? '']);

// --- Tenant real email ---
$email = 'funcionario.teste.' . time() . '@gmail.com';
$passE = 'EmailLogin!88';
$ctrlE = new UsersController($pdoT, ['id' => 1, 'role' => 'gestor', 'nome' => 'Admin'], [
    'nome' => 'Email Teste',
    'username' => $email,
    'senha' => $passE,
    'nivel' => 1,
]);
$saveE = $ctrlE->saveUser();
$okE = tryLogin($email, $passE);
echo ($okE ? 'PASS' : 'FAIL') . " tenant login e-mail real [$email]\n";
if (!$okE) $fail++;
$pdoT->prepare('DELETE FROM usuarios WHERE email = ?')->execute([$saveE['email'] ?? '']);

TenantResolver::clearForcedTenant();

echo $fail === 0 ? "\nOK\n" : "\nFALHAS: $fail\n";
exit($fail === 0 ? 0 : 1);
