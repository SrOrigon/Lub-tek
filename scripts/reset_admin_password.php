<?php
/**
 * Redefine senha do administrador master (SQLite + users.json).
 * Uso: php scripts/reset_admin_password.php [nova_senha]
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../api/UsersController.php';

$newPass = $argv[1] ?? 'Admin';
if ($newPass === '') {
    fwrite(STDERR, "Informe a senha.\n");
    exit(1);
}

TenantResolver::forceTenant(null);
$pdo = DB::getMaster();
$row = $pdo->query('SELECT id, nome, email FROM usuarios WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "Usuario id=1 não encontrado no SQLite master.\n");
    exit(1);
}

// Restaura e-mail corporativo se foi corrompido por sync anterior
$email = (string) ($row['email'] ?? '');
if ($email === '' || strpos($email, '@') === false) {
    $email = 'admin@rodrigo.com';
    $pdo->prepare('UPDATE usuarios SET email = ? WHERE id = 1')->execute([$email]);
    $row['email'] = $email;
}

$ctrl = new UsersController($pdo, ['id' => 1, 'role' => 'developer', 'nome' => 'CLI'], [
    'id' => 1,
    'nome' => (string) ($row['nome'] ?? 'Admin'),
    'username' => 'Admin',
    'senha' => $newPass,
    'nivel' => 3,
]);
$ctrl->saveUser();

$auth = new AuthSystem();
$_SESSION = [];
$tests = [
    [(string) ($row['nome'] ?? 'Admin'), $newPass],
    [(string) ($row['email'] ?? 'admin@rodrigo.com'), $newPass],
];
$ok = false;
foreach ($tests as [$login, $pass]) {
    $_SESSION = [];
    $r = $auth->login($login, $pass);
    if (!empty($r['success'])) {
        echo "OK login via [$login]\n";
        $ok = true;
    }
}

if (!$ok) {
    fwrite(STDERR, "Falha ao validar login após reset.\n");
    exit(1);
}

echo "Senha master redefinida com sucesso.\n";
exit(0);
