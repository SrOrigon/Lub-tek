/**
 * LUB-TEK Ecosystem Bridge
 * Navegação unificada + atalhos entre módulos (Ativos ↔ O.S. ↔ Engenharia ↔ KPI)
 */
(function () {
    const MODULES = {
        home: 'Início',
        dash: 'Ordens de Serviço',
        assets: 'Máquinas e Equipamentos',
        catalog: 'Almoxarifado de Óleos',
        calc: 'Calculadoras de Engenharia',
        reports: 'Exportar Documentos',
        kpi: 'Relatório de Desempenho',
        routes: 'Roteiro de Campo',
        users: 'Equipe e Acessos',
        audit: 'Histórico de Atividades',
        pi: 'Sensores em Tempo Real',
        sap: 'Integração SAP',
        '3d': 'Visualizador 3D'
    };

    let currentView = 'home';
    let currentAsset = null;

    const typeLabels = {
        unidade: 'Unidade',
        setor: 'Setor',
        equipamento: 'Equipamento',
        componente: 'Componente',
        ponto: 'Ponto'
    };

    function getAssetFullName(node) {
        if (!node) return '';
        const typeLabel = typeLabels[node.tipo] || node.tipo || '';
        const capitalizedType = typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1);
        return `${capitalizedType}: ${node.nome}`;
    }

    function updateTopBar(viewId, extra) {
        currentView = viewId || 'home';
        extra = extra || {};

        document.body.classList.toggle('on-home', currentView === 'home');

        const trail = document.getElementById('topbar-trail');
        if (trail) {
            if (currentView === 'home') {
                trail.innerHTML = '';
            } else {
                const labels = window.NOMBRES_AMIGAVEIS_PAGINAS || MODULES;
                const mod = labels[currentView] || currentView;
                const asset = extra.assetName || (currentAsset && getAssetFullName(currentAsset));
                trail.innerHTML = asset
                    ? `<strong id="breadcrumb-page-title">${mod}</strong> › ${escapeHtml(asset)}`
                    : `<strong id="breadcrumb-page-title">${mod}</strong>`;
            }
        }

        renderEcosystemActions();
        if (window.lucide) lucide.createIcons();
    }

    function escapeHtml(s) {
        const d = document.createElement('span');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function renderEcosystemActions() {
        const box = document.getElementById('topbar-ecosystem');
        if (!box) return;

        const isWorker = typeof isTrabalhadorUser === 'function' && isTrabalhadorUser();
        const node = window.editingNode || currentAsset;
        let html = '';

        if (currentView === 'assets' && node && node.id) {
            if (!isWorker) {
                html += ecoBtn('file-text', 'Nova O.S.', 'Ecosystem.createOSForAsset()');
                html += ecoBtn('ruler', 'Engenharia', 'Ecosystem.openEngineering()');
                html += ecoBtn('bar-chart-2', 'KPIs', 'Ecosystem.openKPIs()');
                html += ecoBtn('package', 'Inventário', 'Ecosystem.openCatalog()');
            } else {
                html += ecoBtn('map', 'Rotas', "nav('routes')");
                html += ecoBtn('clipboard-list', 'Ordens', "nav('dash')");
            }
        } else if (currentView === 'dash' || currentView === 'home') {
            html += ecoBtn('map', 'Rotas', "nav('routes')");
            html += ecoBtn('network', 'Meus Ativos', "nav('assets')");
            if (!isWorker) {
                html += ecoBtn('calculator', 'Engenharia', "nav('calc')");
            }
        } else if (currentView === 'routes') {
            html += ecoBtn('network', 'Meus Ativos', "nav('assets')");
            html += ecoBtn('clipboard-list', 'Ordens', "nav('dash')");
        } else if (currentView === 'catalog') {
            html += ecoBtn('network', 'Meus Ativos', "nav('assets')");
            html += ecoBtn('clipboard-list', 'Ordens', "nav('dash')");
        } else if (currentView === 'calc') {
            html += ecoBtn('network', 'Voltar aos Ativos', "nav('assets')");
        } else if (currentView !== 'home') {
            html += ecoBtn('layout-grid', 'Painel', "nav('home')");
        }

        box.innerHTML = html;
    }

    function ecoBtn(icon, label, onclick) {
        return `<button type="button" class="topbar-eco-btn" onclick="${onclick}"><i data-lucide="${icon}" style="width:14px;"></i>${label}</button>`;
    }

    function setCurrentAsset(node) {
        currentAsset = node || null;
        window.currentAsset = node || null;
        if (currentView === 'assets') {
            updateTopBar('assets', node ? { assetName: getAssetFullName(node) } : {});
        }
    }

    function createOSForAsset() {
        const node = window.editingNode;
        if (!node || !node.id) {
            if (typeof showToast === 'function') showToast('Selecione um ativo na árvore à esquerda.', 'warning');
            return;
        }
        window.__ecosystemPending = {
            type: 'os',
            assetId: node.id,
            nome: node.nome,
            tag: node.tag || ''
        };
        if (typeof nav === 'function') nav('dash');
    }

    function openEngineering() {
        const node = window.editingNode;
        if (node) {
            window.__ecosystemPending = { type: 'calc', assetName: node.nome, rpm: getTech(node, 'rpm') };
        }
        if (typeof nav === 'function') nav('calc');
    }

    function openKPIs() {
        if (typeof nav === 'function') nav('kpi');
    }

    function openCatalog() {
        if (typeof nav === 'function') nav('catalog');
    }

    function getTech(node, key) {
        try {
            let t = node.dados_tecnicos;
            if (typeof t === 'string') t = JSON.parse(t);
            return (t && t[key]) ? t[key] : '';
        } catch (e) { return ''; }
    }

    /** Aplica pendências ao entrar em um módulo (O.S. vinculada ao ativo, etc.) */
    function applyPendingNavigation(viewId) {
        const pending = window.__ecosystemPending;
        if (!pending) return;

        if (viewId === 'dash' && pending.type === 'os') {
            window.__ecosystemPending = null;
            setTimeout(function () {
                if (typeof createNewOS === 'function') createNewOS();
                const sel = document.getElementById('f-os-ativo');
                if (sel) sel.value = pending.assetId;
                if (typeof syncAssetData === 'function') syncAssetData(pending.assetId);
                const desc = document.getElementById('f-os-serv');
                if (desc) desc.value = 'Lubrificação / Manutenção — ' + (pending.nome || '');
                if (typeof showToast === 'function') {
                    showToast('O.S. criada e vinculada ao ativo: ' + (pending.nome || ''), 'success');
                }
            }, 500);
        }

        if (viewId === 'calc' && pending.type === 'calc') {
            window.__ecosystemPending = null;
            setTimeout(function () {
                const rpm = document.getElementById('synth-rol-rpm') || document.getElementById('eng-rpm') || document.querySelector('[id*="rpm"]');
                if (rpm && pending.rpm) rpm.value = pending.rpm;
                if (typeof showToast === 'function') {
                    showToast('Engenharia aberta para: ' + (pending.assetName || 'ativo'), 'info');
                }
            }, 400);
        }
    }

    function wireEvents() {
        if (window.AppEvents) {
            window.AppEvents.on('view:changed', function (viewId) {
                updateTopBar(viewId);
                applyPendingNavigation(viewId);
            });
            window.AppEvents.on('node:selected', function (node) {
                setCurrentAsset(node);
            });
        }
        if (window.Events) {
            window.Events.on('data_changed', function (info) {
                if (info && info.action && info.action.includes('save_asset') && window.editingNode) {
                    setCurrentAsset(window.editingNode);
                }
            });
        }
    }

    window.Ecosystem = {
        MODULES,
        updateTopBar,
        setCurrentAsset,
        createOSForAsset,
        openEngineering,
        openKPIs,
        openCatalog,
        applyPendingNavigation
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wireEvents);
    } else {
        wireEvents();
    }
})();
