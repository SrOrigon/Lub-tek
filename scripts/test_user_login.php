<?php
/**
 * Testes de parse/lookup de login (Equipe e Acessos).
 */
require_once __DIR__ . '/../includes/tenant.php';

$fail = 0;
function expect($cond, $msg) {
    global $fail;
    if ($cond) {
        echo "PASS  $msg\n";
        return;
    }
    $fail++;
    echo "FAIL  $msg\n";
}

$v = TenantResolver::identityLookupVariants('admin@rodrigo.com');
expect(in_array('admin@rodrigo.com', $v, true), 'variante original com @');
expect(in_array('admin.rodrigo.com', $v, true), '@ vira ponto');

$v2 = TenantResolver::identityLookupVariants('admin.rodrigo.com');
expect(in_array('admin@rodrigo.com', $v2, true), 'e-mail com TLD aceita @');

$parsed = TenantResolver::parseLoginUsername('admin@rodrigo.com');
expect($parsed['tenant'] === null, 'admin@rodrigo.com não é tenant (admin reservado)');
expect($parsed['username'] === 'admin@rodrigo.com', 'username admin permanece o e-mail');

require_once __DIR__ . '/../includes/auth.php';
$auth = new AuthSystem();
$_SESSION = [];
$r = $auth->login('Admin', 'Admin');
expect(!empty($r['success']), 'login Admin por nome funciona após sync');

$parsedCorp = TenantResolver::parseLoginUsername('john@microsoft.com');
expect($parsedCorp['tenant'] === null, 'e-mail corporativo com TLD não vira tenant automático');

$parsedTenantEmail = TenantResolver::parseLoginUsername('fabio@cocacola');
if (TenantResolver::tenantExists('cocacola')) {
    expect($parsedTenantEmail['tenant'] === 'cocacola', 'usuario@slug sem TLD resolve tenant');
}

$hash = password_hash('SenhaForte!1', PASSWORD_DEFAULT);
expect(password_verify('SenhaForte!1', $hash), 'hash de senha inicial verifica');

require_once __DIR__ . '/../includes/auth.php';
$bcrypt = AuthSystem::hashPassword('SenhaForte!1');
expect(AuthSystem::verifyPassword('SenhaForte!1', $bcrypt), 'bcrypt verifica');
expect(AuthSystem::verifyPassword('  SenhaForte!1  ', $bcrypt), 'senha com espaços nas pontas verifica');
expect(AuthSystem::verifyPassword('SenhaForte!1', '  ' . $bcrypt . '  '), 'hash com espaços verifica');
expect(AuthSystem::verifyPassword('SenhaForte!1', 'SenhaForte!1'), 'legado plaintext verifica');
expect(!AuthSystem::verifyPassword('errada', $bcrypt), 'senha errada é rejeitada');
$md5 = md5('abc123');
expect(AuthSystem::verifyPassword('abc123', $md5), 'legado md5 verifica');
expect(AuthSystem::shouldRehashPassword('SenhaForte!1'), 'plaintext pede rehash');
expect(AuthSystem::shouldRehashPassword($md5), 'md5 pede rehash');

echo $fail === 0 ? "\nOK\n" : "\nFALHAS: $fail\n";
exit($fail === 0 ? 0 : 1);
