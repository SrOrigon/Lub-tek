<?php
require_once 'config.php';
require_once 'includes/auth.php';
$auth = new AuthSystem();
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}
$usersFileOk = file_exists(__DIR__ . '/data/users.json');
$sessionsDir = __DIR__ . '/data/sessions';
$sessionsWritable = is_dir($sessionsDir) && is_writable($sessionsDir);
$prefillUser = trim((string) ($_GET['user'] ?? ''));
$loginError = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LUB-TEK 3.0</title>
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <link rel="manifest" href="assets/pwa/manifest.webmanifest">
    <meta name="theme-color" content="#0284c7">
    <script src="assets/js/security_guard.js" defer></script>
    <link rel="icon" href="assets/img/system/img_69581d7fcdbbf.jpeg" type="image/jpeg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --bg-body: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --input-bg: #f8fafc;
            --input-border: #cbd5e1;
            --success: #16a34a;
            --danger: #dc2626;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-body);
            background-image: 
                radial-gradient(at 0% 0%, rgba(2, 132, 199, 0.06) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(14, 165, 233, 0.06) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08), 0 0 1px rgba(15, 23, 42, 0.05);
            animation: slideUp 0.5s ease-out;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 36px;
        }

        .logo-wrapper {
            width: 90px;
            height: 90px;
            border-radius: 22px;
            margin: 0 auto 20px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            padding: 8px;
            transition: all 0.3s ease;
        }

        .logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 14px;
        }

        h1 {
            font-family: 'Outfit', 'Inter', sans-serif;
            font-size: 2.1rem;
            font-weight: 800;
            margin-bottom: 6px;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 0.92rem;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.82rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            width: 20px;
            height: 20px;
            transition: color 0.2s;
        }

        .input-wrapper:focus-within .input-icon {
            color: var(--primary);
        }

        input {
            width: 100%;
            padding: 15px 16px 15px 48px;
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 0.98rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        input.has-password-toggle {
            padding-right: 48px;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
        }

        input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
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

        .toggle-password:hover,
        .toggle-password:focus-visible {
            color: var(--primary);
            background: rgba(2, 132, 199, 0.08);
            outline: none;
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        .caps-hint {
            display: none;
            margin-top: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #c2410c;
        }

        .caps-hint.visible {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(2, 132, 199, 0.35);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 22px;
            font-size: 0.9rem;
            font-weight: 500;
            display: none;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        .footer {
            text-align: center;
            margin-top: 32px;
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 500;
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <div class="logo-wrapper" id="login-logo-wrapper">
                    <img id="login-logo-img" src="assets/img/system/img_69581d7fcdbbf.jpeg" alt="LUB-TEK Logo">
                </div>
                <h1 id="login-title-text">LUB-TEK 3.0</h1>
                <p class="subtitle" id="login-subtitle-text">Sistema Avançado de Gestão</p>
            </div>

            <div class="alert alert-error" id="alert-error"<?php echo $loginError ? ' style="display:block;"' : ''; ?>>
                <?php echo $loginError ? 'Usuário ou senha inválidos. Confira o login (Empresa.usuario) e tente de novo.' : ''; ?>
            </div>
            <div class="alert alert-success" id="alert-success"></div>
            <p id="login-redirect-hint" style="display:none;text-align:center;margin-bottom:16px;font-size:0.9rem;">
                <a id="login-redirect-link" href="index.php" style="color:#0284c7;font-weight:700;text-decoration:underline;">
                    Clique aqui se não for redirecionado automaticamente
                </a>
            </p>

            <?php if (!$usersFileOk): ?>
            <div class="alert" style="display:block;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;">
                Login de equipe usa o banco do sistema. Contas criadas em Equipe e Acessos entram com o mesmo usuário e senha.
            </div>
            <?php endif; ?>

            <?php if (!$sessionsWritable): ?>
            <div class="alert alert-error" style="display:block;background:#fff7ed;border-color:#fed7aa;color:#c2410c;">
                Pasta de sessões (data/sessions) sem permissão de escrita. O login pode falhar após autenticar.
            </div>
            <?php endif; ?>

            <form id="login-form" method="POST" action="login_process.php">
                <div class="form-group">
                    <label for="username">Usuário</label>
                    <div class="input-wrapper">
                        <i data-lucide="user" class="input-icon"></i>
                        <input type="text" id="username" name="username" placeholder="Empresa.usuario ou seu login" required autocomplete="username" spellcheck="false" autocapitalize="off" value="<?php echo htmlspecialchars($prefillUser, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <div class="input-wrapper">
                        <i data-lucide="lock" class="input-icon"></i>
                        <input type="password" id="password" name="password" class="has-password-toggle" placeholder="Digite sua senha" required autocomplete="current-password" spellcheck="false">
                        <button type="button" class="toggle-password" id="toggle-password" aria-label="Mostrar senha" aria-pressed="false" title="Mostrar senha">
                            <i data-lucide="eye" id="toggle-password-icon"></i>
                        </button>
                    </div>
                    <p class="caps-hint" id="caps-hint">Caps Lock está ligado</p>
                </div>

                <button type="submit" class="btn-login" id="btn-submit">
                    Entrar no Sistema
                </button>
            </form>

            <div class="footer">
                © 2026 LUB-TEK Systems • <a href="mailto:suporte@lubtek.com.br">Suporte</a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const togglePasswordBtn = document.getElementById('toggle-password');
        const capsHint = document.getElementById('caps-hint');

        try {
            const savedUser = sessionStorage.getItem('lubtek_login_user');
            if (savedUser && usernameInput && !usernameInput.value) {
                usernameInput.value = savedUser;
            }
        } catch (e) { /* ignore */ }

        function refreshLucideIcons() {
            if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }
        }

        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', function () {
                const showing = passwordInput.type === 'password';
                passwordInput.type = showing ? 'text' : 'password';
                togglePasswordBtn.setAttribute('aria-pressed', showing ? 'true' : 'false');
                togglePasswordBtn.setAttribute('aria-label', showing ? 'Ocultar senha' : 'Mostrar senha');
                togglePasswordBtn.title = showing ? 'Ocultar senha' : 'Mostrar senha';
                togglePasswordBtn.innerHTML = '';
                const icon = document.createElement('i');
                icon.setAttribute('data-lucide', showing ? 'eye-off' : 'eye');
                icon.id = 'toggle-password-icon';
                togglePasswordBtn.appendChild(icon);
                refreshLucideIcons();
                passwordInput.focus();
            });
        }

        function updateCapsHint(event) {
            if (!capsHint) return;
            const on = event.getModifierState && event.getModifierState('CapsLock');
            capsHint.classList.toggle('visible', !!on);
        }
        if (passwordInput) {
            ['keydown', 'keyup', 'click'].forEach(function (evt) {
                passwordInput.addEventListener(evt, updateCapsHint);
            });
        }

        // Dynamic Branding Loading based on username (Tenant.User)
        const logoImg = document.getElementById('login-logo-img');
        const titleText = document.getElementById('login-title-text');
        
        let lastDetectedTenant = '';
        const defaultLogo = 'assets/img/system/img_69581d7fcdbbf.jpeg';

        function detectTenantFromLogin(raw) {
            const val = (raw || '').trim();
            if (!val) return '';

            if (val.includes('@')) {
                const parts = val.split('@', 2);
                const domain = (parts[1] || '').trim().toLowerCase();
                if (domain && !domain.includes('.')) {
                    return domain.replace(/[^a-z0-9-]/g, '');
                }
                const localMatch = domain.match(/^([a-z0-9-]+)\.local$/);
                if (localMatch) {
                    return localMatch[1].replace(/[^a-z0-9-]/g, '');
                }
                return '';
            }

            if (val.includes('.')) {
                const segments = val.split('.').map(s => s.trim().toLowerCase().replace(/[^a-z0-9-]/g, '')).filter(Boolean);
                if (segments.length >= 2) {
                    return segments[segments.length - 1];
                }
            }

            return '';
        }

        async function updateLoginBranding() {
            const tenant = detectTenantFromLogin(usernameInput.value);

            if (tenant === lastDetectedTenant) return;
            lastDetectedTenant = tenant;

            if (tenant !== '') {
                try {
                    const res = await fetch(`api.php?action=get_tenant_branding&tenant=${encodeURIComponent(tenant)}`);
                    const data = await res.json();
                    
                    if ((data.ok || data.success) && data.company_logo) {
                        logoImg.src = data.company_logo;
                        if (data.company_name) {
                            titleText.textContent = data.company_name;
                        }
                    }
                } catch (e) {
                    console.warn('Erro ao carregar branding do tenant:', e);
                }
            } else {
                logoImg.src = defaultLogo;
                titleText.textContent = 'LUB-TEK 3.0';
            }
        }

        usernameInput.addEventListener('input', updateLoginBranding);
        usernameInput.addEventListener('blur', updateLoginBranding);
        window.addEventListener('DOMContentLoaded', updateLoginBranding);

        // Submissão do Formulário e Suporte ao Enter
        const loginForm = document.getElementById('login-form');
        
        document.querySelectorAll('#username, #password').forEach(input => {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (typeof loginForm.requestSubmit === 'function') {
                        loginForm.requestSubmit();
                    } else {
                        loginForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }
                }
            });
        });

        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const alertError = document.getElementById('alert-error');
            const alertSuccess = document.getElementById('alert-success');
            const btn = document.getElementById('btn-submit');
            let loginFinished = false;

            const resetBtn = () => {
                btn.disabled = false;
                btn.textContent = 'Entrar no Sistema';
            };

            const fallbackSubmit = () => {
                if (loginFinished) return;
                resetBtn();
                HTMLFormElement.prototype.submit.call(loginForm);
            };

            alertError.style.display = 'none';
            alertSuccess.style.display = 'none';

            try {
                sessionStorage.setItem('lubtek_login_user', username);
            } catch (e) { /* ignore */ }

            if (!username || !password) {
                alertError.textContent = 'Preencha usuário e senha.';
                alertError.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Entrando...';

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 20000);

            try {
                const response = await fetch('api.php?action=login', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json; charset=UTF-8' },
                    body: JSON.stringify({ username: username, password: password }),
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseErr) {
                    console.error('Resposta inválida do servidor:', text.substring(0, 200));
                    alertError.textContent = 'Resposta inválida do servidor. Tentando método alternativo...';
                    alertError.style.display = 'block';
                    fallbackSubmit();
                    return;
                }

                if (result.success || result.ok) {
                    loginFinished = true;
                    alertSuccess.textContent = 'Login realizado! Redirecionando...';
                    alertSuccess.style.display = 'block';

                    const homePage = result.home_page || 'home';
                    const home = 'index.php?page=' + encodeURIComponent(homePage);

                    const redirectHint = document.getElementById('login-redirect-hint');
                    const redirectLink = document.getElementById('login-redirect-link');
                    if (redirectLink) redirectLink.href = home;
                    if (redirectHint) redirectHint.style.display = 'block';

                    // Redirecionamento imediato (sem esperar setTimeout)
                    window.location.replace(home);

                    // Fallback caso o navegador bloqueie a navegação
                    setTimeout(() => { window.location.href = home; }, 1500);
                    return;
                } else {
                    alertError.textContent = result.message || result.error || 'Usuário ou senha inválidos.';
                    alertError.style.display = 'block';
                    resetBtn();
                }
            } catch (error) {
                clearTimeout(timeoutId);
                if (loginFinished) return;
                console.warn('Fetch falhou:', error);
                if (error.name === 'AbortError') {
                    alertError.textContent = 'Servidor demorou para responder. Tentando método alternativo...';
                } else {
                    alertError.textContent = 'Erro de conexão. Tentando método alternativo...';
                }
                alertError.style.display = 'block';
                fallbackSubmit();
            }
        });

    </script>
</body>

</html>
