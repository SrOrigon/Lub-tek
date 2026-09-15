<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

if (!isset($auth)) {
    $auth = new AuthSystem();
}
if (!isset($currentUser)) {
    $currentUser = $auth->getCurrentUser();
}

$companyName = 'LUB-TEK';
$companyLogo = 'assets/img/system/img_69581d7fcdbbf.jpeg';

if ($auth->isLoggedIn()) {
    $tenant = TenantResolver::getCurrentTenant();
    try {
        // Evita travar a página enquanto cria/migra o SQLite no primeiro acesso
        $dbPath = TenantResolver::resolvePath($tenant);
        if (is_file($dbPath)) {
            $customName = DB::getSystemMeta('company_name', $tenant);
            $customLogo = DB::getSystemMeta('company_logo', $tenant);

            if ($customName) {
                $companyName = $customName;
            } else if ($tenant) {
                $companyName = ucfirst($tenant);
            }

            if ($customLogo) {
                $companyLogo = $customLogo;
            }
        } else if ($tenant) {
            $companyName = ucfirst($tenant);
        }
    } catch (Throwable $e) {
        if ($tenant) {
            $companyName = ucfirst($tenant);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($companyName); ?> | Gestão Inteligente de Ativos 4.0</title>
    <meta name="description"
        content="Plataforma avançada de Gestão de Ativos, Digital Twin e Manutenção Preditiva com Inteligência Artificial. Otimize sua planta industrial com o <?php echo htmlspecialchars($companyName); ?>.">
    <meta name="keywords"
        content="Gestão de Ativos, Manutenção Preditiva, Digital Twin, Indústria 4.0, PCM, Lubrificação, Inteligência Artificial">
    <meta name="author" content="LUB-TEK Solutions">

    <!-- PWA / Mobile Ecosystem -->
    <link rel="manifest" href="assets/pwa/manifest.webmanifest">
    <meta name="theme-color" content="#0284c7">
    <link rel="apple-touch-icon" href="assets/img/system/img_69581d7fcdbbf.jpeg">

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="LUB-TEK | Gestão Inteligente de Ativos">
    <meta property="og:description" content="Revolucione sua manutenção com Digital Twin e IA.">
    <meta property="og:image" content="assets/img/system/img_69581d7fcdbbf.jpeg">

    <link rel="shortcut icon" href="assets/img/system/img_69581d7fcdbbf.jpeg" type="image/jpeg">
    <link rel="stylesheet" href="assets/css/responsive.css?v=3.2.6">
    <!-- Modern Fonts & Icons -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap"
        rel="stylesheet">

    <script src="assets/js/security_guard.js" defer></script>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"
        defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" defer></script>
    <!-- EXCELJS for Advanced Import (Images) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js" defer></script>
    <style>
        /* --- PREMIUM LIGHT THEME (Clean & Professional) --- */
        :root {
            /* Core Palette - Clean Slate & Professional Blue */
            --bg-body: #f1f5f9;
            /* Slate 100 - Soft Light Grey */
            --bg-sidebar: #ffffff;
            --bg-card: #ffffff;
            --bg-input: #f8fafc;
            /* Slate 50 */

            --border: #e2e8f0;
            /* Slate 200 */
            --border-hover: #bae6fd;
            /* Sky 200 */

            --primary: #0284c7;
            /* Sky 600 - Stronger Blue for Light Mode */
            --primary-glow: rgba(14, 165, 233, 0.15);
            --primary-dark: #0369a1;
            /* Sky 700 */

            --text-main: #0f172a;
            /* Slate 900 */
            --text-muted: #64748b;
            /* Slate 500 */

            --success: #10b981;
            /* Emerald 500 */
            --danger: #ef4444;
            /* Red 500 */
            --warning: #f59e0b;
            /* Amber 500 */

            --glass-blur: 8px;
            /* Subtle blur for light mode context */
            --radius-md: 8px;
            --radius-lg: 16px;
        }

        /* OFFLINE INDICATOR */
        #offline-status {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--danger);
            color: white;
            text-align: center;
            padding: 5px;
            font-weight: bold;
            font-size: 0.8rem;
            z-index: 9999;
            display: none;
            /* Hidden by default */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        #offline-status.syncing {
            background: var(--warning);
            color: white;
        }

        #offline-status.online {
            background: var(--success);
        }

        /* Correção da Caixa de Entrada de Texto da Lúbria e Chat Inputs */
        .lubria-chat-input, 
        textarea#prompt, 
        textarea#neural-prompt, 
        input[type="text"].chat-input-field, 
        #neural-input {
            background-color: #1e293b !important; /* Fundo escuro elegante */
            color: #ffffff !important;           /* Texto 100% branco e legível */
            border: 1px solid #38bdf8 !important; /* Borda azul clara de destaque */
            caret-color: #38bdf8 !important;     /* Cursor visível */
        }

        /* Cor do texto placeholder (quando está vazio) */
        .lubria-chat-input::placeholder, 
        textarea#prompt::placeholder, 
        textarea#neural-prompt::placeholder, 
        input[type="text"].chat-input-field::placeholder, 
        #neural-input::placeholder {
            color: #94a3b8 !important;
            opacity: 1;
        }

        /* CONNECTIVITY BADGE */
        .connectivity-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 700;
            margin-left: 10px;
            vertical-align: middle;
            transition: all 0.3s ease;
        }
        .connectivity-badge.online {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .connectivity-badge.offline {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }
        .connectivity-badge .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }
        .connectivity-badge.online .dot {
            background: #10b981;
            box-shadow: 0 0 6px #10b981;
            animation: pulse-green 2s infinite;
        }
        .connectivity-badge.offline .dot {
            background: #ef4444;
            box-shadow: 0 0 6px #ef4444;
        }
        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                box-shadow: 0 0 0 5px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            outline: none;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: var(--bg-body);
            /* Subtle Mesh Gradient for depth - Light Version */
            background-image:
                radial-gradient(circle at 10% 20%, rgba(224, 242, 254, 0.5) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(243, 232, 255, 0.5) 0%, transparent 20%);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            font-size: 16px;
            /* Pragmatic Readability */
            line-height: 1.6;
        }

        /* --- SCROLLBAR (Darker thumb for light mode visibility) --- */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            /* Slate 300 */
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
            /* Slate 400 */
        }

        /* --- SIDEBAR --- */
        aside {
            width: 260px;
            /* Slight reduction for content space */
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 10;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.03);
            /* Subtle separation shadow */
        }

        /* ... existing brand styles ... */

        .nav-item:hover {
            background: #f1f5f9;
            color: var(--text-main);
            transform: translateX(3px);
            /* Subtler movement */
        }

        /* Clean Numbers */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .brand {
            padding: 15px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 5px;
        }

        .brand img {
            width: 60%;
            max-width: 90px;
            border-radius: 12px;
            /* No glow needed in light mode, just clean rendering */
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.05));
            background: #fff;
            /* Ensure logo looks right if it has transparency or white bg */
            padding: 4px;
        }

        .brand div {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--primary);
            letter-spacing: 0.5px;
            /* Solid color for better contrast on white */
            background: none;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: initial;
        }

        .nav {
            padding: 15px 20px;
            /* More breathing room */
            display: flex;
            flex-direction: column;
            gap: 8px;
            /* Separated items */
            overflow-y: auto;
        }

        .nav-item {
            padding: 14px 20px;
            /* Larger touch targets */
            border-radius: 12px;
            /* Softer */
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 1rem;
            /* Clearer text */
            border: 1px solid transparent;
        }

        .nav-item:hover {
            background: #f1f5f9;
            color: var(--text-main);
            transform: translateX(5px);
            /* Playful feedback */
        }

        .nav-item.active {
            background: rgba(2, 132, 199, 0.08);
            /* Primary Very Light */
            color: var(--primary);
            border: 1px solid rgba(2, 132, 199, 0.1);
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.1);
            /* Soft glow */
        }

        .nav-item i {
            transition: transform 0.3s;
        }

        .nav-item:hover i {
            transform: scale(1.1);
        }

        /* --- MAIN CONTENT --- */
        main {
            flex: 1;
            min-height: 0;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: transparent;
            width: 100%;
            max-width: 100%;
        }

        .view-container {
            flex: 1;
            min-height: 0;
            padding: 24px;
            overflow-y: auto;
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            max-width: 100%;
            width: 100%;
        }

        /* Ensure inputs don't break layout */
        input,
        select,
        textarea {
            max-width: 100%;
        }

        .view-container.active {
            display: block;
            opacity: 1;
            transform: none;
        }

        .view-container.view-flex.active {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .view-container.view-block.active {
            display: block;
            min-height: 0;
        }

        /* Workspaces com scroll interno — evita blocos brancos gigantes */
        .dash-workspace.active,
        .assets-workspace.active {
            overflow: hidden !important;
        }

        #view-home.view-flex.active {
            overflow-y: auto;
        }

        .dash-workspace .card:hover,
        .assets-workspace .card:hover {
            transform: none;
        }

        /* --- HEADINGS --- */
        h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            /* Bolder */
            font-size: 2.4rem;
            margin-bottom: 30px;
            color: var(--text-main);
            letter-spacing: -1px;
        }

        h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            color: var(--text-main);
            margin-bottom: 20px;
        }

        h3 {
            font-size: 0.95rem;
            /* Readable subheaders */
            color: var(--text-muted);
            margin-bottom: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- CARDS & GLASS PANELS --- */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            /* Very friendly */
            padding: 32px;
            /* Spacious */
            /* Stronger shadow for "lifted" paper look */
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.08);
            /* Lift effect */
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }

        /* --- FORMS (Pragmatic & Big) --- */
        input,
        select,
        textarea {
            background: #f8fafc;
            /* Clearly interactive area */
            border: 1px solid var(--border);
            color: var(--text-main) !important;
            border-radius: 12px;
            /* Soft corners */
            padding: 14px 20px;
            /* Big click area */
            width: 100%;
            transition: all 0.2s;
            font-size: 1rem;
            /* No squinting */
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.1);
            /* Obvious focus ring */
            background: #ffffff;
        }

        ::placeholder {
            color: #94a3b8;
        }

        label {
            color: var(--text-muted) !important;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 8px;
            display: block;
        }

        /* --- BUTTONS (Pragmatic Actions) --- */
        button.btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border: none;
            padding: 14px 28px;
            /* Big & Confident */
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 16px -4px var(--primary-glow);
            font-size: 1rem;
        }

        button.btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -4px rgba(56, 189, 248, 0.5);
        }

        button.btn:active {
            transform: translateY(1px);
        }

        button.btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            /* Thicker border */
            color: var(--text-main);
            padding: 12px 26px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }

        button.btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(56, 189, 248, 0.05);
        }

        button.btn-danger {
            background: var(--danger);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        button.btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(239, 68, 68, 0.4);
        }

        button.btn-sm, .btn.btn-sm {
            padding: 8px 16px;
            font-size: 0.85rem;
            border-radius: 10px;
            min-height: 36px;
            font-weight: 700;
        }

        button.btn-ghost {
            background: transparent;
            border: 1px dashed var(--border);
            color: var(--text-muted);
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        button.btn-ghost:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(2, 132, 199, 0.05);
        }

        /* Foco visível — navegação por teclado */
        :focus-visible {
            outline: 3px solid var(--primary);
            outline-offset: 2px;
        }

        button:focus:not(:focus-visible),
        a:focus:not(:focus-visible) {
            outline: none;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* --- UTILITIES --- */
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        /* --- PRINT STYLES (Fixed & Robust) --- */
        @media print {
            @page {
                margin: 1cm;
                size: auto;
            }

            :root {
                --bg-body: #fff !important;
                --text-main: #000 !important;
                --text-muted: #333 !important;
            }

            body,
            html {
                background: white !important;
                height: auto !important;
                overflow: visible !important;
            }

            /* Hide Navigation & UI */
            aside,
            .btn,
            .no-print,
            .asset-sticky-header,
            .nav,
            .brand,
            ::-webkit-scrollbar {
                display: none !important;
            }

            /* Reset Layout Containers to allow flow */
            main,
            .view-container,
            .card,
            #asset-form {
                position: relative !important;
                top: auto !important;
                left: auto !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                display: block !important;
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                transform: none !important;
                opacity: 1 !important;
            }

            /* Specific: If printing Asset Details, hide the Tree */
            #asset-tree-panel {
                display: none !important;
            }

            /* Typography & Colors */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color: black !important;
            }

            input,
            select,
            textarea {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
            }

            /* Ensure groups don't break weirdly */
            .lub-group {
                border: 1px solid #333 !important;
                break-inside: avoid;
                margin-bottom: 20px !important;
            }
        }

        .tree-node .node-content {
                color: var(--text-main);
            }

            .tree-node:hover>.node-content {
                background: #f1f5f9;
            }

            .tree-node.active-item>.node-content {
                background: var(--primary) !important;
                color: #fff !important;
                box-shadow: 0 4px 6px rgba(2, 132, 199, 0.2);
            }

            /* --- TOASTS --- */
            #toast-container {
                position: fixed;
                top: 24px;
                right: 24px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .toast {
                background: white;
                color: #1e293b;
                padding: 16px 20px;
                border-radius: 12px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 320px;
                border: 1px solid var(--border);
                animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            }

            .toast.success {
                border-left: 4px solid var(--success);
            }

            .toast.error {
                border-left: 4px solid var(--danger);
            }

            .toast.warning {
                border-left: 4px solid var(--warning);
            }

            /* List Item for Light Mode */
            .list-item {
                background: #ffffff;
                border-bottom: 1px solid var(--border);
                color: var(--text-main);
            }

            .list-item:hover {
                background: #f8fafc;
            }

            .list-item.active-item,
            .cat-list-item.active-item {
                background: #e0f2fe !important;
                border-color: #7dd3fc !important;
                box-shadow: inset 4px 0 0 var(--primary);
            }

            /* --- FULL SCREEN OVERLAY (Assets/etc) --- */
            .full-screen-active {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                z-index: 99999 !important;
                background: white !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 40px !important;
                overflow-y: auto !important;
                max-width: none !important;
                box-shadow: none !important;
                display: flex !important;
                /* Ensure it's visible if it was hidden */
                flex-direction: column;
            }

            body.hide-sidebar aside {
                display: none !important;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateX(50px);
                }

                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(50px);
                }
            }

            .toast.info {
                border-left: 4px solid var(--primary);
            }

            .toast-large {
                min-width: 360px;
                padding: 20px 24px;
                font-size: 1rem;
                font-weight: 700;
                box-shadow: 0 16px 40px rgba(0, 0, 0, 0.18);
            }
            .toast-large .toast-message {
                font-size: 1rem;
                line-height: 1.4;
            }
            body.password-reset-lock {
                overflow: hidden !important;
            }
            body.password-reset-lock main,
            body.password-reset-lock .lubtek-topbar {
                pointer-events: none;
                user-select: none;
            }
            #modal-troca-senha-obrigatoria {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.88);
                backdrop-filter: blur(8px);
                z-index: 99999;
                justify-content: center;
                align-items: center;
            }
            #modal-troca-senha-obrigatoria.active {
                display: flex !important;
            }
            body.password-reset-lock {
                overflow: hidden !important;
            }
            body.password-reset-lock main,
            body.password-reset-lock .lubtek-topbar {
                pointer-events: none;
                user-select: none;
            }

            @media (max-width: 768px) {
                body {
                    flex-direction: column;
                    height: auto;
                    overflow-y: auto;
                }

                /* --- LUB-TEK MOBILE-FIRST RESPONSIVE ENGINE --- */
                .lub-row, .lub-form-row {
                    grid-template-columns: 1fr !important;
                    gap: 12px !important;
                }
                
                [style*="grid-template-columns"] {
                    grid-template-columns: 1fr !important;
                    gap: 15px !important;
                }
                
                .assets-main-layout {
                    flex-direction: column !important;
                    height: auto !important;
                }
                
                #asset-tree-panel {
                    width: 100% !important;
                    max-width: 100% !important;
                    height: 350px !important;
                }
                
                #asset-form, #asset-empty {
                    width: 100% !important;
                    max-width: 100% !important;
                    height: auto !important;
                    min-height: 500px !important;
                }

                #pi-main-grid {
                    grid-template-columns: 1fr !important;
                    gap: 15px !important;
                }

                [style*="grid-template-columns: repeat(3, 1fr)"],
                [style*="grid-template-columns: repeat(4, 1fr)"] {
                    grid-template-columns: 1fr !important;
                    gap: 12px !important;
                }

                aside {
                    width: 100%;
                    height: auto;
                    border-right: none;
                    border-bottom: 1px solid var(--border);
                    position: relative;
                    padding-bottom: 10px;
                }

                main {
                    width: 100%;
                    height: auto;
                    overflow: visible;
                }

                .view-container {
                    padding: 15px;
                    height: auto !important;
                    overflow: visible !important;
                }

                .grid-2,
                .grid-3 {
                    grid-template-columns: 1fr;
                }

                .card {
                    padding: 20px;
                }

                /* Fix status cards on mobile */
                [style*="grid-template-columns: repeat(3, 1fr)"] {
                    grid-template-columns: 1fr !important;
                    gap: 10px !important;
                }

                /* Tables responsive */
                .lub-table {
                    font-size: 0.75rem;
                }

                .lub-table th,
                .lub-table td {
                    padding: 8px 6px;
                }

                /* Buttons responsive */
                .btn,
                .btn-outline {
                    padding: 10px 14px !important;
                    font-size: 0.8rem !important;
                }

                /* Flex-between stacks on mobile */
                .flex-between {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 15px;
                }

                /* Header improvements */
                h1 {
                    font-size: 1.6rem;
                    margin-bottom: 10px;
                }

                /* Tab buttons */
                .tab-btn {
                    font-size: 0.75rem;
                    padding-bottom: 6px;
                }

                .nav {
                    display: flex;
                    flex-direction: row;
                    overflow-x: auto;
                    padding: 10px;
                    gap: 10px;
                }

                .nav-item {
                    flex: 0 0 auto;
                    padding: 10px;
                    font-size: 0.9rem;
                }

                .brand {
                    flex-direction: row;
                    justify-content: center;
                    padding: 15px;
                }

                .brand img {
                    width: 40px;
                }

                .brand div {
                    font-size: 1.2rem;
                }

                /* Offline Status adjustment */
                #offline-status {
                    position: fixed;
                    top: 0;
                }

                /* Mini stats */
                .mini-stat {
                    padding: 6px 10px;
                }

                .mini-stat .val {
                    font-size: 1rem;
                }
            }

            /* Extra small screens */
            @media (max-width: 480px) {
                .view-container {
                    padding: 10px;
                }

                h1 {
                    font-size: 1.3rem;
                }

                .card {
                    padding: 15px;
                    border-radius: 12px;
                }

                .btn,
                .btn-outline {
                    padding: 8px 10px !important;
                    font-size: 0.75rem !important;
                }

                /* Hide button text on very small screens */
                .btn i+span,
                .btn-outline i+span {
                    display: none;
                }
            }
    #cliente-readonly-banner {
        background: #ecfeff;
        color: #0e7490;
        text-align: center;
        font-size: 0.82rem;
        font-weight: 700;
        padding: 8px 16px;
        border-bottom: 1px solid #a5f3fc;
        z-index: 40;
        position: relative;
    }
    body.role-cliente .require-write,
    body.role-cliente [data-write] {
        display: none !important;
    }
    </style>
</head>

<body>
    <!-- PRELOADER (Instant Feedback) -->
    <div id="app-preloader"
        style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:#f8fafc; z-index:999999; display:flex; flex-direction:column; align-items:center; justify-content:center; transition: opacity 0.4s ease-out;">
        <img id="preloader-logo-img" src="<?php echo htmlspecialchars($companyLogo); ?>" style="width:80px; margin-bottom:20px; border-radius:12px; object-fit: contain;">
        <div id="preloader-title-text" style="font-family:'Inter',sans-serif; font-size:1.5rem; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($companyName); ?></div>
        <div
            style="margin-top:10px; width:40px; height:40px; border:3px solid #e2e8f0; border-top:3px solid #0284c7; border-radius:50%; animation:spin 0.8s linear infinite;">
        </div>
        <style>
            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>
    </div>
    <script>
        window.addEventListener('load', () => {
            const p = document.getElementById('app-preloader');
            if (p) {
                p.style.opacity = '0';
                setTimeout(() => p.remove(), 400);
            }
        });
        // Failsafe
        setTimeout(() => {
            const p = document.getElementById('app-preloader');
            if (p) p.style.display = 'none';
        }, 5000);
    </script>

    <div id="offline-status"></div>

    <!-- BARRA GLOBAL DE NAVEGACAO (ecossistema) -->
    <div id="lubtek-topbar" class="lubtek-topbar" role="navigation" aria-label="Navegação principal">
        <button type="button" class="topbar-home" onclick="nav('home')" title="Voltar ao Início">
            <img id="topbar-logo-img" src="<?php echo htmlspecialchars($companyLogo); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" width="28" height="28" style="object-fit: contain;">
            <span>Início</span>
        </button>
        <nav id="topbar-trail" class="topbar-trail" aria-label="Você está em"><strong id="breadcrumb-page-title"></strong></nav>
        <div class="topbar-spacer"></div>
        <div id="topbar-ecosystem" class="topbar-ecosystem">
            <?php if ($auth->isLoggedIn()): ?>
            <div class="topbar-quick-folders" style="display: flex; gap: 4px; align-items: center; margin-right: 6px;">
                <button type="button" onclick="if(typeof toggleHighContrastMode==='function')toggleHighContrastMode()" class="topbar-eco-btn" title="Modo Alto Contraste (Campo)">
                    <i data-lucide="eye" style="width: 14px; height: 14px; color: #10b981;"></i>
                    <span>Contraste</span>
                </button>
                <button type="button" onclick="if(typeof openFloatingDosageModal==='function')openFloatingDosageModal()" class="topbar-eco-btn" title="Calculadora de Dosagem em Campo">
                    <i data-lucide="calculator" style="width: 14px; height: 14px; color: #0284c7;"></i>
                    <span>Dosagem</span>
                </button>
                <button type="button" onclick="if(typeof openKittingShiftModal==='function')openKittingShiftModal()" class="topbar-eco-btn" title="Lista de Separação / Kitting do Turno">
                    <i data-lucide="package-check" style="width: 14px; height: 14px; color: #8b5cf6;"></i>
                    <span>Cesta do Dia</span>
                </button>
                <button type="button" onclick="if(typeof openGreaseMatrixModal==='function')openGreaseMatrixModal()" class="topbar-eco-btn" title="Matriz de Compatibilidade de Graxas">
                    <i data-lucide="shield-alert" style="width: 14px; height: 14px; color: #eab308;"></i>
                    <span>Compatibilidade</span>
                </button>
                <button type="button" onclick="nav('home')" class="topbar-eco-btn" title="Painel Principal">
                    <i data-lucide="layout-dashboard" style="width:14px; height:14px;"></i>
                    <span>Painel</span>
                </button>
                <button type="button" onclick="nav('assets')" class="topbar-eco-btn" title="Planta & Equipamentos">
                    <i data-lucide="folder-tree" style="width:14px; height:14px;"></i>
                    <span>Ativos</span>
                </button>
                <button type="button" onclick="nav('orders')" class="topbar-eco-btn" title="Ordens de Serviço">
                    <i data-lucide="check-square" style="width:14px; height:14px;"></i>
                    <span>Ordens</span>
                </button>
            </div>
            <button type="button" onclick="quickCreateOS()" class="topbar-eco-btn topbar-quick-os" data-gestor-only title="Registrar anomalia ou lubrificação em segundos">
                <i data-lucide="zap" style="width:14px; height:14px;"></i>
                <span>O.S. Rápida</span>
            </button>
            <?php endif; ?>
            <?php if ($auth->isLoggedIn() && ($auth->hasRole('gestor') || $auth->hasRole('developer') || $auth->hasRole('admin'))): ?>
            <button type="button" onclick="openBrandingModal()" class="topbar-eco-btn" style="color: #64748b; border-color: #cbd5e1;" title="Personalizar Marca (White-Label)">
                <i data-lucide="palette" style="width:14px; height:14px;"></i>
                <span>Branding</span>
            </button>
            <button type="button" onclick="openApiKeyModal()" class="topbar-eco-btn" style="color: #64748b; border-color: #cbd5e1;" title="Integrações IoT / Chave de API">
                <i data-lucide="key" style="width:14px; height:14px;"></i>
                <span>API Key</span>
            </button>
            <?php endif; ?>
        </div>
        <?php if (!empty($currentUser['name'])): ?>
        <span id="topbar-user" class="topbar-user">
            Olá, <?php echo htmlspecialchars($currentUser['name']); ?>
            <?php if (!empty($currentUser['role_label'])): ?>
                <span style="opacity:0.65; font-size:0.78em;"> · <?php echo htmlspecialchars($currentUser['role_label']); ?></span>
            <?php endif; ?>
            <?php if (!empty($currentUser['tenant_label']) && $currentUser['tenant_label'] !== 'Admin'): ?>
                <span style="opacity:0.75; font-size:0.85em;"> · <?php echo htmlspecialchars($currentUser['tenant_label']); ?></span>
            <?php endif; ?>
            <span id="connectivity-badge" class="connectivity-badge online"><span class="dot"></span> Online</span>
        </span>
        <?php else: ?>
        <span id="topbar-user" class="topbar-user"></span>
        <?php endif; ?>
        <a href="logout.php" class="topbar-logout" title="Encerrar sessão" onclick="return window.__lubtekLogout ? window.__lubtekLogout(event) : true;">
            <i data-lucide="log-out" style="width:16px;"></i>
            <span>Sair</span>
        </a>
        <script>
            // Logout via POST (evita CSRF de logout por GET/link de terceiros mantendo o mesmo botão/UX).
            window.__lubtekLogout = function (e) {
                e.preventDefault();
                fetch('logout.php', { method: 'POST', credentials: 'same-origin' })
                    .catch(function () {})
                    .finally(function () { window.location.href = 'login.php'; });
                return false;
            };
        </script>
    </div>

    <style>
        .lubtek-topbar {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            flex-shrink: 0;
            padding: 12px 20px;
            background: #fff;
            border-bottom: 1px solid var(--border, #e2e8f0);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
            position: sticky;
            top: 0;
            z-index: 500;
            flex-wrap: wrap;
        }
        .topbar-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border: none;
            border-radius: 10px;
            background: #f1f5f9;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
            color: #0f172a;
            transition: background 0.2s;
        }
        .topbar-home:hover { background: #e2e8f0; }
        .topbar-home img { border-radius: 6px; object-fit: cover; }
        .topbar-trail {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
        }
        .topbar-trail strong { color: #0f172a; font-weight: 700; }
        .topbar-spacer { flex: 1; min-width: 8px; }
        .topbar-ecosystem {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        .topbar-eco-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            color: #0284c7;
            cursor: pointer;
            white-space: nowrap;
        }
        .topbar-eco-btn:hover { background: #f0f9ff; border-color: #bae6fd; }
        .topbar-quick-os {
            background: linear-gradient(135deg, #0284c7, #0369a1) !important;
            color: #fff !important;
            border-color: transparent !important;
            font-weight: 700;
            min-height: 36px;
        }
        .topbar-quick-os:hover {
            background: linear-gradient(135deg, #0369a1, #075985) !important;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
        }
        .topbar-user {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
        }
        .topbar-logout {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
        }
        .topbar-logout:hover { background: #f1f5f9; color: #0f172a; }
        body.on-home .topbar-trail { display: none; }
    </style>

    <!-- CONTENT -->
    <main>