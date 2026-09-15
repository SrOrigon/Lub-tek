<?php
/**
 * Teste CLI de login multi-tenant (sem servidor web)
 * Uso: php scripts/test_login.php
 */
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config.php';

$tests = [
    ['Cocacola.Admin', 'changeme', true, 'gestor'],
    ['Cocacola.Funcionario', 'changeme', true, 'trabalhador'],
    ['ambev.Admin', 'changeme', true, 'gestor'],
    ['Admin@ambev', 'changeme', true, 'gestor'],
    ['abnersynthoil.ambev', '123456', true, 'trabalhador'],
    ['Cocacola.Admin', 'wrong', false, null],
    ['Inexistente.Admin', 'changeme', false, null],
];

require_once __DIR__ . '/../includes/auth.php';

$passed = 0;
foreach ($tests as [$user, $pass, $expectOk, $expectRole]) {
    session_unset();
    $_SESSION = [];

    $auth = new AuthSystem();
    $result = $auth->login($user, $pass);
    $ok = !empty($result['success']);

    $roleOk = true;
    if ($expectOk && $expectRole) {
        $roleOk = Permissions::normalizeRole($result['user']['role'] ?? '') === $expectRole
            || ($expectRole === 'gestor' && Permissions::isGestor($result['user']['role'] ?? ''))
            || ($expectRole === 'trabalhador' && Permissions::isTrabalhador($result['user']['role'] ?? ''));
    }

    $tenant = $_SESSION['tenant'] ?? null;
    $testOk = ($ok === $expectOk) && ($expectOk ? $roleOk : true);

    if ($testOk) {
        $passed++;
        $info = $ok ? "tenant={$tenant} role=" . ($result['user']['role'] ?? '-') : 'rejeitado';
        echo "[OK] {$user} -> {$info}\n";
    } else {
        echo "[FAIL] {$user} expect=" . ($expectOk ? 'ok' : 'fail') . " got=" . ($ok ? 'ok' : 'fail');
        if ($ok) echo " role=" . ($result['user']['role'] ?? '-');
        echo "\n";
    }
}

echo "\n=== PHP Auth: {$passed}/" . count($tests) . " OK ===\n";
exit($passed === count($tests) ? 0 : 1);
