<?php
/**
 * RBAC — níveis de acesso LUB-TEK
 *
 * developer  → Admin global (todos os bancos, tudo no sistema)
 * gestor     → Dono/supervisor da empresa (acesso total ao banco do tenant)
 * trabalhador → Operador de campo (checklist OS + rotas)
 * cliente    → Visualização do cliente (sem criar, editar ou excluir)
 */

class Permissions
{
    public const ROLE_DEVELOPER = 'developer';
    public const ROLE_GESTOR = 'gestor';
    public const ROLE_TRABALHADOR = 'trabalhador';
    public const ROLE_CLIENTE = 'cliente';

    /** Roles legados mapeados para gestor */
    private const GESTOR_ALIASES = ['developer', 'admin', 'administrador', 'gestor', 'supervisor'];

    /** Roles legados mapeados para trabalhador (não incluir "cliente") */
    private const TRABALHADOR_ALIASES = ['trabalhador', 'client', 'user', 'funcionario'];

    /** Conta de cliente: somente leitura */
    private const CLIENTE_ALIASES = ['cliente', 'viewer', 'leitura', 'readonly', 'read_only', 'cliente_viewer'];

    public static function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        if ($role === self::ROLE_DEVELOPER) {
            return self::ROLE_DEVELOPER;
        }
        if (in_array($role, self::CLIENTE_ALIASES, true)) {
            return self::ROLE_CLIENTE;
        }
        if (in_array($role, self::GESTOR_ALIASES, true)) {
            return $role === 'developer' ? self::ROLE_DEVELOPER : self::ROLE_GESTOR;
        }
        if (in_array($role, self::TRABALHADOR_ALIASES, true)) {
            return self::ROLE_TRABALHADOR;
        }
        return self::ROLE_TRABALHADOR;
    }

    public static function isDeveloper(?string $role): bool
    {
        return strtolower(trim($role ?? '')) === self::ROLE_DEVELOPER;
    }

    public static function isGestor(?string $role): bool
    {
        $role = strtolower(trim($role ?? ''));
        // Reutiliza a constante central (evita drift caso GESTOR_ALIASES seja
        // atualizada e este método continue com uma lista literal desatualizada).
        return in_array($role, self::GESTOR_ALIASES, true);
    }

    public static function isCliente(?string $role): bool
    {
        return in_array(strtolower(trim($role ?? '')), self::CLIENTE_ALIASES, true);
    }

    public static function isTrabalhador(?string $role): bool
    {
        if (self::isGatewayRole($role) || self::isCliente($role)) {
            return false;
        }
        return self::normalizeRole($role ?? '') === self::ROLE_TRABALHADOR
            && !self::isDeveloper($role);
    }

    public static function roleLabel(?string $role): string
    {
        $norm = self::normalizeRole($role ?? '');
        return match ($norm) {
            self::ROLE_DEVELOPER => 'Administrador Global',
            self::ROLE_GESTOR => 'Gestor da Empresa',
            self::ROLE_CLIENTE => 'Cliente (somente visualização)',
            self::ROLE_TRABALHADOR => 'Trabalhador',
            default => 'Usuário',
        };
    }

    /**
     * Matriz de permissões por role normalizado.
     */
    public static function forRole(string $role): array
    {
        if (self::isDeveloper($role)) {
            return ['all' => true];
        }

        if (self::isCliente($role)) {
            return [
                'all' => false,
                'dashboard' => true,
                'assets' => 'read_only',
                'catalog' => true,
                'inventory' => 'read_only',
                'engineering' => true,
                'reports' => true,
                'kpi' => true,
                'routes' => false,
                'pi' => false,
                'sap' => false,
                'digital_twin' => true,
                'marketplace' => false,
                'neural' => true,
                'orders_view' => true,
                'orders_create' => false,
                'orders_edit' => false,
                'orders_delete' => false,
                'orders_execute' => false,
                'create_assets' => false,
                'edit_assets' => false,
                'delete_assets' => false,
                'manage_users' => false,
                'system_settings' => false,
                'cross_tenant' => false,
                'audit_logs' => false,
            ];
        }

        if (self::isGestor($role)) {
            return [
                'all' => false,
                'dashboard' => true,
                'assets' => true,
                'catalog' => true,
                'inventory' => true,
                'engineering' => true,
                'reports' => true,
                'kpi' => true,
                'routes' => true,
                'pi' => true,
                'sap' => true,
                'digital_twin' => true,
                'marketplace' => true,
                'neural' => true,
                'orders_view' => true,
                'orders_create' => true,
                'orders_edit' => true,
                'orders_delete' => true,
                'orders_execute' => true,
                'create_assets' => true,
                'edit_assets' => true,
                'delete_assets' => true,
                'manage_users' => true,
                'system_settings' => false,
                'cross_tenant' => false,
                'audit_logs' => true,
            ];
        }

        // Trabalhador — checklist operacional
        return [
            'all' => false,
            'dashboard' => true,
            'assets' => 'read_only',
            'catalog' => false,
            'inventory' => false,
            'engineering' => false,
            'reports' => false,
            'kpi' => false,
            'routes' => true,
            'pi' => false,
            'sap' => false,
            'digital_twin' => false,
            'marketplace' => false,
            'neural' => false,
            'orders_view' => true,
            'orders_create' => false,
            'orders_edit' => false,
            'orders_delete' => false,
            'orders_execute' => true,
            'create_assets' => false,
            'edit_assets' => false,
            'delete_assets' => false,
            'manage_users' => false,
            'system_settings' => false,
            'cross_tenant' => false,
            'audit_logs' => false,
        ];
    }

    public static function can(array $permissions, string $key): bool
    {
        if (!empty($permissions['all'])) {
            return true;
        }
        if (!isset($permissions[$key])) {
            return false;
        }
        $val = $permissions[$key];
        return $val === true || $val === 'read_only' || $val === 'basic';
    }

    public static function canWrite(array $permissions, string $key): bool
    {
        if (!empty($permissions['all'])) {
            return true;
        }
        return ($permissions[$key] ?? false) === true;
    }

    /** Páginas SPA permitidas por role */
    public static function allowedPages(string $role): array
    {
        if (self::isDeveloper($role) || self::isGestor($role)) {
            return ['home', 'dash', 'assets', 'catalog', 'calc', 'reports', 'kpi', 'routes', 'pi', 'sap', '3d', 'audit', 'users'];
        }
        if (self::isCliente($role)) {
            return ['home', 'dash', 'assets', 'catalog', 'calc', 'reports', 'kpi', '3d'];
        }
        return ['home', 'dash', 'assets', 'routes'];
    }

    public static function canAccessPage(string $role, string $page): bool
    {
        return in_array($page, self::allowedPages($role), true);
    }

    public static function clienteApiWhitelist(): array
    {
        return [
            'login', 'logout', 'change_password',
            'get_tree', 'get_catalog', 'get_tasks', 'get_asset_timeline', 'get_thickener',
            'get_kpis', 'get_stats', 'get_dash_stats', 'get_sync_revision',
            'get_plans', 'get_lubricant_consumption',
            'suggest_lubrication', 'calc_bearing', 'calc_dn', 'calc_viscosity', 'calc_kappa', 'calc_bushing',
            'calc_filtering', 'search_engineering_catalog',
            'get_analysis_history', 'generate_checklist_report',
            'get_lubrication_plan_meta', 'get_lubrication_plan_chunk', 'render_lubrication_plan_cover',
            'ask_neural', 'get_neural_context', 'neural_predict', 'get_asset_reliability',
        ];
    }
    public static function trabalhadorApiWhitelist(): array
    {
        return [
            'login', 'logout',
            'get_tree', 'get_catalog', 'get_tasks', 'get_asset_timeline', 'get_thickener',
            'save_task', 'suggest_lubrication', 'calc_bearing', 'calc_dn', 'calc_viscosity', 'calc_kappa', 'calc_bushing',
            'calc_filtering', 'search_engineering_catalog',
            'get_analysis_history', 'generate_checklist_report', 'ask_neural', 'get_neural_context',
            'get_lubrication_plan_meta', 'get_lubrication_plan_chunk', 'render_lubrication_plan_cover',
            'get_lubricant_consumption',
            'neural_predict', 'get_asset_reliability', 'get_sync_revision',
            'set_asset_status', 'report_route_alert', 'change_password', 'upload_image', 'update_asset_image', 'get_stats', 'get_dash_stats',
            'import_analysis_csv', 'save_analysis',
        ];
    }

    /** Gateway IoT / Power BI — privilégio mínimo (nunca developer) */
    public static function gatewayApiWhitelist(): array
    {
        return [
            'receive_pi_telemetry', 'pi_ai_diagnose', 'save_asset', 'save_order', 'update_order_status', 'save_task',
            'powerbi_kpis', 'powerbi_orders', 'powerbi_telemetry', 'powerbi_assets', 'powerbi_audit',
            'powerbi', 'powerbi_all',
        ];
    }

    public static function isGatewayRole(?string $role): bool
    {
        $role = strtolower(trim($role ?? ''));
        return $role === 'iot_gateway' || $role === 'powerbi_reader';
    }

    public static function canAccessApiAction(string $role, string $action): bool
    {
        $role = strtolower(trim($role));

        if (self::isGatewayRole($role)) {
            return in_array($action, self::gatewayApiWhitelist(), true);
        }

        if (self::isDeveloper($role)) {
            return true;
        }

        if (self::isGestor($role)) {
            if (in_array($action, ['wipe_db', 'cleanup_sem_nome'], true)) {
                return false;
            }
            return true;
        }

        if (self::isCliente($role)) {
            return in_array($action, self::clienteApiWhitelist(), true);
        }

        return in_array($action, self::trabalhadorApiWhitelist(), true);
    }

    public static function defaultHomePage(string $role): string
    {
        if (self::isTrabalhador($role)) {
            return 'routes';
        }
        return 'home';
    }

    /**
     * Modal bloqueante de 1ª senha: apenas gestor (dono) logado em tenant.
     * Admin global (sem tenant) e trabalhadores nunca são bloqueados.
     */
    public static function requiresPasswordResetLock(?array $user): bool
    {
        if (!defined('PASSWORD_RESET_LOCK_ENABLED') || !PASSWORD_RESET_LOCK_ENABLED) {
            return false;
        }
        if (empty($user) || empty($user['password_reset_required'])) {
            return false;
        }
        if (empty($user['tenant'])) {
            return false;
        }

        return self::normalizeRole($user['role'] ?? '') === self::ROLE_GESTOR;
    }
}
