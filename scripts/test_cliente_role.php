<?php
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/tenant.php';

$fail = 0;
function expect($c, $m) {
    global $fail;
    if ($c) {
        echo "PASS  $m\n";
        return;
    }
    $fail++;
    echo "FAIL  $m\n";
}

expect(Permissions::isCliente('cliente'), 'isCliente');
expect(!Permissions::isTrabalhador('cliente'), 'cliente não é trabalhador');
expect(!Permissions::isGestor('cliente'), 'cliente não é gestor');
expect(Permissions::normalizeRole('viewer') === Permissions::ROLE_CLIENTE, 'alias viewer');
expect(Permissions::roleLabel('cliente') === 'Cliente (somente visualização)', 'label');
expect(in_array('kpi', Permissions::allowedPages('cliente'), true), 'vê KPI');
expect(!in_array('users', Permissions::allowedPages('cliente'), true), 'não vê Equipe');
expect(!in_array('routes', Permissions::allowedPages('cliente'), true), 'não vê rotas de execução');
expect(Permissions::canAccessApiAction('cliente', 'get_kpis'), 'GET kpis');
expect(!Permissions::canAccessApiAction('cliente', 'save_asset'), 'bloqueia save_asset');
expect(!Permissions::canAccessApiAction('cliente', 'save_task'), 'bloqueia save_task');
expect(!Permissions::canAccessApiAction('cliente', 'delete_user'), 'bloqueia delete_user');
expect(!Permissions::canAccessApiAction('cliente', 'set_asset_status'), 'bloqueia status');
expect(Permissions::canAccessApiAction('trabalhador', 'save_task'), 'trabalhador ainda executa OS');
expect(TenantResolver::nivelToRole(4) === Permissions::ROLE_CLIENTE, 'nivel 4');
expect(TenantResolver::nivelToRole(3) === Permissions::ROLE_GESTOR, 'nivel 3 gestor');
expect(TenantResolver::nivelToRole(1) === Permissions::ROLE_TRABALHADOR, 'nivel 1');
$p = Permissions::forRole('cliente');
expect($p['orders_edit'] === false && $p['orders_view'] === true, 'vê OS sem editar');
expect(Permissions::canWrite($p, 'edit_assets') === false, 'canWrite assets false');
expect(Permissions::can($p, 'assets') === true, 'can read assets');

echo $fail === 0 ? "\nOK\n" : "\nFALHAS: $fail\n";
exit($fail === 0 ? 0 : 1);
