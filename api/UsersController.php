<?php
/**
 * Users Controller — Multi-tenant (SQLite por empresa)
 * Gestores criam funcionários no banco isolado do tenant atual.
 */

require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/permissions.php';

class UsersController
{
    private $db;
    private $user;
    private $input;

    public function __construct($db, $user, $input)
    {
        $this->db = $db;
        $this->user = $user;
        $this->input = $input;
    }

    private function assertCanManage(): void
    {
        $role = $this->user['role'] ?? '';
        if (!Permissions::isDeveloper($role) && !Permissions::isGestor($role)) {
            throw new Exception(json_encode([
                'error_code' => 'FORBIDDEN',
                'message' => 'Apenas administradores podem gerenciar usuários.',
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    private function buildEmailLogin(string $loginCurto): string
    {
        $loginCurto = strtolower(trim($loginCurto));
        $tenantSlug = TenantResolver::getCurrentTenant();

        if ($tenantSlug) {
            // E-mail real (com TLD): mantém @ para login igual ao cadastro
            if (strpos($loginCurto, '@') !== false) {
                $cleanEmail = preg_replace('/[^a-z0-9._@+-]/', '', $loginCurto);
                if ($cleanEmail !== '' && preg_match('/^[^@]+@[^@]+\.[^@]+$/', $cleanEmail)) {
                    return $cleanEmail;
                }
            }

            $loginLimpo = str_replace('@', '.', $loginCurto);
            $loginLimpo = preg_replace('/[^a-z0-9._-]/', '', $loginLimpo);
            if ($loginLimpo === '') {
                throw new Exception(json_encode([
                    'error_code' => 'INVALID_LOGIN',
                    'message' => 'Login inválido.',
                ], JSON_UNESCAPED_UNICODE));
            }
            $suffix = '.' . $tenantSlug;
            $prefix = $tenantSlug . '.';
            if (strlen($loginLimpo) > strlen($suffix) && substr($loginLimpo, -strlen($suffix)) === $suffix) {
                $loginLimpo = substr($loginLimpo, 0, -strlen($suffix));
            }
            if (strlen($loginLimpo) > strlen($prefix) && substr($loginLimpo, 0, strlen($prefix)) === $prefix) {
                $loginLimpo = substr($loginLimpo, strlen($prefix));
            }
            $loginLimpo = preg_replace('/[^a-z0-9._-]/', '', $loginLimpo);
            if ($loginLimpo === '') {
                throw new Exception(json_encode([
                    'error_code' => 'INVALID_LOGIN',
                    'message' => 'Login inválido.',
                ], JSON_UNESCAPED_UNICODE));
            }
            return $loginLimpo . '.' . $tenantSlug;
        }

        if (strpos($loginCurto, '@') !== false) {
            $loginCurto = preg_replace('/[^a-z0-9._@+-]/', '', $loginCurto);
        } else {
            $loginCurto = preg_replace('/[^a-z0-9._-]/', '', $loginCurto);
        }
        if ($loginCurto === '') {
            throw new Exception(json_encode([
                'error_code' => 'INVALID_LOGIN',
                'message' => 'Login inválido.',
            ], JSON_UNESCAPED_UNICODE));
        }

        return $loginCurto;
    }

    private function nivelLabel(int $nivel): string
    {
        if ($nivel === 4) {
            return 'Cliente / Somente visualização';
        }
        return $nivel >= 2 ? 'Gestor / Administrador' : 'Lubrificador / Campo';
    }

    /**
     * Remove o sufixo ".{tenant}" de um e-mail/login, se presente. Usado para calcular o
     * "login curto" a partir do e-mail interno completo (ex: joao.silva.acme -> joao.silva).
     * NÃO usar explode('.', $email)[0] aqui: quebra logins que contêm ponto (ex: "joao.silva").
     */
    private function stripTenantSuffix(string $email, ?string $tenant): string
    {
        if (!$tenant) {
            return $email;
        }
        $suffix = '.' . strtolower($tenant);
        $emailLower = strtolower($email);
        if (strlen($emailLower) > strlen($suffix) && substr($emailLower, -strlen($suffix)) === $suffix) {
            return substr($email, 0, -strlen($suffix));
        }
        return $email;
    }

    public function getAll()
    {
        $this->assertCanManage();

        $stmt = $this->db->query(
            "SELECT id, nome, email, nivel, password_reset_required FROM usuarios ORDER BY nome ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tenant = TenantResolver::getCurrentTenant();
        $tenantLabel = TenantResolver::getTenantLabel($tenant);

        return array_map(function ($row) use ($tenant, $tenantLabel) {
            $loginShort = $this->stripTenantSuffix($row['email'], $tenant);
            $hints = $this->loginHints($tenant, $loginShort, $row['email']);

            return [
                'id' => (int) $row['id'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'username' => $loginShort,
                'nivel' => (int) $row['nivel'],
                'nivel_label' => $this->nivelLabel((int) $row['nivel']),
                'password_reset_required' => (bool) ($row['password_reset_required'] ?? 0),
                'login_hint' => $hints['primary'],
                'login_alts' => $hints['alts'],
                'tenant' => $tenant,
                'tenant_label' => $tenantLabel,
            ];
        }, $rows);
    }

    private function loginHints(?string $tenant, string $loginShort, string $email): array
    {
        if (!$tenant) {
            return ['primary' => $email, 'alts' => []];
        }
        if (strpos($email, '@') !== false && preg_match('/^[^@]+@[^@]+\.[^@]+$/', $email)) {
            $label = ucfirst($tenant);
            return [
                'primary' => $email,
                'alts' => array_values(array_unique(array_filter([
                    $label . '.' . $loginShort,
                    $label . '@' . $loginShort,
                ], static function ($v) use ($email) {
                    return strcasecmp($v, $email) !== 0;
                }))),
            ];
        }
        $label = ucfirst($tenant);
        $primary = $label . '.' . $loginShort;
        $alts = array_values(array_unique(array_filter([
            $label . '@' . $loginShort,
            $email,
        ], static function ($v) use ($primary) {
            return strcasecmp($v, $primary) !== 0;
        })));
        return ['primary' => $primary, 'alts' => $alts];
    }

    public function saveUser()
    {
        $this->assertCanManage();

        $id = intval($this->input['id'] ?? 0);
        $nome = trim($this->input['nome'] ?? $this->input['name'] ?? '');
        $loginCurto = trim($this->input['username'] ?? $this->input['login'] ?? '');
        $senha = (string) ($this->input['senha'] ?? $this->input['password'] ?? '');
        $senha = trim(str_replace(["\r\n", "\r"], "\n", $senha));
        $nivel = intval($this->input['nivel'] ?? 1);

        if ($nome === '' || $loginCurto === '') {
            return ['error' => 'Nome e login são obrigatórios.'];
        }

        if ($nivel === 2) {
            $nivel = 3;
        }
        if ($nivel !== 1 && $nivel !== 3 && $nivel !== 4) {
            $nivel = $nivel >= 2 ? 3 : 1;
        }

        $emailLogin = $this->buildEmailLogin($loginCurto);
        if ($id > 0 && strpos($loginCurto, '@') === false) {
            $prevStmt = $this->db->prepare('SELECT email FROM usuarios WHERE id = ?');
            $prevStmt->execute([$id]);
            $prevEmail = trim((string) ($prevStmt->fetchColumn() ?: ''));
            if ($prevEmail !== '' && strpos($prevEmail, '@') !== false) {
                $emailLogin = $prevEmail;
            }
        }

        // Evita duplicidade de e-mail/login
        $dupSql = 'SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?)';
        $dupParams = [$emailLogin];
        if ($id > 0) {
            $dupSql .= ' AND id != ?';
            $dupParams[] = $id;
        }
        $dup = $this->db->prepare($dupSql);
        $dup->execute($dupParams);
        if ($dup->fetchColumn()) {
            throw new Exception(json_encode([
                'error_code' => 'DUPLICATE_LOGIN',
                'message' => "O login '$emailLogin' já está em uso nesta empresa.",
            ], JSON_UNESCAPED_UNICODE));
        }

        if ($id > 0) {
            if (!empty($senha)) {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $this->db->prepare(
                    'UPDATE usuarios SET nome = ?, email = ?, senha = ?, nivel = ? WHERE id = ?'
                );
                $stmt->execute([$nome, $emailLogin, $hash, $nivel, $id]);
            } else {
                $stmt = $this->db->prepare(
                    'UPDATE usuarios SET nome = ?, email = ?, nivel = ? WHERE id = ?'
                );
                $stmt->execute([$nome, $emailLogin, $nivel, $id]);
            }
            DB::log($this->user['nome'] ?? 'SYSTEM', 'USER_UPDATE', 'usuarios:' . $id, null, [
                'nome' => $nome,
                'email' => $emailLogin,
                'nivel' => $nivel,
            ]);
        } else {
            if ($senha === '') {
                throw new Exception(json_encode([
                    'error_code' => 'PASSWORD_REQUIRED',
                    'message' => 'Informe uma senha inicial para o novo funcionário.',
                ], JSON_UNESCAPED_UNICODE));
            }
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $tenantNow = TenantResolver::getCurrentTenant();
            $requireReset = ($tenantNow !== null && TenantResolver::nivelToRole($nivel) === Permissions::ROLE_GESTOR) ? 1 : 0;
            $stmt = $this->db->prepare(
                'INSERT INTO usuarios (nome, email, senha, nivel, password_reset_required) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nome, $emailLogin, $hash, $nivel, $requireReset]);
            $id = (int) $this->db->lastInsertId();
            DB::log($this->user['nome'] ?? 'SYSTEM', 'USER_CREATE', 'usuarios:' . $id, null, [
                'nome' => $nome,
                'email' => $emailLogin,
                'nivel' => $nivel,
            ]);
        }

        $tenant = TenantResolver::getCurrentTenant();
        $loginShort = $this->stripTenantSuffix($emailLogin, $tenant);
        $hints = $this->loginHints($tenant, $loginShort, $emailLogin);

        $this->syncUsersJson('save', $emailLogin, $nome, $senha, $nivel, $id, $loginCurto);

        return [
            'success' => true,
            'ok' => true,
            'id' => $id,
            'email' => $emailLogin,
            'login_hint' => $hints['primary'],
            'login_alts' => $hints['alts'],
            'message' => $id > 0 && !empty($this->input['id'])
                ? "Acesso de '{$nome}' atualizado."
                : "Conta criada. Entre no sistema com: {$hints['primary']}",
        ];
    }

    public function deleteUser()
    {
        $this->assertCanManage();

        $id = intval($this->input['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('ID inválido.');
        }

        if ($id === intval($this->user['id'] ?? 0)) {
            throw new Exception('Você não pode excluir sua própria conta.');
        }

        $stmt = $this->db->prepare('SELECT nome, email FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Usuário não encontrado.');
        }

        $emailDeleted = $row['email'] ?? '';

        $this->db->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        DB::log($this->user['nome'] ?? 'SYSTEM', 'USER_DELETE', 'usuarios:' . $id, $row, null);

        if (!empty($emailDeleted)) {
            $this->syncUsersJson('delete', $emailDeleted, '', '', 1, $id);
        }

        return [
            'success' => true,
            'ok' => true,
            'message' => 'Usuário removido.',
        ];
    }

    /**
     * Mantém data/users.json sincronizado com o banco master no modo administrador.
     */
    private function syncUsersJson(string $action, string $emailLogin, string $nome = '', string $senha = '', int $nivel = 1, int $sqliteId = 0, string $loginCurto = ''): void
    {
        $tenant = TenantResolver::getCurrentTenant();
        if ($tenant !== null) {
            return;
        }

        $file = __DIR__ . '/../data/users.json';
        if (!file_exists($file)) {
            return;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!isset($data['users']) || !is_array($data['users'])) {
            return;
        }

        if ($nivel === 4) {
            $role = 'cliente';
        } else {
            $role = $nivel >= 2 ? 'admin' : 'trabalhador';
        }

        if ($action === 'save') {
            $found = false;
            $jsonUsername = $this->resolveJsonUsername($loginCurto, $nome, $emailLogin, $sqliteId, $data);

            // 1) Atualiza pelo ID SQLite (evita duplicata quando o e-mail muda)
            if ($sqliteId > 0) {
                foreach ($data['users'] as &$u) {
                    if ((int) ($u['id'] ?? 0) === $sqliteId) {
                        $prevRole = (string) ($u['role'] ?? $role);
                        $u['name'] = $nome !== '' ? $nome : ($u['name'] ?? $nome);
                        $u['username'] = $jsonUsername;
                        $u['email'] = $emailLogin;
                        $u['role'] = ($sqliteId === 1 && $prevRole === 'developer') ? 'developer' : $role;
                        if ($u['role'] === 'developer') {
                            $u['plan'] = 'unlimited';
                            $u['permissions'] = ['all' => true];
                        }
                        if ($senha !== '') {
                            $u['password'] = password_hash($senha, PASSWORD_DEFAULT);
                        }
                        $found = true;
                        break;
                    }
                }
                unset($u);
            }

            // 2) Fallback: identidade (e-mail / variantes)
            if (!$found) {
                foreach ($data['users'] as &$u) {
                    $sameIdentity = static function (string $stored) use ($emailLogin): bool {
                        foreach (TenantResolver::identityLookupVariants($emailLogin) as $v) {
                            if (strcasecmp($stored, $v) === 0) {
                                return true;
                            }
                        }
                        foreach (TenantResolver::identityLookupVariants($stored) as $v) {
                            if (strcasecmp($emailLogin, $v) === 0) {
                                return true;
                            }
                        }
                        return false;
                    };
                    if (
                        $sameIdentity((string) ($u['username'] ?? '')) ||
                        $sameIdentity((string) ($u['email'] ?? ''))
                    ) {
                        $prevRole = (string) ($u['role'] ?? $role);
                        $u['name'] = $nome !== '' ? $nome : ($u['name'] ?? $nome);
                        $u['email'] = $emailLogin;
                        $u['username'] = $jsonUsername;
                        $u['role'] = ($sqliteId === 1 && $prevRole === 'developer') ? 'developer' : $role;
                        if ($u['role'] === 'developer') {
                            $u['plan'] = 'unlimited';
                            $u['permissions'] = ['all' => true];
                        }
                        if ($sqliteId > 0) {
                            $u['id'] = $sqliteId;
                        }
                        if ($senha !== '') {
                            $u['password'] = password_hash($senha, PASSWORD_DEFAULT);
                        }
                        $found = true;
                        break;
                    }
                }
                unset($u);
            }

            if (!$found) {
                $maxId = 0;
                foreach ($data['users'] as $u) {
                    if (($u['id'] ?? 0) > $maxId) {
                        $maxId = (int) $u['id'];
                    }
                }
                $newId = $sqliteId > 0 ? $sqliteId : ($maxId + 1);
                $data['users'][] = [
                    'id' => $newId,
                    'username' => $jsonUsername,
                    'password' => password_hash($senha, PASSWORD_DEFAULT),
                    'name' => $nome,
                    'email' => $emailLogin,
                    'role' => $role,
                    'plan' => 'pro',
                    'limits' => [
                        'max_machines' => 200,
                        'max_points' => 1500,
                        'max_users' => 10
                    ],
                    'permissions' => Permissions::forRole($role),
                    'created_at' => date('c'),
                    'status' => 'active',
                    'password_reset_required' => 0
                ];
            }
        } elseif ($action === 'delete') {
            $data['users'] = array_values(array_filter($data['users'], function ($u) use ($emailLogin, $sqliteId) {
                if ($sqliteId > 0 && (int) ($u['id'] ?? 0) === $sqliteId) {
                    return false;
                }
                return strcasecmp($u['username'] ?? '', $emailLogin) !== 0
                    && strcasecmp($u['email'] ?? '', $emailLogin) !== 0;
            }));
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Login curto no users.json — preserva "Admin" mesmo quando e-mail é o identificador canônico.
     */
    private function resolveJsonUsername(string $loginCurto, string $nome, string $emailLogin, int $sqliteId, array $data): string
    {
        $loginCurto = trim($loginCurto);
        $nome = trim($nome);
        if ($loginCurto !== '' && strcasecmp($loginCurto, $emailLogin) !== 0) {
            return $loginCurto;
        }
        if ($nome !== '' && strpos($nome, '@') === false) {
            return $nome;
        }
        if ($sqliteId > 0) {
            foreach ($data['users'] as $u) {
                if ((int) ($u['id'] ?? 0) !== $sqliteId) {
                    continue;
                }
                $existing = trim((string) ($u['username'] ?? ''));
                if ($existing !== '' && strpos($existing, '@') === false) {
                    return $existing;
                }
            }
        }
        return $emailLogin;
    }
}
