<?php
// ===========================================
// AUTHENTICATION & PERMISSIONS
// ===========================================
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
$auth = new AuthSystem();

// Require login to access system
$auth->requireLogin();

// Paginas autenticadas nunca devem ser cacheadas (CDN/navegador)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Get current user info
$currentUser = $auth->getCurrentUser();

// Guarda de páginas por role (trabalhador só acessa dash + routes)
$page = $_GET['page'] ?? Permissions::defaultHomePage($currentUser['role'] ?? '');
if (!$auth->canAccessPage($page)) {
    $page = Permissions::defaultHomePage($currentUser['role'] ?? '');
}
// ===========================================
// SYSTEM INCLUDES
// ===========================================
$BUILD_VER = "3.2." . date('YmdHis');

// LUB-TEK System Entry Point
?>
<?php
// ROUTER SYSTEM (Performance Optimization)
$routes = [
    'home' => 'includes/views/home.php',
    'dash' => 'includes/views/dashboard.php',
    'assets' => 'includes/views/assets.php',
    'catalog' => 'includes/views/catalog.php',
    'calc' => 'includes/views/engineering.php',
    'reports' => 'includes/views/reports.php',
    'kpi' => 'includes/views/kpi.php',
    'routes' => 'includes/views/routes.php',
    '3d' => 'includes/views/view_3d_revolutionary.php',
    'pi' => 'includes/views/pi.php',
    'sap' => 'includes/views/sap_integration.php',
    'audit' => 'includes/views/audit.php',
    'users' => 'includes/views/users.php'
];

// Validate Page
if (!array_key_exists($page, $routes)) {
    $page = Permissions::defaultHomePage($currentUser['role'] ?? '');
}
$viewFile = $routes[$page];

// SPECIAL 3D VIEW HANDLER (Standalone Page - No Header/Footer)
if ($page === '3d') {
    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "Erro 3D: Arquivo de visualização não encontrado.";
    }
    exit; // Stops execution here
}
?>

<?php
try {
    require 'includes/header.php';
} catch (Throwable $e) {
    error_log('header.php fatal: ' . $e->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;">';
    echo '<h1>Erro ao carregar o painel</h1>';
    echo '<p>Falha temporária ao iniciar a interface. <a href="login.php">Voltar ao login</a></p>';
    echo '</body></html>';
    exit;
}
?>

<?php
// SPA Mode: renderiza as views permitidas pelo role
$allowedPages = Permissions::allowedPages($currentUser['role'] ?? '');
$renderedPaths = [];

// Garante que a página ativa seja renderizada primeiro (fail-fast se houver erro)
$orderedRoutes = [$page => $routes[$page]];
foreach ($routes as $routeKey => $routePath) {
    if ($routeKey !== $page) {
        $orderedRoutes[$routeKey] = $routePath;
    }
}

foreach ($orderedRoutes as $routeKey => $routePath) {
    if ($routeKey === '3d') continue;
    if (!in_array($routeKey, $allowedPages, true)) continue;
    if (in_array($routePath, $renderedPaths, true)) continue;
    $renderedPaths[] = $routePath;

    if (!file_exists($routePath)) continue;

    try {
        ob_start();
        include $routePath;
        $html = ob_get_clean();
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        error_log("View {$routeKey} error: " . $e->getMessage());
        if ($routePath === $routes[$page]) {
            echo '<div class="view-container active" style="padding:40px;text-align:center;">';
            echo '<h2>Erro ao carregar esta tela</h2>';
            echo '<p style="color:#64748b;">Tente novamente ou escolha outro módulo no menu.</p>';
            echo '</div>';
        }
        continue;
    }

    $isActive = ($routePath === $routes[$page]);
    if ($isActive) {
        if (!preg_match('/class=(["\'])[^"\']*\bactive\b/', $html)) {
            $html = preg_replace(
                '/class=(["\'])(view-container)(\s[^"\']*)?\1/i',
                'class=$1$2 active$3$1',
                $html,
                1
            );
        }
        echo $html;
    } else {
        $inactiveHtml = preg_replace(
            '/class=(["\'])(view-container)\s+active(\s[^"\']*)?\1/i',
            'class=$1$2$3$1',
            $html,
            1
        );
        echo $inactiveHtml;
    }
}
?>

</main>

<!-- TOAST NOTIFICATION CONTAINER -->
<div id="toast-container"></div>

<?php include 'includes/neural_assistant.php'; ?>

<!-- PRINT CONTAINER -->
<div id="print-area"></div>

<style>
    #print-area {
        display: none;
    }

    @media print {

        /* 1. Global UI Hiding (Sidebar, Buttons, Inputs if needed) */
        nav,
        .sidebar,
        header,
        .btn,
        .btn-icon,
        .floating-btn,
        .toast-container,
        #toast-container,
        .no-print {
            display: none !important;
        }

        /* 2. Reset Layout for Print */
        body,
        main {
            background: white !important;
            color: black !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            overflow: visible !important;
        }

        /* 3. Dedicated Print Area Logic */
        /* If #print-area has content (class 'active'), hide everything else */
        body.printing-mode>*:not(#print-area) {
            display: none !important;
        }

        body.printing-mode #print-area {
            display: block !important;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
        }

        /* Fix Tables for Print */
        table {
            width: 100% !important;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd !important;
            padding: 8px;
            color: black !important;
        }
    }
</style>

<!-- CORE SCRIPTS -->
<!-- 0. ERROR PREVENTION (Must load VERY FIRST) -->
<script src="assets/js/error_prevention.js?v=<?php echo $BUILD_VER; ?>"></script>
<script>
    // Navegacao temporaria ate scripts_main.js carregar (SPA)
    window.nav = function(viewId, btn, isPopState) {
        if (typeof window.__navReady === 'function') {
            return window.__navReady(viewId, btn, isPopState);
        }
        const url = new URL(window.location);
        url.searchParams.set('page', viewId);
        window.location.href = url.toString();
    };
</script>

<!-- 1. REAL-TIME ECOSYSTEM CORE (Must load FIRST before anything) -->
<script src="assets/js/realtime_core.js?v=<?php echo $BUILD_VER; ?>"></script>

<!-- 2. MAIN SCRIPTS (API canônica) — carrega antes dos extras -->
<script src="assets/js/scripts_main.js?v=3.2.6_<?php echo time(); ?>"></script>

<!-- 2b. ECOSYSTEM BRIDGE -->
<script src="assets/js/ecosystem_bridge.js?v=<?php echo $BUILD_VER; ?>"></script>

<!-- 3. MODALS -->
<?php include 'includes/modals.php'; ?>

<!-- 4. COMPONENT AUTO-SYNC -->
<script src="assets/js/component_sync.js?v=<?php echo $BUILD_VER; ?>"></script>

<!-- 4b. OS MANAGER -->
<script src="assets/js/os_manager.js?v=<?php echo $BUILD_VER; ?>"></script>

<!-- 5. EXTRA (rotas, calculadoras) — depois do main para não sobrescrever api() -->
<script src="assets/js/scripts_extra.js?v=<?php echo $BUILD_VER; ?>"></script>
<script src="assets/js/lubricant_consumption_report.js?v=<?php echo $BUILD_VER; ?>"></script>
<script src="assets/js/lubrication_plan_export.js?v=<?php echo $BUILD_VER; ?>"></script>

<!-- 6. MARKETPLACE -->
<script src="assets/js/marketplace_intelligence.js?v=<?php echo $BUILD_VER; ?>"></script>

<!-- 7. RELIABILITY MONITOR -->
<script src="assets/js/reliability_monitor.js?v=<?php echo $BUILD_VER; ?>"></script>

<!-- 8. NEURAL ASSISTANT -->
<script src="assets/js/neural_assistant.js?v=<?php echo $BUILD_VER; ?>"></script>

<!-- MODAL OBRIGATÓRIO DE TROCA DE SENHA (PRIMEIRO ACESSO) -->
<div id="modal-troca-senha-obrigatoria" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="titulo-troca-senha">
    <div class="modal-card" style="background:#fff;border-radius:16px;width:90%;max-width:420px;padding:30px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.3);border:1px solid #e2e8f0;text-align:center;">
        <div style="width:56px;height:56px;background:rgba(14,165,233,0.1);color:#0284c7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 15px;">
            <i data-lucide="shield-alert" style="width:28px;height:28px;"></i>
        </div>
        <h3 id="titulo-troca-senha" style="font-weight:800;color:#0f172a;margin-bottom:8px;">Primeiro Acesso Detectado</h3>
        <p style="font-size:0.9rem;color:#64748b;margin-bottom:20px;">
            Por razões de segurança, defina uma nova senha pessoal para continuar acessando o sistema LUB-TEK.
        </p>
        <form id="form-troca-senha-obrigatoria" onsubmit="executarTrocaSenhaObrigatoria(event)">
            <div style="text-align:left;margin-bottom:15px;">
                <label style="display:block;font-size:0.8rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:5px;">Senha Atual (Inicial)</label>
                <input type="password" id="obrig-senha-atual" required placeholder="Sua senha temporária" style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.95rem;box-sizing:border-box;">
            </div>
            <div style="text-align:left;margin-bottom:20px;">
                <label style="display:block;font-size:0.8rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:5px;">Nova Senha</label>
                <input type="password" id="obrig-nova-senha" required minlength="6" placeholder="Digite sua nova senha" style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.95rem;box-sizing:border-box;">
            </div>
            <p id="obrig-senha-erro" style="display:none;color:#ef4444;font-size:0.85rem;font-weight:600;margin-bottom:12px;text-align:left;"></p>
            <button type="submit" id="btn-salvar-senha-obrig" style="width:100%;padding:14px;background:#0284c7;color:white;border:none;border-radius:8px;font-weight:700;font-size:1rem;cursor:pointer;min-height:48px;">
                Atualizar Senha e Acessar
            </button>
        </form>
    </div>
</div>

<!-- 8. Initialize user context for frontend -->
<script>
    <?php if (isset($currentUser)): ?>
    window.currentUser = <?php echo json_encode($currentUser, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    <?php else: ?>
    window.currentUser = null;
    <?php endif; ?>

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (PASSWORD_RESET_LOCK_ENABLED): ?>
        if (window.currentUser && window.currentUser.password_reset_required) {
            const modal = document.getElementById('modal-troca-senha-obrigatoria');
            if (modal) {
                modal.classList.add('active');
                document.body.classList.add('password-reset-lock');
                if (window.lucide) lucide.createIcons();
                setTimeout(() => document.getElementById('obrig-senha-atual')?.focus(), 200);
            }
        }
        <?php endif; ?>
        const urlParams = new URLSearchParams(window.location.search);
        let page = urlParams.get('page');
        if (page === 'ativos') page = 'assets';
        if (!page && (urlParams.get('id') || urlParams.get('asset_id') || urlParams.get('ativo_id'))) page = 'assets';
        if (!page) {
            page = <?php echo json_encode(Permissions::defaultHomePage($currentUser['role'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
        }
        if (typeof atualizarTituloBreadcrumb === 'function') atualizarTituloBreadcrumb(page);
    });

    async function executarTrocaSenhaObrigatoria(e) {
        e.preventDefault();
        const oldPassword = document.getElementById('obrig-senha-atual').value;
        const newPassword = document.getElementById('obrig-nova-senha').value;
        const btn = document.getElementById('btn-salvar-senha-obrig');
        const errEl = document.getElementById('obrig-senha-erro');

        if (newPassword.length < 6) {
            errEl.textContent = 'A nova senha deve ter pelo menos 6 caracteres.';
            errEl.style.display = 'block';
            return;
        }
        if (newPassword === oldPassword) {
            errEl.textContent = 'A nova senha deve ser diferente da senha atual.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
            const response = await fetch('api.php?action=change_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ old_password: oldPassword, new_password: newPassword })
            });
            const data = await response.json();

            if (data.ok || data.success) {
                if (window.currentUser) window.currentUser.password_reset_required = false;
                document.body.classList.remove('password-reset-lock');
                document.getElementById('modal-troca-senha-obrigatoria').classList.remove('active');
                if (typeof showToast === 'function') {
                    showToast('✅ Senha alterada com sucesso!', 'success', { large: true, duration: 2800 });
                }
                setTimeout(() => window.location.reload(), 1200);
            } else {
                errEl.textContent = data.error || data.message || 'Senha atual incorreta. Tente novamente.';
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Atualizar Senha e Acessar';
            }
        } catch (err) {
            errEl.textContent = 'Falha na comunicação com o servidor.';
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Atualizar Senha e Acessar';
        }
    }
    window.executarTrocaSenhaObrigatoria = executarTrocaSenhaObrigatoria;
</script>

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      const swScript = 'sw.js';
      navigator.serviceWorker.register(swScript)
        .then(registration => {
          console.log('SW PWA Registrado:', registration.scope);
        })
        .catch(err => {
          navigator.serviceWorker.register('service-worker.js').catch(() => {});
        });
    });
  }
</script>

</body>

</html>