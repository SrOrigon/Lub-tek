<?php
if (!defined('BUILD_VER') && !isset($auth)) {
    http_response_code(403);
    die('Acesso negado.');
}

require_once __DIR__ . '/../tenant.php';

$auth->requirePermission('manage_users');

$tenantSlug = TenantResolver::getCurrentTenant();
$tenantLabel = TenantResolver::getTenantLabel($tenantSlug);
$suffixLabel = $tenantSlug ? '.' . htmlspecialchars($tenantSlug) : '';
?>
<div id="view-users" class="view-container view-flex" style="padding:20px; flex-direction:column; gap:20px; background:#f8fafc;">

    <div class="users-toolbar">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i data-lucide="users" style="color:#0284c7; width:26px; height:26px;"></i>
                Gerenciamento de Equipe
            </h1>
            <p style="margin:6px 0 0; color:#64748b; font-size:0.9rem; font-weight:600;">
                Cadastre funcionários da <?php echo htmlspecialchars($tenantLabel); ?>. Inclui conta de <strong>cliente somente leitura</strong>.
            </p>
        </div>
        <button type="button" class="btn btn-sm users-btn-primary" onclick="abrirModalNovoUsuario()" style="min-height:44px;">
            <i data-lucide="user-plus" style="width:16px;"></i> Novo Funcionário
        </button>
    </div>

    <div class="users-info-banner">
        <i data-lucide="info" style="width:18px; flex-shrink:0;"></i>
        <span>
            <?php if ($tenantSlug): ?>
                Para entrar, use <strong><?php echo htmlspecialchars(ucfirst($tenantSlug)); ?>.nome</strong>
                ou <strong><?php echo htmlspecialchars(ucfirst($tenantSlug)); ?>@nome</strong>
                (também vale o login gravado <strong>nome.<?php echo htmlspecialchars($tenantSlug); ?></strong>
                ou o <strong>e-mail completo</strong> se cadastrou com @).
            <?php else: ?>
                Modo administrador: o funcionário entra com o <strong>mesmo login</strong> cadastrado e a senha inicial.
            <?php endif; ?>
        </span>
    </div>

    <div class="users-table-panel">
        <div class="users-table-header">
            <span>Funcionários cadastrados</span>
            <div class="users-table-header-actions">
                <input type="search" id="input-busca-user" class="users-search-input"
                    placeholder="Buscar por nome ou login..."
                    oninput="filtrarTabelaUsuarios()"
                    aria-label="Buscar funcionário">
                <span id="users-count-badge" class="users-count-badge">0</span>
            </div>
        </div>
        <div class="users-table-scroll">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Nome do Colaborador</th>
                        <th>Login de Acesso</th>
                        <th>Como Entrar no Sistema</th>
                        <th>Permissão</th>
                        <th style="width:120px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-usuarios-body">
                    <tr>
                        <td colspan="5" style="text-align:center; padding:40px; color:#64748b;">
                            Carregando equipe...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal cadastro / edição -->
<div id="modal-usuario-overlay" class="users-modal-overlay" onclick="if(event.target===this)fecharModalUsuario()">
    <div class="users-modal-card" role="dialog">
        <div class="users-modal-header">
            <h3 id="modal-usuario-titulo">Adicionar Funcionário</h3>
            <button type="button" class="users-modal-close" onclick="fecharModalUsuario()">&times;</button>
        </div>
        <p class="users-modal-hint">
            <?php if ($tenantSlug): ?>
                O login será gravado como <strong>nome<?php echo $suffixLabel; ?></strong>.
                Para entrar, use <strong><?php echo htmlspecialchars(ucfirst($tenantSlug)); ?>.nome</strong>.
            <?php else: ?>
                O login cadastrado é o mesmo da tela de entrada (pode ser e-mail, por exemplo <strong>admin@rodrigo.com</strong>).
            <?php endif; ?>
        </p>

        <form id="form-usuario" onsubmit="salvarUsuario(event)">
            <input type="hidden" id="user-id" value="0">

            <label class="users-label" for="user-nome">Nome completo</label>
            <input type="text" id="user-nome" class="users-input" placeholder="Ex: Fábio Silva" required>

            <label class="users-label" for="user-login">Login desejado</label>
            <div class="users-login-row">
                <input type="text" id="user-login" class="users-input" placeholder="fabio" required
                    pattern="[a-zA-Z0-9._@-]+" title="Letras, números, ponto, @ ou hífen">
                <?php if ($suffixLabel): ?>
                <span id="label-sufixo-tenant" class="users-login-suffix"><?php echo $suffixLabel; ?></span>
                <?php endif; ?>
            </div>

            <label class="users-label" for="user-senha">Senha inicial</label>
            <div class="users-password-wrap">
                <input type="password" id="user-senha" class="users-input" placeholder="Digite a senha inicial">
                <button type="button" class="users-toggle-password" id="toggle-user-senha" aria-label="Mostrar senha" aria-pressed="false" title="Mostrar senha">
                    <i data-lucide="eye"></i>
                </button>
            </div>
            <p id="user-senha-hint" class="users-field-hint">Obrigatória apenas para novos funcionários.</p>

            <label class="users-label" for="user-nivel">O que este funcionário pode fazer?</label>
            <select id="user-nivel" class="users-input users-select">
                <option value="1">Lubrificador / Operador de Campo — rotas e alertas</option>
                <option value="4">Cliente — somente visualização (sem editar)</option>
                <option value="3">Gestor / Administrador — acesso total da empresa</option>
            </select>

            <div class="users-modal-actions">
                <button type="button" class="users-btn-cancel" onclick="fecharModalUsuario()">Cancelar</button>
                <button type="submit" class="users-btn-primary">Salvar Acesso</button>
            </div>
        </form>
    </div>
</div>

<style>
    .users-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        padding: 16px 20px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 12px rgba(15,23,42,0.02);
        flex-wrap: wrap;
        gap: 16px;
    }
    .users-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0 20px;
        border: none;
        border-radius: 10px;
        background: linear-gradient(135deg, #0284c7, #0369a1);
        color: #fff;
        font-weight: 800;
        cursor: pointer;
    }
    .users-info-banner {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        color: #0369a1;
        padding: 14px 18px;
        border-radius: 12px;
        font-size: 0.88rem;
        font-weight: 600;
        line-height: 1.45;
    }
    .users-table-panel {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .users-table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 14px 20px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
    }
    .users-table-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        justify-content: flex-end;
        min-width: 200px;
    }
    .users-search-input {
        flex: 1;
        max-width: 280px;
        min-height: 40px;
        padding: 0 14px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: none;
        color: #0f172a;
        background: #fff;
    }
    .users-search-input:focus {
        outline: none;
        border-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }
    .users-count-badge {
        background: #e0f2fe;
        color: #0369a1;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
    }
    .users-table-scroll { overflow-x: auto; }
    .users-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .users-table th {
        text-align: left;
        padding: 12px 16px;
        background: #f8fafc;
        color: #64748b;
        font-size: 0.72rem;
        text-transform: uppercase;
        font-weight: 800;
    }
    .users-table td {
        padding: 14px 16px;
        border-top: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .users-perm-pill {
        display: inline-flex;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.72rem;
        font-weight: 800;
    }
    .users-perm-field { background: rgba(245,158,11,0.15); color: #d97706; }
    .users-perm-admin { background: rgba(16,185,129,0.15); color: #059669; }
    .users-perm-view { background: rgba(14,165,233,0.15); color: #0369a1; }
    .users-action-btn {
        border: none;
        background: #f1f5f9;
        border-radius: 8px;
        padding: 6px 10px;
        cursor: pointer;
        margin-right: 4px;
    }
    .users-action-btn.delete { color: #dc2626; background: #fef2f2; }
    .users-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.45);
        z-index: 10060;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .users-modal-overlay.active { display: flex; }
    .users-modal-card {
        background: #fff;
        border-radius: 16px;
        width: 100%;
        max-width: 480px;
        padding: 22px 24px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.2);
    }
    .users-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    .users-modal-header h3 { margin: 0; font-size: 1.15rem; font-weight: 800; }
    .users-modal-close {
        border: none;
        background: #f1f5f9;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        font-size: 1.25rem;
        cursor: pointer;
        color: #64748b;
    }
    .users-modal-hint {
        margin: 0 0 18px;
        font-size: 0.85rem;
        color: #64748b;
        line-height: 1.4;
    }
    .users-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #475569;
        margin: 0 0 6px;
    }
    .users-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 1rem;
        margin-bottom: 14px;
    }
    .users-password-wrap {
        position: relative;
        margin-bottom: 14px;
    }
    .users-password-wrap .users-input {
        margin-bottom: 0;
        padding-right: 44px;
    }
    .users-toggle-password {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 34px;
        height: 34px;
        border: none;
        background: transparent;
        color: #94a3b8;
        cursor: pointer;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }
    .users-toggle-password:hover {
        color: #0284c7;
        background: rgba(2, 132, 199, 0.08);
    }
    .users-select { min-height: 48px; }
    .users-login-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 14px;
    }
    .users-login-row .users-input { margin-bottom: 0; flex: 1; }
    .users-login-suffix {
        font-weight: 800;
        color: #0284c7;
        white-space: nowrap;
        font-size: 1rem;
    }
    .users-field-hint {
        margin: -8px 0 14px;
        font-size: 0.78rem;
        color: #94a3b8;
    }
    .users-modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 8px;
    }
    .users-btn-cancel {
        min-height: 44px;
        padding: 0 18px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        font-weight: 700;
        cursor: pointer;
        color: #64748b;
    }
</style>

<script>
    let usersCache = [];
    const USERS_IS_TENANT = <?php echo $tenantSlug ? 'true' : 'false'; ?>;

    function initUsersPage() {
        loadUsuarios();
    }

    window.addEventListener('load', () => {
        const view = document.getElementById('view-users');
        if (view && view.classList.contains('active')) initUsersPage();
    });

    (function setupUserPasswordToggle() {
        const btn = document.getElementById('toggle-user-senha');
        const input = document.getElementById('user-senha');
        if (!btn || !input) return;
        btn.addEventListener('click', function () {
            const showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            btn.setAttribute('aria-pressed', showing ? 'true' : 'false');
            btn.setAttribute('aria-label', showing ? 'Ocultar senha' : 'Mostrar senha');
            btn.title = showing ? 'Ocultar senha' : 'Mostrar senha';
            btn.innerHTML = '';
            const icon = document.createElement('i');
            icon.setAttribute('data-lucide', showing ? 'eye-off' : 'eye');
            btn.appendChild(icon);
            if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
            input.focus();
        });
    })();

    const _navUsers = window.nav;
    window.nav = function(viewId, btn, isPopState) {
        if (typeof _navUsers === 'function') _navUsers(viewId, btn, isPopState);
        if (viewId === 'users') setTimeout(loadUsuarios, 50);
    };

    async function loadUsuarios() {
        const tbody = document.getElementById('lista-usuarios-body');
        const badge = document.getElementById('users-count-badge');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:#64748b;">Carregando equipe...</td></tr>';

        try {
            const res = await api('get_users');
            let list = [];
            if (Array.isArray(res)) list = res;
            else if (res && Array.isArray(res.data)) list = res.data;
            else if (res && res.ok) list = res.data || [];
            else if (res && Array.isArray(res.users)) list = res.users;

            usersCache = list;
            if (badge) badge.textContent = list.length + ' funcionário(s)';

            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:#64748b;">Nenhum funcionário cadastrado ainda. Clique em <strong>Novo Funcionário</strong>.</td></tr>';
                return;
            }

            tbody.innerHTML = list.map(u => {
                const n = Number(u.nivel || 1);
                const isCliente = n === 4;
                const isGestor = !isCliente && n >= 2;
                const permClass = isCliente ? 'users-perm-view' : (isGestor ? 'users-perm-admin' : 'users-perm-field');
                const permLabel = u.nivel_label || (isCliente ? 'Cliente' : (isGestor ? 'Gestor' : 'Campo'));
                const isSelf = window.currentUser && String(window.currentUser.id) === String(u.id);
                return `<tr>
                    <td><strong>${escapeUsersHtml(u.nome)}</strong></td>
                    <td><code style="background:#f1f5f9;padding:3px 8px;border-radius:6px;">${escapeUsersHtml(u.email)}</code></td>
                    <td>
                        <div style="font-weight:800;color:#0369a1;">${escapeUsersHtml(u.login_hint || u.email)}</div>
                        ${(u.login_alts && u.login_alts.length) ? `<div style="font-size:0.72rem;color:#64748b;margin-top:2px;">também: ${escapeUsersHtml(u.login_alts.join(' · '))}</div>` : ''}
                    </td>
                    <td><span class="users-perm-pill ${permClass}">${escapeUsersHtml(permLabel)}</span></td>
                    <td>
                        <button type="button" class="users-action-btn" title="Editar" onclick="editarUsuario(${u.id})"><i data-lucide="pencil" style="width:14px;"></i></button>
                        ${isSelf ? '' : `<button type="button" class="users-action-btn delete" title="Remover" onclick="excluirUsuario(${u.id})"><i data-lucide="trash-2" style="width:14px;"></i></button>`}
                    </td>
                </tr>`;
            }).join('');

            if (typeof lucide !== 'undefined') lucide.createIcons();
            filtrarTabelaUsuarios();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:#991b1b;">Erro ao carregar usuários.</td></tr>';
        }
    }

    function filtrarTabelaUsuarios() {
        const termo = (document.getElementById('input-busca-user')?.value || '').toLowerCase().trim();
        const linhas = document.querySelectorAll('#lista-usuarios-body tr');
        let visiveis = 0;

        linhas.forEach(linha => {
            if (linha.querySelector('td[colspan]')) return;
            const textoLinha = linha.textContent.toLowerCase();
            const mostrar = !termo || textoLinha.includes(termo);
            linha.style.display = mostrar ? '' : 'none';
            if (mostrar) visiveis++;
        });

        const badge = document.getElementById('users-count-badge');
        if (badge && usersCache.length) {
            badge.textContent = termo
                ? `${visiveis} de ${usersCache.length}`
                : `${usersCache.length} funcionário(s)`;
        }
    }
    window.filtrarTabelaUsuarios = filtrarTabelaUsuarios;

    function escapeUsersHtml(str) {
        const d = document.createElement('span');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function abrirModalNovoUsuario() {
        document.getElementById('modal-usuario-titulo').textContent = 'Adicionar Funcionário';
        document.getElementById('user-id').value = '0';
        document.getElementById('user-nome').value = '';
        document.getElementById('user-login').value = '';
        document.getElementById('user-senha').value = '';
        document.getElementById('user-senha').required = true;
        document.getElementById('user-nivel').value = '1';
        document.getElementById('user-senha-hint').textContent = USERS_IS_TENANT
            ? 'Obrigatória. Com ela a pessoa já entra no sistema (gestor da empresa pode trocar no primeiro acesso).'
            : 'Obrigatória. Com ela a pessoa já entra no sistema com este login e senha.';
        document.getElementById('modal-usuario-overlay').classList.add('active');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function editarUsuario(id) {
        const u = usersCache.find(x => String(x.id) === String(id));
        if (!u) return;
        document.getElementById('modal-usuario-titulo').textContent = 'Editar Funcionário';
        document.getElementById('user-id').value = u.id;
        document.getElementById('user-nome').value = u.nome || '';
        document.getElementById('user-login').value = u.username || u.email || '';
        document.getElementById('user-senha').value = '';
        document.getElementById('user-senha').required = false;
        const n = Number(u.nivel || 1);
        document.getElementById('user-nivel').value = String(n === 4 ? 4 : (n >= 2 ? 3 : 1));
        document.getElementById('user-senha-hint').textContent = 'Deixe em branco para manter a senha atual.';
        document.getElementById('modal-usuario-overlay').classList.add('active');
    }

    function fecharModalUsuario() {
        document.getElementById('modal-usuario-overlay').classList.remove('active');
    }

    async function salvarUsuario(e) {
        e.preventDefault();
        const id = parseInt(document.getElementById('user-id').value, 10) || 0;
        const payload = {
            id: id,
            nome: document.getElementById('user-nome').value.trim(),
            username: document.getElementById('user-login').value.trim(),
            senha: document.getElementById('user-senha').value,
            nivel: parseInt(document.getElementById('user-nivel').value, 10) || 1
        };

        if (!payload.nome || !payload.username) {
            showToast('Preencha nome e login.', 'warning');
            return;
        }
        if (id === 0 && !payload.senha) {
            showToast('Informe a senha inicial.', 'warning');
            return;
        }

        try {
            const res = await api('save_user', payload);
            if (res && (res.ok || res.success)) {
                const como = res.login_hint || payload.username;
                showToast(res.message || ('Conta pronta. Login: ' + como), 'success');
                fecharModalUsuario();
                loadUsuarios();
            } else {
                showToast(res?.error || 'Erro ao salvar.', 'error');
            }
        } catch (err) {
            showToast('Erro ao salvar funcionário.', 'error');
        }
    }

    async function excluirUsuario(id) {
        const u = usersCache.find(x => String(x.id) === String(id));
        if (!u) return;
        if (!confirm(`Remover o acesso de "${u.nome}"?\n\nEsta ação não pode ser desfeita.`)) return;

        try {
            const res = await api('delete_user', { id });
            if (res && (res.ok || res.success)) {
                showToast(res.message || 'Usuário removido.', 'success');
                loadUsuarios();
            } else {
                showToast(res?.error || 'Erro ao remover.', 'error');
            }
        } catch (e) {
            showToast('Erro ao remover usuário.', 'error');
        }
    }

    window.loadUsuarios = loadUsuarios;
    window.abrirModalNovoUsuario = abrirModalNovoUsuario;
    window.fecharModalUsuario = fecharModalUsuario;
    window.salvarUsuario = salvarUsuario;
    window.editarUsuario = editarUsuario;
    window.excluirUsuario = excluirUsuario;
</script>
