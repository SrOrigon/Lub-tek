<?php
/**
 * Provisiona um novo banco de dados para cliente (tenant)
 *
 * Uso:
 *   php provision_tenant.php cocacola
 *   php provision_tenant.php ambev --admin-pass="SenhaForte123"
 *
 * Cria banco de dados/{empresa}.sqlite com usuários Admin e Funcionario.
 */

require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/db.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Execute via CLI: php provision_tenant.php {empresa}');
}

$slug = TenantResolver::sanitizeSlug($argv[1] ?? '');
if (!$slug) {
    fwrite(STDERR, "Uso: php provision_tenant.php {empresa} [--admin-pass=senha]\n");
    fwrite(STDERR, "Exemplo: php provision_tenant.php cocacola\n");
    exit(1);
}

$adminPass = 'changeme';
foreach ($argv as $arg) {
    if (strpos($arg, '--admin-pass=') === 0) {
        $adminPass = substr($arg, 13);
    }
}

TenantResolver::protectDatabasesDir();
$dbPath = TenantResolver::resolvePath($slug);

if (file_exists($dbPath)) {
    fwrite(STDERR, "[ERRO] Banco já existe: $dbPath\n");
    exit(1);
}

try {
    // Instancia o banco (initDatabase roda automaticamente se arquivo não existir)
    $pdo = DB::getInstance($slug);
    DB::forceMigration($slug);

    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    $email = 'admin@' . $slug . '.local';

    $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel, password_reset_required) VALUES (?, ?, ?, ?, 1)")
        ->execute(['Admin', $email, $hash, 3]);

    $funcHash = password_hash($adminPass, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel, password_reset_required) VALUES (?, ?, ?, ?, 0)")
        ->execute(['Funcionario', 'funcionario@' . $slug . '.local', $funcHash, 1]);

    // Remove admin padrão do initDatabase (não usado em tenants)
    $pdo->exec("DELETE FROM usuarios WHERE email = 'admin@rodrigo.com'");

    echo "[OK] Tenant '$slug' provisionado em: $dbPath\n";
    echo "     Login gestor:      {$slug}.Admin\n";
    echo "     Login funcionário: {$slug}.Funcionario\n";
    echo "     Senha inicial:   $adminPass\n";
    echo "     IMPORTANTE: altere a senha após o primeiro acesso.\n";

} catch (Exception $e) {
    fwrite(STDERR, "[ERRO] " . $e->getMessage() . "\n");
    if (file_exists($dbPath)) {
        @unlink($dbPath);
    }
    exit(1);
}
