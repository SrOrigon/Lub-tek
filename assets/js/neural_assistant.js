/**
 * LUB-TEK Motor Neural — Assistente contextual global
 * Conhece todos os módulos e entende onde o usuário está.
 */
(function () {
    'use strict';

    const MODULE_LABELS = {
        home: 'Painel Principal',
        dash: 'Ordens de Serviço',
        assets: 'Meus Ativos',
        catalog: 'Inventário',
        calc: 'Engenharia',
        reports: 'Relatórios',
        kpi: 'KPIs',
        routes: 'Rotas de Lubrificação',
        pi: 'Portal PI System',
        sap: 'Integração SAP',
        '3d': 'Visualizador 3D'
    };

    let chatHistory = [];
    let currentPage = 'home';
    let initialized = false;

    function getCurrentPage() {
        // 1. View ativa no DOM (mais confiável em SPA)
        const active = document.querySelector('.view-container.active');
        if (active && active.id && active.id.startsWith('view-')) {
            const fromDom = active.id.replace('view-', '');
            if (fromDom) return fromDom;
        }
        // 2. Variável global setada pela navegação
        if (window.__lubtekCurrentPage) return window.__lubtekCurrentPage;
        // 3. Parâmetro da URL
        return new URLSearchParams(window.location.search).get('page') || 'home';
    }

    function getSelectedAsset() {
        if (window.editingNode && window.editingNode.nome) return window.editingNode.nome;
        if (window.currentAsset && window.currentAsset.nome) return window.currentAsset.nome;
        return null;
    }

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text || '';
        return d.innerHTML;
    }

    function formatResponse(text) {
        let f = escapeHtml(text);
        f = f.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        f = f.replace(/\n\n/g, '<br><br>');
        f = f.replace(/\n/g, '<br>');
        return f;
    }

    function updateContextLabel() {
        const label = document.getElementById('neural-context-label');
        if (label) {
            const mod = MODULE_LABELS[currentPage] || currentPage;
            label.textContent = 'Você está em: ' + mod;
        }
    }

    function updateWelcomeMessage() {
        const container = document.getElementById('neural-messages');
        if (!container) return;

        const mod = MODULE_LABELS[currentPage] || 'o sistema';
        const text =
            'Olá! Sou a **Lúbria**, Inteligência Artificial orquestradora do LUB-TEK. Assumi o controle do sistema para te ajudar.\n\n' +
            'Vejo que você está em **' + mod + '**. Pergunte como fazer qualquer coisa aqui — eu gerencio e te auxilio em tudo!';

        let welcome = container.querySelector('.neural-welcome');
        if (welcome) {
            welcome.innerHTML = formatResponse(text);
        } else if (!container.querySelector('.neural-msg-user, .neural-msg-ai:not(.neural-welcome)')) {
            welcome = document.createElement('div');
            welcome.className = 'neural-msg-ai neural-welcome';
            welcome.innerHTML = formatResponse(text);
            container.appendChild(welcome);
        }
    }

    function showWelcomeIfEmpty() {
        updateWelcomeMessage();
    }

    let quickPromptsRequestId = 0;

    async function loadQuickPrompts() {
        const chips = document.getElementById('neural-quick-chips');
        if (!chips) return;
        chips.innerHTML = '';

        // Evita que uma resposta antiga (ex: troca rápida de página / abrir-fechar o chat)
        // sobrescreva os chips já renderizados por uma chamada mais recente.
        const requestId = ++quickPromptsRequestId;

        try {
            const res = await neuralApi('get_neural_context', { page: currentPage });
            if (requestId !== quickPromptsRequestId) return;
            const prompts = (res && res.prompts) ? res.prompts : getDefaultPrompts(currentPage);

            prompts.forEach(function (text) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'neural-chip';
                btn.textContent = text;
                btn.onclick = function () {
                    const input = document.getElementById('neural-input');
                    if (input) { input.value = text; sendNeuralMessage(); }
                };
                chips.appendChild(btn);
            });
        } catch (e) {
            if (requestId !== quickPromptsRequestId) return;
            getDefaultPrompts(currentPage).forEach(function (text) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'neural-chip';
                btn.textContent = text;
                btn.onclick = function () {
                    const input = document.getElementById('neural-input');
                    if (input) { input.value = text; sendNeuralMessage(); }
                };
                chips.appendChild(btn);
            });
        }
    }

    function getDefaultPrompts(page) {
        const map = {
            home: ['Quais módulos existem?', 'Como começar a cadastrar?'],
            dash: ['Como criar uma O.S.?', 'O que é o Motor Neural?'],
            assets: ['Como cadastrar ativo?', 'Como importar Excel?'],
            routes: ['Como marcar ponto OK?', 'Como reportar alerta?'],
            pi: ['Como usar o simulador?', 'O que é CBM?'],
            calc: ['Como calcular viscosidade?', 'O que é fator DN?'],
            kpi: ['O que é OEE?', 'Como ler os KPIs?'],
            '3d': ['Como carregar modelo 3D?', 'Quais formatos são aceitos?']
        };
        return map[page] || ['Como usar este módulo?', 'Preciso de ajuda'];
    }

    async function neuralApi(action, payload) {
        const url = (window.API_URL || 'api.php') + '?action=' + action;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 30000);
        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload || {}),
                signal: controller.signal
            });
            if (res.status === 401) {
                window.location.href = 'login.php';
                return null;
            }
            const json = await res.json();
            if (!json.ok) return null;
            if (json.data && typeof json.data === 'object' && !Array.isArray(json.data)) {
                return Object.assign({}, json.data, json);
            }
            return json;
        } finally {
            clearTimeout(timer);
        }
    }

    window.toggleNeuralChat = function () {
        const win = document.getElementById('neural-chat-window');
        const trigger = document.getElementById('neural-assistant-trigger');
        if (!win || !trigger) return;

        const isOpen = win.classList.contains('neural-open');
        if (!isOpen) {
            win.style.display = 'flex';
            win.classList.add('neural-open');
            trigger.style.transform = 'scale(0.92)';
            currentPage = getCurrentPage();
            updateContextLabel();
            showWelcomeIfEmpty();
            loadQuickPrompts();
            if (typeof lucide !== 'undefined') lucide.createIcons();
            const input = document.getElementById('neural-input');
            if (input) setTimeout(function () { input.focus(); }, 100);
        } else {
            win.classList.remove('neural-open');
            win.style.display = 'none';
            trigger.style.transform = 'scale(1)';
        }
    };

    function addMessage(role, html, id) {
        const container = document.getElementById('neural-messages');
        if (!container) return null;
        const div = document.createElement('div');
        if (id) div.id = id;
        div.className = role === 'user' ? 'neural-msg-user' : 'neural-msg-ai';
        div.innerHTML = html;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
        return div;
    }

    window.sendNeuralMessage = async function () {
        const input = document.getElementById('neural-input');
        const msg = input ? input.value.trim() : '';
        if (!msg) return;

        currentPage = getCurrentPage();
        addMessage('user', escapeHtml(msg));
        if (input) input.value = '';

        chatHistory.push({ role: 'user', text: msg });
        if (chatHistory.length > 10) chatHistory = chatHistory.slice(-10);

        const loadingId = 'neural-load-' + Date.now();
        addMessage('ai', '<span class="neural-typing-dots">Analisando</span>', loadingId);

        try {
            const payload = {
                prompt: msg,
                page: currentPage,
                history: chatHistory.slice(-4),
                selected_asset: getSelectedAsset(),
                force_ai: !!(document.getElementById('neural-force-ai') && document.getElementById('neural-force-ai').checked)
            };

            let res = null;
            if (typeof api === 'function') {
                res = await api('ask_neural', payload);
            } else {
                res = await neuralApi('ask_neural', payload);
            }

            const loadingEl = document.getElementById(loadingId);
            if (!loadingEl) return;

            let text = null;
            if (res) {
                text = res.text || (res.data && res.data.text) || null;
            }

            if (!text) {
                text = 'Não consegui processar sua pergunta. Tente reformular ou verifique sua conexão.';
            }

            let html = formatResponse(text);
            if (res && res.source === 'local' && res.hint) {
                html += '<br><small style="color:#94a3b8;">' + escapeHtml(res.hint) + '</small>';
            }

            loadingEl.innerHTML = html;
            loadingEl.classList.remove('neural-typing-dots');
            chatHistory.push({ role: 'assistant', text: text });

        } catch (e) {
            const loadingEl = document.getElementById(loadingId);
            if (loadingEl) {
                const isTimeout = e && e.name === 'AbortError';
                loadingEl.innerHTML = isTimeout
                    ? 'A resposta demorou demais. Tente uma pergunta mais simples.'
                    : 'Erro de conexão. Verifique sua internet e tente novamente.';
            }
        }

        const container = document.getElementById('neural-messages');
        if (container) container.scrollTop = container.scrollHeight;
    };

    function onPageChange(page) {
        currentPage = page || getCurrentPage();
        window.__lubtekCurrentPage = currentPage;
        updateContextLabel();
        updateWelcomeMessage();
        // Atualiza chips se chat estiver aberto
        const win = document.getElementById('neural-chat-window');
        if (win && win.classList.contains('neural-open')) {
            loadQuickPrompts();
        }
    }

    function init() {
        if (initialized) return;
        initialized = true;

        currentPage = getCurrentPage();
        window.__lubtekCurrentPage = currentPage;
        updateContextLabel();
        showWelcomeIfEmpty();

        if (window.AppEvents) {
            window.AppEvents.on('view:changed', onPageChange);
            // Sincroniza após o router principal ativar a view correta
            window.AppEvents.once('app:ready', function () {
                onPageChange(getCurrentPage());
            });
        }

        // Fallback: observar mudanças na URL (popstate / SPA)
        window.addEventListener('popstate', function () {
            onPageChange(getCurrentPage());
        });

        // Re-inicializa ícones
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.NeuralAssistant = { onPageChange: onPageChange, getCurrentPage: getCurrentPage };
})();
