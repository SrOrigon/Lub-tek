<?php
/**
 * Multi-tenancy: Database-per-Tenant
 * - Banco padrão (admin/testes): database.sqlite na raiz
 * - Bancos de clientes: banco de dados/{empresa}.sqlite
 * - Login: Empresa.Admin (gestor) | Empresa.Funcionario (trabalhador)
 */

require_once __DIR__ . '/permissions.php';

class TenantResolver
{
    /** Slug reservado — não pode ser usado como tenant de cliente */
    const RESERVED_SLUGS = ['admin', 'default', 'master', 'system', 'www', 'api'];

    /**
     * Interpreta login no formato Empresa.Usuario, Empresa@Usuario ou login simples (modo admin/testes).
     *
     * @return array{tenant: ?string, username: string, raw: string}
     */
    public static function parseLoginUsername(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            return ['tenant' => null, 'username' => '', 'raw' => $input];
        }

        $tryTenant = static function (?string $tenantPart, string $userPart) use ($input): ?array {
            $tenant = $tenantPart !== null ? self::sanitizeSlug($tenantPart) : null;
            $user = trim($userPart);
            if ($tenant !== null && $user !== '' && self::tenantExists($tenant)) {
                return ['tenant' => $tenant, 'username' => $user, 'raw' => $input];
            }
            return null;
        };

        if (strpos($input, '@') !== false) {
            $parts = explode('@', $input, 2);
            $hit = $tryTenant($parts[0], $parts[1] ?? '') ?: $tryTenant($parts[1] ?? '', $parts[0]);
            if ($hit) {
                return $hit;
            }
            // usuario@slug ou gestor@slug (sem TLD público)
            $domain = trim($parts[1] ?? '');
            if ($domain !== '' && strpos($domain, '.') === false) {
                $hit = $tryTenant($domain, $parts[0]);
                if ($hit) {
                    return $hit;
                }
            }
            // admin@slug.local (provisionamento padrão de tenants)
            if (preg_match('/^([a-z0-9-]+)\.local$/i', $domain, $localMatch)) {
                $hit = $tryTenant($localMatch[1], $parts[0]);
                if ($hit) {
                    return $hit;
                }
            }
        }

        if (strpos($input, '.') !== false) {
            $parts = explode('.', $input);
            if (count($parts) >= 2) {
                $first = $parts[0];
                $rest = implode('.', array_slice($parts, 1));
                $last = $parts[count($parts) - 1];
                $head = implode('.', array_slice($parts, 0, -1));

                // Empresa.usuario  OU  usuario.empresa (e-mail gravado no banco)
                $hit = $tryTenant($first, $rest) ?: $tryTenant($last, $head);
                if ($hit) {
                    return $hit;
                }

                // Percorre partes intermediárias para encontrar tenant existente
                foreach ($parts as $idx => $p) {
                    $otherParts = $parts;
                    unset($otherParts[$idx]);
                    $hit = $tryTenant($p, implode('.', $otherParts));
                    if ($hit) {
                        return $hit;
                    }
                }
            }
        }

        return ['tenant' => null, 'username' => $input, 'raw' => $input];
    }

    /**
     * Variantes do mesmo identificador (e-mail com @ vs pontos) para lookup no banco.
     *
     * @return string[]
     */
    public static function identityLookupVariants(string $input): array
    {
        $raw = trim($input);
        if ($raw === '') {
            return [];
        }

        $out = [$raw];
        if (strpos($raw, '@') !== false) {
            $out[] = str_replace('@', '.', $raw);
        } elseif (preg_match('/^([a-z0-9._+-]+)\.([a-z0-9-]+\.[a-z]{2,})$/i', $raw, $m)) {
            $out[] = $m[1] . '@' . $m[2];
        }

        return array_values(array_unique($out));
    }

    public static function sanitizeSlug(string $slug): ?string
    {
        $slug = trim($slug);
        // Regra restrita de fail-fast: apenas letras, números e traços são aceitos
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
            return null;
        }

        $slug = strtolower($slug);

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            return null;
        }

        return $slug;
    }

    private static $forcedTenant = null;
    private static $hasForcedTenant = false;

    /**
     * Força o tenant do request (inclui null = banco master), ignorando a sessão.
     */
    public static function forceTenant(?string $tenant): void
    {
        self::$forcedTenant = $tenant;
        self::$hasForcedTenant = true;
    }

    public static function clearForcedTenant(): void
    {
        self::$forcedTenant = null;
        self::$hasForcedTenant = false;
    }

    public static function getCurrentTenant(): ?string
    {
        if (self::$hasForcedTenant) {
            return self::$forcedTenant;
        }

        if (isset($_SESSION) && is_array($_SESSION)) {
            $tenant = $_SESSION['tenant'] ?? null;
            return ($tenant === null || $tenant === '') ? null : (string) $tenant;
        }

        return null;
    }

    public static function setTenant(?string $tenant): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION['tenant'] = $tenant;
    }

    public static function getTenantLabel(?string $tenant = null): string
    {
        $tenant = $tenant ?? self::getCurrentTenant();
        return $tenant ? ucfirst($tenant) : 'Admin';
    }

    /**
     * Caminho absoluto do arquivo SQLite do tenant.
     */
    public static function resolvePath(?string $tenant): string
    {
        if ($tenant === null) {
            return __DIR__ . '/../database.sqlite';
        }

        // Validação adicional de segurança para mitigar travessia de diretórios (.. ou /)
        $sanitized = self::sanitizeSlug($tenant);
        if ($sanitized === null || $sanitized !== $tenant) {
            throw new Exception("Nome de empresa inválido ou não autorizado.");
        }

        $dir = self::getDatabasesDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            self::protectDatabasesDir($dir);
        }

        return $dir . '/' . $tenant . '.sqlite';
    }

    public static function getDatabasesDir(): string
    {
        $dir = __DIR__ . '/../banco de dados';
        $legacy = __DIR__ . '/../databases';

        // Migração automática da pasta antiga "databases/" → "banco de dados/"
        if (is_dir($legacy)) {
            if (!is_dir($dir)) {
                @rename($legacy, $dir);
            } else {
                foreach (glob($legacy . '/*.sqlite') as $file) {
                    $dest = $dir . DIRECTORY_SEPARATOR . basename($file);
                    if (!file_exists($dest)) {
                        @rename($file, $dest);
                    }
                }
            }
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            self::protectDatabasesDir($dir);
        }

        return $dir;
    }

    /**
     * Aliases de login amigáveis → nomes no banco (usuarios.nome)
     * Ex: Cocacola.Admin, Cocacola.Gestor → Admin
     *     Cocacola.Funcionario, Cocacola.Trabalhador → Funcionario
     *
     * @return string[]
     */
    public static function usernameLookupVariants(string $username): array
    {
        $raw = trim($username);
        $key = strtolower(str_replace(['á', 'é', 'í', 'ó', 'ú', 'ã', 'õ', 'ç'], ['a', 'e', 'i', 'o', 'u', 'a', 'o', 'c'], $raw));

        $gestorKeys = ['admin', 'gestor', 'supervisor', 'dono', 'gerente'];
        $workerKeys = ['funcionario', 'trabalhador', 'operador', 'tecnico', 'mecanico'];

        if (in_array($key, $gestorKeys, true)) {
            return array_values(array_unique(['Admin', 'Gestor', $raw]));
        }
        if (in_array($key, $workerKeys, true)) {
            return array_values(array_unique(['Funcionario', 'Trabalhador', $raw]));
        }

        return [$raw];
    }

    public static function protectDatabasesDir(?string $dir = null): void
    {
        require_once __DIR__ . '/htaccess_guard.php';
        $dir = $dir ?? self::getDatabasesDir();
        HtaccessGuard::writeDenyAll($dir);

        // Protege também pasta legada se existir
        $legacy = __DIR__ . '/../databases';
        if (is_dir($legacy)) {
            HtaccessGuard::writeDenyAll($legacy);
        }
    }

    /**
     * Lista slugs de todos os bancos de clientes (não inclui o admin padrão).
     *
     * @return string[]
     */
    public static function listTenantSlugs(): array
    {
        $dir = self::getDatabasesDir();
        if (!is_dir($dir)) {
            return [];
        }

        $slugs = [];
        foreach (glob($dir . '/*.sqlite') as $file) {
            $slug = basename($file, '.sqlite');
            if (self::sanitizeSlug($slug)) {
                $slugs[] = $slug;
            }
        }

        sort($slugs);
        return $slugs;
    }

    public static function tenantExists(?string $tenant): bool
    {
        if ($tenant === null) {
            return file_exists(__DIR__ . '/../database.sqlite');
        }

        return file_exists(self::resolvePath($tenant));
    }

    /**
     * 4 = cliente (somente leitura) | >=2 (exceto 4) = gestor | 1 = trabalhador
     */
    public static function nivelToRole(int $nivel): string
    {
        if ($nivel === 4) {
            return Permissions::ROLE_CLIENTE;
        }
        if ($nivel >= 2) {
            return Permissions::ROLE_GESTOR;
        }
        return Permissions::ROLE_TRABALHADOR;
    }

    /**
     * Permissões por role — delegado ao Permissions central.
     */
    public static function defaultPermissionsForRole(string $role): array
    {
        return Permissions::forRole($role);
    }
}
