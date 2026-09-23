// --- GLOBAL CONSTANTS & OBJECTS (Must be declared first) ---
const API_URL = 'api.php';
window.isOfflineMode = false;

/** Evita warning "value null cannot be parsed" em inputs number/date */
function safeInputValue(val, forNumber) {
    if (val == null || val === '' || val === 'null' || val === 'undefined') {
        return forNumber ? '' : '';
    }
    return val;
}

function escapeAttr(str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/** Bloqueia javascript: e data: em URLs persistidas (XSS). */
function safeUrl(url) {
    if (url == null || url === '') return '';
    const s = String(url).trim();
    if (/^(javascript|data|vbscript):/i.test(s)) return '';
    if (/^\/\//.test(s)) return 'https:' + s;
    return s;
}
window.safeUrl = safeUrl;

function getCompanyLogoUrl() {
    const topbar = document.getElementById('topbar-logo-img');
    if (topbar && topbar.src) return topbar.src;
    return 'assets/img/system/img_69581d7fcdbbf.jpeg';
}
window.getCompanyLogoUrl = getCompanyLogoUrl;

// --- REAL-TIME EVENT SYSTEM (Global State) ---
const Events = {
    listeners: {},
    on(event, cb) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(cb);
    },
    emit(event, data) {
        if (this.listeners[event]) this.listeners[event].forEach(cb => cb(data));
    }
};
window.Events = Events;

// Auth Removed
window.onload = initApp;
window.isDragging3D = false; // Prevent ReferenceError

// --- NUCLEAR CSS FOR FULL SCREEN MODE (Bypass Cache) ---
const fsStyle = document.createElement('style');
fsStyle.innerHTML = `
        .full-screen-active {
            position: fixed !important; top: 0 !important; left: 0 !important;
            width: 100vw !important; height: 100vh !important;
            z-index: 999999 !important; background: white !important;
            padding: 40px !important; overflow-y: auto !important;
            display: flex !important; flex-direction: column !important;
            border: none !important; border-radius: 0 !important;
        }
        body.hide-sidebar aside { display: none !important; }
        body.hide-sidebar main { 
            margin: 0 !important; width: 100vw !important; max-width: 100vw !important; 
        }
    `;
document.head.appendChild(fsStyle);

function initApp() {
    lucide.createIcons();
    applyRoleBasedUI();

    // Router Logic Frontend Side
    const params = new URLSearchParams(window.location.search);
    let page = params.get('page');
    if (!page) {
        page = (typeof isTrabalhadorUser === 'function' && isTrabalhadorUser()) ? 'routes' : 'home';
    }
    if (page === 'ativos') page = 'assets';
    const directAssetId = params.get('id') || params.get('asset_id') || params.get('ativo_id');
    if (directAssetId && (page === 'home' || page === 'routes')) {
        page = 'assets';
    }

    // 1. Sidebar Active State
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    const found = document.querySelector(`.nav-item[onclick*="'${page}'"]`);
    if (found) found.classList.add('active');

    // 2. View Activation (Show the active view container)
    document.querySelectorAll('.view-container').forEach(el => el.classList.remove('active'));
    const targetId = 'view-' + (page === 'calc' ? 'calc' : page);
    const target = document.getElementById(targetId) || document.getElementById('view-home');
    if (target) {
        target.classList.add('active');
    }

    // 2b. Motor Neural — contexto correto na carga inicial
    window.__lubtekCurrentPage = page;
    if (window.NeuralAssistant) NeuralAssistant.onPageChange(page);

    // 3. Load Page Specifics
    if (page === 'dash') loadDash();
    if (page === 'assets' && typeof loadTree === 'function') loadTree();
    if (page === 'catalog' && typeof loadCatalog === 'function') loadCatalog();
    if (page === 'kpi' && typeof loadKpiView === 'function') loadKpiView();
    if (page === 'pi' && typeof loadPiView === 'function') loadPiView();
    if (page === 'routes' && typeof loadRoutes === 'function') loadRoutes();

    // Catálogo: ao redimensionar para desktop, restaura layout split
    if (!window.__catalogResizeBound) {
        window.__catalogResizeBound = true;
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                document.getElementById('view-catalog')?.classList.remove('mobile-detail-open');
            }
        });
    }

    // 4. 3D View Special
    if (page === '3d' && window.open3DView) window.open3DView();

    // 5. Register History Navigation (PopState)
    if (!window.hasPopStateRegistered) {
        window.addEventListener('popstate', (e) => {
            const page = new URLSearchParams(window.location.search).get('page') || 'home';
            nav(page, null, true);
        });
        window.hasPopStateRegistered = true;
    }

    // Check params after a slight delay
    setTimeout(checkURLParams, 100);

    // Barra global + ecossistema
    if (window.Ecosystem) {
        Ecosystem.updateTopBar(page);
    }
    if (window.AppEvents) {
        window.AppEvents.emit('view:changed', page);
    }
    if (window.AppEvents && !window.__appReadyEmitted) {
        window.__appReadyEmitted = true;
        window.AppEvents.emit('app:ready');
    }

    // Fechar menu de ferramentas ao clicar fora
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('admin-tools-menu');
        if (!menu || menu.style.display !== 'flex') return;
        if (!e.target.closest('#admin-tools-menu') && !e.target.closest('[onclick*="toggleAdminToolsMenu"]')) {
            menu.style.display = 'none';
        }
    });
}

// --- NOMES AMIGÁVEIS NO BREADCRUMB (usuários leigos) ---
const NOMBRES_AMIGAVEIS_PAGINAS = {
    home: 'Painel Principal',
    dash: 'Ordens de Serviço',
    assets: 'Máquinas e Equipamentos',
    catalog: 'Almoxarifado & Lubrificantes',
    calc: 'Calculadoras Técnicas',
    reports: 'Relatórios e Checklists',
    kpi: 'Relatório de Desempenho',
    routes: 'Roteiro de Campo / Checklist',
    users: 'Equipe e Acessos',
    audit: 'Histórico de Atividades',
    '3d': 'Visualizador 3D',
    pi: 'Sensores em Tempo Real',
    sap: 'Integração SAP'
};
window.NOMBRES_AMIGAVEIS_PAGINAS = NOMBRES_AMIGAVEIS_PAGINAS;

function atualizarTituloBreadcrumb(viewId, assetName) {
    const trail = document.getElementById('topbar-trail');
    const titleEl = document.getElementById('breadcrumb-page-title');
    if (!trail) return;

    if (viewId === 'home') {
        trail.innerHTML = '';
        return;
    }

    const label = NOMBRES_AMIGAVEIS_PAGINAS[viewId] || 'LUB-TEK';
    if (assetName) {
        trail.innerHTML = `<strong id="breadcrumb-page-title">${label}</strong> › ${String(assetName).replace(/</g, '&lt;')}`;
    } else if (titleEl) {
        titleEl.textContent = label;
    } else {
        trail.innerHTML = `<strong id="breadcrumb-page-title">${label}</strong>`;
    }
}
window.atualizarTituloBreadcrumb = atualizarTituloBreadcrumb;

// --- NAVIGATION ENGINE (SPA Mode) ---
window.__navReady = function(viewId, btn, isPopState = false) {
    if (viewId === '3d') {
        window.location.href = '?page=3d';
        return;
    }

    if (isTrabalhadorUser()) {
        const allowed = ['home', 'dash', 'assets', 'routes'];
        if (!allowed.includes(viewId)) {
            if (typeof showToast === 'function') showToast('Seu perfil de Trabalhador tem acesso apenas a Ordens de Serviço, Rotas e Especificações de Ativos.', 'warning');
            viewId = 'dash';
        }
    }

    const current = new URLSearchParams(window.location.search).get('page')
        || ((typeof isTrabalhadorUser === 'function' && isTrabalhadorUser()) ? 'routes' : 'home');
    if (current === viewId && !isPopState) {
        if (window.Ecosystem) Ecosystem.updateTopBar(viewId);
        const targetId = 'view-' + (viewId === 'calc' ? 'calc' : viewId);
        const target = document.getElementById(targetId);
        if (target && !target.classList.contains('active')) {
            document.querySelectorAll('.view-container').forEach(el => el.classList.remove('active'));
            target.classList.add('active');
        }
        // Mesma página: recarrega dados (evita placeholders presos)
        if (viewId === 'dash') { loadDash(); return; }
        if (viewId === 'home') {
            if (window.lucide) lucide.createIcons();
            return;
        }
        if (viewId === 'routes' && typeof loadRoutes === 'function') { loadRoutes(); return; }
        if (viewId === 'users' && typeof loadUsuarios === 'function') { loadUsuarios(); return; }
        if (viewId === 'kpi' && typeof loadKpiView === 'function') { loadKpiView(); return; }
        if (viewId === 'catalog' && typeof loadCatalog === 'function') { loadCatalog(); return; }
        return;
    }

    Session.saveView(viewId);

    // 1. Update address bar if not popstate
    if (!isPopState) {
        const url = new URL(window.location);
        url.searchParams.set('page', viewId);
        window.history.pushState({ page: viewId }, '', url.toString());
    }

    // 2. Sidebar Active State
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    const found = document.querySelector(`.nav-item[onclick*="'${viewId}'"]`);
    if (found) found.classList.add('active');

    // 3. View Activation
    document.querySelectorAll('.view-container').forEach(el => el.classList.remove('active'));
    const targetId = 'view-' + (viewId === 'calc' ? 'calc' : viewId);
    const target = document.getElementById(targetId) || document.getElementById('view-home');
    if (target) {
        target.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        target.scrollTop = 0;
    }

    // 4. Barra global de navegação
    if (window.Ecosystem) {
        Ecosystem.updateTopBar(viewId);
        if (viewId === 'home') Ecosystem.setCurrentAsset(null);
    }
    if (typeof atualizarTituloBreadcrumb === 'function') {
        const assetNode = window.editingNode || window.currentAsset;
        const assetName = assetNode && assetNode.nome ? assetNode.nome : null;
        atualizarTituloBreadcrumb(viewId, assetName);
    }

    // 4b. Motor Neural — atualiza contexto da IA
    window.__lubtekCurrentPage = viewId;
    if (window.NeuralAssistant) NeuralAssistant.onPageChange(viewId);

    // 5. Load Page Specifics dynamically
    if (viewId === 'dash') loadDash();
    if (viewId === 'home' && window.lucide) lucide.createIcons();
    if (viewId === 'assets' && typeof loadTree === 'function') loadTree();
    if (viewId === 'catalog' && typeof loadCatalog === 'function') loadCatalog();
    if (viewId === 'kpi' && typeof loadKpiView === 'function') loadKpiView();
    if (viewId === 'pi' && typeof loadPiView === 'function') loadPiView();
    if (viewId === 'routes' && typeof loadRoutes === 'function') loadRoutes();
    if (viewId === 'users' && typeof loadUsuarios === 'function') loadUsuarios();
    if (viewId === 'audit' && typeof loadAuditLogs === 'function') loadAuditLogs();

    if (window.AppEvents) {
        window.AppEvents.emit('view:changed', viewId);
    }

    // Sync legado (uma vez por sessão)
    if (window.AppEvents && !window.__appReadyEmitted) {
        window.__appReadyEmitted = true;
        window.AppEvents.emit('app:ready');
    }

    if (window.Ecosystem) Ecosystem.applyPendingNavigation(viewId);
};
window.nav = window.__navReady;

/** Oculta módulos e ferramentas conforme role/permissões */
function hasPerm(key) {
    const p = (window.currentUser && window.currentUser.permissions) ? window.currentUser.permissions : {};
    if (p.all === true) return true;
    const val = p[key];
    return val === true || val === 'read_only' || val === 'basic';
}

function isGestorUser() {
    const role = (window.currentUser && window.currentUser.role) ? window.currentUser.role : '';
    return role === 'developer' || role === 'admin' || role === 'gestor' || role === 'supervisor';
}

function isClienteUser() {
    const role = (window.currentUser && window.currentUser.role) ? String(window.currentUser.role).toLowerCase() : '';
    return role === 'cliente' || role === 'viewer' || role === 'leitura' || role === 'readonly' || role === 'read_only' || role === 'cliente_viewer';
}

function isTrabalhadorUser() {
    if (isClienteUser()) return false;
    const role = (window.currentUser && window.currentUser.role) ? window.currentUser.role : '';
    return role === 'trabalhador' || role === 'client' || role === 'user' || role === 'funcionario';
}

function applyRoleBasedUI() {
    const role = (window.currentUser && window.currentUser.role) ? window.currentUser.role.toLowerCase() : 'trabalhador';
    const isPower = isGestorUser() || role === 'developer';

    document.querySelectorAll('[data-admin-only]').forEach(el => {
        el.style.display = isPower ? '' : 'none';
    });
    document.querySelectorAll('[data-gestor-only]').forEach(el => {
        el.style.display = isPower ? '' : 'none';
    });
    document.querySelectorAll('[data-trabalhador-only]').forEach(el => {
        el.style.display = isTrabalhadorUser() ? '' : 'none';
    });
    document.querySelectorAll('[data-power-hint]').forEach(el => {
        el.style.display = isPower ? 'none' : '';
    });

    document.querySelectorAll('[data-perm]').forEach(el => {
        const perm = el.getAttribute('data-perm');
        el.style.display = hasPerm(perm) ? '' : 'none';
    });

    // Custom CSS requirements / Classes protection
    if (role === 'trabalhador') {
        document.querySelectorAll('.require-gestor, .require-admin').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.btn-delete-os, .config-panel').forEach(el => {
            el.remove(); // Remove completely
        });
    }

    if (role === 'gestor') {
        document.querySelectorAll('.require-admin').forEach(el => {
            el.style.display = 'none';
        });
    }

    document.body.classList.toggle('role-trabalhador', isTrabalhadorUser());
    document.body.classList.toggle('role-gestor', isGestorUser() && role !== 'developer');
    document.body.classList.toggle('role-developer', role === 'developer');
    document.body.classList.toggle('role-cliente', isClienteUser());

    if (isClienteUser()) {
        document.querySelectorAll('.require-gestor, .require-admin, .require-write, [data-write], .btn-delete-os').forEach(el => {
            el.style.display = 'none';
        });
        let bar = document.getElementById('cliente-readonly-banner');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'cliente-readonly-banner';
            bar.textContent = 'Conta de cliente: somente visualização. Criar, editar e excluir estão desativados.';
            document.body.insertBefore(bar, document.body.firstChild);
        }
    }
}

window.hasPerm = hasPerm;
window.isGestorUser = isGestorUser;
window.isTrabalhadorUser = isTrabalhadorUser;
window.isClienteUser = isClienteUser;

function toggleAdminToolsMenu() {
    const menu = document.getElementById('admin-tools-menu');
    if (menu) menu.style.display = menu.style.display === 'flex' ? 'none' : 'flex';
}

window.applyRoleBasedUI = applyRoleBasedUI;
window.toggleAdminToolsMenu = toggleAdminToolsMenu;

// --- DEEP LINKING (QR CODE SCAN) ---
async function checkURLParams() {
    const params = new URLSearchParams(window.location.search);
    const assetId = params.get('id') || params.get('asset_id') || params.get('ativo_id');
    const assetTag = params.get('tag');

    if (assetId || assetTag) {
        // 1. Switch View to Assets
        if (typeof nav === 'function') {
            const assetNavBtn = document.querySelector('.nav-item[onclick*="\'assets\'"]');
            nav('assets', assetNavBtn);
        }

        // 2. Wait for Tree (Poll)
        let attempts = 0;
        const poll = setInterval(async () => {
            attempts++;
            if (typeof loadTree !== 'function' || attempts > 25) { clearInterval(poll); return; }

            // If tree logic isn't ready or empty, try load
            if (!window.treeState || window.treeState.length === 0) {
                await loadTree();
            }

            // Try select
            if (window.treeState && window.treeState.length > 0) {
                let targetId = assetId;

                // Resolve by Tag if assetId not specified directly
                if (!targetId && assetTag && window.allNodesMap) {
                    for (let [id, node] of window.allNodesMap.entries()) {
                        if (node.tag && node.tag.trim().toLowerCase() === assetTag.trim().toLowerCase()) {
                            targetId = id;
                            break;
                        }
                    }
                }

                if (targetId && typeof selectNode === 'function') {
                    selectNode(targetId);
                    // Check if success (editingNode set)
                    if (window.editingNode && (window.editingNode.id == targetId || (assetTag && window.editingNode.tag == assetTag))) {
                        clearInterval(poll);
                        const tabs = document.querySelectorAll('.asset-tab');
                        if (tabs[0]) tabs[0].click();
                        showToast(`Ativo '${window.editingNode.nome}' carregado via QR Code!`, 'success');
                    }
                }
            }
        }, 300);
    }
}

// --- TOAST NOTIFICATION SYSTEM ---
function showToast(message, type = 'info', options = {}) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}${options.large ? ' toast-large' : ''}`;

    let icon = 'info';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'x-circle';
    if (type === 'warning') icon = 'alert-triangle';

    toast.innerHTML = `
                <i data-lucide="${icon}" class="toast-icon"></i>
                <div class="toast-message">${escapeHtml(message)}</div>
            `;

    container.appendChild(toast);
    if (typeof lucide !== 'undefined') lucide.createIcons();

    if (options.vibrate && navigator.vibrate) {
        navigator.vibrate(Array.isArray(options.vibrate) ? options.vibrate : 80);
    }

    const duration = options.duration || 2500;
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

window.showToast = showToast;

/** Feedback de campo: toast grande + vibração (operadores com luvas/celular) */
function notificarSucessoCampo(mensagem, tipo = 'success') {
    const pattern = tipo === 'warning' ? [70, 40, 70] : [80, 50, 80];
    showToast(mensagem, tipo, {
        large: true,
        vibrate: pattern,
        duration: tipo === 'warning' ? 3000 : 2800
    });
}
window.notificarSucessoCampo = notificarSucessoCampo;

/** Classe visual padronizada para badges de status (O.S. / ativos) */
function statusPillClass(status) {
    const s = String(status || 'pendente').toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '');
    if (['concluido', 'cancelado', 'ok', 'executada', 'normal'].includes(s)) return 'st-ok';
    if (['critico', 'critica', 'atrasado', 'atrasada', 'falha', 'falhatecnica'].includes(s)) return 'st-crit';
    return 'st-warn';
}
window.statusPillClass = statusPillClass;

/** O.S. rápida — atalho global no header para campo */
function quickCreateOS() {
    const page = new URLSearchParams(window.location.search).get('page') || 'home';
    const openForm = () => {
        if (typeof createNewOS === 'function') {
            createNewOS();
            if (typeof showToast === 'function') {
                showToast('Nova O.S. — preencha e salve.', 'info');
            }
        }
    };
    if (page === 'dash' || page === 'home') {
        if (page === 'home' && typeof nav === 'function') {
            nav('dash');
            setTimeout(openForm, 350);
        } else {
            openForm();
        }
    } else if (typeof nav === 'function') {
        nav('dash');
        setTimeout(openForm, 350);
    }
}
window.quickCreateOS = quickCreateOS;

// --- MARKETPLACE LOGIC ---
async function loadMarketData(catId) {
    const container = document.getElementById(`market-list-${catId}`);
    // If the element doesn't exist (e.g., old cache or view closed), stop.
    if (!container) return;

    try {
        const offers = await api('get_market_data', { cat_id: catId });
        if (offers.length === 0) {
            container.innerHTML = '<div style="color:var(--text-muted); font-size:0.9rem;">Nenhum parceiro vinculado a este item.</div>';
            return;
        }

        container.innerHTML = offers.map(o => `
                    <div style="background:rgba(0,0,0,0.05); padding:10px; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; gap:12px; align-items:center;">
                            <i data-lucide="external-link" style="width:14px; opacity:0.7;"></i>
                            <div>
                                <div style="font-weight:600; font-size:0.95rem;">${escapeHtml(o.vendor_name)}</div>
                                <a href="${escapeAttr(safeUrl(o.vendor_url))}" target="_blank" rel="noopener noreferrer" style="font-size:0.8rem; color:var(--primary); text-decoration:none;">${escapeHtml(o.vendor_url)}</a>
                            </div>
                        </div>
                        <div style="text-align:right; display:flex; align-items:center; gap:15px;">
                            <div style="font-size:1.1rem; font-weight:bold; color:var(--success);">R$ ${(parseFloat(o.price) || 0).toFixed(2)}</div>
                            <button onclick="delMarketOffer(${o.id}, ${catId})" style="border:none; background:transparent; color:var(--danger); cursor:pointer;"><i data-lucide="trash-2" style="width:16px;"></i></button>
                        </div>
                    </div>
                `).join('');
        lucide.createIcons();
    } catch (e) { console.error(e); }
}

// addMarketOffer definido mais abaixo (catálogo — com UI otimista)

async function delMarketOffer(id, catId) {
    if (confirm('Remover oferta?')) {
        await api('delete_market_offer', { id });
        loadMarketData(catId);
    }
}

// --- SESSION MANAGER ---
const Session = {
    getUserId: function () {
        let uid = localStorage.getItem('lubtek_uid');
        if (!uid) {
            uid = 'user_' + Date.now() + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('lubtek_uid', uid);
        }
        return uid;
    },
    saveView: function (viewId) {
        localStorage.setItem('lubtek_last_view', viewId);
    },
    getLastView: function () {
        return localStorage.getItem('lubtek_last_view') || 'home';
    }
};

// --- DASHBOARD: LUB-IT CONSOLE ENGINE (Work Order Intelligence) ---
let tasks = [];
let cachedCatalog = [];
let taskFilter = 'all';
let taskSearch = '';
let selectedOS = null;

async function loadDash() {
    // SAFETY GUARD: Check if we are on dashboard view
    const pEl = document.getElementById('dash-pending-count');
    const tableBody = document.getElementById('os-table-body');

    if (!pEl && !tableBody) return;

    // Reset background interval to avoid duplicates
    if (window.__dashInterval) {
        clearInterval(window.__dashInterval);
        window.__dashInterval = null;
    }

    // Set up silent polling for new OS
    window.__dashInterval = setInterval(async () => {
        const activeContainer = document.getElementById('view-dash');
        if (activeContainer && activeContainer.classList.contains('active') && !draggingOSId) {
            try {
                const taskData = await api('get_tasks');
                let taskArray = [];
                if (taskData) {
                    if (Array.isArray(taskData)) taskArray = taskData;
                    else if (taskData.data && Array.isArray(taskData.data)) taskArray = taskData.data;
                }

                const tasksChanged = window.ChangeDetector
                    ? window.ChangeDetector.hasChanged('dash-tasks-poll', taskArray)
                    : (taskArray.length !== tasks.length);
                if (tasksChanged) {
                    const grew = taskArray.length > tasks.length;
                    tasks = taskArray;
                    refreshOSView();
                    if (grew) showToast('Nova Ordem de Serviço Detectada!', 'warning');
                    
                    // Update header counts silently
                    const d = await api('get_stats');
                    if (d) {
                        if (document.getElementById('dash-pending-count')) document.getElementById('dash-pending-count').innerText = d.pending || 0;
                        if (document.getElementById('dash-crit-count')) document.getElementById('dash-crit-count').innerText = d.alerts || 0;
                        if (document.getElementById('dash-done-count')) document.getElementById('dash-done-count').innerText = d.done || 0;
                    }
                }
            } catch (e) {
                // Silent catch for background failures
            }
        }
    }, 15000);

    try {
        // 1. Basic Counts
        const d = await api('get_stats');
        if (d) {
            if (pEl) pEl.innerText = d.pending || 0;
            const cEl = document.getElementById('dash-crit-count');
            if (cEl) cEl.innerText = d.alerts || 0;
            const dEl = document.getElementById('dash-done-count');
            if (dEl) dEl.innerText = d.done || 0;
        }

        // 2. Load Tasks (O.S. List)
        const taskData = await api('get_tasks');

        // Handle API variability safely
        let taskArray = [];
        if (taskData) {
            if (Array.isArray(taskData)) taskArray = taskData;
            else if (taskData.data && Array.isArray(taskData.data)) taskArray = taskData.data;
        }

        tasks = taskArray; // Update Global Variable
        if (window.ChangeDetector) window.ChangeDetector.hasChanged('dash-tasks-poll', taskArray);
        refreshOSView();

        // 3. Dynamic Form Elements (Assets & Materials)
        await populateFormDropdowns();

        // Feedback for manual refresh
        if (window.event && window.event.type === 'click') {
            showToast('Painel de Operações atualizado.', 'success');
        }

        // Motor Neural — insights do dashboard
        if (typeof loadNeuralInsights === 'function') loadNeuralInsights();

    } catch (e) {
        showToast("Falha ao carregar dashboard.", "error");
    }
}

async function populateFormDropdowns() {
    const ativoSel = document.getElementById('f-os-ativo');
    const matSel = document.getElementById('f-os-material');

    if (!ativoSel) return;

    // Populate Assets
    // Populate Assets
    const treeData = await api('get_tree');
    const tree = Array.isArray(treeData) ? treeData : (treeData?.data || []);
    let assetOps = '<option value="">Selecione o Ativo...</option>';

    function traverse(nodes, depth = 0) {
        if (!nodes || !Array.isArray(nodes)) return;
        nodes.forEach(n => {
            const pad = '&nbsp;'.repeat(depth * 4);
            // Safe values
            const nome = n.nome || 'Sem nome';
            const tag = n.tag || '';
            assetOps += `<option value="${escapeAttr(n.id)}">${pad} ${escapeHtml(nome)} [${escapeHtml(tag)}]</option>`;
            if (n.children) traverse(n.children, depth + 1);
        });
    }

    if (tree && tree.length > 0) {
        traverse(tree);
    } else {
        assetOps += '<option value="" disabled>Nenhum ativo disponível</option>';
    }

    ativoSel.innerHTML = assetOps;

    // Populate Materials
    if (matSel) {
        const catalog = await api('get_catalog');
        let matOps = '<option value="">Selecione o Produto...</option>';
        if (Array.isArray(catalog)) {
            catalog.forEach(m => {
                matOps += `<option value="${escapeAttr(m.id)}">${escapeHtml(m.nome)} (${escapeHtml(m.fabricante || 'Geral')})</option>`;
            });
        }
        matSel.innerHTML = matOps;
    }
}

function filterOSText(val) {
    taskSearch = val.toLowerCase();
    refreshOSView();
}

function renderOSTable() {
    const body = document.getElementById('os-table-body');
    if (!body) return;

    try {
        // Safety check for global tasks variable
        const currentTasks = Array.isArray(tasks) ? tasks : [];

        const filtered = currentTasks.filter(t => {
            if (!t) return false;

            // Status Filter (case-insensitive, accent-safe)
            if (taskFilter !== 'all') {
                const sit = String(t.situacao || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                const want = String(taskFilter).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                if (sit !== want) return false;
            }

            // Text Filter
            if (taskSearch) {
                const s = taskSearch.toLowerCase();
                const desc = t.desc ? String(t.desc).toLowerCase() : '';
                const tid = String(t.id || '');
                const ip = t.ip ? String(t.ip).toLowerCase() : '';
                const cs = t.cod_serv ? String(t.cod_serv).toLowerCase() : '';
                const r = t.resp ? String(t.resp).toLowerCase() : '';
                const msap = t.materiais_sap ? String(t.materiais_sap).toLowerCase() : '';
                const atag = t.ativo_tag ? String(t.ativo_tag).toLowerCase() : '';
                const anome = t.ativo_nome ? String(t.ativo_nome).toLowerCase() : '';
                return desc.includes(s) || tid.includes(s) || ip.includes(s) || cs.includes(s) || r.includes(s) || msap.includes(s) || atag.includes(s) || anome.includes(s);
            }
            return true;
        });

        // Empty State
        if (filtered.length === 0) {
            const canCreate = !(typeof isTrabalhadorUser === 'function' && isTrabalhadorUser());
            body.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:60px;">
                    <div style="color:var(--text-muted);">
                        <i data-lucide="inbox" style="width:48px; height:48px; opacity:0.3; display:block; margin:0 auto 15px;"></i>
                        <div style="font-size:1.1rem; font-weight:600; margin-bottom:8px;">Nenhuma Ordem de Serviço encontrada</div>
                        ${canCreate ? `<div style="font-size:0.9rem; margin-bottom:20px;">Clique no botão abaixo para criar sua primeira O.S.</div>
                        <button onclick="createNewOS()" class="btn" style="padding:10px 25px; background:var(--primary);">
                            <i data-lucide="plus" style="width:16px;"></i> Nova O.S.
                        </button>` : `<div style="font-size:0.9rem;">Nenhuma ordem atribuída no momento.</div>`}
                    </div>
                </td></tr>`;

            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }

        // Render Rows
        body.innerHTML = filtered.map(t => {
            const id = t.id || '';
            const ip = t.ip || '';
            const cod = t.cod_serv || '';
            const desc = t.desc || 'Sem Descrição';
            const resp = t.resp || '-';
            const status = t.situacao || 'Pendente';
            const dateEmit = formatDateBR(t.data_emissao);
            const dateProg = formatDateBR(t.date);
            const dateExec = formatDateBR(t.data_execucao);

            const pillClass = statusPillClass(status);

            return `
                <tr onclick="selectOS(${id})" id="os-row-${id}" class="os-row" style="cursor:pointer; border-bottom:1px solid var(--border);">
                    <td style="padding:12px 10px;">
                        <span style="font-weight:700; color:var(--primary);">${escapeHtml(id)}</span>
                        ${t.materiais_sap ? `<div style="font-size:0.7rem; color:var(--text-muted); font-weight:700; margin-top:2px;">SAP: ${escapeHtml(t.materiais_sap)}</div>` : ''}
                    </td>
                    <td style="padding:12px 10px;">${escapeHtml(ip)}</td>
                    <td style="padding:12px 10px;">${escapeHtml(cod)}</td>
                    <td style="padding:12px 10px;">
                        <div style="font-weight:600; color:var(--text-main); margin-bottom:2px;">${escapeHtml(desc)}</div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">Técnico: ${escapeHtml(resp)}</div>
                    </td>
                    <td style="padding:12px 10px;"><span class="st-pill ${pillClass}">${escapeHtml(status)}</span></td>
                    <td style="font-size:0.85rem; color:var(--text-muted); padding:12px 10px;">${escapeHtml(dateEmit)}</td>
                    <td style="font-size:0.85rem; color:var(--text-muted); padding:12px 10px;">${escapeHtml(dateProg)}</td>
                    <td style="font-size:0.85rem; color:var(--text-muted); padding:12px 10px;">${escapeHtml(dateExec)}</td>
                </tr>`;
        }).join('');

        // Highlight selected
        if (selectedOS) {
            const row = document.getElementById(`os-row-${selectedOS.id}`);
            if (row) row.classList.add('selected');
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();

    } catch (e) {
        // Silent fail
    }
}

function formatDateBR(d) {
    if (!d) return '-';
    try {
        // Handle ISO strings or YYYY-MM-DD
        if (d.includes('T')) d = d.split('T')[0];
        const parts = d.split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    } catch (e) { }
    return d;
}

function showOSForm(show) {
    const form = document.getElementById('os-form-container');
    const overlay = document.getElementById('offcanvas-overlay');
    if (!form) return;
    if (show) {
        form.classList.add('os-form-visible');
        if (overlay) overlay.classList.add('active');
    } else {
        form.classList.remove('os-form-visible');
        if (overlay) overlay.classList.remove('active');
    }
}
window.showOSForm = showOSForm;

function selectOS(id) {
    selectedOS = tasks.find(t => t.id == id);
    if (!selectedOS) return;

    // UI Update
    document.querySelectorAll('#os-table-body tr').forEach(r => r.classList.remove('selected'));
    const row = document.getElementById(`os-row-${id}`);
    if (row) row.classList.add('selected');

    // Map to Form
    document.getElementById('f-os-id').value = selectedOS.id;
    document.getElementById('f-os-ip').value = selectedOS.ip || '';
    document.getElementById('f-os-ativo').value = selectedOS.ativo_id || '';
    document.getElementById('f-os-codserv').value = selectedOS.cod_serv || '';
    document.getElementById('f-os-serv').value = selectedOS.desc || '';
    document.getElementById('f-os-rota').value = selectedOS.rota || '';
    document.getElementById('f-os-material').value = selectedOS.materiais_sap || ''; // Note: materials_sap field is reused or distinct
    document.getElementById('f-os-prio').value = selectedOS.prio || 'Média';
    document.getElementById('f-os-situacao').value = selectedOS.situacao || 'Pendente';
    document.getElementById('f-os-condicao').value = selectedOS.condicao_servico || 'Rodando';
    document.getElementById('f-os-sap').value = selectedOS.materiais_sap || '';
    document.getElementById('f-os-reserva').value = selectedOS.reserva_almox || '';
    document.getElementById('f-os-pontos').value = safeInputValue(selectedOS.num_pontos, true) || '0';
    document.getElementById('f-os-complemento').value = safeInputValue(selectedOS.complemento, false);
    document.getElementById('f-os-dt-emissao').value = safeInputValue(selectedOS.data_emissao, false);
    document.getElementById('f-os-dt-prog').value = safeInputValue(selectedOS.date, false);
    document.getElementById('f-os-dt-exec').value = safeInputValue(selectedOS.data_execucao, false);
    document.getElementById('f-os-qtd-real').value = selectedOS.qtd_real || '';
    document.getElementById('f-os-horas').value = safeInputValue(selectedOS.horas_exec, true) || '0';
    document.getElementById('f-os-minutos').value = safeInputValue(selectedOS.minutos_exec, true) || '0';
    document.getElementById('f-os-resp').value = safeInputValue(selectedOS.resp, false);
    document.getElementById('f-os-codexec').value = safeInputValue(selectedOS.cod_exec, false) || '1';
    document.getElementById('f-os-conc').value = safeInputValue(selectedOS.conc_percent, true) || '0';
    document.getElementById('f-os-ph').value = safeInputValue(selectedOS.ph, true) || '0';
    document.getElementById('f-os-agua').value = safeInputValue(selectedOS.agua_l, true) || '0';
    document.getElementById('f-os-obsexec').value = selectedOS.obs_exec || '';
    document.getElementById('f-os-motivos').value = selectedOS.motivos || '';

    document.getElementById('btn-del-os').style.display = 'block';
    document.getElementById('form-title').innerText = `Editando O.S. #${selectedOS.id}`;
    showOSForm(true);
}

function createNewOS() {
    if (typeof isTrabalhadorUser === 'function' && isTrabalhadorUser()) {
        if (typeof showToast === 'function') showToast('Seu perfil não pode criar novas O.S. Use Rotas para concluir tarefas ou reportar alertas.', 'warning');
        return;
    }
    selectedOS = null;
    document.querySelectorAll('#os-table-body tr').forEach(r => r.classList.remove('selected'));
    // Reset Form
    const fields = ['f-os-id', 'f-os-ip', 'f-os-ativo', 'f-os-codserv', 'f-os-serv', 'f-os-rota', 'f-os-material',
        'f-os-sap', 'f-os-reserva', 'f-os-pontos', 'f-os-complemento', 'f-os-dt-emissao', 'f-os-dt-prog',
        'f-os-dt-exec', 'f-os-qtd-real', 'f-os-horas', 'f-os-minutos', 'f-os-resp', 'f-os-conc', 'f-os-ph',
        'f-os-agua', 'f-os-obsexec', 'f-os-motivos'];

    fields.forEach(f => {
        const el = document.getElementById(f);
        if (el) el.value = (el.type === 'number') ? 0 : '';
    });

    document.getElementById('f-os-prio').value = 'Média';
    document.getElementById('f-os-situacao').value = 'Pendente';
    document.getElementById('f-os-condicao').value = 'Rodando';
    document.getElementById('f-os-dt-emissao').value = new Date().toISOString().split('T')[0];
    document.getElementById('f-os-dt-prog').value = new Date().toISOString().split('T')[0];

    document.getElementById('btn-del-os').style.display = 'none';
    document.getElementById('form-title').innerText = 'Nova Ordem de Serviço';
    showOSForm(true);
}

async function saveOS() {
    const os = {
        id: selectedOS ? selectedOS.id : ('new' + Date.now()),
        ip: document.getElementById('f-os-ip').value,
        ativo_id: document.getElementById('f-os-ativo').value,
        cod_serv: document.getElementById('f-os-codserv').value,
        desc: document.getElementById('f-os-serv').value,
        rota: document.getElementById('f-os-rota').value,
        prio: document.getElementById('f-os-prio').value,
        situacao: document.getElementById('f-os-situacao').value,
        condicao_servico: document.getElementById('f-os-condicao').value,
        materiais_sap: document.getElementById('f-os-sap').value,
        reserva_almox: document.getElementById('f-os-reserva').value,
        num_pontos: document.getElementById('f-os-pontos').value,
        complemento: document.getElementById('f-os-complemento').value,
        data_emissao: document.getElementById('f-os-dt-emissao').value,
        date: document.getElementById('f-os-dt-prog').value,
        data_execucao: document.getElementById('f-os-dt-exec').value,
        horas_exec: document.getElementById('f-os-horas').value,
        minutos_exec: document.getElementById('f-os-minutos').value,
        resp: document.getElementById('f-os-resp').value,
        cod_exec: document.getElementById('f-os-codexec').value,
        conc_percent: document.getElementById('f-os-conc').value,
        ph: document.getElementById('f-os-ph').value,
        agua_l: document.getElementById('f-os-agua').value,
        obs_exec: document.getElementById('f-os-obsexec').value,
        motivos: document.getElementById('f-os-motivos').value,
        qtd_real: document.getElementById('f-os-qtd-real').value,
        done: document.getElementById('f-os-situacao').value === 'Concluído'
    };

    if (!os.desc) return showToast('Descrição do serviço é obrigatória.', 'warning');

    try {
        const res = await api('save_task', os);
        if (res.ok) {
            showToast('Ordem de serviço salva com sucesso!', 'success');
            showOSForm(false);
            selectedOS = null;
            await loadDash();
        } else {
            showToast(res.error || 'Erro ao salvar O.S.', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Erro técnico ao salvar.', 'error');
    }
}

async function deleteSelectedOS() {
    if (!selectedOS) return;
    if (confirm(`Excluir permanentemente a O.S. #${selectedOS.id}?`)) {
        await api('delete_task', { id: selectedOS.id });
        showToast('O.S. excluída.', 'info');
        selectedOS = null;
        showOSForm(false);
        loadDash();
    }
}

function filterOS(status, btn) {
    taskFilter = status;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderOSTable();
}

async function syncAssetData(assetId) {
    if (!assetId) return;
    try {
        // Find asset in tree or fetch its data
        // For now, assume tree is loaded in window.treeState (need to ensure this)
        // Or just fetch direct.
        const tree = await api('get_tree');
        let found = null;
        function search(nodes) {
            for (const n of nodes) {
                if (n.id == assetId) { found = n; break; }
                if (n.children) search(n.children);
            }
        }
        search(tree);

        if (found) {
            let tech = {};
            try {
                tech = typeof found.dados_tecnicos === 'string' ? JSON.parse(found.dados_tecnicos) : (found.dados_tecnicos || {});
                if (found.data) tech = { ...tech, ...found.data };
            } catch (e) { }

            // Auto-fill form
            // Auto-fill form
            if (tech.servico) document.getElementById('f-os-serv').value = tech.servico;
            if (tech.ip) document.getElementById('f-os-ip').value = tech.ip;

            // New Lub-IT Fields
            if (tech.ordem_rota) document.getElementById('f-os-rota').value = tech.ordem_rota;
            if (tech.cod_serv) document.getElementById('f-os-codserv').value = tech.cod_serv;
            if (tech.sap) document.getElementById('f-os-sap').value = tech.sap;
            if (tech.almox) document.getElementById('f-os-reserva').value = tech.almox;
            if (tech.complemento) document.getElementById('f-os-complemento').value = tech.complemento;
            if (tech.num_pontos) document.getElementById('f-os-pontos').value = tech.num_pontos;

            if (tech.material) {
                const matSel = document.getElementById('f-os-material');
                if (matSel) {
                    // Attempt to match by text if value not found, or just set if values match names
                    // Loop options to find fuzzy match? Or assume clean data.
                    // For now, try direct value.
                    matSel.value = tech.material; // Assumes option value is name

                    // If that didn't work (id vs name), try text match
                    if (!matSel.value) {
                        for (let opt of matSel.options) {
                            if (opt.text.includes(tech.material)) {
                                matSel.value = opt.value;
                                break;
                            }
                        }
                    }
                }
            }

            showToast(`Dados técnicos do ativo '${found.nome}' aplicados.`, 'info');
        }
    } catch (e) { }
}

function printCurrentOS() {
    if (!selectedOS) return showToast('Selecione uma O.S. para imprimir.', 'warning');
    window.print(); // Browser will print the whole page, but our CSS handles @media print
}

function closeOSForm() {
    selectedOS = null;
    document.querySelectorAll('#os-table-body tr').forEach(r => r.classList.remove('selected'));
    showOSForm(false);
}

// --- KPI LOGIC (Maintained for other views if needed, or compact background) ---
let kpiCharts = {};
let charts = {};

async function loadKPIs() {
    if (typeof loadKpiView === 'function') {
        await loadKpiView();
    }
}


// --- REATIVIDADE GLOBAL (sem recarregar arvore automaticamente) ---
Events.on('data_changed', (info) => {
    const view = Session.getLastView();
    if (view === 'dash') loadDash();
    if (view === 'catalog') loadCatalog();
    // Ativos: atualiza so apos save/delete explicito, com debounce
    if (view === 'assets' && info && info.action && /save|delete|update|set|apply|migrate|import/i.test(info.action)) {
        loadTree(true);
    }
});

function filterTasks(type, btn) {
    taskFilter = type;
    // Update buttons UI
    if (btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    renderTasks();
}

function renderTasks() {
    const pendingContainer = document.getElementById('task-list-pending');
    const doneContainer = document.getElementById('task-list-done');

    // Dashboard atual usa tabela de O.S. — delegar quando DOM legado nao existe
    if (!pendingContainer || !doneContainer) {
        refreshOSView();
        return;
    }

    // Safety check: ensure tasks is an array
    if (!Array.isArray(tasks)) {
        tasks = [];
    }

    // Filter Logic
    let activeTasks = tasks.filter(t => !t.done);
    let doneTasks = tasks.filter(t => t.done);

    if (taskFilter === 'high') {
        activeTasks = activeTasks.filter(t => t.prio === 'Alta' || t.prio === 'Crítica');
    }
    if (taskFilter === 'my') {
        // Filter by Session ID (Author) OR Responsible Name (approximate)
        activeTasks = activeTasks.filter(t => t.user_id === currentUserId || (t.resp && t.resp.toLowerCase().includes('eu')));
    }

    // Update Counts
    if (document.getElementById('dash-pending-count')) document.getElementById('dash-pending-count').innerText = tasks.filter(t => !t.done).length;
    if (document.getElementById('dash-done-count')) document.getElementById('dash-done-count').innerText = doneTasks.length;

    // Render Pending
    if (activeTasks.length === 0) {
        pendingContainer.innerHTML = '<div class="card" style="padding:20px; text-align:center; color:var(--text-muted); border-style:dashed;">Nenhuma ordem de serviço encontrada para este filtro.</div>';
    } else {
        pendingContainer.innerHTML = activeTasks.map(t => `
                    <div class="card" style="padding:15px; border-left:4px solid ${getPrioColor(t.prio)}; display:flex; gap:15px; align-items:center;">
                        <button onclick="toggleTask(${t.id})" style="background:transparent; border:2px solid var(--border); width:24px; height:24px; border-radius:6px; cursor:pointer; color:transparent; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.borderColor='var(--success)'; this.innerHTML='<svg width=14 height=14 viewBox=\'0 0 24 24\' fill=none stroke=currentColor stroke-width=2><polyline points=\'20 6 9 17 4 12\'/></svg>' " onmouseout="this.style.borderColor='var(--border)'; this.innerHTML=''"> </button>
                        <div style="flex:1;">
                            <div style="font-weight:600; font-size:1rem; color:white;">${escapeHtml(t.desc)}</div>
                            <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">
                                <i data-lucide="user" style="width:12px;"></i> ${escapeHtml(t.resp)} • 
                                <i data-lucide="calendar" style="width:12px;"></i> ${escapeHtml(formatDate(t.date))}
                            </div>
                        </div>
                        <span class="tag" style="background:${getPrioColor(t.prio)}20; color:${getPrioColor(t.prio)}; font-size:0.75rem; padding:4px 8px; border-radius:4px;">${escapeHtml(t.prio)}</span>
                        <button onclick="deleteTask(${t.id})" style="background:transparent; border:none; color:var(--danger); cursor:pointer;"><i data-lucide="trash-2" style="width:16px;"></i></button>
                    </div>
                `).join('');
    }

    // Render Done (Limit to 5 recent)
    doneContainer.innerHTML = doneTasks.slice(0, 5).map(t => `
                <div class="card" style="padding:10px 15px; background:rgba(0,0,0,0.2); display:flex; gap:15px; align-items:center;">
                    <button onclick="toggleTask(${t.id})" style="background:var(--success); border:none; width:24px; height:24px; border-radius:6px; cursor:pointer; color:white; display:flex; align-items:center; justify-content:center;"><i data-lucide="check" style="width:14px;"></i></button>
                    <div style="flex:1; text-decoration:line-through; color:var(--text-muted);">
                        ${escapeHtml(t.desc)}
                    </div>
                    <button onclick="deleteTask(${t.id})" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer;"><i data-lucide="trash-2" style="width:14px;"></i></button>
                </div>
            `).join('');

    lucide.createIcons();
}

// EXPOSE FUNCTIONS TO WINDOW (For Component Sync)
window.renderTasks = renderTasks;
window.reloadTasks = renderTasks; // Alias for compatibility
// updateCharts removed (deprecated)
// We also expose loadDash if available in this scope
if (typeof loadDash === 'function') window.loadDash = loadDash;


function getPrioColor(p) {
    if (p === 'Baixa') return '#10b981';
    if (p === 'Média') return '#f59e0b';
    if (p === 'Alta') return '#ef4444';
    if (p === 'Crítica') return '#a855f7';
    return '#94a3b8';
}

function formatDate(d) {
    if (!d) return 'Sem data';
    const [y, m, d_] = d.split('-');
    return `${d_}/${m}`;
}

function openTaskModal() {
    const modal = document.getElementById('task-modal');
    const descEl = document.getElementById('new-task-desc');
    const respEl = document.getElementById('new-task-resp');
    const dateEl = document.getElementById('new-task-date');

    if (modal) modal.style.display = 'flex';
    if (descEl) descEl.value = '';
    if (respEl) respEl.value = '';
    if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];

    // Populate Asset List if empty
    const sel = document.getElementById('new-task-asset');
    if (sel && sel.options.length <= 1) {
        loadAssetOptions();
    }
}

async function loadAssetOptions() {
    const sel = document.getElementById('new-task-asset');
    if (!sel) return;

    sel.innerHTML = '<option value="">Carregando...</option>';
    try {
        // Fetch simple list (reuse get_tree or get_catalog? tree is better for hierarchy but heavy. 
        // Ideally we need a lightweight list endpoint, but get_tree is cached/fast enough for now or we filter client side)
        // Actually, let's use the catalog API since it's lighter? No, catalog is parts.
        // We use get_tree and flatten it.
        const tree = await api('get_tree');

        let options = '<option value="">(Opcional) Geral / Sem Vínculo</option>';

        function traverseOptions(nodes, depth = 0) {
            nodes.forEach(n => {
                const pad = '&nbsp;'.repeat(depth * 4);
                // Only show Units, Areas, Equipment (skip points to reduce noise?)
                // Let's show everything but maybe visually distinct.
                options += `<option value="${escapeAttr(n.id)}">${pad} ${escapeHtml(n.nome)}</option>`;
                if (n.children) traverseOptions(n.children, depth + 1);
            });
        }
        traverseOptions(tree);
        sel.innerHTML = options;

    } catch (e) {
        sel.innerHTML = '<option value="">Erro ao carregar</option>';
    }
}

async function saveNewTask() {
    const desc = document.getElementById('new-task-desc').value;
    if (!desc) return showToast('Digite a descrição do serviço.', 'warning');

    const task = {
        id: 'new' + Date.now(), // Temporary ID logic, DB will assign real one on reload/insert
        desc: desc,
        resp: document.getElementById('new-task-resp').value || 'Não atribuído',
        date: document.getElementById('new-task-date').value,
        prio: document.getElementById('new-task-prio').value,
        ativo_id: document.getElementById('new-task-asset').value || null,
        done: false
    };

    // Save to API
    const res = await api('save_task', task);
    if (res.ok) {
        showToast('Ordem de serviço criada com sucesso!', 'success');
        const taskModal = document.getElementById('task-modal');
        if (taskModal) taskModal.style.display = 'none';
        loadDash(); // Reload to get definitive list from DB
    } else {
        showToast('Erro ao salvar no banco de dados.', 'error');
    }
}

async function toggleTask(id) {
    const t = tasks.find(x => x.id == id);
    if (t) {
        t.done = !t.done;
        t.situacao = t.done ? 'Concluído' : 'Pendente';
        await api('save_task', t); // Update status in DB
        renderTasks(); // Optimistic update or reload? Render is fast.
        loadDash(); // Sync counters
    }
}

async function deleteTask(id) {
    if (confirm('Excluir tarefa permanentemente da base de dados?')) {
        await api('delete_task', { id });
        loadDash();
    }
}

// --- EXPORT & PRINT LOGIC ---
function printPage() {
    window.print();
}

function generateAssetQRCode() {
    if (!editingNode) return showToast('Selecione um ativo.', 'warning');

    // Create Print Window
    const win = window.open('', '', 'width=450,height=550');
    const baseUrl = window.location.origin + window.location.pathname;
    const url = `${baseUrl}?page=assets&id=${editingNode.id}&asset_id=${editingNode.id}`;

    win.document.write(`
        <html>
        <head>
            <title>Etiqueta LUB-TEK - ${escapeHtml(editingNode.nome)}</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; text-align: center; padding: 20px; background: #fff; color: #1e293b; }
                .label-card { border: 2px solid #0f172a; padding: 20px; border-radius: 12px; display: inline-block; max-width: 320px; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
                .company-header { font-size: 0.8rem; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
                h2 { margin: 5px 0; font-size: 1.2rem; font-weight: 800; color: #0f172a; word-break: break-word; }
                .tag-badge { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; margin: 6px 0 14px 0; }
                .qm { margin: 15px auto; display: flex; justify-content: center; }
                .url-text { font-size: 0.65rem; color: #64748b; font-family: monospace; word-break: break-all; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="label-card">
                <div class="company-header">LUB-TEK SISTEMA INDUSTRIAL</div>
                <h2>${escapeHtml(editingNode.nome)}</h2>
                <div class="tag-badge">TAG: ${escapeHtml(editingNode.tag || 'S/N')}</div>
                <div id="qrcode" class="qm"></div>
                <div class="url-text">${url}</div>
            </div>
            <scr` + `ipt src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></scr` + `ipt>
            <scr` + `ipt>
                setTimeout(() => {
                    if (typeof QRCode !== 'undefined') {
                        new QRCode(document.getElementById("qrcode"), { 
                            text: "${url}", 
                            width: 160, 
                            height: 160
                        });
                        setTimeout(() => { window.print(); }, 500);
                    }
                }, 400);
            </scr` + `ipt>
        </body>
        </html>
    `);
    win.document.close();
}

async function exportCatalog(format) {
    const list = await api('get_catalog');
    const data = list.map(i => ({
        Nome: i.nome,
        Codigo: i.codigo,
        Fabricante: i.fabricante,
        Tipo: i.tipo,
        Estoque: i.estoque_atual,
        Local: i.localizacao
    }));

    const ts = new Date().toISOString().slice(0, 10);
    if (format === 'excel') {
        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Catalogo");
        XLSX.writeFile(wb, `CATALOGO_LUBTEK_${ts}.xlsx`);
    }
    else if (format === 'pdf') {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.text("Relatório de Catálogo - Rodrigo 3.0", 10, 10);
        doc.autoTable({
            head: [['Nome', 'Código', 'Fabr.', 'Tipo', 'Qtd', 'Local']],
            body: data.map(Object.values)
        });
        doc.save("Catalogo_Rodrigo3.pdf");
    }
}

async function exportCatalogExcel() {
    try {
        showToast('Preparando download...', 'info');
        const list = await api('get_catalog');

        if (!list || list.length === 0) return showToast('O inventário está vazio.', 'warning');
        if (typeof ExcelJS === 'undefined') return showToast('Biblioteca ExcelJS não carregada.', 'error');

        const workbook = new ExcelJS.Workbook();
        const sheet = workbook.addWorksheet('Inventário Técnico');

        // --- 1. CONFIGURAÇÃO DE COLUNAS ---
        sheet.columns = [
            { header: 'IP (ID)', key: 'ip', width: 10 },
            { header: 'ITEM / DESCRICAO', key: 'nome', width: 45 },
            { header: 'FABRICANTE', key: 'fabricante', width: 25 },
            { header: 'CODIGO / PN', key: 'codigo', width: 25 },
            { header: 'CATEGORIA', key: 'tipo', width: 20 },
            { header: 'ESTOQUE ATUAL', key: 'estoque', width: 15 },
            { header: 'LOCALIZAÇÃO', key: 'local', width: 20 },
            { header: 'ESPECIFICAÇÕES TÉCNICAS', key: 'specs', width: 60 },
            { header: 'OBSERVAÇÕES', key: 'desc', width: 40 }
        ];

        // --- 2. MAPEAMENTO DE DADOS ---
        const rows = list.map(item => {
            // Flatten specs efficiently
            let specStr = '';
            try {
                const s = typeof item.specs === 'string' ? JSON.parse(item.specs) : item.specs;
                if (s) {
                    specStr = Object.entries(s)
                        .map(([k, v]) => `${k.toUpperCase()}: ${v}`)
                        .join('; ');
                }
            } catch (e) { }

            return {
                ip: item.ip || item.id,
                nome: (item.nome || '').toUpperCase(),
                fabricante: (item.fabricante || '').toUpperCase(),
                codigo: (item.codigo || '').toUpperCase(),
                tipo: (item.tipo || '').toUpperCase(),
                estoque: parseInt(item.estoque_atual) || 0,
                local: (item.localizacao || '').toUpperCase(),
                specs: specStr,
                desc: item.descricao || ''
            };
        });

        sheet.addRows(rows);

        // --- 3. ESTILIZAÇÃO PREMIUM (SENIOR LEVEL) ---
        const headerRow = sheet.getRow(1);
        headerRow.height = 30;
        headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10, name: 'Arial' };
        headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F172A' } }; // Brand Dark
        headerRow.alignment = { vertical: 'middle', horizontal: 'center' };

        sheet.eachRow((row, rowNum) => {
            if (rowNum === 1) return;

            // Alignment & Border
            row.alignment = { vertical: 'middle', horizontal: 'left' };
            row.border = { bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } } };

            // Striped Rows
            if (rowNum % 2 === 0) {
                row.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF8FAFC' } };
            }

            // Data Formatting
            const stockCell = row.getCell('estoque');
            stockCell.alignment = { horizontal: 'center' };

            // Critical Stock Highlight
            if (stockCell.value <= 0) {
                stockCell.font = { color: { argb: 'FFEF4444' }, bold: true };
            } else if (stockCell.value < 5) {
                stockCell.font = { color: { argb: 'FFF59E0B' }, bold: true };
            }
        });

        // Auto-filter
        sheet.autoFilter = {
            from: 'A1',
            to: { row: 1, column: sheet.columns.length }
        };

        // --- 4. EXPORTAÇÃO ---
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        const ts = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        a.href = url;
        a.download = `LUBTEK_INVENTARIO_${ts}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showToast('Arquivo Excel gerado com sucesso!', 'success');

    } catch (e) {
        console.error(e);
        showToast('Erro ao gerar planilha.', 'error');
    }
}

function printChecklist() {
    if (!editingNode) return showToast('Selecione uma planta ou área para imprimir.', 'warning');
    const checklistPrintRows = [];
    function traverse(node, context = { area: '', equipment: '' }) {
        const type = (node.tipo || '').toLowerCase();
        let newContext = { ...context };
        if (type === 'setor' || type === 'area') newContext.area = node.nome;
        if (type === 'equipamento') newContext.equipment = node.nome;
        let tech = {};
        try {
            if (typeof node.dados_tecnicos === 'string') tech = JSON.parse(node.dados_tecnicos || '{}');
            else if (typeof node.dados_tecnicos === 'object') tech = node.dados_tecnicos || {};
            if (node.data) tech = { ...tech, ...node.data };
        } catch (e) { }
        if ((tech['qtd_material'] || tech['material'])) {
            checklistPrintRows.push({
                Area: newContext.area || 'Geral',
                Equipamento: newContext.equipment || node.nome,
                Tag: node.tag || '-',
                Ponto: tech['ponto_lub'] || node.nome,
                Material: tech['material'] || '-',
                Qtd: tech['qtd_material'] || '-'
            });
        }
        if (node.children && node.children.length > 0) node.children.forEach(child => traverse(child, newContext));
    }
    traverse(editingNode);
    if (checklistPrintRows.length === 0) return showToast('Nada para imprimir nesta área.', 'warning');

    const printArea = document.getElementById('print-area');
    const dateStr = new Date().toLocaleDateString();
    printArea.innerHTML = `
                <div class="print-header">
                    <img src="${getCompanyLogoUrl()}" class="print-logo-img">
                    <div class="print-title-center"><strong>CHECKLIST DE INSPECAO</strong><span>PROJETO: ${escapeHtml((editingNode.nome || '').toUpperCase())}</span></div>
                    <div class="print-info">DATA: ${dateStr}<br>PÁGINA: 1/1</div>
                </div>
                <div class="safety-box" style="background:#fff !important; color:#000 !important; border:2px solid #000 !important;">
                    <strong>RECOMENDACOES DE SEGURANCA (SSMA):</strong><br>
                    1. Realize o bloqueio LOTO antes de intervir em equipamentos parados.<br>
                    2. Utilize EPIs obrigatórios: Óculos, Luvas de Nitrila, Protetor Auricular e Calçado de Segurança.
                </div>
                <table class="print-table" style="color:#000 !important;">
                    <thead><tr><th>Item</th><th>Equipamento / Tag</th><th>Ponto / Lubrificante</th><th>Qtd</th><th>Condição</th><th>Status</th><th>Visto</th></tr></thead>
                    <tbody>${checklistPrintRows.map((r, i) => `
                        <tr>
                            <td style="text-align:center;">${i + 1}</td>
                            <td><strong>${escapeHtml(r.Equipamento)}</strong><br><small>${escapeHtml(r.Tag)}</small></td>
                            <td><strong>${escapeHtml(r.Ponto)}</strong><br><small>${escapeHtml(r.Material)}</small></td>
                            <td>${escapeHtml(r.Qtd)}</td>
                            <td>Parado / Rodando</td>
                            <td>[ ] OK [ ] NOK</td>
                            <td></td>
                        </tr>`).join('')}</tbody>
                </table>`;
    window.print();
}

function printAssetVTIMap() {
    if (!editingNode) return showToast('Selecione um ativo.', 'warning');
    const points = [];
    function traverse(node) {
        let tech = {};
        try {
            if (typeof node.dados_tecnicos === 'string') tech = JSON.parse(node.dados_tecnicos || '{}');
            else if (typeof node.dados_tecnicos === 'object') tech = node.dados_tecnicos || {};
            if (node.data) tech = { ...tech, ...node.data };
        } catch (e) { }
        if ((node.tipo === 'ponto' || tech['ponto_lub'] || tech['material'])) {
            points.push({
                ip: tech.ip || node.id,
                ponto: tech['ponto_lub'] || node.nome,
                material: tech['material'] || '-',
                qty: tech['qtd_material'] || '-'
            });
        }
        if (node.children) node.children.forEach(traverse);
    }
    traverse(editingNode);
    if (points.length === 0) return showToast('Nenhum ponto neste ativo.', 'info');

    const date = new Date().toLocaleDateString();
    const html = `
            <style>
                @page { size: portrait; margin: 1cm; }
                .vti-body { font-family: 'Segoe UI', sans-serif; color: #1e293b; padding: 20px; border: 1px solid #e2e8f0; }
                .vti-header { display: flex; align-items: center; border-bottom: 3px solid #0f172a; padding-bottom: 10px; margin-bottom: 20px; }
                .vti-logo { height: 40px; margin-right: 20px; }
                .vti-title { flex: 1; }
                .vti-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .vti-table th, .vti-table td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; font-size: 0.8rem; }
                .vti-table th { background: #0f172a; color: white; text-transform: uppercase; font-size: 0.65rem; }
                .vti-asset-box { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
            </style>
            <div class="vti-body">
                <div class="vti-header">
                    <img src="${getCompanyLogoUrl()}" class="vti-logo">
                    <div class="vti-title">
                        <h2 style="margin:0; font-size:1.2rem;">INSTRUCAO TECNICA VISUAL (VTI)</h2>
                    </div>
                    <div style="text-align:right; font-size:0.7rem; color:#64748b;">
                        EMISSAO: ${date}<br>
                        LUBRIFICAÇÃO 4.0
                    </div>
                </div>
                <div class="vti-asset-box">
                    <div style="font-size:0.6rem; color:#64748b; font-weight:800; text-transform:uppercase;">Identificação do Ativo</div>
                    <div style="font-size:1.1rem; font-weight:800; color:#0f172a;">${escapeHtml((editingNode.nome || '').toUpperCase())}</div>
                    <div style="font-size:0.8rem; margin-top:5px;"><b>TAG:</b> ${escapeHtml(editingNode.tag || '-')} | <b>MODELO:</b> ${escapeHtml(editingNode.modelo || '-')}</div>
                </div>
                <table class="vti-table">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>PONTO / COMPONENTE</th>
                            <th>LUBRIFICANTE</th>
                            <th>QUANTIDADE</th>
                            <th>FREQUÊNCIA</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${points.map(p => `
                            <tr>
                                <td><span style="font-weight:700; color:#64748b;">${escapeHtml(p.ip || '-')}</span></td>
                                <td><b>${escapeHtml(p.ponto)}</b></td>
                                <td><span style="background:#f1f5f9; padding:2px 6px; border-radius:4px;">${escapeHtml(p.material)}</span></td>
                                <td><b>${escapeHtml(p.qty)}</b></td>
                                <td>--</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                <div style="margin-top:30px; padding:15px; background:#fff7ed; border:1px solid #fdba74; border-radius:6px; font-size:0.7rem;">
                    <b>OBSERVAÇÃO TÉCNICA:</b> Esta instrução deve ser mantida próxima ao equipamento para orientação do plano de lubrificação preventiva.
                </div>
            </div>`;

    const pArea = document.getElementById('print-area');
    if (pArea) {
        pArea.innerHTML = html;
        setTimeout(() => window.print(), 300);
    }
}

async function exportChecklistExcel(filterFreq) {
    if (!editingNode) return showToast('Selecione um ativo.', 'warning');

    const checklistRows = [];
    function traverse(node, context = { area: '', equipment: '' }) {
        let newContext = { ...context };
        if (node.tipo === 'setor' || node.tipo === 'unidade') newContext.area = node.nome;
        if (node.tipo === 'equipamento') newContext.equipment = node.nome;

        let tech = {};
        try {
            tech = typeof node.dados_tecnicos === 'string' ? JSON.parse(node.dados_tecnicos || '{}') : (node.dados_tecnicos || {});
        } catch (e) { }

        const freq = tech['periodo'] || '';
        const match = freq && filterFreq ? freq.toLowerCase().includes(filterFreq.toLowerCase()) : false;

        if (match && (tech['material'] || node.tipo === 'ponto')) {
            checklistRows.push({
                'ÁREA / SETOR': String(newContext.area || 'GERAL').toUpperCase(),
                'EQUIPAMENTO': String(newContext.equipment || node.nome).toUpperCase(),
                'TAG': String(node.tag || '-').toUpperCase(),
                'PONTO': String(tech['ponto_lub'] || node.nome).toUpperCase(),
                'LUBRIFICANTE': String(tech['material'] || '-').toUpperCase(),
                'QUANTIDADE': (tech['qtd_material'] || '') + ' ' + (tech['unid_material'] || ''),
                'FREQUÊNCIA': freq.toUpperCase(),
                'STATUS': ' (  ) ',
                'OBSERVAÇÕES': ''
            });
        }
        if (node.children) node.children.forEach(c => traverse(c, newContext));
    }
    traverse(editingNode);

    if (checklistRows.length === 0) return showToast(`Nenhum ponto ${filterFreq} encontrado.`, 'info');

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(checklistRows);

    ws['!cols'] = [
        { wch: 20 }, { wch: 25 }, { wch: 15 }, { wch: 30 },
        { wch: 30 }, { wch: 15 }, { wch: 15 }, { wch: 10 }, { wch: 20 }
    ];

    XLSX.utils.book_append_sheet(wb, ws, "Checklist_" + filterFreq);
    XLSX.writeFile(wb, `CHECKLIST_${filterFreq.toUpperCase()}_${(editingNode.nome || 'ativo').replace(/\s+/g, '_')}.xlsx`);
    showToast(`Checklist ${filterFreq} gerado com sucesso!`, 'success');
}

function renderChecklistHtmlFromData(parentName, frequency, rows) {
    const esc = (v) => escapeHtml(String(v ?? ''));
    const dateStr = new Date().toLocaleString('pt-BR');
    const frequencyUpper = String(frequency || '').toUpperCase();

    let rowsHtml = '';
    rows.forEach((row, idx) => {
        const bgColor = idx % 2 === 0 ? '#ffffff' : '#f8fafc';
        rowsHtml += `
            <tr style="background: ${bgColor}; border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">${esc((row.area || '').toUpperCase())}</td>
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; color: #475569;">${esc((row.equipment || '').toUpperCase())}</td>
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; font-family: monospace; font-size: 12px; color: #64748b; font-weight: 600;">${esc((row.tag || '').toUpperCase())}</td>
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; font-weight: 600; color: #0284c7;">${esc((row.ponto || '').toUpperCase())}</td>
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; color: #475569; font-size: 12px;">${esc((row.lubrificante || '').toUpperCase())}</td>
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; color: #334155; font-weight: 600; white-space: nowrap;">${esc(row.quantidade)}</td>
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #0369a1;"><span style="background: #e0f2fe; padding: 3px 8px; border-radius: 6px; border: 1px solid #bae6fd;">${esc((row.frequencia || '').toUpperCase())}</span></td>
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; text-align: center; font-size: 16px; color: #94a3b8; font-family: monospace; font-weight: bold;">[ &nbsp;&nbsp; ]</td>
                <td style="padding: 12px 15px; border: 1px solid #e2e8f0; color: #64748b;">&nbsp;</td>
            </tr>`;
    });

    return `
        <div style="font-family: 'Outfit', sans-serif; color: #0f172a; padding: 30px; max-width: 1200px; margin: 0 auto; background: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0284c7; padding-bottom: 20px; margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 24px; font-weight: 800; color: #0284c7; margin: 0; text-transform: uppercase;">LUB-TEK - CHECKLIST PERIÓDICO</h1>
                    <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">Ativo de Origem: <b>${esc(parentName)}</b></p>
                </div>
                <div style="text-align: right;">
                    <span style="background: #e0f2fe; color: #0369a1; padding: 6px 16px; border-radius: 20px; font-weight: 800; font-size: 14px; border: 1px solid #bae6fd; text-transform: uppercase;">${esc(frequencyUpper)}</span>
                    <p style="margin: 8px 0 0 0; color: #64748b; font-size: 12px;">Gerado em: ${esc(dateStr)}</p>
                </div>
            </div>
            <div style="background: #fffbeb; border: 1px solid #fef3c7; border-left: 6px solid #f59e0b; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
                <h3 style="margin: 0 0 10px 0; color: #b45309; font-size: 16px; font-weight: 700;">⚠️ RECOMENDAÇÕES DE SEGURANÇA, SAÚDE E MEIO AMBIENTE (SSMA)</h3>
                <ul style="margin: 0; padding-left: 20px; color: #78350f; font-size: 13px; line-height: 1.6;">
                    <li>Use obrigatoriamente os EPIs recomendados (Óculos de segurança, Luvas nitrílicas/químicas e Sapato de proteção).</li>
                    <li>Realize o bloqueio elétrico e mecânico (LOTO) do equipamento antes de iniciar qualquer atividade física de lubrificação.</li>
                    <li>Utilize panos absorventes/estopas para limpeza e evite respingos ou derramamentos de óleo/graxa no solo.</li>
                    <li>Descarte resíduos contaminados exclusivamente nas caixas coletoras sinalizadas de classe I.</li>
                    <li>Em caso de vazamento severo, utilize o kit de mitigação imediatamente e notifique a liderança.</li>
                </ul>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px; text-align: left; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden;">
                <thead>
                    <tr style="background: #0284c7; color: white;">
                        <th style="padding: 12px 15px;">ÁREA / SETOR</th>
                        <th style="padding: 12px 15px;">EQUIPAMENTO</th>
                        <th style="padding: 12px 15px;">TAG</th>
                        <th style="padding: 12px 15px;">PONTO</th>
                        <th style="padding: 12px 15px;">LUBRIFICANTE</th>
                        <th style="padding: 12px 15px;">QTD</th>
                        <th style="padding: 12px 15px;">FREQUÊNCIA</th>
                        <th style="padding: 12px 15px; text-align: center;">STATUS</th>
                        <th style="padding: 12px 15px;">OBSERVAÇÕES</th>
                    </tr>
                </thead>
                <tbody>${rowsHtml}</tbody>
            </table>
            <div style="margin-top: 50px; display: flex; justify-content: space-between; font-size: 12px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 30px;">
                <div><p style="margin: 0;">Assinatura do Técnico Executante:</p><div style="border-bottom: 1px solid #94a3b8; width: 250px; margin-top: 30px;"></div></div>
                <div><p style="margin: 0;">Assinatura do Supervisor/Inspetor:</p><div style="border-bottom: 1px solid #94a3b8; width: 250px; margin-top: 30px;"></div></div>
                <div style="text-align: right;"><p style="margin: 0; font-weight: 600;">LUB-TEK SAAS - Módulo de Confiabilidade</p><p style="margin: 4px 0 0 0;">Relatório gerado em conformidade com ISO 9001 e normas de SSMA</p></div>
            </div>
        </div>`;
}

async function printPeriodicChecklist(freq) {
    if (!editingNode) return showToast('Selecione uma área.', 'warning');
    try {
        const res = await api('generate_checklist_report', { id: editingNode.id, frequency: freq });
        const pArea = document.getElementById('print-area');
        if (!pArea) return;

        if (res && res.empty) {
            pArea.innerHTML = `<div style="padding:20px; font-family:sans-serif; text-align:center;">Nenhum ponto de lubrificação encontrado com a frequência <b>${escapeHtml(freq)}</b> para o ativo selecionado.</div>`;
            setTimeout(() => window.print(), 500);
            return;
        }

        if (res && Array.isArray(res.rows)) {
            pArea.innerHTML = renderChecklistHtmlFromData(res.parent_name || editingNode.nome, res.frequency || freq, res.rows);
            setTimeout(() => window.print(), 500);
        }
    } catch (e) { showToast('Erro de conexão.', 'error'); }
}

// Ensure the page layout is optimized for printing dynamically
window.addEventListener('beforeprint', () => {
    const pArea = document.getElementById('print-area');
    if (pArea && pArea.innerHTML.trim() !== '') {
        document.body.classList.add('printing-mode');
    }
});
window.addEventListener('afterprint', () => {
    document.body.classList.remove('printing-mode');
    const pArea = document.getElementById('print-area');
    if (pArea) {
        pArea.innerHTML = '';
    }
});

async function generateScheduledOrders() {
    showToast('Iniciando geração de preventivas...', 'info');
    try {
        const res = await api('generate_scheduled_orders');
        if (res && res.success) {
            showToast(res.message || 'Ordens geradas com sucesso!', 'success');
            // Refresh stats and tasks
            if (window.GlobalSync && window.GlobalSync.dashboard.isActive) {
                await window.GlobalSync.dashboard.refreshStats();
            } else if (typeof loadDash === 'function') {
                await loadDash();
            }
        } else {
            showToast('Erro ao processar ordens preventivas.', 'error');
        }
    } catch (e) {
        showToast('Erro de conexão com o servidor.', 'error');
    }
}

async function exportDetailedPlanExcel() {
    if (!editingNode) return showToast('Selecione uma planta.', 'warning');
    if (typeof ExcelJS === 'undefined') return showToast('ExcelJS não carregado.', 'error');

    showToast('Gerando Plano Diretor Executivo...', 'info');
    if (cachedCatalog.length === 0) await loadCatalog();

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Plano Diretor');

    // --- 1. DEFINE COLUMNS ---
    sheet.columns = [
        { header: 'ÁREA / UNIDADE', key: 'area', width: 25 },
        { header: 'EQUIPAMENTO', key: 'equip', width: 30 },
        { header: 'TAG', key: 'tag', width: 15 },
        { header: 'PONTO DE LUBRIFICAÇÃO', key: 'ponto', width: 35 },
        { header: 'LUBRIFICANTE', key: 'lub', width: 30 },
        { header: 'QTD APLIC.', key: 'qty', width: 12 },
        { header: 'UNIDADE', key: 'unit', width: 10 },
        { header: 'FREQUÊNCIA', key: 'freq', width: 15 },
        { header: 'CODIGO SAP', key: 'sap', width: 15 },
        { header: 'ALMOXARIFADO', key: 'almox', width: 15 },
        { header: 'CÓD. SERVIÇO', key: 'cod_serv', width: 15 },
        { header: 'ROTA', key: 'rota', width: 12 },
        { header: 'COMPLEMENTO', key: 'complemento', width: 25 },
        { header: 'CONSUMO ANUAL', key: 'annual_qty', width: 15 },
        { header: 'CUSTO ANUAL (EST.)', key: 'annual_cost', width: 18 }
    ];

    // --- 2. TRAVERSE & POPULATE ---
    const rows = [];
    function traverse(node, context = { area: '', equip: '' }) {
        let nCtx = { ...context };
        if (node.tipo === 'unidade' || node.tipo === 'setor') nCtx.area = node.nome;
        if (node.tipo === 'equipamento') nCtx.equip = node.nome;

        let tech = {};
        try { tech = typeof node.dados_tecnicos === 'string' ? JSON.parse(node.dados_tecnicos || '{}') : (node.dados_tecnicos || {}); } catch (e) { }

        if (tech['material']) {
            const qty = parseFloat(tech['qtd_material']) || 0;
            const freq = tech['periodo'] || '';
            let mult = 0;
            if (freq.includes('Di')) mult = 365;
            else if (freq.includes('Semanal')) mult = 52;
            else if (freq.includes('Mensal')) mult = 12;
            else if (freq.includes('Anual')) mult = 1;
            else mult = 4; // Default quarterly approximation

            const annualQty = qty * mult;
            const catItem = cachedCatalog.find(c => c.nome === tech['material']);
            const unitPrice = catItem ? parseFloat(catItem.custo_medio || 0) : 0;
            const costMult = (tech['unid_material'] === 'g' || tech['unid_material'] === 'ml') ? 0.001 : 1;
            const annualCost = annualQty * costMult * unitPrice;

            rows.push({
                area: nCtx.area.toUpperCase(),
                equip: nCtx.equip.toUpperCase(),
                tag: (node.tag || '-').toUpperCase(),
                ponto: (tech['ponto_lub'] || node.nome).toUpperCase(),
                lub: tech['material'].toUpperCase(),
                qty: qty,
                unit: tech['unid_material'] || 'g',
                freq: freq,
                sap: tech['sap'] || '',
                almox: tech['almox'] || '',
                cod_serv: tech['cod_serv'] || '',
                rota: tech['rota'] || '',
                complemento: tech['complemento'] || '',
                annual_qty: annualQty.toFixed(2),
                annual_cost: annualCost
            });
        }
        if (node.children) node.children.forEach(c => traverse(c, nCtx));
    }
    traverse(editingNode);
    sheet.addRows(rows);

    // --- 3. STYLING (SENIOR LOOK) ---
    // Header Style
    const headerRow = sheet.getRow(1);
    headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' } };
    headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0284C7' } }; // LUB-TEK Primary Blue
    headerRow.alignment = { vertical: 'middle', horizontal: 'center' };

    // Formatting columns
    sheet.getColumn('annual_cost').numFmt = '"R$ "#,##0.00';

    // Alternating row colors
    sheet.eachRow((row, rowNum) => {
        if (rowNum === 1) return;
        if (rowNum % 2 === 0) {
            row.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF8FAFC' } };
        }
        row.border = { bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } } };
    });

    // --- 4. EXPORT ---
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const ts = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
    a.href = url;
    a.download = `PLANO_EXTRA_LUB_${(editingNode.nome || 'ativo').replace(/\s+/g, '_').toUpperCase()}_${ts}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);

    showToast('Plano Diretor gerado com sucesso!', 'success');
}

function printQuickReport() {
    if (!editingNode) return;

    const dateStr = new Date().toLocaleDateString();
    const path = document.getElementById('ash-path-display')?.innerText || '';

    const reportRows = [];
    function traverse(node, context = { area: '', equipment: '' }) {
        let newContext = { ...context };
        if (node.tipo === 'setor' || node.tipo === 'unidade') newContext.area = node.nome;
        if (node.tipo === 'equipamento') newContext.equipment = node.nome;

        let tech = {};
        try { tech = typeof node.dados_tecnicos === 'string' ? JSON.parse(node.dados_tecnicos || '{}') : (node.dados_tecnicos || {}); } catch (e) { }

        if (tech['material'] || node.tipo === 'ponto') {
            reportRows.push({
                tag: node.tag || '-',
                equip: (newContext.equipment || node.nome).toUpperCase(),
                ponto: (tech['ponto_lub'] || node.nome).toUpperCase(),
                lub: (tech['material'] || '-').toUpperCase(),
                freq: (tech['periodo'] || '-').toUpperCase(),
                qtd: (tech['qtd_material'] || '') + ' ' + (tech['unid_material'] || ''),
                status: tech['situacao'] || 'Normal'
            });
        }
        if (node.children) node.children.forEach(c => traverse(c, newContext));
    }
    traverse(editingNode);

    const win = window.open('', '_blank');
    win.document.write(`
            <html>
            <head>
                <title>LAUDO_TECNICO_${escapeHtml((editingNode.nome || '').toUpperCase())}</title>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; margin: 0; padding: 0; background: #fff; }
                    .page { padding: 40px; }
                    .header { display: flex; justify-content: space-between; border-bottom: 4px solid #0f172a; padding-bottom: 20px; margin-bottom: 30px; }
                    .header-left h1 { margin: 0; color: #0f172a; font-size: 1.8rem; font-weight: 800; letter-spacing: -1px; }
                    .header-left p { margin: 5px 0 0; color: #64748b; font-size: 0.8rem; font-weight: 600; }
                    .header-right { text-align: right; }
                    .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; }
                    .meta-item { border-left: 3px solid #0ea5e9; padding-left: 12px; }
                    .meta-label { font-size: 0.65rem; color: #64748b; text-transform: uppercase; font-weight: 800; }
                    .meta-value { font-size: 1rem; color: #0f172a; font-weight: 800; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th { background: #0f172a; color: white; padding: 12px; text-align: left; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; }
                    td { padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 0.8rem; }
                    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: 800; font-size: 0.65rem; }
                    .status-normal { background: #dcfce7; color: #166534; }
                    .status-alerta { background: #fef9c3; color: #854d0e; }
                    .status-critico { background: #fee2e2; color: #991b1b; }
                    .footer { margin-top: 60px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 40px; }
                    .sig-box { border-top: 2px solid #0f172a; padding-top: 10px; text-align: center; }
                    .sig-label { font-size: 0.65rem; font-weight: 800; color: #64748b; }
                </style>
            </head>
            <body>
                <div class="page">
                    <div class="header">
                        <div class="header-left">
                            <h1>LAUDO TéCNICO DE ATIVOS</h1>
                            <p>ENGENHARIA DE CONFIABILIDADE & LUBRIFICAÇÃO AVANÇADA</p>
                        </div>
                        <div class="header-right">
                            <div style="font-weight:900; color:#0f172a;">LUB-TEK</div>
                            <div style="font-size:0.7rem; color:#64748b;">EMISSAO: ${dateStr}</div>
                        </div>
                    </div>
                    <div class="meta-grid">
                        <div class="meta-item"><div class="meta-label">Ativo Principal</div><div class="meta-value">${escapeHtml((editingNode.nome || '').toUpperCase())}</div></div>
                        <div class="meta-item"><div class="meta-label">TAG do Ativo</div><div class="meta-value">${escapeHtml(editingNode.tag || 'N/A')}</div></div>
                        <div class="meta-item"><div class="meta-label">Caminho / Área</div><div class="meta-value" style="font-size:0.8rem;">${escapeHtml(path)}</div></div>
                        <div class="meta-item"><div class="meta-label">Tipo</div><div class="meta-value">${escapeHtml((editingNode.tipo || '').toUpperCase())}</div></div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>TAG</th>
                                <th>EQUIPAMENTO</th>
                                <th>PONTO</th>
                                <th>LUBRIFICANTE</th>
                                <th>FREQ.</th>
                                <th>QTD.</th>
                                <th>SITUAÇÃO</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${reportRows.map(r => `
                                <tr>
                                    <td><span style="font-family:monospace; font-weight:bold;">${escapeHtml(r.tag)}</span></td>
                                    <td>${escapeHtml(r.equip)}</td>
                                    <td><b>${escapeHtml(r.ponto)}</b></td>
                                    <td>${escapeHtml(r.lub)}</td>
                                    <td>${escapeHtml(r.freq)}</td>
                                    <td>${escapeHtml(r.qtd)}</td>
                                    <td><span class="status-badge status-${escapeAttr(String(r.status || 'Normal').toLowerCase())}">${escapeHtml(r.status || 'NORMAL')}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    <div class="footer">
                        <div class="sig-box"><div class="sig-label">ENG. DE CONFIABILIDADE</div></div>
                        <div class="sig-box"><div class="sig-label">TéCNICO RESPONSÁVEL</div></div>
                        <div class="sig-box"><div class="sig-label">LUB-TEK SISTEMAS</div></div>
                    </div>
                    <div style="margin-top:30px; font-size:0.6rem; color:#94a3b8; text-align:center;">
                        Este documento é gerado eletronicamente e possui validade técnica para fins de planejamento e certificação de ativos.
                    </div>
                </div>
                \x3Cscript>window.onload = () => window.print();\x3C/script>
            </body>
            </html>
        `);
    win.document.close();
}

async function exportLabelSheet() {
    if (!editingNode) return showToast('Selecione um ativo para gerar as etiquetas.', 'warning');

    const labelRows = [];

    function traverse(node) {
        let tech = {};
        try {
            if (typeof node.dados_tecnicos === 'string') {
                tech = JSON.parse(node.dados_tecnicos || '{}');
            } else if (typeof node.dados_tecnicos === 'object') {
                tech = node.dados_tecnicos || {};
            }
        } catch (e) { }

        // Only add if it's a Lubrication Point or has essential data
        if (tech['material'] || tech['qtd_material'] || node.tipo === 'ponto') {
            labelRows.push({
                TAG: String(node.tag || '-').toUpperCase(),
                'CODIGO SAP': String(tech['codigo_sap'] || node['codigo'] || '-').toUpperCase(),
                QUANTIDADE: String(tech['qtd_material'] || '-').toUpperCase(),
                'UN. MEDIDA': String(tech['unid_material'] || 'G').toUpperCase(),
                'MÉTODO': String(tech['metodo'] || 'MANUAL').toUpperCase(),
                'TIPO LUBRIFICANTE': String(tech['material'] || '-').toUpperCase(),
                IP: String(tech['ip'] || node.id).toUpperCase()
            });
        }

        if (node.children && node.children.length > 0) {
            node.children.forEach(child => traverse(child));
        }
    }

    traverse(editingNode);

    if (labelRows.length === 0) {
        return showToast('Nenhum ponto de lubrificação encontrado nesta hierarquia.', 'warning');
    }

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(labelRows);

    // Column Widths for thermal labels
    const wscols = [
        { wch: 15 }, { wch: 15 }, { wch: 12 }, { wch: 12 }, { wch: 15 }, { wch: 30 }, { wch: 10 }
    ];
    ws['!cols'] = wscols;

    XLSX.utils.book_append_sheet(wb, ws, "Etiquetas");
    const ts = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `ETIQUETAS_${(editingNode.nome || 'ativo').toUpperCase().replace(/\s+/g, '_')}_${ts}.xlsx`);

    showToast('Planilha de etiquetas gerada!', 'success');
}

function exportTasks(format) {
    const data = tasks.map(t => ({
        Descricao: t.desc,
        Responsavel: t.resp,
        Data: t.date,
        Prioridade: t.prio,
        Situacao: t.done ? 'Concluído' : 'Pendente'
    }));

    const ts = new Date().toISOString().slice(0, 10);
    if (format === 'excel') {
        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Tarefas");
        XLSX.writeFile(wb, `ORDENS_SERVICO_${ts}.xlsx`);
    }
    else if (format === 'pdf') {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.text("Relatório de Ordens de Serviço - LUB-TEK", 10, 10);
        doc.autoTable({
            head: [['Descrição / Ativo', 'Responsável', 'Data Limite', 'Prioridade', 'Situação']],
            body: data.map(Object.values),
            theme: 'striped',
            headStyles: { fillColor: [2, 132, 199] }
        });
        doc.save(`OS_LUBTEK_${ts}.pdf`);
    }
}

// CALCULATORS LOGIC
function showCalc(id) {
    document.querySelectorAll('.calc-box').forEach(b => b.style.display = 'none');
    document.getElementById(`calc-${id}`).style.display = 'block';
}

async function runCalc(type) {
    const resDiv = document.getElementById(type === 'filter' ? 'res-filter' : type === 'dn' ? 'res-dn' : 'res-bearing');

    if (type === 'bearing') {
        const data = {
            D: document.getElementById('cb-D').value,
            B: document.getElementById('cb-B').value,
            rpm: document.getElementById('cb-rpm').value,
            temp: document.getElementById('cb-temp').value
        };
        const res = await api('calc_bearing', data);
        resDiv.style.display = 'block';
        if (!res || res.found === false || res.error) {
            resDiv.innerHTML = escapeHtml((res && res.error) || 'Não foi possível calcular. Verifique os dados informados.');
            return;
        }
        resDiv.innerHTML = `
Quantidade: ${res.grams != null ? res.grams : '--'} g<br>
Intervalo: ${res.hours != null ? res.hours : '--'} horas (${res.days != null ? res.days : '--'} dias)
`;
    }
    else if (type === 'dn') {
        const data = {
            d: document.getElementById('cdn-d').value,
            D: document.getElementById('cdn-D').value,
            rpm: document.getElementById('cdn-rpm').value
        };
        const res = await api('calc_dn', data);
        if (!res || res.error) {
            if (typeof showToast === 'function') showToast((res && res.error) || 'Não foi possível calcular o DN.', 'error');
            return;
        }
        document.getElementById('dn-result-container').style.display = 'block';
        document.getElementById('dn-value').innerText = (res.dn != null ? res.dn : 0).toLocaleString();

        const statusEl = document.getElementById('dn-status');
        statusEl.innerText = res.easy_status;
        statusEl.style.color = res.color;

        const suggEl = document.getElementById('dn-suggestion');
        if (res.suggestion) {
            suggEl.style.display = 'block';
            suggEl.innerText = res.suggestion;
            suggEl.style.background = 'rgba(239,68,68,0.2)';
            suggEl.style.color = '#fca5a5';
            suggEl.style.border = '1px solid #ef4444';
        } else {
            suggEl.style.display = 'none';
        }

        // Render Gauge
        const ctx = document.getElementById('dn-gauge').getContext('2d');
        if (charts['dn_gauge']) charts['dn_gauge'].destroy();

        const max = res.max_scale || 1000000;
        const value = Math.min(res.dn, max);

        charts['dn_gauge'] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['DN', 'Restante'],
                datasets: [{
                    data: [value, max - value],
                    backgroundColor: [res.color, '#1e293b'],
                    borderWidth: 0,
                    borderRadius: 5
                }]
            },
            options: {
                rotation: -90,
                circumference: 180,
                cutout: '80%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
    }
    else if (type === 'thick') {
        const t = document.getElementById('ct-type').value;
        if (!t) return;
        const res = await api('get_thickener', { type: t });
        if (!res || res.error) return;
        document.getElementById('thick-info').style.display = 'block';
        document.getElementById('ti-title').innerText = res.title || '';
        document.getElementById('ti-feat').innerText = res.features || '';
    }
    else if (type === 'filter') {
        const data = {
            vol: document.getElementById('cf-vol').value,
            flow: document.getElementById('cf-flow').value,
            nas_cur: document.getElementById('cf-cur').value,
            nas_target: document.getElementById('cf-target').value
        };
        const res = await api('calc_filtering', data);
        resDiv.style.display = 'block';
        if (!res || res.error) {
            resDiv.innerHTML = escapeHtml((res && res.error) || 'Não foi possível calcular.');
            return;
        }
        resDiv.innerHTML = `Tempo Estimado: ${res.hours != null ? res.hours : '--'} Horas (${res.passes != null ? res.passes : '--'} passadas)`;
    }
}


// --- CATALOG ADVANCED LOGIC ---
let catalog = [];
let currentCatItem = null;

async function loadCatalog() {
    let list = [];
    try {
        const raw = await api('get_catalog');
        list = Array.isArray(raw) ? raw : (raw && Array.isArray(raw.data) ? raw.data : []);
    } catch (e) {
        list = [];
    }

    catalog = list.map(i => ({ ...i, tipo: normalizeType(i.nome, i.tipo) }));
    cachedCatalog = catalog;

    const dl = document.getElementById('lubricant-list');
    if (dl) {
        dl.innerHTML = catalog
            .filter(i => i.tipo === 'Lubrificante')
            .map(i => `<option value="${escapeAttr(i.nome)}">`)
            .join('');
    }

    if (document.getElementById('cat-search') || document.getElementById('catalog-grid') || document.getElementById('cat-list')) {
        filterCatalog();
    }
}

function normalizeType(name, type) {
    // Smart auto-categorize if type is vague
    if (type) return type;
    const n = name.toLowerCase();
    if (n.includes('rolamento')) return 'Rolamento';
    if (n.includes('graxa') || n.includes('mobil') || n.includes('shell') || n.includes('óleo')) return 'Lubrificante';
    if (n.includes('motor')) return 'Motor';
    if (n.includes('acoplamento') || n.includes('bomba')) return 'Componente';
    return 'Outros';
}

function filterCatalog() {
    const searchEl = document.getElementById('cat-search');
    const sortEl = document.getElementById('cat-sort');
    const typeEl = document.getElementById('cat-filter-type');
    if (!searchEl && !document.getElementById('catalog-grid') && !document.getElementById('cat-list')) return;

    const s = (searchEl?.value || '').toLowerCase();
    const sort = sortEl?.value || 'az';
    const filterType = typeEl?.value || 'all';
    const source = Array.isArray(catalog) ? catalog : [];

    let filtered = source.filter(i =>
        ((i.nome || '').toLowerCase().includes(s) || (i.codigo || '').toLowerCase().includes(s) || (i.ip ||
            '').toString().includes(s)
            || (i.fabricante || '').toLowerCase().includes(s)) &&
        (filterType === 'all' || i.tipo === filterType)
    );

    // Sort
    if (sort === 'az') filtered.sort((a, b) => a.nome.localeCompare(b.nome));
    if (sort === 'za') filtered.sort((a, b) => b.nome.localeCompare(a.nome));
    if (sort === 'stock_desc') filtered.sort((a, b) => b.estoque_atual - a.estoque_atual);
    if (sort === 'stock_asc') filtered.sort((a, b) => a.estoque_atual - b.estoque_atual);

    renderCatList(filtered);
}

function renderCatList(list) {
    const container = document.getElementById('cat-grid');
    if (list.length === 0) {
        container.innerHTML = `<div style="padding:20px; text-align:center; color:var(--text-muted);">
                Nenhum item encontrado.
            </div>`;
        return;
    }

    container.innerHTML = list.map(i => {
        const stock = i.estoque_atual != null ? i.estoque_atual : 0;
        const tipoIcon = (i.tipo === 'Rolamento') ? 'circle-dot' : (i.tipo === 'Lubrificante' ? 'droplet' : 'package');
        const thumb = i.imagem
            ? `<img src="${escapeAttr(i.imagem)}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;flex-shrink:0;">`
            : `<div style="width:48px;height:48px;border-radius:8px;background:linear-gradient(135deg,#e0f2fe,#f0f9ff);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i data-lucide="${tipoIcon}" style="width:22px;color:var(--primary);opacity:0.7;"></i></div>`;
        return `
    <div class="list-item cat-list-item" onclick="viewCatDetail(${i.id}, this)"
        style="padding:12px; cursor:pointer; transition:0.2s; display:flex; gap:12px; align-items:center;">
        ${thumb}
        <div style="flex:1; min-width:0;">
            <div class="flex-between">
                <div style="font-weight:600; font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    ${i.ip ? `<span style="color:var(--primary); font-family:monospace; margin-right:4px; font-weight:800;">#${i.ip}</span>` : ''} ${escapeHtml(i.nome || 'Sem nome')}
                </div>
                <span class="tag" style="font-size:0.65rem; padding:2px 6px; border-radius:4px; background:rgba(0,0,0,0.06); color:#334155; flex-shrink:0;">${escapeHtml(i.tipo || '')}</span>
            </div>
            <div class="flex-between" style="margin-top:4px;">
                <div style="font-size:0.8rem; opacity:0.7;">${escapeHtml(i.fabricante || 'Genérico')}</div>
                <div style="font-weight:bold; color:${stock > 0 ? '#059669' : '#dc2626'}; font-size:0.85rem;">${stock} un</div>
            </div>
        </div>
    </div>`;
    }).join('');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function generateNextIP() {
    let max = 4000; // Start range
    catalog.forEach(i => {
        const n = parseInt(i.ip);
        if (!isNaN(n) && n > max) max = n;
    });
    return max + 1;
}

function addNewItem() {
    const nextIp = generateNextIP();
    currentCatItem = {
        id: null, nome: '', tipo: 'Componente', specs: '{}', estoque_atual: 0, ip: nextIp, imagem: ''
    };
    renderDetailForm(currentCatItem);
}

async function viewCatDetail(id, el) {
    document.querySelectorAll('.list-item').forEach(x => {
        x.classList.remove('active-item');
    });
    el.classList.add('active-item');

    const item = catalog.find(x => x.id == id);
    if (!item) return;

    // Auto IP for Catalog
    if (!item.ip) {
        const newIp = generateNextIP();
        item.ip = newIp;

        // Silent Save
        try {
            await api('save_catalog_item', item);
        } catch (e) { console.error("Catalog Auto-IP save failed", e); }
    }

    currentCatItem = item;
    renderDetailForm(item);
    reloadMarketAnalysis(item.id, item.nome);

    // Mobile: tela cheia na ficha técnica
    if (window.innerWidth <= 768) {
        const view = document.getElementById('view-catalog');
        if (view) view.classList.add('mobile-detail-open');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function closeCatalogDetailMobile() {
    const view = document.getElementById('view-catalog');
    if (view) view.classList.remove('mobile-detail-open');
    const detail = document.getElementById('cat-detail-container');
    const empty = document.getElementById('cat-empty');
    if (detail) detail.style.display = 'none';
    if (empty) empty.style.display = 'flex';
}
window.closeCatalogDetailMobile = closeCatalogDetailMobile;

// --- AUTOMATED LUBRICATION SUGGESTION ---
async function checkLubSuggestion() {
    const name = document.getElementById('af-name').value;
    // Only auto-suggest if name contains 4 digits (heuristic for bearing) and plan is empty
    if (name.length > 4 && /\d{4}/.test(name)) {
        const currentQtd = document.getElementById('af-qtd_material').value;
        if (currentQtd && currentQtd > 0) return; // Don't overwrite if already filled

        showToast('Analisando especificações...', 'info');
        const res = await api('suggest_lubrication', { name: name });

        if (res.found) {
            if (confirm(`Sugestão encontrada para rolamento ${res.model}:\n\nQuantidade: ${res.grams}g\nFrequência: ${res.freq_label} (${res.hours}h)\n\nDeseja aplicar?`)) {
                // Apply to Plan Tab fields (even if hidden)
                document.getElementById('af-qtd_material').value = res.grams;
                document.getElementById('af-unid_material').value = 'g';
                document.getElementById('af-periodo').value = res.freq_label;
                document.getElementById('af-duracao_h').value = 0;
                document.getElementById('af-duracao_m').value = 15; // default 15m

                // Tech Specs (Dimensions)
                if (!editingNode.dados_tecnicos) editingNode.dados_tecnicos = {};
                editingNode.dados_tecnicos.rpm = 1500; // default
                editingNode.dados_tecnicos.d = res.d;
                editingNode.dados_tecnicos.D = res.D;
                editingNode.dados_tecnicos.B = res.B;

                updateLocalNode(); // trigger save
                showToast('Especificações aplicadas com sucesso!', 'success');
            }
        }
    }
}

// --- SEARCH USAGE IN ASSET TREE (Reverse Lookup) ---
function findUsageInTree(catalogId) {
    const findings = [];
    if (!window.treeState) return findings;

    function traverse(node, path = []) {
        // Check direct linkage
        // Assuming node might have 'catalogo_id' or we search in technical data
        if (node.catalogo_id == catalogId) {
            findings.push({ node, path });
        }

        // Also check consumables/materials list if available
        // (This depends on data structure, assuming simple check for now)

        if (node.children) {
            node.children.forEach(c => traverse(c, [...path, node.nome]));
        }
    }

    window.treeState.forEach(n => traverse(n));
    return findings;
}

function renderDetailForm(item) {
    const catEmpty = document.getElementById('cat-empty');
    const detail = document.getElementById('cat-detail-container');

    if (catEmpty) catEmpty.style.display = 'none';
    if (!detail) return;
    detail.style.display = 'block';

    // Parse Specs
    let specs = {};
    try { specs = JSON.parse(item.specs || '{}'); } catch (e) { }

    // Find Usage
    const usage = findUsageInTree(item.id);

    // Nome preparado para uso seguro dentro de atributo onclick com aspas simples
    const nomeForJsAttr = escapeAttr(String(item.nome || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'"));

    // Determine Status based on Stock & Usage
    let statusBadge = '';
    if (item.estoque_atual > 0) statusBadge = '<span class="st-pill st-ok">Em Estoque</span>';
    else statusBadge = '<span class="st-pill st-crit">Esgotado</span>';

    // New Layout: INVENTORY CARD 4.0
    detail.innerHTML = `
    <!-- HEADER: IDENTITY -->
    <div style="background:white; padding:20px; border-radius:12px; border:1px solid var(--border); box-shadow:0 2px 10px rgba(0,0,0,0.02); margin-bottom:20px;">
        <button type="button" class="catalog-mobile-back btn btn-outline" onclick="closeCatalogDetailMobile()"
            style="display:none; margin-bottom:12px; padding:8px 14px; font-size:0.85rem; align-items:center; gap:6px;">
            <i data-lucide="arrow-left" style="width:16px;"></i> Voltar à lista
        </button>
        <div class="flex-between" style="align-items:flex-start;">
            <div style="flex:1;">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">
                    Ficha de Item #${item.ip || 'N/A'}
                </div>
                <input id="ced-name" value="${escapeAttr(item.nome || '')}" 
                    style="font-size:1.5rem; font-weight:800; color:var(--text-main); border:none; border-bottom:2px solid transparent; width:100%; transition:0.2s;"
                    placeholder="Nome do Item" onfocus="this.style.borderBottom='2px solid var(--primary)'" onblur="this.style.borderBottom='2px solid transparent'">
                
                <div style="display:flex; gap:15px; margin-top:10px; flex-wrap:wrap;">
                    <div class="input-group-clean">
                        <label for="ced-manuf">Fabricante</label>
                        <input id="ced-manuf" value="${escapeAttr(item.fabricante || '')}" placeholder="Marca (Ex: SKF)">
                    </div>
                    <div class="input-group-clean">
                        <label for="ced-code">Código / PN</label>
                        <input id="ced-code" value="${escapeAttr(item.codigo || '')}" placeholder="Part Number">
                    </div>
                </div>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:10px; align-items:flex-end;">
                 <button type="button" class="btn" onclick="saveCatalogChanges(${item.id})" style="padding:10px 25px; font-weight:700;" aria-label="Salvar alterações da ficha">
                    <i data-lucide="save" aria-hidden="true"></i> Salvar Ficha
                 </button>
                 <div style="display:flex; gap:10px;">
                    ${statusBadge}
                    <span class="st-pill st-warn" style="cursor:pointer;" title="Clique para alterar status de validação">Validado</span>
                 </div>
            </div>
        </div>
    </div>

    <!-- MARKETPLACE & INTELLIGENCE HERO -->
    <div style="background:white; border-radius:12px; border:1px solid var(--border); overflow:hidden; margin-bottom:25px; box-shadow:0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <div style="background:linear-gradient(to right, #0f172a, #334155); padding:12px 20px; display:flex; justify-content:space-between; align-items:center;">
             <h3 style="margin:0; color:white; font-size:0.9rem; font-weight:700; display:flex; align-items:center; gap:8px;">
                <i data-lucide="shopping-cart" style="color:#38bdf8;"></i> Marketplace & Inteligência de Compras
            </h3>
            <span class="badge" style="background:rgba(56, 189, 248, 0.15); color:#38bdf8; border:1px solid rgba(56, 189, 248, 0.3); font-size:0.65rem;">IA ATIVA</span>
        </div>

        <div style="display:flex; flex-wrap:wrap;">
            <!-- LEFT: OFFERS LIST -->
            <div style="flex:2; padding:20px; border-right:1px solid var(--border); min-width:300px; display:flex; flex-direction:column;">
                <div id="market-list-${item.id}" style="display:flex; flex-direction:column; gap:10px; flex:1;">
                    <div style="font-style:italic; color:var(--text-muted); font-size:0.85rem; text-align:center; padding:15px; background:#f8fafc; border-radius:8px;">
                        <i data-lucide="search" style="width:16px; margin-bottom:5px; opacity:0.5;"></i><br>
                        Buscando melhores ofertas...
                    </div>
                </div>

                <div style="margin-top:15px; padding-top:15px; border-top:1px dashed var(--border);">
                    <div style="font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px;">Adicionar Oferta Manual</div>
                    <div style="display:grid; grid-template-columns: 2fr 2fr 1fr auto; gap:8px; align-items:center;">
                        <input id="mk-vendor" class="input-clean" placeholder="Fornecedor (Ex: Amazon)" style="padding:6px 10px; font-size:0.8rem; border:1px solid var(--border); border-radius:6px;">
                        <input id="mk-url" class="input-clean" placeholder="https://..." style="padding:6px 10px; font-size:0.8rem; border:1px solid var(--border); border-radius:6px;">
                        <input id="mk-price" type="number" class="input-clean" placeholder="R$" style="padding:6px 10px; font-size:0.8rem; border:1px solid var(--border); border-radius:6px;">
                        <button class="btn-icon" style="background:var(--success); color:white; width:34px; height:34px; border-radius:6px; display:flex; align-items:center; justify-content:center;" onclick="addMarketOffer(${item.id})" title="Adicionar Oferta">
                            <i data-lucide="plus" style="width:16px;"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT: PRICE INTELLIGENCE CHART -->
            <div style="flex:1; padding:20px; background:#f8fafc; min-width:250px; display:flex; flex-direction:column;">
                <h4 style="margin:0 0 10px 0; font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">
                    Tendência (12 Meses)
                </h4>
                <div style="flex:1; width:100%; position:relative; min-height:120px; margin-bottom:15px;">
                    <canvas id="market-price-trend"></canvas>
                </div>

                <div class="card" style="padding:12px; background:white; border:1px solid var(--border); margin-bottom:10px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                         <div>
                            <div style="font-size:0.7rem; color:var(--text-muted);">Economia Potencial</div>
                            <div style="font-size:1.1rem; font-weight:800; color:#10b981;" id="market-savings-total">R$ --</div>
                         </div>
                         <i data-lucide="trending-down" style="color:#10b981; opacity:0.2; width:32px; height:32px;"></i>
                    </div>
                </div>
                
                <button class="btn btn-outline" style="width:100%; justify-content:center; font-size:0.8rem; background:white;" onclick="reloadMarketAnalysis('${item.id}', '${nomeForJsAttr}')">
                    <i data-lucide="refresh-cw" style="width:12px;"></i> Analisar Novamente
                </button>
            </div>
        </div>
    </div>

    <div style="display:flex; gap:20px; flex-wrap:wrap;">
        
        <!-- COL 1: STOCK & IMAGE -->
        <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:20px;">
            
            <!-- STOCK CONTROL CARD -->
            <div class="card" style="padding:20px; background:#f8fafc; border:1px solid var(--border);">
                <h4 style="margin:0 0 15px 0; color:var(--text-main); font-size:0.9rem; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="package"></i> Controle de Estoque
                </h4>
                
                <div style="display:flex; align-items:center; justify-content:space-between; background:white; padding:15px; border-radius:8px; border:1px solid var(--border);">
                    <button class="btn-icon circle" onclick="adjustQuickStock(-1)" style="width:40px; height:40px; background:#fee2e2; color:#ef4444;"><i data-lucide="minus"></i></button>
                    <div style="text-align:center;">
                        <input type="number" id="ced-stock" value="${item.estoque_atual != null ? item.estoque_atual : 0}" 
                            style="font-size:2rem; font-weight:800; color:var(--text-main); border:none; text-align:center; width:100px; background:transparent;">
                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">UNIDADES</div>
                    </div>
                    <button class="btn-icon circle" onclick="adjustQuickStock(1)" style="width:40px; height:40px; background:#dcfce7; color:#166534;"><i data-lucide="plus"></i></button>
                </div>

                <div style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="input-group-clean" style="flex:1; min-width:140px;">
                        <label for="ced-loc">Localização (Rua/Prat.)</label>
                        <input id="ced-loc" value="${escapeAttr(item.localizacao || '')}" placeholder="Ex: A-12">
                    </div>
                    <div class="input-group-clean" style="flex:1; min-width:140px;">
                        <label for="ced-type">Categoria</label>
                        <select id="ced-type" aria-label="Categoria do item">
                            <option value="Componente" ${item.tipo === 'Componente' ? 'selected' : ''}>Componente</option>
                            <option value="Rolamento" ${item.tipo === 'Rolamento' ? 'selected' : ''}>Rolamento</option>
                            <option value="Lubrificante" ${item.tipo === 'Lubrificante' ? 'selected' : ''}>Lubrificante</option>
                            <option value="Motor" ${item.tipo === 'Motor' ? 'selected' : ''}>Motor</option>
                            <option value="Bomba" ${item.tipo === 'Bomba' ? 'selected' : ''}>Bomba</option>
                            <option value="Acoplamento" ${item.tipo === 'Acoplamento' ? 'selected' : ''}>Acoplamento</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- IMAGE & EVIDENCE -->
            <div class="card catalog-photo-card" style="padding:0; overflow:hidden; position:relative; min-height:200px; background:#e2e8f0; border:1px solid var(--border);">
                 <div id="ced-img-preview" class="catalog-photo-upload" role="button" tabindex="0"
                    aria-label="Adicionar ou alterar foto do item"
                    onclick="triggerUpload('ced-img-input')"
                    onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();triggerUpload('ced-img-input');}">
                    ${item.imagem ?
            `<img src="${escapeAttr(item.imagem)}" style="width:100%; height:100%; object-fit:cover;">` :
            `<div class="catalog-photo-placeholder"><i data-lucide="camera" aria-hidden="true"></i><span>Adicionar foto</span></div>`
        }
                     <div class="catalog-photo-badge" aria-hidden="true">
                        <i data-lucide="image"></i> Alterar
                     </div>
                 </div>
                 <input type="file" id="ced-img-input" style="display:none;" onchange="uploadImage(this, 'ced-img-preview', 'ced_img_val')">
                 <input id="ced_img_val" type="hidden" value="${escapeAttr(item.imagem || '')}">
            </div>
        </div>

        <!-- COL 2: SPECS, USAGE & MARKET -->
        <div style="flex:1.5; min-width:300px; display:flex; flex-direction:column; gap:20px;">
            
            <!-- WHERE USED (TRACEABILITY) -->
             <div class="card" style="padding:20px; border:1px solid var(--border);">
                <h4 style="margin:0 0 15px 0; color:var(--text-main); font-size:0.9rem; display:flex; align-items:center; justify-content:space-between;">
                    <span style="display:flex; gap:8px;"><i data-lucide="link"></i> Rastreabilidade de Uso</span>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1;">${usage.length} Ativos</span>
                </h4>
                
                ${usage.length === 0 ?
            `<div style="text-align:center; padding:20px; color:var(--text-muted); font-size:0.9rem; border:1px dashed var(--border); border-radius:8px;">
                        Este item não está vinculado a nenhum ativo na árvore.
                     </div>` :
            `<div style="max-height:150px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
                        ${usage.map(u => `
                            <div class="flex-between" style="padding:10px; background:#f8fafc; border-radius:6px; border:1px solid var(--border);">
                                <div>
                                    <div style="font-weight:700; color:var(--text-main); font-size:0.9rem;">${escapeHtml(u.node.nome)}</div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);">${escapeHtml(u.path.join(' > '))}</div>
                                </div>
                                <button class="btn-sm btn-outline" onclick="locateAssetInTree(${u.node.id})"><i data-lucide="crosshatched"></i> Ir</button>
                            </div>
                        `).join('')}
                     </div>`
        }
            </div>

            <!-- TECH SPECS -->
            <div class="card" style="padding:20px; border:1px solid var(--border);">
                <div class="flex-between" style="margin-bottom:15px;">
                     <h4 style="margin:0; color:var(--text-main); font-size:0.9rem; display:flex; align-items:center; gap:8px;">
                        <i data-lucide="settings"></i> Especificações
                    </h4>
                    <button class="btn-sm btn-ghost" onclick="addSpecRow()">+ Adicionar</button>
                </div>
                
                <div id="spec-editor-container" style="display:grid; grid-template-columns: 1fr; gap:10px;">
                     ${Object.keys(specs).length === 0 ? `<div id="no-specs" style="color:var(--text-muted); font-style:italic; font-size:0.9rem;">Nenhuma especificação cadastrada.</div>` : ''}
                     ${Object.entries(specs).map(([k, v]) => `
                        <div class="flex-between" style="background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid var(--border);">
                            <input class="spec-key" value="${escapeAttr(k)}" style="border:none; background:transparent; font-weight:600; font-size:0.85rem; color:var(--text-muted); width:40%;" placeholder="Propriedade">
                            <input class="spec-val" value="${escapeAttr(v != null ? v : '')}" style="border:none; background:transparent; text-align:right; font-weight:600; color:var(--text-main); width:55%;" placeholder="Valor">
                            <button type="button" class="spec-remove-btn" aria-label="Remover especificação" onclick="this.parentElement.remove()">
                                <i data-lucide="x" aria-hidden="true"></i>
                            </button>
                        </div>
                     `).join('')}
                </div>
            </div>

            <!-- DESCRIPTION -->
             <div class="card" style="padding:20px; border:1px solid var(--border);">
                <label for="ced-desc" style="font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-bottom:8px; display:block;">Notas Técnicas / Observações</label>
                <textarea id="ced-desc" rows="3" style="width:100%; border:1px solid var(--border); border-radius:8px; padding:10px; font-size:0.9rem; resize:vertical;" placeholder="Detalhes adicionais sobre o item...">${escapeHtml(item.descricao || '')}</textarea>
             </div>
        </div>
    </div>
    
    <div style="margin-top:30px;">
         ${item.id ? `
         <div class="catalog-danger-zone" role="region" aria-labelledby="catalog-danger-title">
            <div class="catalog-danger-zone__content">
                <div class="catalog-danger-zone__icon" aria-hidden="true">
                    <i data-lucide="alert-triangle"></i>
                </div>
                <div>
                    <h4 id="catalog-danger-title" class="catalog-danger-zone__title">Excluir este item</h4>
                    <p class="catalog-danger-zone__desc">Remove <strong>${escapeHtml(item.nome || 'este item')}</strong> do inventário de forma permanente. Esta ação não pode ser desfeita.</p>
                </div>
            </div>
            <button type="button" class="btn-danger catalog-delete-btn"
                onclick="delItem(${item.id})"
                aria-label="Excluir ${escapeAttr(item.nome || 'item')} do catálogo permanentemente">
                <i data-lucide="trash-2" aria-hidden="true"></i>
                Excluir item do catálogo
            </button>
         </div>` : ''}
    </div>
    `;
    lucide.createIcons();
}

// Quick Stock Adjuster
function adjustQuickStock(delta) {
    const input = document.getElementById('ced-stock');
    let val = parseInt(input.value) || 0;
    val += delta;
    if (val < 0) val = 0;
    input.value = val;
}

// Helper helper
function locateAssetInTree(assetId) {
    // Switch to Assets view and select
    nav('assets');
    // Wait a bit
    setTimeout(() => {
        if (window.selectNode) {
            window.selectNode(assetId);
            // Expand to show it? (Would need path logic, but selectNode usually handles)
        }
    }, 500);
}

// --- IMAGE UPLOAD LOGIC ---
function triggerUpload(inputId) {
    document.getElementById(inputId).click();
}

async function uploadImage(input, previewId, valueId) {
    const file = input.files[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('action', 'upload_image');
    fd.append('file', file);

    const isAsset = valueId === 'af_img_val' || (previewId && previewId.startsWith('af-'));
    if (isAsset && editingNode && editingNode.id) {
        fd.append('asset_id', editingNode.id);
        const oldVal = document.getElementById('af_img_val')?.value;
        if (oldVal) fd.append('old_path', oldVal);
    }

    try {
        showToast('Enviando imagem...', 'info');
        const res = await fetch('api.php?action=upload_image', {
            method: 'POST',
            body: fd
        });
        const json = await res.json();

        if (json.ok && json.path) {
            const cacheBustedPath = json.path + '?t=' + Date.now();

            // Update Preview
            const prev = document.getElementById(previewId);
            let img = null;
            let span = null;

            if (prev) {
                if (prev.tagName === 'IMG') {
                    img = prev;
                    if (prev.parentElement) span = prev.parentElement.querySelector('span');
                } else {
                    img = prev.querySelector('img');
                    span = prev.querySelector('span');
                    // Catalog or custom container without <img> yet
                    if (!img) {
                        const placeholderDiv = prev.querySelector('.catalog-photo-placeholder');
                        if (placeholderDiv) placeholderDiv.style.display = 'none';
                        img = document.createElement('img');
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        prev.prepend(img);
                    }
                }
            }

            const imgValInput = document.getElementById(valueId);

            // FORCE UPDATE DOM
            if (img) {
                img.src = cacheBustedPath;
                img.style.display = 'block';
                img.style.objectFit = 'cover';
            }

            if (span) span.style.display = 'none';

            if (imgValInput) imgValInput.value = json.path;

            // Zoom & Remove Buttons
            const zoomBtn = document.getElementById(isAsset ? 'af-zoom-btn' : 'ced-zoom-btn');
            if (zoomBtn) zoomBtn.style.display = 'flex';

            const removeBtn = document.getElementById('af-remove-btn');
            if (isAsset && removeBtn) removeBtn.style.display = 'inline-block';

            // Asset Form Placeholder
            const placeholder = document.getElementById('af-img-placeholder');
            if (placeholder) placeholder.style.display = 'none';

            // Sticky Header Image
            const headerImg = document.getElementById('ash-header-img');
            if (isAsset && headerImg) {
                headerImg.src = cacheBustedPath;
                headerImg.style.display = 'block';
            }

            showToast('Imagem salva com sucesso!', 'success');

            // If it's the Asset Form, sync memory and persist directly
            if (isAsset && editingNode) {
                editingNode.imagem = json.path;
                if (typeof allNodesMap !== 'undefined' && allNodesMap.has(String(editingNode.id))) {
                    allNodesMap.get(String(editingNode.id)).imagem = json.path;
                }

                // Direct DB sync to guarantee persistence
                api('update_asset_image', { id: editingNode.id, imagem: json.path }).catch(err => {
                    console.warn('update_asset_image background sync:', err);
                });

                if (typeof updateLocalNode === 'function') updateLocalNode();
            }
        } else {
            showToast(json.error || 'Erro no upload: Resposta inválida', 'error');
        }
    } catch (e) {
        console.error('Upload Error:', e);
        showToast('Erro de conexão no upload.', 'error');
    } finally {
        input.value = '';
    }
}

async function removeAssetImage() {
    if (!editingNode || !editingNode.id) return;
    if (!confirm('Deseja remover a foto deste ativo?')) return;

    try {
        showToast('Removendo imagem...', 'info');
        const oldPath = editingNode.imagem || '';
        const res = await api('update_asset_image', { id: editingNode.id, imagem: '', old_path: oldPath });
        if (res && res.ok) {
            editingNode.imagem = '';
            if (typeof allNodesMap !== 'undefined' && allNodesMap.has(String(editingNode.id))) {
                allNodesMap.get(String(editingNode.id)).imagem = '';
            }

            const imgValInput = document.getElementById('af_img_val');
            if (imgValInput) imgValInput.value = '';

            const imgDisplay = document.getElementById('af-img-display');
            if (imgDisplay) {
                imgDisplay.removeAttribute('src');
                imgDisplay.style.display = 'none';
            }

            const placeholder = document.getElementById('af-img-placeholder');
            if (placeholder) placeholder.style.display = 'flex';

            const zoomBtn = document.getElementById('af-zoom-btn');
            if (zoomBtn) zoomBtn.style.display = 'none';

            const removeBtn = document.getElementById('af-remove-btn');
            if (removeBtn) removeBtn.style.display = 'none';

            const headerImg = document.getElementById('ash-header-img');
            if (headerImg) headerImg.src = getCompanyLogoUrl();

            showToast('Imagem removida com sucesso!', 'success');
            if (typeof updateLocalNode === 'function') updateLocalNode();
        } else {
            showToast((res && res.error) || 'Erro ao remover imagem.', 'error');
        }
    } catch (e) {
        console.error('removeAssetImage error:', e);
        showToast('Erro ao remover imagem.', 'error');
    }
}
window.removeAssetImage = removeAssetImage;

async function saveCatalogChanges(id) {
    const specs = {};
    document.querySelectorAll('#spec-editor-container .flex-between').forEach(row => {
        const k = row.querySelector('.spec-key').value;
        const v = row.querySelector('.spec-val').value;
        if (k) specs[k] = v;
    });

    const data = {
        id: id,
        nome: document.getElementById('ced-name').value || 'Novo Item',
        ip: document.getElementById('ced-ip')?.value ?? (currentCatItem ? currentCatItem.ip : ''),
        fabricante: document.getElementById('ced-manuf').value,
        codigo: document.getElementById('ced-code').value,
        estoque: document.getElementById('ced-stock').value,
        descricao: document.getElementById('ced-desc').value,
        localizacao: document.getElementById('ced-loc').value,
        tipo: document.getElementById('ced-type').value,
        imagem: document.getElementById('ced_img_val').value,
        specs: specs
    };

    const res = await api('save_catalog_item', data);
    if (res.ok) {
        showToast('Item salvo com sucesso!', 'success');
        loadCatalog(); // Refresh list to update sort/filter
    } else {
        showToast(res.error || 'Erro ao comunicar com servidor', 'error');
    }
}

async function delItem(id) {
    const item = catalog.find(x => x.id == id);
    const nome = item ? item.nome : 'este item';
    if (!confirm(`Tem certeza que deseja excluir "${nome}" do catálogo?\n\nEsta ação é permanente e não pode ser desfeita.`)) return;
    try {
        const res = await api('delete_catalog_item', { id });
        if (res.ok) {
            showToast('Item excluído com sucesso!', 'success');
            if (typeof closeCatalogDetailMobile === 'function') closeCatalogDetailMobile();
            document.getElementById('cat-detail-container').style.display = 'none';
            document.getElementById('cat-empty').style.display = 'flex';
            loadCatalog();
        }
    } catch (e) { console.error(e); }
}


// --- INTELLIGENT REAL-TIME SYNC (desativado na tela de ativos — evita piscar) ---
const RealTimeSync = {
    interval: null,
    status: 'online',
    start() {
        if (this.interval) clearInterval(this.interval);
        // Atualizacao leve apenas no dashboard/KPI — nao recarrega arvore de ativos
        this.interval = setInterval(() => this.backgroundRefresh(), 120000);
    },
    async backgroundRefresh() {
        if (document.hidden) return;
        const view = Session.getLastView();
        if (view === 'dash') loadDash();
        if (view === 'kpi') loadKPIs();
    }
};
RealTimeSync.start();

// --- CACHE DE LEITURA (só recarrega quando a planta muda) ---
window.PlantSync = {
    core: null,
    pi: null,
    fetchedAt: 0,
    inflight: null,
    TTL: 4000,
    async load(force) {
        if (!force && this.inflight) return this.inflight;
        if (!force && this.core && (Date.now() - this.fetchedAt) < this.TTL) {
            return { revision: this.core, pi_revision: this.pi };
        }
        this.inflight = (async () => {
            try {
                const res = await fetch(`${API_URL}?action=get_sync_revision`, { credentials: 'same-origin' });
                const json = await res.json();
                const data = (json && json.revision) ? json : (json && json.data) ? json.data : {};
                this.core = data.revision || '';
                this.pi = data.pi_revision || '';
                this.fetchedAt = Date.now();
                return { revision: this.core, pi_revision: this.pi };
            } catch (e) {
                return { revision: this.core || '', pi_revision: this.pi || '' };
            } finally {
                this.inflight = null;
            }
        })();
        return this.inflight;
    },
    invalidate() {
        this.core = null;
        this.pi = null;
        this.fetchedAt = 0;
        if (window.ApiSmartCache) window.ApiSmartCache.clear();
    }
};

window.ApiSmartCache = {
    store: new Map(),
    scopes: {
        get_stats: 'core',
        get_dash_stats: 'core',
        get_tasks: 'core',
        get_tree: 'core',
        get_catalog: 'core',
        get_kpis: 'core',
        neural_predict: 'core',
        get_asset_reliability: 'core',
        get_neural_context: 'core',
        get_analysis_history: 'core',
        get_pi_tags: 'pi',
        get_pi_telemetry: 'pi',
        pi_ai_diagnose: 'pi'
    },
    key(scope, revision, action, payload) {
        return `${scope}|${revision}|${action}|${JSON.stringify(payload || null)}`;
    },
    async lookup(action, payload) {
        const scope = this.scopes[action];
        if (!scope || !window.PlantSync) return null;
        const revs = await window.PlantSync.load();
        const revision = scope === 'pi' ? revs.pi_revision : revs.revision;
        if (!revision) return null;
        const key = this.key(scope, revision, action, payload);
        if (this.store.has(key)) {
            return { hit: true, value: this.store.get(key) };
        }
        return { hit: false, key };
    },
    put(key, value) {
        if (!key) return;
        this.store.set(key, value);
        if (this.store.size > 80) {
            const first = this.store.keys().next().value;
            this.store.delete(first);
        }
    },
    clear() {
        this.store.clear();
    }
};

function isMutatingApiAction(action) {
    return /^(save_|delete_|update_|set_|apply_|wipe_|upload_|report_|rotate_|migrate|receive_)/.test(action)
        || action.includes('_import_')
        || action === 'neural_generate_os'
        || action === 'sap_import_orders'
        || action === 'sap_import_materials';
}

function unwrapApiJson(action, json) {
    const readPrefixes = ['get_', 'list_', 'neural_predict', 'neural_diagnose'];
    const isReadAction = readPrefixes.some(p => action.startsWith(p) || action === p);
    if (json && json.ok === true && Array.isArray(json.data) && isReadAction) {
        return json.data;
    }
    if (json && json.ok === true) {
        return json;
    }
    return json;
}

window.LubtekOfflineQueue = window.LubtekOfflineQueue || {
    KEY: 'lub_api_queue',
    _merge: function (a, b) {
        const seen = {};
        const out = [];
        (a || []).concat(b || []).forEach(function (item) {
            if (!item || !item.action) return;
            const k = item.action + '|' + JSON.stringify(item.payload || null) + '|' + (item.time || 0);
            if (seen[k]) return;
            seen[k] = true;
            out.push(item);
        });
        return out;
    },
    _idb: function () {
        return new Promise(function (resolve, reject) {
            if (!window.indexedDB) return resolve(null);
            const req = indexedDB.open('lubtek_offline', 1);
            req.onupgradeneeded = function () {
                const db = req.result;
                if (!db.objectStoreNames.contains('kv')) db.createObjectStore('kv');
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { resolve(null); };
        });
    },
    save: function (items) {
        try { localStorage.setItem(this.KEY, JSON.stringify(items)); } catch (e) {}
        this._idb().then(function (db) {
            if (!db) return;
            try {
                const tx = db.transaction('kv', 'readwrite');
                tx.objectStore('kv').put(items, 'lub_api_queue');
            } catch (e) {}
        });
    },
    loadSync: function () {
        try { return JSON.parse(localStorage.getItem(this.KEY) || '[]'); } catch (e) { return []; }
    },
    hydrateFromIdb: async function () {
        const db = await this._idb();
        if (!db) return this.loadSync();
        return await new Promise((resolve) => {
            try {
                const tx = db.transaction('kv', 'readonly');
                const g = tx.objectStore('kv').get('lub_api_queue');
                g.onsuccess = () => {
                    const idbItems = Array.isArray(g.result) ? g.result : [];
                    const merged = this._merge(this.loadSync(), idbItems);
                    this.save(merged);
                    resolve(merged);
                };
                g.onerror = () => resolve(this.loadSync());
            } catch (e) { resolve(this.loadSync()); }
        });
    }
};
if (document.readyState === 'complete') {
    window.LubtekOfflineQueue.hydrateFromIdb();
} else {
    window.addEventListener('load', () => window.LubtekOfflineQueue.hydrateFromIdb());
}

// --- API HANDLER WITH VISUAL DEBUGGING ---
async function api(action, payload = null, options = null) {
    const skipCache = !!(options && options.skipCache);

    if (typeof isClienteUser === 'function' && isClienteUser()
        && typeof isMutatingApiAction === 'function' && isMutatingApiAction(action)
        && action !== 'change_password') {
        if (typeof showToast === 'function') {
            showToast('Conta de cliente: somente visualização.', 'warning');
        }
        return { ok: false, error: 'Somente visualização. Esta conta não pode alterar dados.' };
    }

    // Fila offline (mesma chave de scripts_extra) — preserva gravações sem conexão
    try {
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            const isRead = action.startsWith('get_') || action.startsWith('calc_') || action.startsWith('list_');
            if (!isRead) {
                const queue = (window.LubtekOfflineQueue && window.LubtekOfflineQueue.loadSync()) || JSON.parse(localStorage.getItem('lub_api_queue') || '[]');
                queue.push({ action, payload, time: Date.now() });
                if (window.LubtekOfflineQueue) window.LubtekOfflineQueue.save(queue);
                else localStorage.setItem('lub_api_queue', JSON.stringify(queue));
                if (typeof showToast === 'function') {
                    showToast('Salvo em modo OFFLINE. Será sincronizado quando retomar conexão.', 'info');
                }
                return { ok: true, offline: true };
            }
        }
    } catch (e) { /* ignore storage errors */ }

    try {
        if (!skipCache && action !== 'get_sync_revision' && window.ApiSmartCache) {
            const cached = await window.ApiSmartCache.lookup(action, payload);
            if (cached && cached.hit) {
                return cached.value;
            }
            options = Object.assign({}, options || {}, { _cacheKey: cached && cached.key });
        }

        const url = `${API_URL}?action=${action}`;
        const opts = payload ? {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        } : { credentials: 'same-origin' };

        const res = await fetch(url, opts);

        if (res.status === 401) {
            showToast('Sessao expirada. Redirecionando para login...', 'warning');
            setTimeout(() => { window.location.href = 'login.php'; }, 1200);
            throw new Error('HTTP 401 Session Expired');
        }

        // Robust Response Handling
        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch (e) {
            // If response is not JSON, it's a fatal server crash or network issue
            throw new Error(`Server Error (${res.status}): ${text.substring(0, 100)}`);
        }

        // Check logical error or HTTP error
        if (!res.ok || (json && json.ok === false)) {
            return json;
        }

        // Auto-trigger sync events for modification actions
        if (isMutatingApiAction(action)) {
            if (window.PlantSync) window.PlantSync.invalidate();
            Events.emit('data_changed', { action, payload });
            // Immediately refresh dashboard stats if visible
            if (Session.getLastView() === 'dash') loadDash();
        }

        const unwrapped = unwrapApiJson(action, json);
        if (options && options._cacheKey && window.ApiSmartCache) {
            window.ApiSmartCache.put(options._cacheKey, unwrapped);
        }
        return unwrapped;
    } catch (e) {
        console.warn(`API [${action}] falhou:`, e.message || e);
        if (action === 'get_tree' || action === 'get_catalog' || action === 'get_tasks') {
            throw e;
        }
        return null;
    }
}

// --- NODE MAP INDEX (O(1) Lookup) ---
let allNodesMap = new Map();

function indexTree(nodes) {
    if (!nodes) return;
    for (let n of nodes) {
        allNodesMap.set(String(n.id), n);
        if (n.children) indexTree(n.children);
    }
}

let loadTreePromise = null;
let loadTreeLastAt = 0;
const LOAD_TREE_COOLDOWN_MS = 2500;

function applyTreeData(cloudData) {
    treeState = cloudData;
    allNodesMap.clear();
    indexTree(treeState);
    renderTree();
}

async function loadTree(force = false) {
    if (!document.getElementById('asset-tree-container')) return;

    const now = Date.now();
    if (!force && (now - loadTreeLastAt) < LOAD_TREE_COOLDOWN_MS) return;
    if (loadTreePromise) return loadTreePromise;

    loadTreeLastAt = now;
    loadTreePromise = (async () => {
        const keepSelection = editingNode && editingNode.id ? String(editingNode.id) : null;

        try {
            const response = await api('get_tree');
            let cloudData;
            if (Array.isArray(response)) {
                cloudData = response;
            } else if (response && Array.isArray(response.data)) {
                cloudData = response.data;
            } else {
                cloudData = [];
            }

            applyTreeData(cloudData);

            const params = new URLSearchParams(window.location.search);
            const urlAssetId = params.get('id') || params.get('asset_id') || params.get('ativo_id');
            const reselectId = urlAssetId || keepSelection;
            if (reselectId && typeof selectNode === 'function') {
                const exists = allNodesMap.has(String(reselectId));
                if (exists) {
                    selectNode(reselectId, { soft: true });
                }
            }
        } catch (e) {
            console.warn('Falha ao carregar arvore — mantendo dados anteriores.', e);
            if (treeState && treeState.length > 0) {
                renderTree();
            } else {
                const container = document.getElementById('asset-tree-container');
                if (container) {
                    container.innerHTML = '<div style="color:var(--text-muted); text-align:center; padding:20px; font-size:0.9rem;">Falha ao carregar. Clique em Atualizar.</div>';
                }
            }
        }
    })().finally(() => { loadTreePromise = null; });

    return loadTreePromise;
}

// --- SEARCH / FILTER TREE ---
let currentFilter = '';
function filterTree(query) {
    currentFilter = query.toLowerCase();
    if (!currentFilter) {
        renderTree(treeState); // Reset
        return;
    }

    // Recursive Filter Function
    function filterNodes(nodes) {
        let filtered = [];
        for (let n of nodes) {
            // Extract IP & SAP code for search
            let ip = '';
            let sapCode = '';
            try {
                let tech = n.dados_tecnicos;
                if (typeof tech === 'string') tech = JSON.parse(tech);
                if (tech) {
                    if (tech.ip) ip = String(tech.ip).toLowerCase();
                    sapCode = String(tech.codigo_sap || tech.sap || tech.sap_code || tech.codigo || '').toLowerCase();
                }
            } catch (e) { }
            if (n.codigo) {
                sapCode = String(n.codigo).toLowerCase();
            }

            // Check current node
            const match = (n.nome || '').toLowerCase().includes(currentFilter) ||
                (n.tag && n.tag.toLowerCase().includes(currentFilter)) ||
                (n.tipo && n.tipo.toLowerCase().includes(currentFilter)) ||
                (ip && ip.includes(currentFilter)) ||
                (sapCode && sapCode.includes(currentFilter));

            // Recursively check children
            let childMatches = [];
            if (n.children) {
                childMatches = filterNodes(n.children);
            }

            // Keep node if it matches OR if it has matching children (pathway)
            if (match || childMatches.length > 0) {
                // Clone node to avoid mutating state
                const newNode = { ...n, children: childMatches };
                filtered.push(newNode);
            }
        }
        return filtered;
    }

    const filteredState = filterNodes(treeState);
    // Render with "Expand All" true because searching implies seeing results
    renderTree(filteredState, 0, true);
}


// --- PLANT BUILDER LOGIC ---
let treeState = [];
let editingNode = null;
// Restore expanded folders from LocalStorage
let expandedNodes = new Set(JSON.parse(localStorage.getItem('expanded_nodes') || '[]'));

// Recursive Render with Collapsible Logic
function renderTree(nodes = treeState, level = 0, forceExpand = false) {
    // SAFETY: Ensure Map Index exists (Fix for "Only works once" bug)
    if (level === 0 && allNodesMap.size === 0 && nodes && nodes.length > 0) {
        // Re-indexing tree for consistency...
        indexTree(nodes);
    }

    const container = document.getElementById('asset-tree-container');
    if (level === 0 && !container) return; // Prevent render if view is not active
    if (level === 0) container.innerHTML = ''; // Clear root

    if (!nodes || nodes.length === 0) {
        if (level === 0) {
            container.innerHTML = `
                <div style="color:var(--text-muted); text-align:center; padding:30px 16px; font-size:0.9rem; background:#f8fafc; border-radius:10px; margin:10px; border:1px dashed #cbd5e1;">
                    <i data-lucide="factory" style="width:36px; height:36px; color:var(--primary); margin-bottom:10px; opacity:0.7;"></i>
                    <p style="margin:0 0 12px 0; font-weight:600; color:var(--text-main);">Nenhum ativo na planta</p>
                    <button class="btn btn-primary" onclick="addNode(null)" style="padding:8px 14px; font-size:0.8rem; border-radius:8px;">
                        <i data-lucide="plus-circle" style="width:14px; margin-right:6px;"></i> Nova Fábrica
                    </button>
                </div>`;
            if (window.lucide) lucide.createIcons();
        }
        return;
    }

    // Sort:
    // 1. Type priority (Setor > Equip > Comp > Ponto)
    // 2. Alphabetical by Name
    const typeScore = { 'unidade': 5, 'setor': 4, 'equipamento': 3, 'componente': 2, 'ponto': 1 };
    nodes.sort((a, b) => {
        const scoreA = typeScore[a.tipo] || 0;
        const scoreB = typeScore[b.tipo] || 0;
        if (scoreA !== scoreB) return scoreB - scoreA;
        return (a.nome || '').localeCompare(b.nome || '');
    });

    const ul = document.createElement('div');
    // Strategic Indentation: Increased from 16px to 24px for better depth perception
    ul.style.paddingLeft = level === 0 ? '0' : '24px';
    if (level > 0) ul.style.borderLeft = '1px solid var(--border)'; // Visual guideline

    nodes.forEach(node => {
        const el = document.createElement('div');
        const hasChildren = node.children && node.children.length > 0;
        const isExpanded = forceExpand || expandedNodes.has(String(node.id));

        // Auto-expand removed to fix "forced open" usability issue
        // if (level === 0 && expandedNodes.size === 0 && !forceExpand) { ... }

        // Icon & Color Logic (Official Industrial Look)
        let icon = 'folder';
        let color = '#94a3b8';

        if (node.tipo === 'unidade' || node.tipo === 'setor') {
            icon = 'folder'; // Official "Pasta" icon
            color = (node.tipo === 'setor') ? '#f59e0b' : '#fbbf24';
        } else if (node.tipo === 'equipamento') {
            icon = 'settings'; // fa-cog replacement (settings/cog)
            color = '#3b82f6';
        } else if (node.tipo === 'componente') {
            icon = 'cog';
            color = '#a8a29e';
        } else if (node.tipo === 'ponto') {
            icon = 'target';
            color = '#ef4444';
        }

        // HEALTH STATUS OVERRIDE logic
        // node.health_recursive comes from API: 2=Critical, 1=Alert, 0=OK
        if (node.health_recursive == 2) color = 'var(--danger)';
        else if (node.health_recursive == 1) color = 'var(--warning)';

        // Active state
        const isActive = editingNode && editingNode.id === node.id;
        const activeClass = isActive ? 'active-item' : '';
        const txtColor = isActive ? '#000000' : 'var(--text-muted)';
        const chevron = hasChildren ? (isExpanded ? 'chevron-down' : 'chevron-right') : 'minus';
        const chevronOpacity = hasChildren ? '1' : '0.3';
        const cursorStyle = hasChildren ? 'pointer' : 'default';

        // Extract IP for display
        let ipBadge = '';
        try {
            let tech = node.dados_tecnicos;
            if (typeof tech === 'string') tech = JSON.parse(tech);
            if (tech && tech.ip) {
                ipBadge = `<span style="font-size:0.75em; color:var(--text-muted); opacity:0.8; margin-left:6px; background:rgba(0,0,0,0.05); padding:1px 4px; border-radius:3px; font-family:monospace; font-weight:bold;">#${escapeHtml(tech.ip)}</span>`;
            }
        } catch (e) { }

        // Recursive OS Counter Badge
        let osBadge = '';
        if (node.os_recursive > 0) {
            let badgeColor = (node.health_recursive == 2) ? 'var(--danger)' : ((node.health_recursive == 1) ?
                'var(--warning)' :
                'var(--primary)');
            osBadge = `<span
            style="background:${badgeColor}; color:white; padding:1px 6px; border-radius:10px; font-size:0.7rem; margin-left:6px; font-weight:bold; min-width:16px; text-align:center;">${node.os_recursive}</span>`;
        }

        // Name Simplification (Visual Only)
        // Removes "Unidade:", "Setor:", "Enchedora", "Linha" ONLY if used as prefix
        let cleanName = (node.nome || 'Sem Nome');
        cleanName = cleanName.replace(/^(Unidade|Setor|Enchedora|Linha|Equipamento|Componente)(\s+|:|-)\s*/i, "");

        // ISO 14224 Taxonomy Mapping
        let typeLabel = node.tipo;
        if (node.tipo === 'setor') typeLabel = 'Sistema';

        el.innerHTML = `
        <div class="tree-node ${activeClass}" id="tn-${node.id}"
            draggable="true"
            ondragstart="handleDragStart(event, '${node.id}')"
            ondragend="handleDragEnd(event, '${node.id}')"
            ondragover="handleDragOver(event, '${node.id}')"
            ondragenter="handleDragEnter(event, '${node.id}')"
            ondragleave="handleDragLeave(event, '${node.id}')"
            ondrop="handleAssetDrop(event, '${node.id}')"
            style="padding:4px 0; margin:1px 0; border-radius:4px; display:flex; align-items:center; gap:0px; transition:0.1s; color:${txtColor}; border:1px solid transparent; position: relative;"
            onmouseover="this.querySelector('.node-bg').style.background='rgba(0,0,0,0.03)'" onmouseout="this.querySelector('.node-bg').style.background='transparent'">
            
            <!-- TOGGLE BUTTON (Target Hit Area Optimized) -->
            <button onclick="toggleNode('${node.id}', event)"
                style="background:transparent; border:none; color:inherit; padding:0; width:30px; height:30px; min-width:30px; cursor:${cursorStyle}; opacity:${chevronOpacity}; display:flex; align-items:center; justify-content:center; border-radius:4px; margin-right:2px; transition:0.2s;"
                onmouseover="this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.background='transparent'">
                <i data-lucide="${hasChildren ? (isExpanded ? 'chevron-down' : 'chevron-right') : 'dot'}" style="width:18px; height:18px;"></i>
            </button>

            <!-- LABEL CONTENT -->
            <div onclick="selectNode('${node.id}')" class="node-bg"
                style="flex:1; display:flex; align-items:center; gap:8px; overflow:hidden; padding:4px 8px; border-radius:4px; cursor:pointer;" title="${escapeAttr(typeLabel)}: ${escapeAttr(cleanName)}">
                <i data-lucide="${icon}" style="width:16px; color:${color}; min-width:16px;"></i>
                <span class="node-name" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.9rem; font-weight:${isActive ? '700' : '400'}; color:${isActive ? 'black' : 'inherit'};">${escapeHtml(cleanName)}</span>
                ${ipBadge}
                ${osBadge}
            </div>
        </div>
        `;
        ul.appendChild(el);

        // Render Children ONLY if expanded
        if (hasChildren && isExpanded) {
            const childrenContainer = renderTree(node.children, level + 1, forceExpand);
            if (childrenContainer) ul.appendChild(childrenContainer);
        }
    });

    if (level === 0) {
        container.appendChild(ul);
        lucide.createIcons();
    }
    return ul;
}


function toggleNode(id, event) {
    if (event) event.stopPropagation();

    // Ensure ID is string for consistent Set matching
    const strId = String(id);

    if (expandedNodes.has(strId)) {
        // CLOSING: Recursive Collapse using MAP (O(1) access)
        expandedNodes.delete(strId);

        const node = allNodesMap.get(strId);
        if (node) {
            // Helper to close all descendants
            const closeRecursive = (n) => {
                if (n.children && n.children.length > 0) {
                    n.children.forEach(child => {
                        expandedNodes.delete(String(child.id));
                        closeRecursive(child);
                    });
                }
            };
            closeRecursive(node);
        }
    } else {
        // OPENING
        expandedNodes.add(strId);
    }

    // Persist
    localStorage.setItem('expanded_nodes', JSON.stringify(Array.from(expandedNodes)));

    // Force re-render with current local state
    renderTree();
}

window.applyTreeData = applyTreeData;
window.loadTree = loadTree;
window.reloadTree = loadTree; // Alias

// Fast Lookup using Map
function findNode(id, nodes = treeState) {
    const found = allNodesMap.get(String(id));
    return found || null;
}

// --- TAB LOGIC ---
function setAssetTab(tabId, btn) {
    document.querySelectorAll('.asset-tab-content').forEach(c => c.style.display = 'none');
    const target = document.getElementById('tab-' + tabId);
    if (target) target.style.display = 'block';

    document.querySelectorAll('.tab-btn, .asset-tab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    if (!editingNode) return;

    if (tabId === 'monitor' && typeof loadAnalysisHistory === 'function') {
        loadAnalysisHistory(editingNode.id);
    }
    if (tabId === 'hist' && typeof loadAssetTimeline === 'function') {
        loadAssetTimeline(editingNode.id);
    }
    if (tabId === 'market_tab') {
        const mat = document.getElementById('af-material')?.value;
        if (mat && typeof checkMarketForProduct === 'function') checkMarketForProduct(mat);
    }
}
window.setAssetTab = setAssetTab;

// --- BUTTON ACTIONS IMPLEMENTATION ---
// 1. 3D VIEW (Redirects to Revolutionary Engine with asset context)
function open3DView() {
    if (!editingNode || !editingNode.id) {
        return showToast('Selecione um ativo para visualizar.', 'warning');
    }
    window.location.href = '?page=3d&id=' + editingNode.id;
}
window.open3DView = open3DView;

// 2. MAPA (Generates Org Chart / Map Print)
function printAssetMap() {
    if (!editingNode) return showToast('Selecione um ativo para gerar o mapa.', 'warning');

    const printArea = document.getElementById('print-area');
    if (!printArea) return;

    // Generate Map HTML (Hierarchy)
    let html = `
            <div style="padding:40px; font-family:'Segoe UI', Arial, sans-serif;">
                <div style="border-bottom:2px solid #000; padding-bottom:15px; margin-bottom:30px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h1 style="margin:0; font-size:24px;">MAPA DE ATIVOS - HIERARQUIA</h1>
                        <p style="margin:5px 0 0 0; color:#666;">Visualização Topológica Estendida</p>
                    </div>
                    <div style="text-align:right; font-size:12px; color:#444;">
                        <strong>Data:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}<br>
                        <strong>Sistema:</strong> LUB-TEK 3.0 Enterprise
                    </div>
                </div>
                
                <div style="display:flex; flex-direction:column; align-items:center; width:100%;">
        `;

    // Recursive Map Builder Function
    function buildMap(node, depth = 0) {
        const hasChildren = node.children && node.children.length > 0;
        const colors = {
            'unidade': '#2563eb',
            'setor': '#d97706',
            'equipamento': '#059669',
            'componente': '#475569',
            'ponto': '#dc2626'
        };
        const color = colors[node.tipo] || '#64748b';

        let tech = node.dados_tecnicos;
        try { if (typeof tech === 'string') tech = JSON.parse(tech || '{}'); } catch (e) { tech = null; }

        let chartHtml = `
                <div style="display:flex; flex-direction:column; align-items:center; margin:0 10px;">
                    <div style="
                        border:2px solid ${color}; 
                        border-left:5px solid ${color};
                        border-radius:6px; 
                        padding:8px 15px; 
                        margin-bottom:20px; 
                        background:#fff;
                        text-align:center;
                        min-width:140px;
                        box-shadow:0 2px 5px rgba(0,0,0,0.05);
                        font-size:12px;
                    ">
                        <div style="font-weight:800; color:#1e293b; font-size:14px; margin-bottom:2px;">${escapeHtml(node.nome)}</div>
                        <div style="font-size:10px; color:${color}; text-transform:uppercase; font-weight:bold;">${escapeHtml(node.tipo)}</div>
                        ${tech && tech.ip ? `<div style="margin-top:4px; font-family:monospace; background:#f1f5f9; padding:2px;">#${escapeHtml(tech.ip)}</div>` : ''}
                    </div>
            `;

        if (hasChildren) {
            // Connector Line Logic
            chartHtml += `<div style="display:flex; position:relative; padding-top:20px; border-top:2px solid #cbd5e1; gap:15px;">`;

            // Vertical Line connecting Parent to Bar
            chartHtml += `<div style="position:absolute; top:0; left:50%; width:2px; height:20px; background:#cbd5e1; transform:translateX(-50%);"></div>`;

            node.children.forEach(child => {
                chartHtml += buildMap(child, depth + 1);
            });
            chartHtml += `</div>`;
        }

        chartHtml += `</div>`;
        return chartHtml;
    }

    html += buildMap(editingNode);
    html += `
                </div>
                <div style="margin-top:50px; border-top:1px solid #ddd; padding-top:10px; text-align:center; font-size:10px; color:#999;">
                    Este documento é gerado automaticamente pelo sistema LUB-TEK.
                </div>
            </div>`;

    printArea.innerHTML = html;
    window.print();

    // Auto-cleanup not strictly necessary as print-area is hidden, but good practice
}

function toggleFullWidth() {
    const form = document.getElementById('asset-form');
    const btn = document.getElementById('btn-toggle-asset-full');

    const isActivating = !form.classList.contains('full-screen-active');

    if (isActivating) {
        form.classList.add('full-screen-active');
        document.body.classList.add('hide-sidebar');
        const treePanel = document.getElementById('asset-tree-panel');
        if (treePanel) treePanel.style.display = 'none';
        showToast('Modo Foco Ativado', 'success');
        if (btn) btn.innerHTML = '<i data-lucide="minimize-2" style="width:16px;"></i>';

        // --- ATOMIC STYLES (Inline to beat cache) ---
        form.style.setProperty('position', 'fixed', 'important');
        form.style.setProperty('top', '0', 'important');
        form.style.setProperty('left', '0', 'important');
        form.style.setProperty('width', '100vw', 'important');
        form.style.setProperty('height', '100vh', 'important');
        form.style.setProperty('z-index', '999999', 'important');
        form.style.setProperty('background', 'white', 'important');
        form.style.setProperty('padding', '40px', 'important');
        form.style.setProperty('display', 'flex', 'important');

        // Force Sidebar Hide (Atomic)
        const aside = document.querySelector('aside');
        const main = document.querySelector('main');
        if (aside) aside.style.setProperty('display', 'none', 'important');
        if (main) {
            main.style.setProperty('margin', '0', 'important');
            main.style.setProperty('width', '100vw', 'important');
            main.style.setProperty('max-width', '100vw', 'important');
        }
    } else {
        form.classList.remove('full-screen-active');
        document.body.classList.remove('hide-sidebar');
        const treePanel = document.getElementById('asset-tree-panel');
        if (treePanel) treePanel.style.removeProperty('display');
        showToast('Modo Normal Restaurado', 'info');
        if (btn) btn.innerHTML = '<i data-lucide="maximize-2" style="width:16px;"></i>';

        // --- RESTORE ---
        const props = ['position', 'top', 'left', 'width', 'height', 'z-index', 'background', 'padding', 'display'];
        props.forEach(p => form.style.removeProperty(p));

        const aside = document.querySelector('aside');
        const main = document.querySelector('main');
        if (aside) aside.style.removeProperty('display');
        if (main) {
            main.style.removeProperty('margin');
            main.style.removeProperty('width');
            main.style.removeProperty('max-width');
        }
    }

    // Trigger resize for charts
    setTimeout(() => {
        window.dispatchEvent(new Event('resize'));
        lucide.createIcons();
    }, 150);
}

async function loadAssetTimeline(id) {
    const container = document.getElementById('timeline-container');
    container.innerHTML = '<div style="text-align:center; color:var(--text-muted); padding:20px;">Carregando histórico...<br><i data-lucide="loader-2" class="spin"></i></div>';
    lucide.createIcons();

    try {
        const res = await api('get_asset_timeline', { id: id });
        const timeline = res?.timeline || [];

        if (timeline.length === 0) {
            container.innerHTML = `
        <div style="text-align:center; color:var(--text-muted); opacity:0.6; margin-top:30px;">
            <i data-lucide="clock" style="width:48px; height:48px;"></i>
            <p>Nenhum evento registrado para este ativo.</p>
        </div>
        `;
            lucide.createIcons();
            return;
        }

        container.innerHTML = timeline.map((item, index) => {
            const isLast = index === timeline.length - 1;
            const dateObj = new Date(item.date);
            const day = dateObj.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            const time = dateObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

            return `
        <div style="display:flex; gap:15px;">
            <!--Time Column-->
            <div style="width:60px; text-align:right; flex-shrink:0; padding-top:4px;">
                <div style="font-weight:bold; color:var(--text-main); font-size:0.9rem;">${day}</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">${time}</div>
            </div>

            <!--Timeline Line & Dot-- >
    <div style="position:relative; display:flex; flex-direction:column; align-items:center;">
        <!-- Line -->
            <div
                style="width:2px; background:var(--border); flex:1; display:${isLast ? 'none' : 'block'}; min-height:20px;">
            </div>
            <!-- Dot -->
            <div
                style="width:12px; height:12px; border-radius:50%; background:${item.color}; border:2px solid var(--bg-panel); position:absolute; top:6px;">
            </div>
        </div>

        <!--Content Card-- >
                    <div class="card" style="flex:1; padding:12px; margin-bottom:20px; border-left:3px solid ${item.color};">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <h4 style="margin:0; font-size:0.95rem; display:flex; align-items:center; gap:8px;">
                                <i data-lucide="${item.icon}" style="width:14px; color:${item.color};"></i>
                                ${escapeHtml(item.title)}
                            </h4>
                            <span
                                style="font-size:0.7rem; background:${item.type === 'task' ? 'rgba(0,0,0,0.2)' : 'rgba(255,255,255,0.1)'}; padding:2px 6px; border-radius:4px;">${item.type
                    === 'task' ? 'OS' : 'AUDIT'}</span>
                        </div>
                        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:4px;">${escapeHtml(item.subtitle)}</div>
                        ${item.details ? `<div
            style="font-size:0.8rem; margin-top:6px; background:rgba(0,0,0,0.2); padding:6px; border-radius:4px; font-style:italic;">
            "${escapeHtml(item.details)}"</div>` : ''}
                    </div>
</div >
                    `;
        }).join('');

        lucide.createIcons();

    } catch (e) {
        console.error(e);
        container.innerHTML = '<div style="color:var(--danger);">Erro ao carregar histórico.</div>';
    }
}

// RECURSIVE FIND (Safe Helpers)
function findNode(nodes, id) {
    if (!nodes || !Array.isArray(nodes)) return null;
    for (let node of nodes) {
        if (node.id == id) return node;
        if (node.children) {
            const found = findNode(node.children, id);
            if (found) return found;
        }
    }
    return null;
}

async function selectNode(id, opts = {}) {
    const soft = opts.soft === true;
    // 1. Locate Node (mapa O(1) ou busca recursiva)
    const node = allNodesMap.get(String(id)) || findNode(treeState, id);
    if (!node) return;

    // CHECK: Are we on the Asset Page?
    const assetForm = document.getElementById('asset-form');
    if (!assetForm) {
        if (typeof nav === 'function') {
            nav('assets');
            setTimeout(() => selectNode(id), 300);
        } else {
            window.location.href = '?page=assets&id=' + id + '&asset_id=' + id;
        }
        return;
    }

    // Re-selecao silenciosa apos refresh da arvore (nao reseta abas)
    if (soft && editingNode && String(editingNode.id) === String(id)) {
        document.querySelectorAll('.tree-node').forEach(el => el.classList.remove('active-item'));
        const treeEl = document.getElementById(`tn-${id}`);
        if (treeEl) treeEl.classList.add('active-item');
        editingNode = node;
        return;
    }

    // 2. UI Persistence & Highlight
    editingNode = node;
    window.editingNode = node;
    document.querySelectorAll('.tree-node').forEach(el => el.classList.remove('active-item'));
    const treeEl = document.getElementById(`tn-${id}`);
    if (treeEl) treeEl.classList.add('active-item');

    // 3. Switch View
    const assetEmpty = document.getElementById('asset-empty');
    if (assetEmpty) assetEmpty.style.display = 'none';
    assetForm.style.display = 'flex';

    // 4. Fill Basic Inputs (IDs corretos do formulário)
    const basicFieldMap = {
        nome: 'af-name',
        tag: 'af-tag',
        fabricante: 'af-fabricante',
        modelo: 'af-modelo',
        num_serie: 'af-num_serie',
        obs: 'af-obs',
        tipo: 'af-type',
        pai_id: 'af-parent'
    };
    Object.entries(basicFieldMap).forEach(([key, elId]) => {
        const el = document.getElementById(elId);
        if (el) el.value = (node[key] != null && node[key] !== '') ? node[key] : '';
    });

    // Título do painel
    const titleEl = document.getElementById('af-title');
    if (titleEl) titleEl.textContent = node.nome || 'Editar Item';

    // 5. Fill Technical Data (Safe Parse)
    let tech = {};
    if (typeof node.dados_tecnicos === 'string') {
        try { tech = JSON.parse(node.dados_tecnicos); } catch (e) { }
    } else if (typeof node.dados_tecnicos === 'object') {
        tech = node.dados_tecnicos || {};
    }

    const techFields = [
        'ip', 'ponto_lub', 'servico', 'num_pontos',
        'duracao_h', 'duracao_m', 'periodo', 'prioridade',
        'rota', 'procedimento', 'complemento',
        'condicao', 'sistema_lub', 'last_os', 'next_os',
        'material', 'qtd_material', 'unid_material', 'kit',
        'bushing_d', 'bushing_l', 'bushing_k',
        'instrucao', 'metodo', 'ordem_rota', 'capacidade', 'rpm',
        'sap', 'almox', 'cod_serv', 'complemento',
        'clientes', 'profissionais', 'marker_x', 'marker_y'
    ];
    techFields.forEach(k => {
        const el = document.getElementById(`af-${k}`);
        if (!el) return;
        const v = tech[k];
        if (el.type === 'number') {
            el.value = safeInputValue(v, true);
            if (el.value === '' && (v === 0 || v === '0')) el.value = '0';
        } else {
            el.value = safeInputValue(v, false);
        }
    });

    // Toggle conditional factory details group
    const factoryGroup = document.getElementById('af-factory-group');
    if (factoryGroup) {
        factoryGroup.style.display = (node.tipo === 'unidade') ? 'block' : 'none';
    }

    // 6. Image Handling
    const imgDisplay = document.getElementById('af-img-display');
    const headerImg = document.getElementById('ash-header-img');
    const placeholder = document.getElementById('af-img-placeholder');
    const zoomBtn = document.getElementById('af-zoom-btn');
    const removeBtn = document.getElementById('af-remove-btn');
    const imgValInput = document.getElementById('af_img_val');

    if (imgValInput) imgValInput.value = node.imagem || '';

    if (node.imagem && String(node.imagem).trim().length > 0) {
        if (imgDisplay) {
            imgDisplay.src = node.imagem;
            imgDisplay.style.display = 'block';
        }
        if (placeholder) placeholder.style.display = 'none';
        if (zoomBtn) zoomBtn.style.display = 'flex';
        if (removeBtn) removeBtn.style.display = 'inline-block';
        if (headerImg) {
            headerImg.src = node.imagem;
            headerImg.style.display = 'block';
        }
    } else {
        if (imgDisplay) {
            imgDisplay.removeAttribute('src');
            imgDisplay.style.display = 'none';
        }
        if (placeholder) placeholder.style.display = 'flex';
        if (zoomBtn) zoomBtn.style.display = 'none';
        if (removeBtn) removeBtn.style.display = 'none';
        if (headerImg) {
            headerImg.src = getCompanyLogoUrl();
        }
    }

    // 7. Breadcrumbs
    function buildPath(id, path = []) {
        const n = findNode(treeState, id);
        if (!n) return path;
        path.unshift((n.nome || '').toUpperCase());
        if (n.pai_id) return buildPath(n.pai_id, path);
        return path;
    }
    const pathArray = buildPath(node.id);
    const pathDisplay = document.getElementById('ash-path-display');
    if (pathDisplay) pathDisplay.innerHTML = pathArray.map(p => escapeHtml(p)).join(' <span>&gt;</span> ');

    // 8. Reset to Data Tab (Visual Preference)
    // Ensure first tab is active
    const firstTab = document.querySelector('.tab-btn');
    if (window.setAssetTab && firstTab) setAssetTab('dados', firstTab);

    // 9. Load History
    if (window.loadAssetTimeline) loadAssetTimeline(id);

    // 10. IA & Confiabilidade (API real)
    if (typeof loadAssetReliability === 'function') {
        loadAssetReliability(id);
    }
    if (window.AppEvents) {
        window.AppEvents.emit('node:selected', node);
    }
    if (window.Ecosystem) {
        Ecosystem.setCurrentAsset(node);
    }

    lucide.createIcons();
}

window.selectNode = selectNode;

async function cleanupUnnamed() {
    if (!confirm("Remover todos os ativos marcados como 'Sem Nome', vazios ou nulos?")) return;

    try {
        const res = await api('cleanup_sem_nome');
        if (res.ok) {
            showToast(`Limpeza concluída. ${res.deleted || 0} itens removidos.`, 'success');
            loadTree();
        } else {
            showToast('Erro ao limpar.', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Erro de conexão.', 'error');
    }
}

function updateLocalNode() {
    if (!editingNode) return;
    // Basic Fields
    editingNode.nome = document.getElementById('af-name').value;
    editingNode.tag = document.getElementById('af-tag') ? document.getElementById('af-tag').value : '';
    editingNode.tipo = document.getElementById('af-type') ? document.getElementById('af-type').value : 'unidade';
    editingNode.obs = document.getElementById('af-obs') ? document.getElementById('af-obs').value : '';

    // Safe Access for Extended Fields
    const fab = document.getElementById('af-fabricante');
    if (fab) editingNode.fabricante = fab.value;

    const mod = document.getElementById('af-modelo');
    if (mod) editingNode.modelo = mod.value;

    const ser = document.getElementById('af-num_serie');
    if (ser) editingNode.num_serie = ser.value;

    // Image
    const imgVal = document.getElementById('af_img_val');
    if (imgVal) editingNode.imagem = imgVal.value;

    // Technical Fields (Sync to memory for persistence across tab switches)
    const tech = editingNode.dados_tecnicos && typeof editingNode.dados_tecnicos === 'object' ? editingNode.dados_tecnicos : {};

    const techFields = [
        'ip', 'ponto_lub', 'servico', 'num_pontos',
        'duracao_h', 'duracao_m', 'periodo', 'prioridade',
        'rota', 'procedimento', 'complemento',
        'condicao', 'sistema_lub', 'last_os', 'next_os',
        'material', 'qtd_material', 'unid_material', 'kit',
        'bushing_d', 'bushing_l', 'bushing_k',
        'instrucao', 'metodo', 'ordem_rota', 'capacidade', 'rpm',
        'sap', 'almox', 'cod_serv', 'complemento',
        'clientes', 'profissionais', 'marker_x', 'marker_y'
    ];

    techFields.forEach(field => {
        const el = document.getElementById('af-' + field);
        if (el) tech[field] = el.value;
    });

    editingNode.dados_tecnicos = tech;

    // Toggle conditional factory details group on type changes
    const factoryGroup = document.getElementById('af-factory-group');
    if (factoryGroup) {
        factoryGroup.style.display = (editingNode.tipo === 'unidade') ? 'block' : 'none';
    }

    // saveLocal(); // Only if using localStorage persistence
    if (window.updateTreeNodeDOM) updateTreeNodeDOM(editingNode);
}

// --- THREE LAYER ABSTRACTION (Identity, Maquette, Engineering) ---
function extractAssetIdentity() {
    return {
        id: editingNode ? editingNode.id : null,
        nome: document.getElementById('af-name').value,
        tag: document.getElementById('af-tag') ? document.getElementById('af-tag').value : '',
        tipo: document.getElementById('af-type') ? document.getElementById('af-type').value : 'unidade',
        obs: document.getElementById('af-obs') ? document.getElementById('af-obs').value : '',
        fabricante: document.getElementById('af-fabricante') ? document.getElementById('af-fabricante').value : '',
        modelo: document.getElementById('af-modelo') ? document.getElementById('af-modelo').value : '',
        num_serie: document.getElementById('af-num_serie') ? document.getElementById('af-num_serie').value : '',
        pai_id: editingNode.pai_id // Preserve parent linkage
    };
}

function extractMaquetteFiles() {
    return {
        imagem: document.getElementById('af_img_val') ? document.getElementById('af_img_val').value : ''
        // Future: 3D models, CAD files, Documents
    };
}

function extractTechnicalData() {
    const tech = {};
    const techFields = [
        'ip', 'ponto_lub', 'servico', 'num_pontos',
        'duracao_h', 'duracao_m', 'periodo', 'prioridade',
        'rota', 'procedimento', 'complemento',
        'condicao', 'sistema_lub', 'last_os', 'next_os',
        'material', 'qtd_material', 'unid_material', 'kit',
        'bushing_d', 'bushing_l', 'bushing_k',
        'instrucao', 'metodo', 'ordem_rota', 'capacidade', 'rpm',
        'sap', 'almox', 'cod_serv', 'complemento',
        'clientes', 'profissionais', 'marker_x', 'marker_y'
    ];
    techFields.forEach(field => {
        const el = document.getElementById('af-' + field);
        if (el) tech[field] = el.value;
    });
    return tech;
}

async function saveToCloudSingle() {
    if (!editingNode) return;

    // 1. Layer Extraction
    const identity = extractAssetIdentity();
    const maquette = extractMaquetteFiles();
    const engineering = extractTechnicalData();

    // Validation
    if (!identity.tipo) return showToast('Por favor, selecione um Objeto/Tipo.', 'error');

    // 2. Data Assemblage
    const payload = {
        ...identity,
        ...maquette,
        dados_tecnicos: engineering
    };

    try {
        const res = await api('save_asset', payload);
        if (res && res.error_code === 'GREASE_INCOMPATIBLE') {
            const ok = confirm((res.error || 'Graxas incompatíveis.') + '\n\nConfirmar purga/limpeza completa do alojamento? Uma O.S. de lavagem será aberta.');
            if (ok) {
                const res2 = await api('save_asset', Object.assign({}, payload, { confirm_purge: true }));
                if (res2 && res2.ok) {
                    showToast('Salvo. O.S. de lavagem gerada.', 'warning');
                    Object.assign(editingNode, payload);
                    updateTreeNodeDOM(editingNode);
                    return;
                }
                showToast((res2 && res2.error) || 'Falha ao salvar após confirmação.', 'error');
                return;
            }
            showToast('Alteração cancelada. O ponto não foi modificado.', 'info');
            return;
        }
        if (res.ok) {
            showToast('Sincronizado com Sucesso!', 'success');

            // Update Local Object
            Object.assign(editingNode, payload);

            // Update DOM (Partial)
            updateTreeNodeDOM(editingNode);
        } else {
            showToast(res.error || 'Erro ao salvar.', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Erro de conexão.', 'error');
    }
}

function updateTreeNodeDOM(node) {
    if (!node || !node.id) return; // Verify node exists

    const el = document.getElementById(`tn-${node.id}`);
    if (!el) return; // Element doesn't exist in DOM

    // Update Name (with simplification)
    const nameSpan = el.querySelector('.node-name');
    if (nameSpan) {
        let nInfo = node.nome || 'Sem Nome';
        nInfo = nInfo.replace(/^(Unidade|Setor|Enchedora|Linha|Equipamento|Componente)(\s+|:|-)\s*/i, "");
        nameSpan.innerText = nInfo;
    }


    // Update IP
    const ipSpan = el.querySelector('.node-ip');
    if (ipSpan) {
        let ipStr = '';
        try {
            const tech = typeof node.dados_tecnicos === 'string' ? JSON.parse(node.dados_tecnicos) : node.dados_tecnicos;
            if (tech && tech.ip) {
                ipStr = `<span style="font-size:0.75em; color:var(--primary); margin-left:8px; background:rgba(14, 165, 233, 0.1); padding:2px 6px; border-radius:4px; font-family:monospace; font-weight:bold;">#${escapeHtml(tech.ip)}</span>`;
            }
        } catch (e) { }
        ipSpan.innerHTML = ipStr;
    }

    // Update Icon/Color based on type
    const iconEl = el.querySelector('i[data-lucide]');
    if (iconEl) {
        let icon = 'folder';
        let color = '#94a3b8';
        if (node.tipo === 'unidade' || node.tipo === 'setor') {
            icon = 'folder';
            color = (node.tipo === 'setor') ? '#f59e0b' : '#fbbf24';
        } else if (node.tipo === 'equipamento') {
            icon = 'settings';
            color = '#3b82f6';
        } else if (node.tipo === 'componente') {
            icon = 'cog';
            color = '#a8a29e';
        } else if (node.tipo === 'ponto') {
            icon = 'target';
            color = '#ef4444';
        }

        iconEl.setAttribute('data-lucide', icon);
        iconEl.style.color = color;
        lucide.createIcons();
    }
}

// --- LOCAL STORAGE (BACKUP ONLY) LOGIC ---
function saveLocal() {
    // Deprecated/Backup only. Main save is now Cloud.
    localStorage.setItem('rodrigo_plant_draft', JSON.stringify(treeState));
}

function loadFromLocal() {
    const data = localStorage.getItem('rodrigo_plant_draft');
    if (data) {
        if (confirm('Carregar backup local?')) {
            treeState = JSON.parse(data);
            renderTree();
        }
    }
}

function generateNextAssetIP(nodes = treeState) {
    let max = 0;

    function traverse(list) {
        list.forEach(node => {
            // Check IP in dados_tecnicos
            try {
                let tech = node.dados_tecnicos;
                if (typeof tech === 'string') tech = JSON.parse(tech);
                if (tech && tech.ip) {
                    const n = parseInt(tech.ip);
                    if (!isNaN(n) && n > max) max = n;
                }
            } catch (e) { }

            if (node.children) traverse(node.children);
        });
    }
    traverse(nodes);
    return max + 1;
}

// --- CLOUD SYNC ---
async function addNode(parentId) {
    const isRoot = parentId === null;
    const defaultName = isRoot ? "Nova Fábrica" : "Novo Item";

    // Type Inference based on Parent
    let newType = 'setor';
    if (editingNode && editingNode.id === parentId) {
        const pType = editingNode.tipo || '';
        if (pType === 'unidade') newType = 'setor';
        else if (pType === 'setor') newType = 'equipamento';
        else if (pType === 'equipamento') newType = 'componente';
        else if (pType === 'componente') newType = 'ponto';
        else newType = 'componente';
    }

    // Enforce immediate COMMIT in DB for the structural link
    try {
        const res = await api('save_asset', {
            nome: defaultName,
            tipo: isRoot ? 'unidade' : newType,
            pai_id: parentId,
            dados_tecnicos: { ip: generateNextAssetIP() }
        });

        if (res && res.ok) {
            // Instead of full reload, we fetch the new node or just reload the tree once
            // BUT to follow the "no re-render" rule, we must inject it.
            // For simplicity and safety on new structures, we reload tree but select THE NEW ID.
            await loadTree();
            selectNode(res.id);

            // Focus on name for immediate editing
            setTimeout(() => {
                const nameInp = document.getElementById('af-name');
                if (nameInp) {
                    nameInp.focus();
                    nameInp.select();
                }
            }, 100);

            showToast('Novo nó criado e vinculado!', 'success');
        } else {
            console.warn('Save failed:', res);
            showToast(res.error || 'Erro ao criar item.', 'error');
        }
    } catch (e) {
        console.error('addNode error:', e);
        showToast('Erro interno: ' + e.message, 'error');
    }
}

async function bulkAutoIP() {
    if (!confirm('Isto irá gerar IPs sequenciais para TODOS os itens que ainda não possuem. Deseja continuar?')) return;

    showToast('Gerando IPs...', 'info');
    let nextIp = generateNextAssetIP(); // Start from next valid
    let modified = 0;

    async function traverseAndFix(list) {
        for (let node of list) {
            let tech = {};
            try {
                tech = typeof node.dados_tecnicos === 'string' ? JSON.parse(node.dados_tecnicos || '{}') : (node.dados_tecnicos || {});
            } catch (e) { tech = {}; }

            if (!tech.ip) {
                tech.ip = nextIp++;
                node.dados_tecnicos = tech;
                modified++;

                // Silent Save
                try {
                    await api('save_asset', {
                        id: node.id,
                        nome: node.nome,
                        tag: node.tag,
                        tipo: node.tipo,
                        obs: node.obs,
                        pai_id: node.pai_id,
                        imagem: node.imagem,
                        dados_tecnicos: tech
                    });
                } catch (e) { console.error("Bulk save fail", e); }
            }

            if (node.children && node.children.length > 0) {
                await traverseAndFix(node.children);
            }
        }
    }

    await traverseAndFix(treeState);
    renderTree();
    showToast(`Processo concluído! ${modified} novos IPs gerados.`, 'success');
}

async function saveSystemSnapshot() {
    if (!confirm('Deseja salvar o estado atual do sistema (Ativos e Catálogo) como o novo "Ponto de Restauração"?\n\nIsso permitirá que você restaure exatamente esta versão no futuro.')) return;

    showToast('Salvando Snapshot no Servidor...', 'info');
    try {
        const res = await api('save_snapshot');
        if (res.ok) {
            showToast(`Snapshot salvo! (${res.assets_count} ativos)`, 'success');
            // Snapshot saved successfully
        } else {
            throw new Error(res.error || 'Erro desconhecido');
        }
    } catch (e) {
        console.error(e);
        showToast('Falha ao salvar snapshot: ' + e.message, 'error');
    }
}

async function wipeDatabase() {
    if (!confirm('PERIGO EXTREMO!\n\nVoce tem certeza que deseja APAGAR TODO O BANCO DE DADOS?\n\nIsso excluira PERMANENTEMENTE:\n- Todas as Fabricas\n- Todos os Ativos\n- Todas as Ordens de Servico\n\nNao ha como desfazer.')) return;

    if (!confirm('CONFIRMAÇÃO FINAL: Deseja realmente limpar tudo?')) return;

    showToast('Resetando Sistema...', 'warning');
    try {
        const res = await api('wipe_db');
        if (res.ok) {
            showToast('Sistema limpo! Recarregando...', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            throw new Error(res.error || 'Erro ao limpar banco.');
        }
    } catch (e) {
        console.error(e);
        showToast('Erro: ' + e.message, 'error');
    }
}

function autoFormatName(el) {
    if (!el || !el.value) return;
    el.value = el.value.toUpperCase();
    updateLocalNode();
}

async function analyzeAssetAI() {
    if (!editingNode) return showToast('Selecione um ativo na lista à esquerda.', 'warning');

    const name = document.getElementById('af-name')?.value || '';
    const tag = document.getElementById('af-tag')?.value || '';
    const type = document.getElementById('af-type')?.value || '';
    const fab = document.getElementById('af-fabricante')?.value || '';
    const model = document.getElementById('af-modelo')?.value || '';
    const rpm = document.getElementById('af-rpm')?.value || '';

    let report = { errors: [], suggestions: [], score: 100 };

    if (!name || name.length < 3) {
        report.errors.push('Nome do ativo muito curto ou vazio.');
        report.score -= 20;
    }
    if (!tag) {
        report.suggestions.push('Adicione uma TAG para facilitar a busca.');
        report.score -= 10;
    }
    if (type === 'componente' && (!fab || !model)) {
        report.suggestions.push('Informe Fabricante e Modelo para componentes.');
        report.score -= 15;
    }
    if (type === 'equipamento' && !document.getElementById('af_img_val')?.value) {
        report.suggestions.push('Adicione uma foto do equipamento para identificação visual.');
        report.score -= 10;
    }
    if (parseInt(rpm, 10) > 3000) {
        report.suggestions.push('Alta rotação: confira o plano de lubrificação e temperatura.');
        report.score -= 10;
    }

    showAIReportModal(report);
}

async function requestAssetAiInsight() {
    const aiBox = document.getElementById('ai-gemini-insight');
    const btn = document.getElementById('ai-gemini-request-btn');
    if (!aiBox) return;

    const name = document.getElementById('af-name')?.value || '';
    const tag = document.getElementById('af-tag')?.value || '';
    const type = document.getElementById('af-type')?.value || '';
    const fab = document.getElementById('af-fabricante')?.value || '';
    const model = document.getElementById('af-modelo')?.value || '';
    const rpm = document.getElementById('af-rpm')?.value || '';

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Analisando...';
    }
    aiBox.style.display = 'block';
    aiBox.innerHTML = '<span style="color:#94a3b8;">Consultando IA avançada...</span>';

    try {
        const prompt = `Analise este ativo industrial e dê 2 recomendações práticas em linguagem simples:\nNome: ${name}\nTAG: ${tag}\nTipo: ${type}\nFabricante: ${fab}\nModelo: ${model}\nRPM: ${rpm || 'não informado'}`;
        const res = await api('ask_neural', { prompt, page: 'assets', force_ai: 1 });
        if (res && res.text) {
            aiBox.innerHTML = '<strong style="color:#8b5cf6;">Análise da IA:</strong><br>' + escapeHtml(res.text).replace(/\n/g, '<br>');
            if (res.source === 'local' && res.hint) {
                aiBox.innerHTML += '<br><small style="color:#94a3b8;">' + escapeHtml(res.hint) + '</small>';
            }
        } else {
            aiBox.innerHTML = '<span style="color:#94a3b8;">IA indisponível no momento. Use as sugestões acima.</span>';
        }
    } catch (e) {
        aiBox.innerHTML = '<span style="color:#94a3b8;">Não foi possível consultar a IA. As regras locais já foram aplicadas.</span>';
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Pedir análise avançada (IA)';
        }
        if (window.lucide) lucide.createIcons();
    }
}

function showAIReportModal(report) {
    // Remove existing if any
    const existing = document.getElementById('ai-assistant-modal');
    if (existing) existing.remove();

    // Create Modal HTML
    const modal = document.createElement('div');
    modal.id = 'ai-assistant-modal';
    modal.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.7); z-index: 10000;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(5px);
        `;

    const scoreColor = report.score > 80 ? '#10b981' : (report.score > 50 ? '#f59e0b' : '#ef4444');

    modal.innerHTML = `
            <div class="card" style="width: 500px; max-width: 90%; background: #0f172a; border: 1px solid #334155; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                <div style="padding: 20px; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; color: #8b5cf6; display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="sparkles"></i> Verificacao do cadastro
                    </h3>
                    <button onclick="document.getElementById('ai-assistant-modal').remove()" style="background: none; border: none; color: #94a3b8; cursor: pointer;">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                
                <div style="padding: 25px; max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; margin-bottom: 25px;">
                        <div style="font-size: 0.8rem; text-transform: uppercase; color: #94a3b8;">Nota do cadastro</div>
                        <div style="font-size: 3rem; font-weight: 800; color: ${scoreColor};">${report.score}%</div>
                    </div>

                    ${report.errors.length > 0 ? `
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #ef4444; margin: 0 0 10px 0; font-size: 0.9rem;">Erros detectados (crítico)</h4>
                            <ul style="list-style: none; padding: 0; margin: 0; background: rgba(239, 68, 68, 0.1); border-radius: 8px; padding: 10px;">
                                ${report.errors.map(e => `<li style="color: #fca5a5; margin-bottom: 5px; font-size: 0.85rem;">• ${e}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}

                    ${report.suggestions.length > 0 ? `
                        <div style="margin-bottom: 20px;">
                            <h4 style="color: #f59e0b; margin: 0 0 10px 0; font-size: 0.9rem;">Sugestões de melhoria</h4>
                            <ul style="list-style: none; padding: 0; margin: 0; background: rgba(245, 158, 11, 0.1); border-radius: 8px; padding: 10px;">
                                ${report.suggestions.map(s => `<li style="color: #fcd34d; margin-bottom: 5px; font-size: 0.85rem;">• ${s}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}

                    ${report.errors.length === 0 && report.suggestions.length === 0 ? `
                        <div style="text-align: center; color: #10b981; padding: 20px;">
                            <i data-lucide="check-circle-2" style="width: 48px; height: 48px; margin-bottom: 10px;"></i>
                            <p>Cadastro completo! Nenhum problema encontrado.</p>
                        </div>
                    ` : ''}

                    <div id="ai-gemini-insight" style="display:none; margin-top:16px; padding:12px; background:rgba(139,92,246,0.1); border-radius:8px; font-size:0.85rem; color:#e2e8f0; line-height:1.5;"></div>
                    <button type="button" id="ai-gemini-request-btn" onclick="requestAssetAiInsight()" class="btn btn-secondary" style="margin-top:12px; width:100%; border-color:#8b5cf6; color:#c084fc;">
                        Pedir análise avançada (IA)
                    </button>
                </div>

                <div style="padding: 15px; background: rgba(0,0,0,0.2); text-align: right;">
                    <button class="btn btn-primary" onclick="document.getElementById('ai-assistant-modal').remove()">
                        Entendi
                    </button>
                </div>
            </div>
        `;

    document.body.appendChild(modal);
    if (window.lucide) lucide.createIcons();
}

// --- SHORTCUTS ---
document.addEventListener('keydown', function (e) {
    // Ctrl+S to Save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        const form = document.getElementById('asset-form');
        if (form && form.style.display !== 'none') {
            e.preventDefault();
            saveToCloudSingle();
        }
    }
});

// --- UNSAVED CHANGES WARNING ---
// Simple check: if form is open, we assume it *might* be dirty (safe bet for now)
window.onbeforeunload = function (e) {
    const form = document.getElementById('asset-form');
    if (form && form.style.display !== 'none' && !isOfflineMode) {
        e.preventDefault();
        e.returnValue = ''; // Modern browsers
    }
};

async function deleteCurrentNode() {
    if (!editingNode) return;
    if (!confirm('ACAO IRREVERSIVEL\n\nTem certeza que deseja excluir: "' + editingNode.nome + '"?\n\nIsso apagará também todos os componentes e pontos filhos.')) return;

    showToast('Excluindo item...', 'info');

    try {
        const res = await api('delete_asset', { id: editingNode.id });

        if (res.ok || res.deleted) {
            showToast('Item excluído permanentemente.', 'success');
            document.getElementById('asset-form').style.display = 'none';
            editingNode = null;
            await loadTree();
        } else {
            throw new Error(res.error || 'O servidor recusou a exclusão.');
        }
    } catch (e) {
        console.error('Delete Error:', e);
        showToast('Erro ao excluir: ' + e.message, 'error');
    }
}

async function syncToCloud() {
    showToast('Sincronizando com banco Global...', 'info');
    try {
        const res = await api('migrate');
        if (res.ok) {
            showToast('Restaurando maquetes...', 'info');
            await loadTree();
            await loadCatalog();
            await loadDash();
            showToast('Pronto! Maquetes sincronizadas.', 'success');
        } else {
            throw new Error(res.error || 'Erro na migração');
        }
    } catch (e) {
        console.error('Deep Sync Error:', e);
        showToast('Falha na sincronizacaoção: ' + e.message, 'error');
    }
}

// --- NAVIGATION & INIT ---
// --- NAVIGATION & INIT (Duplicate Removed) ---
// function nav(view, el) { ... } // REMOVED - Using main nav function at top of file


// --- GLOBAL AUTO SAVE FOR INPUTS ---
document.addEventListener('input', (e) => {
    if (e.target.tagName === 'INPUT' && !e.target.id.startsWith('af-') && !e.target.id.startsWith('ced-')) {
        localStorage.setItem('autosave_' + e.target.id, e.target.value);
    }
});

// --- EXCEL IMPORT LOGIC ---
// --- EXCEL IMPORT LOGIC (ADVANCED: IMAGE SUPPORT) ---
async function importAssetsFromExcel(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    if (typeof ExcelJS === 'undefined') {
        showToast('Erro: ExcelJS não carregado.', 'error');
        return;
    }

    showToast('Preparando motor de importação batch...', 'info');
    const workbook = new ExcelJS.Workbook();
    const rows = [];
    const imageMap = {};

    // Helper functions for normalization and safe fetching
    function normalizeKey(str) {
        if (!str) return "";
        return str.toString()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "") // Remove accents
            .replace(/[^a-zA-Z0-9]/g, "")    // Remove spaces, punctuation, special chars
            .toUpperCase()
            .trim();
    }

    function nGet(normRow, keys, fallback = "") {
        for (let k of keys) {
            if (normRow[k] !== undefined && normRow[k] !== null) {
                return normRow[k].toString().trim();
            }
        }
        return fallback;
    }

    function getExtraFields(normRow, type, name, img) {
        const extra = {
            tag: nGet(normRow, ['TAG', 'CODIGO']),
            obs: nGet(normRow, ['OBS', 'DESCRICAO', 'OBSERVACAO', 'NOTAS']),
            imageBlob: img,
            fabricante: nGet(normRow, ['FABRICANTE', 'MANUFACTURER', 'MARCA']),
            modelo: nGet(normRow, ['MODELO', 'MODEL']),
            num_serie: nGet(normRow, ['NUMSERIE', 'SERIE', 'SERIALNUMBER', 'SERIAL']),
            tech: {}
        };

        if (type === 'ponto') {
            extra.tech = {
                ponto_lub: name,
                servico: nGet(normRow, ['SERVICO', 'PROCESSO', 'ATIVIDADE']),
                material: nGet(normRow, ['LUBRIFICANTE', 'MATERIAL', 'OLEO', 'GRAXA']),
                qtd_material: nGet(normRow, ['QUANTIDADE', 'QTD', 'QTDMATERIAL']),
                unid_material: nGet(normRow, ['UNIDADEMEDIDA', 'UNID', 'MEDIDA', 'UNIDADE']),
                periodo: nGet(normRow, ['PERIODO', 'FREQUENCIA', 'FREQUENCIASEMANAL', 'INTERVALO']),
                condicao: nGet(normRow, ['CONDICAO', 'CONDICAODESERVICO', 'ESTADO']),
                sistema_lub: nGet(normRow, ['SISTEMA', 'SISTEMALUB', 'SISTEMALUBRIFICACAO']),
                num_pontos: nGet(normRow, ['NPONTOS', 'NUMPONTOS', 'QUANTIDADEDEPONTOS'], "1"),
                procedimento: nGet(normRow, ['PROCEDIMENTO', 'METODO', 'INSTRUCAO']),
                complemento: nGet(normRow, ['COMPLEMENTO', 'ADICIONAL']),
                prioridade: nGet(normRow, ['PRIORIDADE', 'CRITICIDADE'], "Rotina"),
                rota: nGet(normRow, ['ROTA']),
                rpm: nGet(normRow, ['RPM', 'ROTACAO']),
                bearing_model: nGet(normRow, ['MODELO', 'MODEL', 'MODELODOROLAMENTO', 'ROLAMENTO'])
            };
        } else if (type === 'equipamento') {
            extra.tech = {
                rpm: nGet(normRow, ['RPM', 'ROTACAO']),
                model: nGet(normRow, ['MODELO', 'MODEL'])
            };
        }
        return extra;
    }

    // --- 1. PRE-INDEX EXISTING TREE (DUPLICATE PREVENTION) ---
    const existingMap = new Map(); // "Nome|PaiID" -> ID
    function indexNode(nodes, parentId = null) {
        nodes.forEach(n => {
            existingMap.set(`${(n.nome || '').trim().toUpperCase()}|${parentId}`, n.id);
            if (n.children) indexNode(n.children, n.id);
        });
    }
    if (window.treeState) indexNode(window.treeState);

    const localCache = new Map(); // Local session cache

    try {
        const arrayBuffer = await file.arrayBuffer();

        try {
            // Try to load via ExcelJS first (supports images)
            await workbook.xlsx.load(arrayBuffer);
            const worksheet = workbook.getWorksheet(1);
            if (!worksheet) throw new Error("Planilha vazia ou ilegível via ExcelJS");

            // Extract Images
            worksheet.getImages().forEach(imgRef => {
                try {
                    const img = workbook.getImage(imgRef.imageId);
                    const rowIdx = Math.floor(imgRef.range.tl.nativeRow) + 1;
                    imageMap[rowIdx] = new Blob([img.buffer], { type: 'image/' + img.extension });
                } catch(e) {}
            });

            // Parse Headers
            let headers = {};
            worksheet.getRow(1).eachCell((cell, col) => {
                headers[col] = cell.value ? cell.value.toString().trim().toUpperCase() : `COL${col}`;
            });

            // Parse Rows
            worksheet.eachRow((row, rowNum) => {
                if (rowNum === 1) return;
                const rowData = {};
                row.eachCell({ includeEmpty: true }, (cell, col) => {
                    let val = cell.value;
                    if (val && typeof val === 'object') {
                        val = val.richText ? val.richText.map(t => t.text).join('') : (val.text || val.result || "");
                    }
                    rowData[headers[col]] = val ? val.toString().trim() : "";
                });
                if (imageMap[rowNum]) rowData['_image'] = imageMap[rowNum];
                rows.push(rowData);
            });
        } catch (excelJsErr) {
            console.warn("ExcelJS falhou, tentando fallback com SheetJS (XLSX)...", excelJsErr.message);
            // Fallback: Read using SheetJS (XLSX) which supports .xls, .csv, and legacy files
            if (typeof XLSX === 'undefined') {
                throw new Error("Erro: ExcelJS falhou e a biblioteca auxiliar XLSX não está disponível.");
            }
            const data = new Uint8Array(arrayBuffer);
            const sWorkbook = XLSX.read(data, { type: 'array' });
            
            let rawData = [];
            for (let sheetName of sWorkbook.SheetNames) {
                const ws = sWorkbook.Sheets[sheetName];
                const sheetRows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: "" });
                if (sheetRows && sheetRows.length > 0) {
                    rawData = sheetRows;
                    break;
                }
            }

            if (rawData.length === 0) {
                throw new Error("Nenhum registro encontrado na planilha (vazia ou formato ilegível).");
            }

            // Find the first row with non-empty values to use as header
            let headerRowIndex = -1;
            for (let i = 0; i < rawData.length; i++) {
                if (rawData[i].some(cell => String(cell).trim() !== "")) {
                    headerRowIndex = i;
                    break;
                }
            }

            if (headerRowIndex === -1) {
                throw new Error("Não foi possível encontrar uma linha de cabeçalho válida na planilha.");
            }

            const rawHeaders = rawData[headerRowIndex].map(h => String(h).trim().toUpperCase());
            
            // Map remaining rows to objects
            for (let i = headerRowIndex + 1; i < rawData.length; i++) {
                const row = rawData[i];
                if (row.every(cell => String(cell).trim() === "")) continue; // Skip empty rows

                const normRow = {};
                rawHeaders.forEach((header, colIdx) => {
                    const cellVal = row[colIdx] !== undefined ? String(row[colIdx]).trim() : "";
                    if (header) {
                        normRow[header] = cellVal;
                    } else {
                        normRow[`COL${colIdx + 1}`] = cellVal;
                    }
                });
                rows.push(normRow);
            }
        }

        showToast(`Lendo ${rows.length} registros...`, 'info');

        // --- 3.5. COGNITIVE MAPPING (LÚBRIA) — opcional, sob demanda ---
        let cognitiveMap = null;
        const useAiMap = confirm('Deseja que a Lúbria analise as colunas da planilha com IA?\n\n(Opcional — o import funciona sem isso.)');
        if (useAiMap) {
            showToast('Lúbria analisando a inteligência da planilha...', 'info');
            const sampleRows = rows.slice(0, 5);
            try {
                const mapRes = await fetch('api.php?action=lubria_excel_mapper', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sample: sampleRows, force_ai: 1 })
                }).then(r => r.json());

                if (mapRes.map) {
                    cognitiveMap = mapRes.map;
                    showToast('Lúbria compreendeu a estrutura! Catalogando de forma inteligente...', 'success');
                } else {
                    console.warn("Falha no mapa da Lúbria:", mapRes.error);
                    showToast('Análise cognitiva falhou. Usando método estático.', 'warning');
                }
            } catch (err) {
                console.warn("Lúbria Map Error:", err);
                showToast('Erro de rede na Lúbria. Usando estático.', 'warning');
            }
        }

        // --- 4. HIERARCHY ENGINE (BRAIN) ---
        const nodesToSave = [];

        async function getOrQueueNode(name, type, parentKey, extra = {}) {
            const cleanedName = name ? name.toString().trim() : "";
            if (!cleanedName || cleanedName === "-" || cleanedName.toUpperCase() === "N/A") {
                return parentKey || "null";
            }
            const parentStr = parentKey ? String(parentKey) : "null";
            const cacheKey = `${cleanedName.toUpperCase()}|${parentStr}`;

            // Check Persistent ID
            if (existingMap.has(cacheKey)) return existingMap.get(cacheKey);
            // Check Session ID (New Nodes)
            if (localCache.has(cacheKey)) return localCache.get(cacheKey);

            // Create Virtual ID for batch linking
            const virtualId = "vnode_" + Math.random().toString(36).substr(2, 9);

            // Handle Image Upload if present
            let imgPath = "";
            if (extra.imageBlob) {
                try {
                    const fd = new FormData();
                    fd.append('file', extra.imageBlob, `import_${Date.now()}.png`);
                    const upRes = await fetch('api.php?action=upload_image', { method: 'POST', body: fd });
                    const up = await upRes.json().catch(() => null);
                    if (up && up.ok) imgPath = up.path;
                } catch (uploadErr) {
                    console.warn('Import image upload failed:', uploadErr);
                }
            }

            nodesToSave.push({
                virtual_id: virtualId,
                nome: cleanedName,
                tipo: type,
                pai_virtual_id: parentStr.startsWith('vnode_') ? parentStr : null,
                pai_id: (!parentStr.startsWith('vnode_') && parentStr !== 'null' && parentStr !== 'undefined') ? parseInt(parentStr) : null,
                tag: extra.tag || '',
                obs: extra.obs || '',
                imagem: imgPath,
                fabricante: extra.fabricante || '',
                modelo: extra.modelo || '',
                num_serie: extra.num_serie || '',
                tech: extra.tech || {}
            });

            localCache.set(cacheKey, virtualId);
            return virtualId;
        }

        for (let r of rows) {
            // Normalize current row keys
            const normRow = {};
            for (let k in r) {
                normRow[normalizeKey(k)] = r[k];
            }

            if (cognitiveMap) {
                // AUTONOMOUS LÚBRIA PARSING
                let u = cognitiveMap.fabrica_col ? normRow[normalizeKey(cognitiveMap.fabrica_col)] : "";
                let s = cognitiveMap.setor_col ? normRow[normalizeKey(cognitiveMap.setor_col)] : "";
                let e = cognitiveMap.equip_col ? normRow[normalizeKey(cognitiveMap.equip_col)] : "";
                let c = cognitiveMap.comp_col ? normRow[normalizeKey(cognitiveMap.comp_col)] : "";
                let p = cognitiveMap.ponto_col ? normRow[normalizeKey(cognitiveMap.ponto_col)] : "";
                
                let tag = cognitiveMap.tag_col ? normRow[normalizeKey(cognitiveMap.tag_col)] : "";
                let obs = cognitiveMap.obs_col ? normRow[normalizeKey(cognitiveMap.obs_col)] : "";
                
                let techData = {};
                if (cognitiveMap.tech_cols && Array.isArray(cognitiveMap.tech_cols)) {
                    cognitiveMap.tech_cols.forEach(tc => {
                        let k = normalizeKey(tc);
                        if (normRow[k]) techData[tc] = normRow[k];
                    });
                }

                if (!u && !e) continue;

                const getExtra = (type, val) => {
                    return { tag: tag, obs: obs, imageBlob: r['_image'], tech: (type === 'ponto' || type === 'equipamento') ? techData : {} };
                };

                let uId = u ? await getOrQueueNode(u, 'unidade', "null", getExtra('unidade', u)) : "null";
                let sId = s ? await getOrQueueNode(s, 'setor', uId, getExtra('setor', s)) : uId;
                let eId = e ? await getOrQueueNode(e, 'equipamento', sId, getExtra('equipamento', e)) : sId;
                let cId = c ? await getOrQueueNode(c, 'componente', eId, getExtra('componente', c)) : eId;

                if (p) {
                    await getOrQueueNode(p, 'ponto', cId, getExtra('ponto', p));
                }

            } else {
                // Classic Multi-column layout fallback
                const caminho = nGet(normRow, ['CAMINHO', 'PATH', 'HIERARQUIA']);
                const tipo = nGet(normRow, ['TIPO', 'TYPE']);

                if (caminho) {
                    const parts = caminho.split(/\s*>\s*/).filter(p => p.trim() !== "");
                    if (parts.length === 0) continue;

                    let currentParentId = "null";
                    for (let i = 0; i < parts.length; i++) {
                        const partName = parts[i];
                        const isLast = (i === parts.length - 1);
                        
                        let partType = 'unidade';
                        if (i === 1) partType = 'setor';
                        else if (i === 2) partType = 'equipamento';
                        else if (i === 3) partType = 'componente';
                        else if (i >= 4) partType = 'ponto';

                        if (isLast && tipo) {
                            partType = tipo.toLowerCase();
                        }

                        const extra = getExtraFields(normRow, partType, partName, r['_image']);
                        currentParentId = await getOrQueueNode(partName, partType, currentParentId, extra);
                    }
                } else {
                    let u = nGet(normRow, ['UNIDADE', 'FABRICA', 'MATRIZ']);
                    let s = nGet(normRow, ['SETOR', 'AREA']);
                    let e = nGet(normRow, ['EQUIPAMENTO', 'MAQUINA']);
                    let c = nGet(normRow, ['COMPONENTE']);
                    let p = nGet(normRow, ['PONTO', 'LUBRIFICACAO']);

                    if (!u && !e) continue;

                    let uId = u ? await getOrQueueNode(u, 'unidade', "null", getExtraFields(normRow, 'unidade', u, r['_image'])) : "null";
                    let sId = s ? await getOrQueueNode(s, 'setor', uId, getExtraFields(normRow, 'setor', s, r['_image'])) : uId;
                    let eId = e ? await getOrQueueNode(e, 'equipamento', sId, getExtraFields(normRow, 'equipamento', e, r['_image'])) : sId;
                    let cId = c ? await getOrQueueNode(c, 'componente', eId, getExtraFields(normRow, 'componente', c, r['_image'])) : eId;

                    if (p) {
                        await getOrQueueNode(p, 'ponto', cId, getExtraFields(normRow, 'ponto', p, r['_image']));
                    }
                }
            }
        }

        // --- 5. EXECUTE BATCH SAVE ---
        if (nodesToSave.length > 0) {
            showToast(`Enviando ${nodesToSave.length} ativos para a nuvem...`, 'info');
            const batchRes = await api('save_asset_batch', { items: nodesToSave });
            if (batchRes.count) {
                showToast(`${batchRes.count} ativos processados com sucesso!`, 'success');
            }
        } else {
            showToast('Nenhuma alteração detectada.', 'info');
        }

        loadTree();

    } catch (err) {
        console.error("Import Error:", err);
        showToast('Falha na importação: ' + err.message, 'error');
    }
    input.value = '';
}

// Helper for Import (Updated with Image Support)
let _importCache = {};
async function ensureNode(name, type, parentId, extra = {}) {
    if (!name) return null;
    let cacheKey = name + "_" + (parentId || "ROOT");
    if (_importCache[cacheKey]) return { id: _importCache[cacheKey] };

    // Try to find in existing frontend tree (Mock check)
    let existingId = findLocalId(name, parentId);
    if (existingId) {
        _importCache[cacheKey] = existingId;
        return { id: existingId };
    }

    const payload = {
        nome: name,
        tipo: type,
        pai_id: parentId,
        tag: extra.tag || '',
        obs: extra.obs || '',
        imagem: extra.img || '', // Attach Image here
        dados_tecnicos: {
            rpm: extra.rpm,
            bearing_model: extra.model
        }
    };

    try {
        const res = await api('save_asset', payload);
        if (res.id) {
            _importCache[cacheKey] = res.id;
            addLocalMock(res.id, name, type, parentId);
            return { id: res.id };
        }
    } catch (e) {
        console.error("Failed to create " + name, e);
    }
    return null;
}

// Transient lookup for import session
let _localTreeMock = []; // [ {id, nome, pai_id} ]

function findLocalId(name, parentId) {
    // Init check
    if (_localTreeMock.length === 0 && Array.isArray(window.appTreeData)) {
        // flatten window.appTreeData?
        // Lets just trust the cache for 'newly created' and maybe duplicates for old.
        // Actually, duplicates are bad.
    }
    // Check mock
    let found = _localTreeMock.find(n => n.nome === name && n.pai_id == parentId);
    if (found) return found.id;
    return null;
}

function addLocalMock(id, name, type, parentId) {
    _localTreeMock.push({ id, nome: name, tipo: type, pai_id: parentId });
}

function checkOfflineStatus() {
    document.querySelectorAll('input').forEach(inp => {
        // Skip file inputs (security restriction) and hidden inputs (often managed by JS)
        if (inp.type === 'file' || inp.type === 'hidden') return;

        const val = localStorage.getItem('autosave_' + inp.id);
        if (val) inp.value = val;
    });
}


// --- SYNTHOIL BEARING CALCULATOR (ADVANCED) ---
async function runSynthRolCalc() {
    const code = document.getElementById('synth-rol-code')?.value;
    const temp = document.getElementById('synth-rol-temp')?.value || 70;
    const rpm = document.getElementById('synth-rol-rpm')?.value || 1750;
    const hoursDay = document.getElementById('synth-rol-hours')?.value || 24;

    const vib = document.getElementById('synth-rol-vib')?.value || 'mid';
    const cont = document.getElementById('synth-rol-cont')?.value || 'normal';
    const pos = document.getElementById('synth-rol-pos')?.value || 'horiz';
    const moist = document.getElementById('synth-rol-moist')?.value || 'dry';

    const resultBox = document.getElementById('synth-rol-result');
    if (!resultBox) return;

    if (!code || !rpm) {
        if (typeof showToast === 'function') showToast('Preencha o Código do Rolamento e a Rotação (RPM).', 'warning');
        return;
    }

    resultBox.innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-muted);"><i data-lucide="loader-2" class="spin" style="width:32px; height:32px;"></i><p style="margin-top:10px;">Calculando Tribologia e Intervalo Físico (Modelo SKF 99%+)...</p></div>';
    if (window.lucide) lucide.createIcons();

    try {
        const vgOil = document.getElementById('synth-rol-vg')?.value || '';

        const res = await api('suggest_lubrication', {
            name: code,
            rpm: rpm,
            temp: temp,
            horas_dia: hoursDay,
            vib: vib,
            cont: cont,
            pos: pos,
            moisture: moist,
            vg: vgOil || undefined
        });

        if (!res || !res.found) {
            resultBox.innerHTML = `
                <div class="card" style="border:2px solid var(--danger); background:rgba(239, 68, 68, 0.05); padding:20px; text-align:center;">
                    <h3 style="color:var(--danger); font-weight:800;">Modelo não reconhecido</h3>
                    <p style="color:var(--text-muted); font-size:0.9rem;">O código "${escapeHtml(code)}" não pôde ser analisado. Tente um código ISO padrão (ex: 6205, 6308, 22210, NU205).</p>
                </div>`;
            return;
        }

        const dn = res.dn || 0;
        let color = '#10b981';
        let statusMsg = 'OPERAÇÃO SEGURA';

        if (dn > 500000) { color = '#ef4444'; statusMsg = 'CRÍTICO (ÓLEO CIRCULANTE)'; }
        else if (dn > 300000) { color = '#f59e0b'; statusMsg = 'ATENÇÃO (LIMITE DE GRAXA)'; }

        let factorsHtml = '';
        if (res.factors) {
            factorsHtml = `
                <div style="margin-top:15px; background:rgba(0,0,0,0.03); padding:12px 15px; border-radius:8px; font-size:0.8rem; border:1px solid var(--border);">
                    <div style="font-weight:700; color:var(--text-main); margin-bottom:6px; text-transform:uppercase; font-size:0.75rem;">Fatores Físicos de Ajuste Tribológico</div>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap:8px; text-align:left;">
                        <div>Familia (Fb): <b>${res.factors.type || 1.0}</b></div>
                        <div>Temp (Ft): <b>${res.factors.temp || 1.0}</b></div>
                        <div>Contam (Fc): <b>${res.factors.cont || 1.0}</b></div>
                        <div>Vibração (Fv): <b>${res.factors.vib || 1.0}</b></div>
                        <div>Posição (Fp): <b>${res.factors.pos || 1.0}</b></div>
                        <div>Umidade (Fm): <b>${res.factors.moisture || 1.0}</b></div>
                        <div>Carga (Fl): <b>${res.factors.load || 1.0}</b></div>
                        <div>Combinado: <b style="color:${color}">${res.factors.ftotal || 1.0}</b></div>
                    </div>
                </div>`;
        }

        let alertHtml = '';
        if (res.warning) {
            alertHtml = `<div style="background:rgba(239,68,68,0.08); border:1px solid var(--danger); color:var(--danger); padding:12px; border-radius:8px; font-size:0.85rem; margin-top:12px; font-weight:600;">⚠️ ${escapeHtml(res.warning)}</div>`;
        }

        resultBox.innerHTML = `
            <div class="card" style="background:#ffffff; border:2px solid ${color}; padding:24px; border-radius:14px; box-shadow:0 8px 25px rgba(0,0,0,0.06);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-bottom:1px solid var(--border); padding-bottom:15px; margin-bottom:15px;">
                    <div>
                        <span style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Diagnóstico Técnico</span>
                        <h3 style="color:${color}; margin:2px 0 0 0; font-weight:900; font-size:1.4rem;">${statusMsg}</h3>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Rolamento Identificado</span>
                        <div style="font-size:1.1rem; font-weight:800; color:var(--text-main);">${escapeHtml(res.model)} <span style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">(${res.d} × ${res.D} × ${res.B} mm)</span></div>
                        <div style="font-size:0.75rem; color:var(--primary); font-weight:600;">${escapeHtml(res.bearing_type || '')} · ${escapeHtml(res.standard || 'ISO')}</div>
                    </div>
                </div>

                <!-- INTERVAL BREAKDOWN CARD (4 TIME UNITS: HOURS, DAYS, WEEKS, MONTHS) -->
                <div style="background:linear-gradient(135deg, rgba(14,165,233,0.04), rgba(14,165,233,0.08)); border:1px solid rgba(14,165,233,0.2); border-radius:10px; padding:15px; margin-bottom:15px;">
                    <div style="font-size:0.8rem; font-weight:800; color:var(--primary); text-transform:uppercase; margin-bottom:10px; text-align:left;">
                        ⏱️ Intervalo Exato de Relubrificação (Regime ${res.horas_dia || 24}h/dia)
                    </div>
                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; text-align:center;">
                        <div style="background:white; padding:10px 5px; border-radius:8px; border:1px solid var(--border);">
                            <div style="font-size:0.68rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Horas Op.</div>
                            <div style="font-size:1.35rem; font-weight:900; color:#0f172a;">${res.hours}h</div>
                        </div>
                        <div style="background:white; padding:10px 5px; border-radius:8px; border:1px solid var(--border);">
                            <div style="font-size:0.68rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Dias Campo</div>
                            <div style="font-size:1.35rem; font-weight:900; color:#0284c7;">${res.days}d</div>
                        </div>
                        <div style="background:white; padding:10px 5px; border-radius:8px; border:1px solid var(--border);">
                            <div style="font-size:0.68rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Semanas</div>
                            <div style="font-size:1.35rem; font-weight:900; color:#8b5cf6;">${res.weeks}sem</div>
                        </div>
                        <div style="background:white; padding:10px 5px; border-radius:8px; border:1px solid var(--border);">
                            <div style="font-size:0.68rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Meses</div>
                            <div style="font-size:1.35rem; font-weight:900; color:#10b981;">${res.months}m</div>
                        </div>
                    </div>
                    <div style="font-size:0.8rem; font-weight:700; color:${color}; margin-top:8px; text-align:center;">Frequência PCM Recomendada: ${escapeHtml(res.freq_label)}</div>
                </div>

                <!-- DOSAGE & TRIBOLOGY METRICS -->
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-bottom:10px;">
                    <div style="background:var(--bg-body); padding:10px; border-radius:8px; border:1px solid var(--border); text-align:center;">
                        <div style="font-size:0.68rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Relubrificação</div>
                        <div style="font-size:1.3rem; font-weight:900; color:var(--text-main);">${res.grams} g</div>
                        <div style="font-size:0.65rem; color:var(--text-muted);">G = 0.005 × D × B</div>
                    </div>
                    <div style="background:var(--bg-body); padding:10px; border-radius:8px; border:1px solid var(--border); text-align:center;">
                        <div style="font-size:0.68rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Carga Inicial</div>
                        <div style="font-size:1.3rem; font-weight:900; color:var(--text-main);">${res.grams_initial || (res.grams*10)} g</div>
                        <div style="font-size:0.65rem; color:var(--text-muted);">Fill 30-50% Caixa</div>
                    </div>
                    <div style="background:var(--bg-body); padding:10px; border-radius:8px; border:1px solid var(--border); text-align:center;">
                        <div style="font-size:0.68rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Fator Ndm (n·dm)</div>
                        <div style="font-size:1.3rem; font-weight:900; color:var(--text-main);">${res.dn}</div>
                        <div style="font-size:0.65rem; color:var(--text-muted);">ν1: ${res.nu1_rec_cst || '--'} cSt · ${escapeHtml(res.iso_vg_rec ? ('ISO VG ' + res.iso_vg_rec) : '')} · ${escapeHtml(res.nlgi_rec || '')}</div>
                    </div>
                </div>

                ${renderKappaBlock(res.kappa || res)}

                ${factorsHtml}
                ${alertHtml}

                <div style="margin-top:15px; text-align:center;">
                    <button class="btn btn-sm btn-outline" onclick="if(typeof saveAndDownloadReport === 'function') saveAndDownloadReport(); else showToast('Laudo registrado no sistema.', 'success');">
                        <i data-lucide="file-down"></i> Baixar Laudo Técnico
                    </button>
                </div>
            </div>`;

        window.lastSmartCalcResult = res;
        if (window.lucide) lucide.createIcons();
    } catch (e) {
        console.error(e);
        resultBox.innerHTML = '<div style="color:var(--danger); text-align:center; padding:20px;">Erro no processamento do cálculo: ' + escapeHtml(e.message) + '</div>';
    }
}
window.runSynthRolCalc = runSynthRolCalc;

let engCatalogIndex = [];

async function loadEngineeringCatalog() {
    const list = document.getElementById('eng-bearing-codes');
    if (!list || list.dataset.loaded === '1') return;
    try {
        const res = await api('search_engineering_catalog', { q: '', limit: 800 });
        const items = (res && res.items) ? res.items : (res && res.data && res.data.items) ? res.data.items : [];
        engCatalogIndex = Array.isArray(items) ? items : [];
        list.innerHTML = engCatalogIndex.map(i =>
            `<option value="${escapeAttr(i.code)}" label="${escapeAttr(i.label || i.code)}"></option>`
        ).join('');
        list.dataset.loaded = '1';
    } catch (e) { /* catálogo opcional */ }
}

function onEngBearingCodeInput(el) {
    if (!el) return;
    const hint = document.getElementById('eng-bearing-hint');
    const q = String(el.value || '').trim().toUpperCase();
    if (!q) {
        if (hint && el.id === 'synth-rol-code') {
            hint.textContent = 'Digite o código — dimensões oficiais ISO 15 / ISO 355 / DIN 618.';
        }
        return;
    }
    const hit = engCatalogIndex.find(i => String(i.code).toUpperCase() === q)
        || engCatalogIndex.find(i => String(i.code).toUpperCase().startsWith(q));
    if (hit) {
        if (hint && el.id === 'synth-rol-code') {
            hint.textContent = `${hit.code}: ${hit.d} × ${hit.D} × ${hit.B} mm · ${hit.type_name || ''} (${hit.standard || ''})`;
        }
        const fill = (dId, DId, bId) => {
            const a = document.getElementById(dId);
            const b = document.getElementById(DId);
            const c = bId ? document.getElementById(bId) : null;
            if (a) a.value = hit.d;
            if (b) b.value = hit.D;
            if (c) c.value = hit.B;
        };
        if (el.id === 'synth-dn-code') fill('synth-dn-d', 'synth-dn-D');
        if (el.id === 'synth-kap-code') fill('synth-kap-d', 'synth-kap-D', 'synth-kap-B');
        if (el.id === 'synth-visc-code') fill('synth-visc-d', 'synth-visc-D');
    } else if (hint && el.id === 'synth-rol-code') {
        hint.textContent = 'Código ainda não reconhecido — continue digitando ou use um ISO padrão.';
    }
}
window.onEngBearingCodeInput = onEngBearingCodeInput;

document.addEventListener('DOMContentLoaded', () => { loadEngineeringCatalog(); });
if (document.readyState !== 'loading') loadEngineeringCatalog();


function renderKappaBlock(k) {
    if (!k || k.kappa == null) return '';
    const color = k.color || '#059669';
    const ep = k.ep_required
        ? '<span style="display:inline-block;margin-top:8px;padding:4px 10px;border-radius:999px;background:#fff7ed;color:#c2410c;font-size:0.72rem;font-weight:800;">ADITIVOS EP RECOMENDADOS</span>'
        : '';
    return `
        <div style="margin-top:14px;text-align:left;border:1px solid ${color};border-radius:12px;padding:16px;background:#fff;">
            <div style="font-size:0.72rem;font-weight:800;letter-spacing:0.4px;text-transform:uppercase;color:${color};">Fator Kappa (κ = ν / ν₁)</div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:12px 0;text-align:center;">
                <div><div style="font-size:0.65rem;color:#64748b;font-weight:700;">κ</div><div style="font-size:1.6rem;font-weight:900;color:${color};">${k.kappa}</div></div>
                <div><div style="font-size:0.65rem;color:#64748b;font-weight:700;">ν real</div><div style="font-size:1.15rem;font-weight:800;">${k.nu} cSt</div></div>
                <div><div style="font-size:0.65rem;color:#64748b;font-weight:700;">ν₁ SKF</div><div style="font-size:1.15rem;font-weight:800;">${k.nu1} cSt</div></div>
                <div><div style="font-size:0.65rem;color:#64748b;font-weight:700;">Fator de vida</div><div style="font-size:1.15rem;font-weight:800;">× ${k.life_factor}</div></div>
            </div>
            <div style="font-weight:800;color:${color};">${escapeHtml(k.band_title || '')}</div>
            <p style="margin:6px 0 0;font-size:0.85rem;color:#334155;font-weight:600;">${escapeHtml(k.regime || '')}</p>
            <p style="margin:8px 0 0;font-size:0.82rem;color:#64748b;">${escapeHtml(k.advice || '')}</p>
            ${ep}
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px;font-size:0.78rem;">
                <div style="background:#f8fafc;border-radius:8px;padding:8px;"><b>ISO VG κ≈1</b><br>VG ${k.iso_vg_kappa_1}</div>
                <div style="background:#f8fafc;border-radius:8px;padding:8px;"><b>ISO VG κ≈4</b><br>VG ${k.iso_vg_kappa_4}</div>
                <div style="background:#f8fafc;border-radius:8px;padding:8px;"><b>Graxa (SKF)</b><br>${k.grams_relub} g relub · ${k.grams_initial} g inicial</div>
            </div>
            <div style="margin-top:8px;font-size:0.72rem;color:#94a3b8;">dm = ${k.dm} mm · VG usado ${k.iso_vg} · ${k.vg_informado ? 'óleo informado' : 'VG ajustado para κ ≈ 1'}</div>
        </div>`;
}

async function runSynthKappaCalc() {
    const resultBox = document.getElementById('synth-kap-result');
    if (!resultBox) return;
    const payload = {
        code: document.getElementById('synth-kap-code')?.value || '',
        d: document.getElementById('synth-kap-d')?.value || '',
        D: document.getElementById('synth-kap-D')?.value || '',
        B: document.getElementById('synth-kap-B')?.value || '',
        rpm: document.getElementById('synth-kap-rpm')?.value || '',
        temp: document.getElementById('synth-kap-temp')?.value || 70,
        vg: document.getElementById('synth-kap-vg')?.value || 68,
        vi: document.getElementById('synth-kap-vi')?.value || 95
    };
    if ((!payload.code && !payload.d) || !payload.rpm) {
        return showToast('Informe o código ISO (ou d/D) e a rotação.', 'warning');
    }
    resultBox.innerHTML = '<div style="text-align:center;padding:20px;"><i data-lucide="loader-2" class="spin"></i></div>';
    if (window.lucide) lucide.createIcons();
    const res = await api('calc_kappa', payload);
    if (!res || res.error || res.found === false) {
        resultBox.innerHTML = `<div style="color:var(--danger);padding:16px;">${escapeHtml((res && res.error) || 'Não foi possível calcular κ.')}</div>`;
        return;
    }
    resultBox.innerHTML = `
        <div class="card" style="border:2px solid ${res.color};padding:8px 8px 16px;background:#fff;">
            <div style="text-align:center;padding:8px 8px 0;">
                <div style="font-size:0.75rem;font-weight:800;color:#64748b;text-transform:uppercase;">${escapeHtml(res.bearing_code || 'Dimensões manuais')} · ${res.d}×${res.D}×${res.B} mm</div>
                <div style="font-size:2.4rem;font-weight:900;color:${res.color};line-height:1.1;margin:6px 0;">κ = ${res.kappa}</div>
                <div style="font-size:0.8rem;color:#64748b;">${escapeHtml(res.formula || 'κ = ν / ν₁')}</div>
            </div>
            ${renderKappaBlock(res)}
            <p style="font-size:0.72rem;color:#94a3b8;margin:12px 16px 0;text-align:left;">${escapeHtml(res.formula_nu1 || '')}<br>${escapeHtml(res.formula_nu || '')}</p>
        </div>`;
    if (window.lucide) lucide.createIcons();
}
window.runSynthKappaCalc = runSynthKappaCalc;
window.renderKappaBlock = renderKappaBlock;

function saveAndDownloadReport() {
    if (window.downloadTechnicalReport) window.downloadTechnicalReport();
}

// --- VISCOSITY CALCULATOR (ASTM) ---
async function runSynthViscCalc() {
    const vg = document.getElementById('synth-visc-vg').value;
    const temp = document.getElementById('synth-visc-temp').value;
    const vi = document.getElementById('synth-visc-vi').value;
    const resultBox = document.getElementById('synth-visc-result');

    if (!temp) return showToast('Informe a temperatura de operação.', 'warning');

    resultBox.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);"><i data-lucide="loader-2" class="spin"></i> Calculando ASTM D341...</div>';
    lucide.createIcons();

    try {
        const res = await api('calc_viscosity', {
            vg, temp, vi,
            code: document.getElementById('synth-visc-code')?.value || '',
            d: document.getElementById('synth-visc-d')?.value || '',
            D: document.getElementById('synth-visc-D')?.value || '',
            rpm: document.getElementById('synth-visc-rpm')?.value || ''
        });

        if (!res || res.error) {
            resultBox.innerHTML = '<div style="color:var(--danger); text-align:center;">' + escapeHtml((res && res.error) || 'Erro ao calcular.') + '</div>';
            return;
        }

        let statusColor = '#10b981';
        if (res.viscosity_op < 15) statusColor = '#ef4444'; // dangerously thin
        else if (res.viscosity_op < 30) statusColor = '#f59e0b'; // warning

        resultBox.innerHTML = `
                <div class="card" style="background:var(--bg-panel); border:2px solid ${statusColor}; padding:20px; text-align:center;">
                     <h3 style="color:${statusColor}; margin-bottom:5px; font-weight:800;">${res.viscosity_op} cSt</h3>
                     <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Viscosidade real @ ${escapeHtml(String(temp))}°C (ASTM D341)</p>
                     
                     <div style="display:flex; gap:10px; justify-content:center; font-size:0.8rem; color:var(--text-muted); background:rgba(0,0,0,0.05); padding:10px; border-radius:6px;">
                        <div>Referência (40°C): <b>${res.v40} cSt</b></div>
                        <div>|</div>
                        <div>Estimado (100°C): <b>${res.v100} cSt</b></div>
                     </div>
                     ${res.alerta ? `<p style="margin:12px 0 0;font-size:0.85rem;color:${statusColor};font-weight:700;">${escapeHtml(res.alerta)}</p>` : ''}
                </div>
                ${res.kappa ? renderKappaBlock(res.kappa) : ''}
            `;
        lucide.createIcons();
    } catch (e) {
        resultBox.innerHTML = '<div style="color:var(--danger); text-align:center;">Erro ao calcular.</div>';
    }
}

async function runSynthDNCalc() {
    const code = document.getElementById('synth-dn-code')?.value;
    const d = document.getElementById('synth-dn-d').value;
    const D = document.getElementById('synth-dn-D').value;
    const rpm = document.getElementById('synth-dn-rpm').value;
    const resultBox = document.getElementById('synth-dn-result');

    if ((!d && !code) || !rpm) return showToast('Preencha o código ISO ou d/D e a rotação.', 'warning');

    resultBox.innerHTML = '<div style="text-align:center; padding:20px;"><i data-lucide="loader-2" class="spin"></i></div>';
    lucide.createIcons();

    const res = await api('calc_dn', { d, D, rpm, code });

    if (!res || res.error) {
        resultBox.innerHTML = `<div style="color:var(--danger); text-align:center; padding:20px;">${escapeHtml((res && res.error) || 'Erro ao calcular.')}</div>`;
        lucide.createIcons();
        return;
    }

    resultBox.innerHTML = `
                <div class="card" style="background:var(--bg-panel); border:2px solid ${res.color}; padding:20px; text-align:center;">
                        <h3 style="color:${res.color}; margin-bottom:10px; font-weight:800;">${escapeHtml(res.easy_status)}</h3>
                        <div style="font-size:3rem; font-weight:900; color:var(--text-main); line-height:1;">${(res.dn || 0).toLocaleString()}</div>
                        <div style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted); margin-bottom:8px;">Ndm = n · dm</div>
                        <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:15px;">
                            DN (n·d) = ${(res.dn_bore || 0).toLocaleString()} · dm = ${res.dm} mm
                            ${res.d && res.D ? ` · ${res.d} × ${res.D} mm` : ''}
                        </div>
                        ${res.suggestion ? `<div style="background:rgba(239,68,68,0.1); color:#ef4444; padding:10px; border-radius:6px; font-size:0.9rem;">${escapeHtml(res.suggestion)}</div>` : ''}
                </div>
             `;
    lucide.createIcons();
}

async function runSynthBucCalc() {
    const d = document.getElementById('synth-buc-d').value;
    const l = document.getElementById('synth-buc-l')?.value;
    const rpm = document.getElementById('synth-buc-rpm').value;
    const k = document.getElementById('synth-buc-k')?.value || 1;
    const resultBox = document.getElementById('synth-buc-result');

    if (!d || !rpm) return showToast('Preencha diâmetro e velocidade.', 'warning');

    const res = await api('calc_bushing', { d: d, l: l || d, rpm: rpm, k: k });

    if (!res || res.error) {
        resultBox.innerHTML = `<div style="color:var(--danger); text-align:center; padding:20px;">${escapeHtml((res && res.error) || 'Erro ao calcular.')}</div>`;
        return;
    }

    resultBox.innerHTML = `
                <div class="card" style="background:var(--bg-panel); border:2px solid var(--primary); padding:20px; text-align:center;">
                    <h3 style="color:var(--primary); margin-bottom:10px;">Relubrificação de bucha</h3>
                    <div style="font-size:2.5rem; font-weight:900; color:var(--text-main);">${res.qty_cm3_monthly != null ? res.qty_cm3_monthly : '--'} <span style="font-size:1rem;">cm³/mês</span></div>
                    <p style="font-size:0.85rem; color:var(--text-muted); margin-top:8px;">
                        ${res.grams_per_100h != null ? res.grams_per_100h + ' g / 100 h de operação' : ''}
                        ${res.assumed_square ? ' · L não informado: usado L = d' : ''}
                    </p>
                </div>
            `;
}

// --- VALIDATION LOGIC ---
function validateCapacity() {
    const qty = parseFloat(document.getElementById('af-qtd_material').value) || 0;
    const cap = parseFloat(document.getElementById('af-capacidade').value) || 0;
    const warn = document.getElementById('cap-warning');

    if (cap > 0 && qty > (cap * 0.5)) {
        if (warn) warn.style.display = 'block';
    } else {
        if (warn) warn.style.display = 'none';
    }
    updateLocalNode();
}
// --- OIL ANALYSIS MONITORING LOGIC ---
async function saveOilAnalysis() {
    if (!editingNode || !editingNode.id) return showToast('Selecione um ativo.', 'warning');

    const visc40 = document.getElementById('mon-visc40').value;
    const visc100 = document.getElementById('mon-visc100').value;
    const acidez = document.getElementById('mon-acidez').value;

    if (!visc40 || !visc100 || !acidez) {
        return showToast('Campos de Viscosidade e Acidez são OBRIGATÓRIOS.', 'warning');
    }

    const data = {
        ativo_id: editingNode.id,
        date: document.getElementById('mon-date').value || new Date().toISOString().split('T')[0],
        lab: document.getElementById('mon-lab').value,
        laudo: document.getElementById('mon-laudo').value,
        iso: document.getElementById('mon-iso').value,
        water: document.getElementById('mon-water').value,
        fe: document.getElementById('mon-fe').value,
        cu: document.getElementById('mon-cu').value,
        si: document.getElementById('mon-si').value,
        visc40: visc40,
        visc100: visc100,
        acidez: acidez
    };

    const res = await api('save_analysis', data);
    if (res.ok) {
        showToast('Análise salva com sucesso!', 'success');
        if (res.new_status && res.new_status !== 'Ok') {
            showToast(`Status do Ativo atualizado para: ${res.new_status}`, 'warning');
            // Could refresh tree here
        }
        loadAnalysisHistory(editingNode.id); // Refresh chart
    } else {
        showToast('Erro ao salvar.', 'error');
    }
}

async function importAnalysisCsv(input) {
    if (!input.files || !input.files[0]) return;
    const fd = new FormData();
    fd.append('file', input.files[0]);
    try {
        const previewRes = await fetch('api.php?action=import_analysis_csv', { method: 'POST', body: fd, credentials: 'same-origin' });
        const preview = await previewRes.json();
        if (!preview || preview.ok === false) {
            showToast((preview && preview.error) || 'CSV inválido.', 'error');
            input.value = '';
            return;
        }
        const n = preview.importable || 0;
        const errs = (preview.errors || []).slice(0, 5).join('\n');
        if (!confirm('Pré-visualização: ' + n + ' linha(s) graváveis.\n' + (errs ? ('Avisos:\n' + errs + '\n') : '') + 'Confirmar importação? Laudos antigos permanecem.')) {
            input.value = '';
            return;
        }
        const fd2 = new FormData();
        fd2.append('file', input.files[0]);
        fd2.append('confirm', '1');
        const res = await fetch('api.php?action=import_analysis_csv', { method: 'POST', body: fd2, credentials: 'same-origin' });
        const json = await res.json();
        if (json && json.ok) {
            showToast('Importados ' + (json.imported || 0) + ' laudo(s).', 'success');
            if (editingNode && editingNode.id) loadAnalysisHistory(editingNode.id);
        } else {
            showToast((json && json.error) || 'Falha na importação.', 'error');
        }
    } catch (e) {
        showToast('Falha ao ler o CSV.', 'error');
    }
    input.value = '';
}

async function loadAnalysisHistory(assetId) {
    const res = await api('get_analysis_history', { ativo_id: assetId });
    if (!res || !res.chart) return;

    const ctx = document.getElementById('monitor-chart').getContext('2d');
    if (charts['monitor']) charts['monitor'].destroy();

    charts['monitor'] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: res.chart.labels,
            datasets: [
                {
                    label: 'Desgaste Ferro (Fe ppm)',
                    data: res.chart.fe,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Água (ppm)',
                    data: res.chart.water,
                    borderColor: '#3b82f6',
                    borderDash: [5, 5],
                    fill: false,
                    yAxisID: 'y'
                },
                {
                    label: 'Visc. 40 °C (cSt)',
                    data: res.chart.visc40 || [],
                    borderColor: '#0ea5e9',
                    fill: false,
                    tension: 0.3,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#94a3b8' } },
                title: { display: true, text: 'Histórico de Contaminação e Viscosidade', color: '#000' }
            },
            scales: {
                y: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { color: '#64748b' } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#0ea5e9' } },
                x: { grid: { display: false }, ticks: { color: '#64748b' } }
            }
        }
    });
}

// Hook tab switch to load chart
// --- MARKETPLACE LOGIC ---
async function checkMarketForProduct(productName) {
    if (!productName) return;

    // Ensure catalog is loaded
    if (cachedCatalog.length === 0) await loadCatalog();

    // 1. Find Catalog ID (Fuzzy Search)
    // Try exact match first, then includes
    let catItem = cachedCatalog.find(c => (c.nome || '').toLowerCase() === productName.toLowerCase());
    if (!catItem) {
        catItem = cachedCatalog.find(c => (c.nome || '').toLowerCase().includes(productName.toLowerCase()));
    }

    if (!catItem) {
        // Even if not in catalog, show generic suggestions based on keywords
        // This ensures the box appears even for "Graxa" if "Graxa" isn't in catalog
        const keywords = ['graxa', 'oleo', 'rolamento', 'filtro', 'correia', 'bucha'];
        const hit = keywords.find(k => productName.toLowerCase().includes(k));

        if (!hit) {
            document.getElementById('market-suggestion-box').style.display = 'none';
            return;
        }
        // Mock a fake item for generic search
        catItem = { id: 9999, nome: productName };
    }

    // 2. Fetch Offers
    const res = await api('get_market_data', { cat_id: catItem.id });
    const offers = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);

    const box = document.getElementById('market-suggestion-box');
    const list = document.getElementById('market-offers-list');
    if (!box || !list) return;

    if (offers.length === 0) {
        box.style.display = 'none';
        return;
    }

    // 3. Estimate Volume (Annual)
    const qty = parseFloat(document.getElementById('af-qtd_material').value) || 0;
    const period = document.getElementById('af-periodo').value || 'Mensal';

    let annualMultiplier = 1;
    if (period === 'Diario') annualMultiplier = 365;
    if (period === 'Semanal') annualMultiplier = 52;
    if (period === 'Quinzenal') annualMultiplier = 26;
    if (period === 'Mensal') annualMultiplier = 12;
    if (period === 'Bimestral') annualMultiplier = 6;

    const totalQty = qty * annualMultiplier;
    const isKg = (document.getElementById('af-unid_material').value === 'g') ? totalQty / 1000 : totalQty;
    const unitLabel = (document.getElementById('af-unid_material').value === 'g') ? 'kg' : 'unidades';

    // 4. Render Smart Cards
    list.innerHTML = '';
    offers.forEach((offer, idx) => {
        const offerPrice = Number(offer.price) || 0;
        const estimCost = (offerPrice * isKg).toFixed(2);
        const isBestDeal = idx === 0;

        const cardHtml = `
                <div style="background: ${isBestDeal ? 'rgba(14, 165, 233, 0.05)' : 'white'}; 
                    padding: 15px; border-radius: 12px; border: 1px solid ${isBestDeal ? 'var(--primary)' : '#e2e8f0'}; 
                    display: flex; flex-direction: column; gap: 8px; position: relative; margin-bottom: 12px;">
                    
                    ${isBestDeal ? `<div style="position:absolute; top:-10px; right:15px; background:var(--primary); color:white; font-size:0.65rem; padding:2px 8px; border-radius:20px; font-weight:800; z-index:1;">MELHOR PRECO</div>` : ''}

                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <div style="font-weight:700; color:var(--text-main); display:flex; align-items:center; gap:5px;">
                                ${escapeHtml(offer.vendor_name)} 
                                ${offer.verified ? '<i data-lucide="check-circle-2" style="width:14px; color:var(--success);"></i>' : ''}
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:10px;">
                                <span>${escapeHtml(offer.rating || '4.5')}</span>
                                <span>Entrega: ${escapeHtml(offer.delivery || 'N/A')}</span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:1.1rem; font-weight:800; color:var(--primary);">R$ ${offerPrice.toFixed(2)}</div>
                            <div style="font-size:0.65rem; color:var(--text-muted);">un</div>
                        </div>
                    </div>
                    
                    ${totalQty > 0 ? `<div style="background:rgba(0,0,0,0.03); padding:8px; border-radius:6px; font-size:0.75rem;">Est. Anual: <b>R$ ${estimCost}</b></div>` : ''}

                    <a href="${escapeAttr(safeUrl(offer.vendor_url))}" target="_blank" rel="noopener noreferrer" class="btn btn-sm" style="width:100%; justify-content:center; text-decoration:none; margin-top:5px; border-radius:8px;">
                        Ver Oferta <i data-lucide="external-link" style="width:12px; margin-left:4px;"></i>
                    </a>
                </div>
            `;

        list.innerHTML += cardHtml;
        const tabList = document.getElementById('tab-market-offers-list');
        if (tabList) {
            if (idx === 0) tabList.innerHTML = ''; // Clear only once
            tabList.innerHTML += cardHtml;
        }
    });

    // Update name in tab
    document.querySelectorAll('.af-mat-preview').forEach(el => el.innerText = productName);

    lucide.createIcons();
    box.style.display = 'block';
}

// --- OIL ANALYSIS REAL-TIME CERTIFICATE ---
document.addEventListener('input', (e) => {
    if (!e.target.id) return;

    if (e.target.id === 'mon-iso') document.getElementById('v-lab-iso').innerText = e.target.value || '--/--/--';
    if (e.target.id === 'mon-water') document.getElementById('v-lab-water').innerText = (e.target.value || '--') + ' ppm';
    if (e.target.id === 'mon-fe') document.getElementById('v-lab-fe').innerText = e.target.value || '--';

    // Dynamic Status on Certificate
    const laudo = document.getElementById('mon-laudo')?.value;
    const statusIA = document.querySelector('#virtual-lab-report span[style*="color:var(--success)"]');
    if (statusIA) {
        if (laudo === 'Critico') {
            statusIA.innerText = 'CRÍTICO / INTERROMPER';
            statusIA.style.color = '#ef4444';
        } else if (laudo === 'Alerta') {
            statusIA.innerText = 'ALERTA / MONITORAR';
            statusIA.style.color = '#f59e0b';
        } else {
            statusIA.innerText = 'DENTRO DO LIMITE';
            statusIA.style.color = '#10b981';
        }
    }
});

// --- NEW INVENTORY MARKETPLACE LOGIC ---
async function reloadMarketAnalysis(id, name) {
    const list = document.getElementById(`market-list-${id}`);
    const chartEl = document.getElementById('market-price-trend');

    if (!list) return;

    try {
        let offers = [];
        // 1. Try Real API
        try {
            const res = await api('get_market_data', { cat_id: id });
            if (Array.isArray(res)) offers = res;
            else if (res && res.data) offers = res.data;
        } catch (e) { }

        if (!Array.isArray(offers)) offers = [];

        // Sem ofertas reais: estado vazio (não inventar preços)
        if (offers.length === 0) {
            list.innerHTML = `<div style="text-align:center;padding:28px 16px;color:var(--text-muted);">
                <p style="margin:0;font-weight:700;">Nenhuma oferta cadastrada</p>
                <p style="margin:8px 0 0;font-size:0.85rem;">Cadastre fornecedores reais no inventário para comparar preços.</p>
            </div>`;
            const savEl = document.getElementById('market-savings-total');
            if (savEl) savEl.innerText = '0';
            const trendBest = document.getElementById('market-trend-best');
            const trendWorst = document.getElementById('market-trend-worst');
            const trendAvg = document.getElementById('market-trend-avg');
            if (trendBest) trendBest.textContent = '—';
            if (trendWorst) trendWorst.textContent = '—';
            if (trendAvg) trendAvg.textContent = '—';
            if (chartEl && typeof Chart !== 'undefined') {
                const existingChart = Chart.getChart(chartEl);
                if (existingChart) existingChart.destroy();
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }

        // Render List
        let html = '';
        const bestPrice = Number(offers[0].price) || 0;
        const avgPrice = offers.reduce((acc, curr) => acc + (Number(curr.price) || 0), 0) / offers.length;
        const savings = (avgPrice - bestPrice) * 12 * 5; // Anual estimado

        offers.forEach((o, i) => {
            const isBest = i === 0;
            const price = Number(o.price) || 0;
            const diff = price - bestPrice;

            html += `
                 <div class="flex-between vendor-offer-row" style="padding:12px; background:${isBest ? '#f0f9ff' : 'white'}; border:1px solid ${isBest ? '#bae6fd' : 'var(--border)'}; border-radius:8px; align-items:flex-start; margin-bottom:8px;" data-vendor-url="${escapeAttr(safeUrl(o.vendor_url))}">
                    <div style="flex:1;">
                        <div style="font-weight:700; color:var(--text-main); display:flex; gap:6px; align-items:center; font-size:0.85rem;">
                            ${escapeHtml(o.vendor_name)} 
                            ${o.verified ? '<i data-lucide="badge-check" style="width:14px; color:#0ea5e9;"></i>' : ''}
                            ${isBest ? '<span style="font-size:0.6rem; background:#0ea5e9; color:white; padding:2px 6px; border-radius:4px; font-weight:800;">MELHOR</span>' : ''}
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">${escapeHtml(o.delivery)}</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:800; color:${isBest ? '#0ea5e9' : 'var(--text-main)'}; font-size:1rem;">
                            R$ ${price.toFixed(2)}
                        </div>
                        ${!isBest ? `<div style="font-size:0.65rem; color:#ef4444;">+ R$ ${diff.toFixed(2)}</div>` : ''}
                    </div>
                    <button type="button" class="btn-sm vendor-offer-link" style="margin-left:10px; height:30px; background:${isBest ? '#0ea5e9' : '#f1f5f9'}; color:${isBest ? 'white' : 'var(--text-muted)'}; border:none; border-radius:6px;">
                        <i data-lucide="external-link" style="width:14px;"></i>
                    </button>
                 </div>
                 `;
        });

        list.innerHTML = html;
        list.querySelectorAll('.vendor-offer-row').forEach(row => {
            const url = safeUrl(row.dataset.vendorUrl || '');
            const btn = row.querySelector('.vendor-offer-link');
            if (btn && url) {
                btn.addEventListener('click', () => window.open(url, '_blank', 'noopener,noreferrer'));
            } else if (btn) {
                btn.disabled = true;
            }
        });
        lucide.createIcons();

        // Update Savings
        const savEl = document.getElementById('market-savings-total');
        if (savEl) savEl.innerText = savings > 0 ? savings.toFixed(0) : '0';
        const savYear = document.getElementById('market-savings-year');
        if (savYear) savYear.innerText = savings > 0 ? Number(savings).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) : '0';

        const prices = offers.map(o => Number(o.price) || 0).filter(p => p > 0);
        const trendBest = document.getElementById('market-trend-best');
        const trendWorst = document.getElementById('market-trend-worst');
        const trendAvg = document.getElementById('market-trend-avg');
        if (prices.length) {
            const minP = Math.min(...prices);
            const maxP = Math.max(...prices);
            const avgP = prices.reduce((a, b) => a + b, 0) / prices.length;
            if (trendBest) trendBest.textContent = 'R$ ' + minP.toFixed(2).replace('.', ',');
            if (trendWorst) trendWorst.textContent = 'R$ ' + maxP.toFixed(2).replace('.', ',');
            if (trendAvg) trendAvg.textContent = 'R$ ' + avgP.toFixed(2).replace('.', ',');
        }

        // Chart — apenas com preços reais das ofertas atuais
        if (chartEl && typeof Chart !== 'undefined') {
            const existingChart = Chart.getChart(chartEl);
            if (existingChart) existingChart.destroy();

            const labels = offers.map(o => o.vendor_name || 'Fornecedor');
            const trend = offers.map(o => Number(o.price) || 0);

            new Chart(chartEl, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Preço por fornecedor',
                        data: trend,
                        borderColor: '#10b981',
                        backgroundColor: (context) => {
                            const ctx = context.chart.ctx;
                            const gradient = ctx.createLinearGradient(0, 0, 0, 200);
                            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
                            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                            return gradient;
                        },
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                    scales: {
                        x: { display: false },
                        y: { display: false, min: Math.min(...trend) * 0.8 }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

    } catch (e) {
        console.error(e);
        list.innerHTML = '<div style="color:red; text-align:center; padding:10px;">Erro na IA de Mercado.</div>';
    }
}

async function addMarketOffer(catId) {
    const v = document.getElementById('mk-vendor').value;
    const p = document.getElementById('mk-price').value;
    const u = document.getElementById('mk-url').value;

    if (!v || !p) return showToast("Preencha fornecedor e preço", "warning");

    // Optimistic UI Update
    const list = document.getElementById(`market-list-${catId}`);
    if (list) {
        const tempHtml = `
             <div class="flex-between" style="padding:12px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; margin-bottom:8px;">
                <div style="font-weight:700; color:#c2410c;">${escapeHtml(v)} (Salvando...)</div>
                <div style="font-weight:800;">R$ ${(parseFloat(p) || 0).toFixed(2)}</div>
             </div>`;
        list.innerHTML = tempHtml + list.innerHTML;
    }

    try {
        const res = await api('save_market_offer', { cat_id: catId, vendor: v, price: p, url: u });
        if (!res || res.ok === false) {
            showToast((res && res.error) || 'Erro ao salvar oferta.', 'error');
            return;
        }
        showToast('Oferta adicionada!', 'success');
        // Reload to re-sort and re-calc IA
        reloadMarketAnalysis(catId, document.getElementById('ced-name').value);

        // Clear inputs
        document.getElementById('mk-vendor').value = '';
        document.getElementById('mk-price').value = '';
        document.getElementById('mk-url').value = '';
    }

    catch (e) {
        showToast('Erro ao salvar oferta.', 'error');
    }
}

function addSpecRow() {
    const container = document.getElementById('spec-editor-container');
    if (!container) return;
    const noSpecs = document.getElementById('no-specs');
    if (noSpecs) noSpecs.remove();
    const row = document.createElement('div');
    row.className = 'flex-between';
    row.style.cssText = 'background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid var(--border);';
    row.innerHTML = `
        <input class="spec-key" style="border:none; background:transparent; font-weight:600; font-size:0.85rem; color:var(--text-muted); width:40%;" placeholder="Propriedade">
        <input class="spec-val" style="border:none; background:transparent; text-align:right; font-weight:600; color:var(--text-main); width:55%;" placeholder="Valor">
        <button type="button" class="spec-remove-btn" aria-label="Remover especificação" onclick="this.parentElement.remove()">
            <i data-lucide="x" aria-hidden="true"></i>
        </button>`;
    container.appendChild(row);
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
window.addSpecRow = addSpecRow;

// --- CENTRALIZED EXCEL & PRINT SYSTEM ---
function getTodayISOStr() {
    return new Date().toISOString().split('T')[0];
}

function exportToExcel(data, filename, sheetName = 'Sheet1') {
    if (typeof XLSX === 'undefined') return showToast('Erro: Biblioteca Excel (SheetJS) não carregada.', 'error');
    if (!data || data.length === 0) return showToast('Sem dados para exportar.', 'warning');

    try {
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.json_to_sheet(data);

        // Auto-width columns
        const colWidths = [];
        // Sample first 50 rows to estimate width to avoid perf hit on large datasets
        const sample = data.slice(0, 50);
        if (sample.length > 0) {
            Object.keys(sample[0]).forEach((key) => {
                let maxLen = key.length;
                sample.forEach(row => {
                    const val = row[key] ? String(row[key]) : '';
                    if (val.length > maxLen) maxLen = val.length;
                });
                colWidths.push({ wch: Math.min(maxLen + 5, 60) });
            });
            ws['!cols'] = colWidths;
        }

        XLSX.utils.book_append_sheet(wb, ws, sheetName);
        XLSX.writeFile(wb, filename + '.xlsx');
        showToast('Relatório Excel gerado com sucesso!', 'success');
    } catch (e) {
        console.error(e);
        showToast('Erro crítico ao gerar Excel.', 'error');
    }
}

// -- Export Wrappers --

// 1. Catalog Export
function exportCatalogExcel() {
    if (!cachedCatalog || cachedCatalog.length === 0) return showToast('Carregue o catálogo primeiro.', 'warning');

    const cleanData = cachedCatalog.map(i => ({
        ID: i.id,
        Nome: i.nome,
        IP: i.ip || '-',
        Fabricante: i.fabricante || '',
        Codigo: i.codigo || '',
        Categoria: i.tipo || '',
        Estoque: i.estoque_atual || 0,
        Localizacao: i.localizacao || '',
        Descricao: i.descricao || ''
    }));

    exportToExcel(cleanData, 'Inventario_LUBTEK_' + getTodayISOStr(), 'Catalogo');
}

// 2. Tasks / OS Export
function exportTasksExcel() {
    // Assume 'tasks' global variable
    if (typeof tasks === 'undefined' || !tasks || tasks.length === 0) return showToast('Nenhuma tarefa carregada.', 'warning');

    const cleanData = tasks.map(t => ({
        OS: t.id,
        IP: t.ip,
        Servico: t.cod_serv,
        Descricao: t.desc,
        Responsavel: t.resp,
        Situacao: t.situacao,
        Emissao: t.data_emissao,
        Previsao: t.date,
        Execucao: t.data_execucao || '-'
    }));

    exportToExcel(cleanData, 'Ordens_Servico_LUBTEK_' + getTodayISOStr(), 'Servicos');
}

// 4. Relatório Técnico PDF (Laudo de Engenharia)
window.downloadTechnicalReport = function () {
    if (typeof jspdf === 'undefined') return showToast('Erro: Biblioteca jsPDF não carregada.', 'error');

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Header Blue Bar
        doc.setFillColor(2, 132, 199); // LUB-TEK Blue
        doc.rect(0, 0, 210, 24, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text("LUB-TEK | Laudo Técnico de Engenharia", 14, 16);
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text("Relatório de Conformidade e Lubrificação - SKF Compliant", 14, 21);

        // Content Check
        const res = window.lastSmartCalcResult;
        if (!res) {
            // Fallback to current open asset name if no calc
            const assetName = document.getElementById('af-name').value || 'Ativo sem Nome';
            doc.setTextColor(0, 0, 0);
            doc.text(`Ativo: ${assetName}`, 14, 40);
            doc.text("Nenhum cálculo de engenharia realizado recentemente para este ativo.", 14, 50);
            doc.save(`Laudo_${assetName}.pdf`);
            return;
        }

        // Metadata
        doc.setTextColor(0, 0, 0);
        doc.setFontSize(11);
        doc.setFont('helvetica', 'bold');
        doc.text(`Ativo: ${document.getElementById('af-name').value || 'N/A'}`, 14, 40);
        doc.setFont('helvetica', 'normal');
        doc.text(`Data do Laudo: ${new Date().toLocaleDateString('pt-BR')}`, 14, 46);

        // Results Table via AutoTable
        const headers = [['Parâmetro Técnico', 'Valor Calculado / Especificação']];
        const rows = [
            ['Modelo Rolamento', res.model || '-'],
            ['Dimensões (d x D x B)', `${res.d}x${res.D}x${res.B} mm`],
            ['Volume de Relubrificação', `${res.grams} g`],
            ['Intervalo (Dias)', `${res.days} dias`],
            ['Intervalo (Horas de Operação)', `${res.hours} h`],
            ['Fator DN (Velocidade)', res.dn ? res.dn.toLocaleString() : '-'],
            ['Rotação Operacional', `${res.rpm} RPM`],
            ['Graxa Recomendada', res.visc_req ? `Viscosidade Requerida > ${res.visc_req} cSt` : 'Verficiar Manual']
        ];

        doc.autoTable({
            startY: 55,
            head: headers,
            body: rows,
            theme: 'grid',
            headStyles: { fillColor: [2, 132, 199], textColor: 255, fontStyle: 'bold' },
            styles: { fontSize: 11, cellPadding: 6, valign: 'middle' },
            columnStyles: { 0: { fontStyle: 'bold', cellWidth: 80 } }
        });

        // Footer
        const finalY = doc.lastAutoTable.finalY + 20;
        doc.setFontSize(9);
        doc.setTextColor(100);
        doc.text("Este documento foi gerado automaticamente pelo LUB-TEK System v4.0.", 14, 280);
        doc.text("Parâmetros baseados nas normas SKF e inputs do usuário.", 14, 284);

        doc.save(`Laudo_Engenharia_${res.model || 'Ativo'}.pdf`);
        showToast('Laudo PDF baixado com sucesso!', 'success');
    } catch (e) {
        console.error(e);
        showToast('Erro ao gerar PDF: ' + e.message, 'error');
    }
}

// 3. Asset Tree Export (Improved)
async function exportAssetsExcelRobust() {
    if (!window.treeState) return showToast('Árvore de ativos não carregada.', 'warning');

    function flat(nodes, parentName = '', list = []) {
        nodes.forEach(n => {
            const fullName = parentName ? parentName + ' > ' + n.nome : n.nome;
            let tech = n.dados_tecnicos;
            try { if (typeof tech === 'string') tech = JSON.parse(tech || '{}'); } catch (e) { tech = {}; }
            if (!tech || typeof tech !== 'object') tech = {};
            list.push({
                ID: n.id,
                Nome: n.nome,
                Caminho: fullName,
                Tipo: n.tipo,
                Status: n.status,
                TAG: n.tag || '',
                Lubrificante: tech.material || '',
                Qtd: tech.qtd_material || '',
                Periodo: tech.periodo || ''
            });
            if (n.children) flat(n.children, fullName, list);
        });
        return list;
    }

    const data = flat(window.treeState);
    exportToExcel(data, 'Ativos_Planta_LUBTEK_' + getTodayISOStr(), 'Hierarquia');
}

// --- HTML5 DRAG & DROP FOR ASSETS TREE ---
let draggedNodeId = null;

function handleDragStart(e, id) {
    draggedNodeId = id;
    e.dataTransfer.setData('text/plain', id);
    e.dataTransfer.effectAllowed = 'move';
    
    const el = document.getElementById('tn-' + id);
    if (el) {
        setTimeout(() => el.style.opacity = '0.4', 0);
    }
}

function handleDragEnd(e, id) {
    const el = document.getElementById('tn-' + id);
    if (el) {
        el.style.opacity = '1';
    }
    draggedNodeId = null;
    
    document.querySelectorAll('.tree-node').forEach(nodeEl => {
        nodeEl.style.border = '1px solid transparent';
        nodeEl.style.background = 'transparent';
    });
}

function handleDragOver(e, targetId) {
    if (draggedNodeId === targetId) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleDragEnter(e, targetId) {
    if (draggedNodeId === targetId) return;
    if (isDescendantLocal(draggedNodeId, targetId)) return;
    
    const el = document.getElementById('tn-' + targetId);
    if (el) {
        el.style.border = '1px dashed var(--primary)';
        el.style.background = 'var(--primary-glow)';
    }
}

function handleDragLeave(e, targetId) {
    const el = document.getElementById('tn-' + targetId);
    if (el) {
        el.style.border = '1px solid transparent';
        el.style.background = 'transparent';
    }
}

async function handleAssetDrop(e, targetId) {
    e.preventDefault();
    e.stopPropagation();
    
    const id = e.dataTransfer.getData('text/plain') || draggedNodeId;
    if (!id) return;
    
    if (id === targetId) return;
    
    if (targetId && isDescendantLocal(id, targetId)) {
        showToast('Não é possível mover um item para dentro de si mesmo ou de seus descendentes.', 'error');
        return;
    }
    
    if (targetId) {
        const el = document.getElementById('tn-' + targetId);
        if (el) {
            el.style.border = '1px solid transparent';
            el.style.background = 'transparent';
        }
    }
    
    showToast('Movendo item...', 'info');
    try {
        const res = await api('move_asset', {
            id: id,
            pai_id: (targetId === 'null' || !targetId) ? null : targetId
        });
        
        if (res.ok) {
            showToast('Item movido com sucesso!', 'success');
            if (targetId && targetId !== 'null') {
                expandedNodes.add(String(targetId));
            }
            await loadTree();
        } else {
            throw new Error(res.error || 'Erro ao mover item no servidor.');
        }
    } catch (err) {
        console.error('Move Error:', err);
        showToast(err.message, 'error');
    }
}

function isDescendantLocal(nodeId, targetId) {
    if (!nodeId || !targetId) return false;
    if (nodeId === targetId) return true;
    
    const node = allNodesMap.get(String(targetId));
    if (!node) return false;
    
    let current = node.pai_id;
    while (current) {
        if (String(current) === String(nodeId)) return true;
        const parentNode = allNodesMap.get(String(current));
        current = parentNode ? parentNode.pai_id : null;
    }
    return false;
}

// Expose handlers to global scope for inline HTML element event binding
window.handleDragStart = handleDragStart;
window.handleDragEnd = handleDragEnd;
window.handleDragOver = handleDragOver;
window.handleDragEnter = handleDragEnter;
window.handleDragLeave = handleDragLeave;
window.handleAssetDrop = handleAssetDrop;

// --- KANBAN BOARD SYSTEM ---
let activeOSView = 'table'; // 'table' or 'kanban'

function switchOSView(view, btn) {
    activeOSView = view;
    document.querySelectorAll('.view-toggle-group .filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const tableWrap = document.getElementById('os-table-view-wrapper');
    const kanbanWrap = document.getElementById('os-kanban-view-wrapper');

    if (view === 'table') {
        if (tableWrap) tableWrap.style.display = 'block';
        if (kanbanWrap) kanbanWrap.style.display = 'none';
        if (typeof renderOSTable === 'function') renderOSTable();
    } else {
        if (tableWrap) tableWrap.style.display = 'none';
        if (kanbanWrap) kanbanWrap.style.display = 'block';
        if (typeof renderOSKanban === 'function') renderOSKanban();
    }
}

function refreshOSView() {
    if (activeOSView === 'kanban') {
        renderOSKanban();
    } else {
        renderOSTable();
    }
}

function renderOSKanban() {
    const container = document.getElementById('kanban-board-container');
    if (!container) return;

    try {
        const currentTasks = Array.isArray(tasks) ? tasks : [];

        // Apply same filters as table
        const filtered = currentTasks.filter(t => {
            if (!t) return false;
            
            if (taskFilter !== 'all') {
                const sit = String(t.situacao || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                const want = String(taskFilter).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                if (sit !== want) return false;
            }

            if (taskSearch) {
                const s = taskSearch.toLowerCase();
                const desc = t.desc ? String(t.desc).toLowerCase() : '';
                const tid = String(t.id || '');
                const ip = t.ip ? String(t.ip).toLowerCase() : '';
                const cs = t.cod_serv ? String(t.cod_serv).toLowerCase() : '';
                const r = t.resp ? String(t.resp).toLowerCase() : '';
                return desc.includes(s) || tid.includes(s) || ip.includes(s) || cs.includes(s) || r.includes(s);
            }
            return true;
        });

        // Define columns
        const columns = [
            { id: 'Pendente', label: '⏳ Pendente', color: '#f59e0b' },
            { id: 'Em Andamento', label: '⚙️ Em Andamento', color: '#3b82f6' },
            { id: 'Aguardando Material', label: '🛒 Aguardando Material', color: '#a855f7' },
            { id: 'Concluído', label: '✅ Concluído', color: '#10b981' }
        ];

        container.innerHTML = columns.map(col => {
            const colTasks = filtered.filter(t => {
                const sit = t.situacao || 'Pendente';
                if (col.id === 'Concluído') {
                    return sit === 'Concluído' || sit === 'Cancelado';
                }
                return sit === col.id;
            });

            const cardsHtml = colTasks.map(t => {
                const id = t.id;
                const desc = t.desc || 'Sem Descrição';
                const resp = t.resp || '-';
                const prio = t.prio || 'Média';
                const ip = t.ip ? ` • IP: ${escapeHtml(t.ip)}` : '';
                
                return `
                    <div class="kanban-card os-card" draggable="true" ondragstart="handleKanbanDragStart(event, ${id})" ondragend="handleKanbanDragEnd(event)" onclick="selectOS(${id})">
                        <div class="kanban-card-id">#${escapeHtml(id)} <span style="float:right; background:${getPrioColor(prio)}20; color:${getPrioColor(prio)}; padding:2px 6px; border-radius:4px; font-size:0.65rem;">${escapeHtml(prio)}</span></div>
                        <div class="kanban-card-title">${escapeHtml(desc)}</div>
                        <div class="kanban-card-meta">
                            <span><i data-lucide="user" style="width:11px; height:11px; display:inline-block; vertical-align:middle; margin-right:3px;"></i>${escapeHtml(resp)}</span>
                            <span><i data-lucide="calendar" style="width:11px; height:11px; display:inline-block; vertical-align:middle; margin-right:3px;"></i>Prog: ${escapeHtml(formatDateBR(t.date))}${ip}</span>
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="kanban-col" ondragover="handleKanbanDragOver(event)" ondrop="handleKanbanDrop(event, '${col.id}')" style="border-top: 4px solid ${col.color};">
                    <div class="kanban-col-header">
                        <div class="kanban-col-title">${col.label}</div>
                        <div class="kanban-col-count">${colTasks.length}</div>
                    </div>
                    <div class="kanban-cards">
                        ${cardsHtml}
                    </div>
                </div>
            `;
        }).join('');

        if (typeof lucide !== 'undefined') lucide.createIcons();

    } catch (e) {
        console.error(e);
    }
}

let draggingOSId = null;

function handleKanbanDragStart(e, id) {
    draggingOSId = id;
    const card = e.currentTarget;
    card.classList.add('dragging');
    e.dataTransfer.setData('text/plain', id);
    e.dataTransfer.effectAllowed = 'move';
}

function handleKanbanDragEnd(e) {
    const card = e.currentTarget;
    card.classList.remove('dragging');
    draggingOSId = null;
}

function handleKanbanDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

async function handleKanbanDrop(e, targetStatus) {
    e.preventDefault();
    const id = e.dataTransfer.getData('text/plain') || draggingOSId;
    if (!id) return;

    const t = tasks.find(x => x.id == id);
    if (!t) return;

    if (t.situacao === targetStatus) return;

    const oldStatus = t.situacao;
    t.situacao = targetStatus;
    t.done = (targetStatus === 'Concluído');

    try {
        const res = await api('save_task', t);
        if (res.ok) {
            showToast(`O.S. #${id} movida para ${targetStatus}`, 'success');
            await loadDash();
        } else {
            t.situacao = oldStatus;
            t.done = (oldStatus === 'Concluído');
            showToast(res.error || 'Erro ao mover O.S.', 'error');
            renderOSKanban();
        }
    } catch (err) {
        t.situacao = oldStatus;
        t.done = (oldStatus === 'Concluído');
        showToast('Erro ao atualizar status da O.S.', 'error');
        renderOSKanban();
    }
}

// Expose Kanban Board functions
window.switchOSView = switchOSView;
window.refreshOSView = refreshOSView;
window.renderOSKanban = renderOSKanban;
window.handleKanbanDragStart = handleKanbanDragStart;
window.handleKanbanDragEnd = handleKanbanDragEnd;
window.handleKanbanDragOver = handleKanbanDragOver;
window.handleKanbanDrop = handleKanbanDrop;

window.toggleNode = toggleNode;
window.filterTree = filterTree;
window.saveToCloudSingle = saveToCloudSingle;
window.bulkAutoIP = bulkAutoIP;
window.cleanupUnnamed = cleanupUnnamed;
window.saveSystemSnapshot = saveSystemSnapshot;
window.wipeDatabase = wipeDatabase;
window.analyzeAssetAI = analyzeAssetAI;
window.requestAssetAiInsight = requestAssetAiInsight;
window.deleteCurrentNode = deleteCurrentNode;
window.importAssetsFromExcel = importAssetsFromExcel;
window.exportAssetsExcelRobust = exportAssetsExcelRobust;
window.toggleFullWidth = toggleFullWidth;
window.updateLocalNode = updateLocalNode;
window.addNode = addNode;

// Expõe api canônica (sobrescreve stub de scripts_extra / error_prevention)
window.__apiMain = api;
window.api = api;
window.loadCatalog = loadCatalog;
window.filterCatalog = filterCatalog;
window.loadDash = loadDash;
window.loadTree = loadTree;
