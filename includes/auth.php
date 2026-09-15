<?php
/**
 * AUTHENTICATION SYSTEM
 * Manages login, logout, permissions and sessions
 * Suporta multi-tenant: Empresa.Usuario → banco de dados/empresa.sqlite
 */

require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/security_guard.php';

class AuthSystem
{
    private $usersFile = __DIR__ . '/../data/users.json';
    private $sessionTimeout = 7200; // 2 hours

    public function __construct()
    {
        $this->initSession();

        // Verifica timeout de sessao (2 horas de inatividade)
        if ($this->isLoggedIn() && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $this->sessionTimeout) {
                $this->logout();
            }
        }

        // Atualiza timestamp de atividade
        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Session bootstrap (Hostinger / HTTPS behind proxy)
     */
    private function initSession()
    {
        SessionBootstrap::ensure();
    }

    /**
     * Load users from JSON file
     */
    private function loadUsers()
    {
        if (!file_exists($this->usersFile)) {
            return ['users' => [], 'roles' => [], 'plans' => []];
        }

        $json = file_get_contents($this->usersFile);
        return json_decode($json, true);
    }

    /**
     * Save users to JSON file
     */
    private function saveUsers($data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->usersFile, $json) !== false;
    }

    /**
     * Chaves de autenticação persistidas na sessão PHP.
     */
    private function authSessionKeys(): array
    {
        return [
            'logged_in', 'user_id', 'username', 'name', 'role', 'role_label',
            'plan', 'permissions', 'limits', 'tenant', 'tenant_label',
            'password_reset_required', 'login_time', 'last_activity',
        ];
    }

    private function snapshotAuthSession(): array
    {
        $snapshot = [];
        foreach ($this->authSessionKeys() as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $snapshot[$key] = $_SESSION[$key];
            }
        }
        return $snapshot;
    }

    private function restoreAuthSession(array $snapshot): void
    {
        foreach ($this->authSessionKeys() as $key) {
            unset($_SESSION[$key]);
        }
        foreach ($snapshot as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    private function enrichLoginResult(array $result): array
    {
        if (!empty($result['success']) && !empty($result['user']['role'])) {
            $result['home_page'] = Permissions::defaultHomePage($result['user']['role']);
        }
        return $result;
    }

    /**
     * Authenticate user com Proteção Brute-Force & Anti-Session Fixation
     */
    public function login($username, $password)
    {
        // Preserva sessão ativa se as credenciais estiverem erradas (evita deslogar quem já está logado).
        $snapshot = $this->snapshotAuthSession();

        // 1) Separa Empresa.Usuario antes de qualquer conexão SQLite
        $parsed = TenantResolver::parseLoginUsername($username);

        if ($parsed['tenant'] !== null) {
            $result = $this->loginTenantUser($parsed['tenant'], $parsed['username'], $password, $parsed['raw']);
        } else {
            $uname = (string) $parsed['username'];
            if ($uname !== '' && strpos($uname, '@') !== false && preg_match('/^[^@]+@[^@]+\.[^@]+$/', $uname)) {
                $result = $this->loginEmailAcrossTenants($uname, $password, $parsed['raw']);
                if (!$result['success']) {
                    $result = $this->loginDefaultUser($uname, $password);
                }
            } else {
                $result = $this->loginDefaultUser($uname, $password);
            }
        }

        if (!$result['success']) {
            $this->restoreAuthSession($snapshot);
            SecurityGuard::recordFailedAttempt($username);
            return $result;
        }

        SecurityGuard::resetFailedAttempts($username);
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        return $this->enrichLoginResult($result);
    }

    /**
     * Login admin/testes — users.json + database.sqlite (comportamento original)
     */
    private function loginDefaultUser($username, $password)
    {
        $data = ['users' => []];
        if (file_exists($this->usersFile)) {
            $loaded = $this->loadUsers();
            if (is_array($loaded) && !empty($loaded['users']) && is_array($loaded['users'])) {
                $data = $loaded;
            }
        }

        foreach ($data['users'] as $idx => $user) {
            $storedUser = (string) ($user['username'] ?? '');
            $storedEmail = (string) ($user['email'] ?? '');
            $storedName = (string) ($user['name'] ?? '');
            if (!$this->identityMatches($username, $storedUser, $storedEmail, $storedName)) {
                continue;
            }
            if (($user['status'] ?? 'active') !== 'active') {
                continue;
            }
            if (empty($user['password']) || !self::verifyPassword($password, (string) $user['password'])) {
                continue;
            }

            if (self::shouldRehashPassword((string) $user['password'])) {
                $data['users'][$idx]['password'] = self::hashPassword($password);
                $this->saveUsers($data);
                $user = $data['users'][$idx];
            }

            $_SESSION['tenant'] = null;
            $_SESSION['tenant_label'] = 'Admin';
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['role_label'] = Permissions::roleLabel($user['role']);
            $_SESSION['plan'] = $user['plan'];
            $_SESSION['permissions'] = Permissions::isDeveloper($user['role']) && !empty($user['permissions'])
                ? $user['permissions']
                : Permissions::forRole($user['role']);
            $_SESSION['limits'] = $user['limits'];
            $_SESSION['password_reset_required'] = false;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            $this->updateLastLogin($user['id']);

            return [
                'success' => true,
                'tenant' => null,
                'tenant_label' => 'Admin',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'plan' => $user['plan']
                ]
            ];
        }

        return $this->loginMasterSqliteUser($username, $password);
    }

    private function identityMatches(string $input, string $storedUser, string $storedEmail, string $storedName = ''): bool
    {
        $input = trim($input);
        if ($input === '') {
            return false;
        }

        if ($storedName !== '' && strcasecmp($input, trim($storedName)) === 0) {
            return true;
        }

        foreach (TenantResolver::usernameLookupVariants($input) as $alias) {
            if ($storedName !== '' && strcasecmp($alias, trim($storedName)) === 0) {
                return true;
            }
        }

        $needles = TenantResolver::identityLookupVariants($input);
        $hay = array_merge(
            $storedUser !== '' ? TenantResolver::identityLookupVariants($storedUser) : [],
            $storedEmail !== '' ? TenantResolver::identityLookupVariants($storedEmail) : []
        );
        foreach ($needles as $n) {
            foreach ($hay as $h) {
                if (strcasecmp($n, $h) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Variantes da senha digitada (espaços, BOM, quebra de linha) para evitar falso "senha errada".
     *
     * @return string[]
     */
    public static function passwordCandidates(string $password): array
    {
        $out = [];
        $add = static function (string $p) use (&$out): void {
            if ($p !== '' && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        };

        $add($password);
        $add(str_replace(["\r\n", "\r"], "\n", $password));
        $add(trim($password));
        $add(trim(str_replace(["\r\n", "\r"], "\n", $password)));

        if (strncmp($password, "\xEF\xBB\xBF", 3) === 0) {
            $add(substr($password, 3));
            $add(trim(substr($password, 3)));
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($password, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) {
                $add(mb_convert_encoding($password, 'UTF-8', 'Windows-1252'));
                $add(mb_convert_encoding($password, 'UTF-8', 'ISO-8859-1'));
            }
        }

        return $out;
    }

    public static function normalizeStoredPassword(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (
            (str_starts_with($stored, '"') && str_ends_with($stored, '"')) ||
            (str_starts_with($stored, "'") && str_ends_with($stored, "'"))
        ) {
            $stored = substr($stored, 1, -1);
        }
        return trim($stored);
    }

    public static function isPasswordHash(string $stored): bool
    {
        $info = password_get_info($stored);
        return !empty($info['algo']);
    }

    /**
     * Aceita bcrypt/argon atuais e hashes legados (texto puro, MD5, SHA1) usados por contas antigas.
     */
    public static function verifyPassword(string $plain, string $stored): bool
    {
        $stored = self::normalizeStoredPassword($stored);
        if ($stored === '') {
            return false;
        }

        $isHash = self::isPasswordHash($stored);

        foreach (self::passwordCandidates($plain) as $candidate) {
            if ($isHash) {
                if (password_verify($candidate, $stored)) {
                    return true;
                }
                continue;
            }

            if (hash_equals($stored, $candidate)) {
                return true;
            }
            if (preg_match('/^[a-f0-9]{32}$/i', $stored) && hash_equals(strtolower($stored), md5($candidate))) {
                return true;
            }
            if (preg_match('/^[a-f0-9]{40}$/i', $stored) && hash_equals(strtolower($stored), sha1($candidate))) {
                return true;
            }
        }

        return false;
    }

    public static function shouldRehashPassword(string $stored): bool
    {
        $stored = self::normalizeStoredPassword($stored);
        if ($stored === '' || !self::isPasswordHash($stored)) {
            return true;
        }
        return password_needs_rehash($stored, PASSWORD_DEFAULT);
    }

    public static function hashPassword(string $plain): string
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $plain));
        if ($normalized === '') {
            $normalized = $plain;
        }
        return password_hash($normalized, PASSWORD_DEFAULT);
    }

    /**
     * Entre várias contas que batem o login (aliases), usa a que a senha realmente confere.
     */
    private function firstRowMatchingPassword(array $rows, string $password): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $hash = (string) ($row['senha'] ?? '');
            if ($hash !== '' && self::verifyPassword($password, $hash)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Colaboradores criados em Equipe (banco master), se não estiverem no users.json.
     */
    private function loginMasterSqliteUser(string $username, string $password): array
    {
        try {
            require_once __DIR__ . '/../db.php';
            $pdo = DB::getMaster();
            $variants = TenantResolver::identityLookupVariants($username);
            if ($variants === []) {
                $variants = [$username];
            }
            $conditions = [];
            $params = [];
            foreach ($variants as $v) {
                $conditions[] = 'LOWER(email) = LOWER(?)';
                $params[] = $v;
                $conditions[] = 'LOWER(nome) = LOWER(?)';
                $params[] = $v;
            }
            $stmt = $pdo->prepare(
                'SELECT * FROM usuarios WHERE (' . implode(' OR ', $conditions) . ')'
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $row = $this->firstRowMatchingPassword($rows, $password);

            if ($row) {
                if (self::shouldRehashPassword((string) $row['senha'])) {
                    try {
                        $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')
                            ->execute([self::hashPassword($password), $row['id']]);
                    } catch (Exception $e) {
                        error_log('loginMasterSqliteUser rehash: ' . $e->getMessage());
                    }
                }
                $role = TenantResolver::nivelToRole((int) ($row['nivel'] ?? 1));
                $_SESSION['tenant'] = null;
                $_SESSION['tenant_label'] = 'Admin';
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['email'] ?? $username;
                $_SESSION['name'] = $row['nome'];
                $_SESSION['role'] = $role;
                $_SESSION['role_label'] = Permissions::roleLabel($role);
                $_SESSION['plan'] = 'unlimited';
                $_SESSION['permissions'] = Permissions::forRole($role);
                $_SESSION['limits'] = [
                    'max_machines' => 999999,
                    'max_points' => 999999,
                    'max_users' => 999999,
                ];
                $_SESSION['password_reset_required'] = false;
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();

                return [
                    'success' => true,
                    'tenant' => null,
                    'tenant_label' => 'Admin',
                    'user' => [
                        'id' => $row['id'],
                        'name' => $row['nome'],
                        'role' => $role,
                        'plan' => 'unlimited'
                    ]
                ];
            }
        } catch (Exception $e) {
            error_log('loginMasterSqliteUser: ' . $e->getMessage());
        }

        return ['success' => false, 'message' => 'Usuário ou senha inválidos'];
    }

    /**
     * E-mail com TLD (ex.: user@gmail.com) gravado no SQLite do tenant — busca em todas as empresas.
     */
    private function loginEmailAcrossTenants(string $email, string $password, string $rawLogin): array
    {
        require_once __DIR__ . '/../db.php';
        foreach (TenantResolver::listTenantSlugs() as $tenant) {
            try {
                $pdo = DB::getInstance($tenant);
            } catch (Exception $e) {
                continue;
            }
            $variants = TenantResolver::identityLookupVariants($email);
            $conditions = [];
            $params = [];
            foreach ($variants as $v) {
                $conditions[] = 'LOWER(email) = LOWER(?)';
                $params[] = $v;
            }
            if ($conditions === []) {
                continue;
            }
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE (' . implode(' OR ', $conditions) . ')');
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $row = $this->firstRowMatchingPassword($rows, $password);
            if (!$row) {
                continue;
            }

            if (self::shouldRehashPassword((string) $row['senha'])) {
                try {
                    $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')
                        ->execute([self::hashPassword($password), $row['id']]);
                } catch (Exception $e) {
                    error_log('loginEmailAcrossTenants rehash: ' . $e->getMessage());
                }
            }

            $role = TenantResolver::nivelToRole((int) ($row['nivel'] ?? 1));
            $_SESSION['tenant'] = $tenant;
            $_SESSION['tenant_label'] = TenantResolver::getTenantLabel($tenant);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $rawLogin !== '' ? $rawLogin : $email;
            $_SESSION['name'] = $row['nome'];
            $_SESSION['role'] = $role;
            $_SESSION['role_label'] = Permissions::roleLabel($role);
            $_SESSION['plan'] = 'enterprise';
            $_SESSION['permissions'] = Permissions::forRole($role);
            $_SESSION['limits'] = [
                'max_machines' => 999999,
                'max_points' => 999999,
                'max_users' => 999999,
            ];
            $_SESSION['password_reset_required'] = !empty($row['password_reset_required'])
                && $role === Permissions::ROLE_GESTOR;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            return [
                'success' => true,
                'tenant' => $tenant,
                'tenant_label' => TenantResolver::getTenantLabel($tenant),
                'user' => [
                    'id' => $row['id'],
                    'name' => $row['nome'],
                    'role' => $role,
                    'plan' => 'enterprise',
                ],
            ];
        }

        return ['success' => false, 'message' => 'Usuário ou senha inválidos.'];
    }

    /**
     * Login de cliente — autentica no SQLite exclusivo da empresa
     */
    private function loginTenantUser($tenant, $username, $password, $rawLogin)
    {
        if (!TenantResolver::tenantExists($tenant)) {
            // Mensagem genérica (igual à de senha inválida) para não permitir enumeração de tenants existentes.
            return [
                'success' => false,
                'message' => 'Usuário ou senha inválidos para esta empresa.'
            ];
        }

        // Conecta ao SQLite da empresa sem alterar a sessão antes da senha validar.
        require_once __DIR__ . '/../db.php';

        try {
            // 3) Conecta apenas ao SQLite da empresa (nunca ao master com login composto)
            $pdo = DB::getInstance($tenant);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao conectar ao banco da empresa.'];
        }

        $variants = array_values(array_unique(array_merge(
            TenantResolver::usernameLookupVariants($username),
            TenantResolver::identityLookupVariants($username)
        )));
        $conditions = [];
        $params = [];

        // Escapa curingas de LIKE (% e _) para que o valor do usuário nunca seja interpretado
        // como padrão SQL (evita autenticar contra a conta "errada" via curinga).
        $likeEscape = static function (string $value): string {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        };

        foreach ($variants as $v) {
            $conditions[] = 'LOWER(nome) = LOWER(?)';
            $params[] = $v;
            $conditions[] = 'LOWER(email) = LOWER(?)';
            $params[] = $v;
            $conditions[] = "LOWER(email) LIKE LOWER(?) ESCAPE '\\'";
            $params[] = $likeEscape($v) . '@%';
        }

        // Login multi-tenant: fabio → fabio.cocacola (email no banco local)
        $usernameClean = strtolower(trim($username));
        if ($usernameClean !== '') {
            $conditions[] = 'LOWER(email) = LOWER(?)';
            $params[] = $usernameClean . '.' . strtolower($tenant);
            $conditions[] = 'LOWER(email) = LOWER(?)';
            $params[] = $usernameClean . '@' . strtolower($tenant);
            if (strpos($usernameClean, '@') !== false) {
                $conditions[] = 'LOWER(email) = LOWER(?)';
                $params[] = str_replace('@', '.', $usernameClean) . '.' . strtolower($tenant);
            }
        }

        $sql = 'SELECT * FROM usuarios WHERE (' . implode(' OR ', $conditions) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $row = $this->firstRowMatchingPassword($rows, $password);

        if (!$row) {
            return ['success' => false, 'message' => 'Usuário ou senha inválidos para esta empresa.'];
        }

        if (self::shouldRehashPassword((string) $row['senha'])) {
            try {
                $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')
                    ->execute([self::hashPassword($password), $row['id']]);
            } catch (Exception $e) {
                error_log('loginTenantUser rehash: ' . $e->getMessage());
            }
        }

        $role = TenantResolver::nivelToRole((int) ($row['nivel'] ?? 1));

        $_SESSION['tenant'] = $tenant;
        $_SESSION['tenant_label'] = TenantResolver::getTenantLabel($tenant);
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $rawLogin;
        $_SESSION['name'] = $row['nome'];
        $_SESSION['role'] = $role;
        $_SESSION['role_label'] = Permissions::roleLabel($role);
        $_SESSION['plan'] = 'enterprise';
        $_SESSION['permissions'] = Permissions::forRole($role);
        $_SESSION['limits'] = [
            'max_machines' => 999999,
            'max_points' => 999999,
            'max_users' => 999999,
        ];
        $_SESSION['password_reset_required'] = !empty($row['password_reset_required'])
            && $role === Permissions::ROLE_GESTOR;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        return [
            'success' => true,
            'tenant' => $tenant,
            'tenant_label' => TenantResolver::getTenantLabel($tenant),
            'user' => [
                'id' => $row['id'],
                'name' => $row['nome'],
                'role' => $role,
                'plan' => 'enterprise'
            ]
        ];
    }
    /**
     * Logout user
     */
    public function logout()
    {
        $_SESSION = array();

        if (isset($_COOKIE[session_name()])) {
            $trustProxy = (defined('TRUSTED_PROXY') && TRUSTED_PROXY) || (getenv('TRUSTED_PROXY') === '1');
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
                || ($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        return true;
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $user = [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'name' => $_SESSION['name'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'role_label' => $_SESSION['role_label'] ?? Permissions::roleLabel($_SESSION['role'] ?? ''),
            'plan' => $_SESSION['plan'] ?? null,
            'tenant' => $_SESSION['tenant'] ?? null,
            'tenant_label' => $_SESSION['tenant_label'] ?? 'Admin',
            'password_reset_required' => !empty($_SESSION['password_reset_required']),
            'permissions' => $_SESSION['permissions'] ?? [],
            'limits' => $_SESSION['limits'] ?? []
        ];

        $user['password_reset_required'] = Permissions::requiresPasswordResetLock($user);
        $user['home_page'] = Permissions::defaultHomePage($user['role'] ?? '');

        return $user;
    }

    /**
     * Check if user has permission
     */
    public function hasPermission($permission)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $permissions = $_SESSION['permissions'] ?? Permissions::forRole($_SESSION['role'] ?? '');

        return Permissions::can($permissions, $permission);
    }

    public function canWrite($permission)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $permissions = $_SESSION['permissions'] ?? Permissions::forRole($_SESSION['role'] ?? '');

        return Permissions::canWrite($permissions, $permission);
    }

    public function canAccessPage($page)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        return Permissions::canAccessPage($_SESSION['role'] ?? '', $page);
    }

    /**
     * Check if user has role
     */
    public function hasRole($role)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        return $_SESSION['role'] === $role;
    }

    /**
     * Check if user has minimum role level
     */
    public function hasMinRole($minRole)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $data = $this->loadUsers();
        $roles = $data['roles'] ?? [];

        $currentLevel = $roles[$_SESSION['role']]['level'] ?? 0;
        $minLevel = $roles[$minRole]['level'] ?? 100;

        return $currentLevel >= $minLevel;
    }

    /**
     * Get permission value (for read_only, basic, etc)
     */
    public function getPermission($permission)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $permissions = $_SESSION['permissions'] ?? [];

        // Developer has all
        if (isset($permissions['all']) && $permissions['all'] === true) {
            return true;
        }

        return $permissions[$permission] ?? false;
    }

    /**
     * Check resource limits
     */
    public function checkLimit($resource, $current)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $limits = $_SESSION['limits'] ?? [];
        $max = $limits['max_' . $resource] ?? 0;

        // Unlimited
        if ($max === 999999 || $max === null) {
            return true;
        }

        return $current < $max;
    }

    /**
     * Get user limits
     */
    public function getLimits()
    {
        if (!$this->isLoggedIn()) {
            return [];
        }

        return $_SESSION['limits'] ?? [];
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin($userId)
    {
        $data = $this->loadUsers();

        foreach ($data['users'] as &$user) {
            if ($user['id'] === $userId) {
                $user['last_login'] = date('Y-m-d H:i:s');
                break;
            }
        }

        $this->saveUsers($data);
    }

    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Require permission (redirect if no permission)
     */
    public function requirePermission($permission)
    {
        $this->requireLogin();

        if (!$this->hasPermission($permission)) {
            header('Location: index.php?error=no_permission');
            exit;
        }
    }

    /**
     * Require role
     */
    public function requireRole($role)
    {
        $this->requireLogin();

        if (!$this->hasRole($role)) {
            header('Location: index.php?error=no_permission');
            exit;
        }
    }
}

// Global auth instance removed to prevent side-effects on include
// $auth = new AuthSystem(); must be called explicitly in entry points (index.php, api.php)
