/**
 * LUB-TEK OS Manager & Neural Interactor
 * Lúbria apenas sugere O.S. — criação exige aceite manual do gestor.
 */

const NEURAL_REJECTED_KEY = 'lubria_rejected_suggestions';

// Nota: escapeHtml/escapeAttr já são definidos globalmente em scripts_main.js
// (que carrega antes deste arquivo). Não redeclarar aqui — declarações de função
// no topo deste script sobrescreveriam window.escapeHtml/escapeAttr para todo o
// resto do app, causando escaping inconsistente em outras telas.

function formatNeuralText(text) {
    let f = escapeHtml(text);
    f = f.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    f = f.replace(/\n\n/g, '<br><br>');
    f = f.replace(/\n/g, '<br>');
    return f;
}

function parseNeuralResponse(data) {
    if (!data) return { insights: [], message: null };
    if (Array.isArray(data.insights)) return data;
    if (data.data && Array.isArray(data.data.insights)) return data.data;
    if (data.ok && data.data) return parseNeuralResponse(data.data);
    return { insights: [], message: data.message || data.error || null };
}

function getRejectedNeuralSuggestions() {
    try {
        const raw = sessionStorage.getItem(NEURAL_REJECTED_KEY);
        const list = raw ? JSON.parse(raw) : [];
        return Array.isArray(list) ? list.map(Number) : [];
    } catch (e) {
        return [];
    }
}

function rejectNeuralSuggestion(assetId) {
    const id = parseInt(assetId, 10);
    if (!id) return;
    const list = getRejectedNeuralSuggestions();
    if (!list.includes(id)) {
        list.push(id);
        sessionStorage.setItem(NEURAL_REJECTED_KEY, JSON.stringify(list));
    }
}

async function fetchWithTimeout(url, opts, ms) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), ms);
    try {
        return await fetch(url, Object.assign({}, opts || {}, { signal: controller.signal }));
    } finally {
        clearTimeout(timer);
    }
}

function setInsightsLoading(msg) {
    const container = document.getElementById('neural-insights-container');
    if (!container) return;
    container.innerHTML = `<div id="neural-insights-placeholder" class="neural-dash-placeholder">${escapeHtml(msg)}</div>`;
}

function buildSuggestionActions(insight) {
    if (insight.risk === 'error') return '';
    if (typeof isTrabalhadorUser === 'function' && isTrabalhadorUser()) return '';

    if (insight.has_pending_os) {
        return `<div style="padding-top:4px;">
            <button class="btn btn-sm" disabled
                style="width:100%;padding:4px;font-size:0.68rem;border-radius:6px;background:#64748b;display:flex;justify-content:center;gap:4px;border:none;color:white;font-weight:700;opacity:0.9;">
                <i data-lucide="clipboard-check" style="width:12px"></i> O.S. já pendente
            </button></div>`;
    }

    return `<div class="neural-suggestion-actions">
        <button type="button" class="btn-neural-accept btn btn-sm"
            data-asset-id="${insight.asset_id}"
            data-suggestion="${escapeAttr(insight.suggestion)}"
            data-risk="${escapeAttr(insight.risk)}"
            style="flex:1;border-radius:8px;background:var(--success);display:flex;justify-content:center;align-items:center;gap:4px;border:none;color:white;cursor:pointer;font-weight:700;">
            <i data-lucide="check" style="width:12px"></i> Aceitar
        </button>
        <button type="button" class="btn-neural-reject btn btn-sm"
            data-asset-id="${insight.asset_id}"
            style="flex:1;border-radius:8px;background:#fff;display:flex;justify-content:center;align-items:center;gap:4px;border:1px solid var(--border);color:var(--text-muted);cursor:pointer;font-weight:700;">
            <i data-lucide="x" style="width:12px"></i> Recusar
        </button>
    </div>`;
}

let neuralInsightsRequestId = 0;

async function loadNeuralInsights() {
    const container = document.getElementById('neural-insights-container');
    if (!container) return;

    // Evita que uma requisição antiga (ex: clique duplo em "Atualizar Análise")
    // sobrescreva o resultado de uma requisição mais recente ao responder fora de ordem.
    const requestId = ++neuralInsightsRequestId;

    setInsightsLoading('Consultando Motor Neural...');

    try {
        let raw = null;

        if (typeof api === 'function') {
            raw = await api('neural_predict');
        } else {
            const response = await fetchWithTimeout('api.php?action=neural_predict', { credentials: 'same-origin' }, 25000);
            if (response.status === 401) {
                window.location.href = 'login.php';
                return;
            }
            raw = await response.json();
        }

        if (requestId !== neuralInsightsRequestId) return;

        const data = parseNeuralResponse(raw);
        const rejected = getRejectedNeuralSuggestions();
        const list = (data.insights || []).filter(insight => {
            const id = parseInt(insight.asset_id, 10);
            return !id || !rejected.includes(id);
        });

        if (list.length > 0) {
            container.innerHTML = '';

            list.forEach(insight => {
                let riskColor = 'var(--success)';
                let riskBg = '#d1fae5';

                if (insight.risk === 'alto') { riskColor = 'var(--danger)'; riskBg = '#fee2e2'; }
                else if (insight.risk === 'medio') { riskColor = 'var(--warning)'; riskBg = '#fef3c7'; }
                else if (insight.risk === 'error') { riskColor = 'var(--text-muted)'; riskBg = '#f1f5f9'; }

                const card = document.createElement('div');
                card.className = 'neural-suggestion-card';
                card.dataset.assetId = String(insight.asset_id || '');
                card.style.borderLeft = `4px solid ${riskColor}`;
                card.innerHTML = `
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px;">
                        <h4 style="margin:0;font-size:0.78rem;font-weight:800;">${escapeHtml(insight.nome)}</h4>
                        <span style="background:${riskBg};color:${riskColor};padding:1px 6px;border-radius:6px;font-size:0.62rem;font-weight:800;text-transform:uppercase;white-space:nowrap;">${insight.risk === 'error' ? 'Falha' : 'Risco ' + insight.risk}</span>
                    </div>
                    <p style="margin:0;font-size:0.72rem;color:var(--text-muted);">${escapeHtml(insight.suggestion)}</p>
                    ${buildSuggestionActions(insight)}`;
                container.appendChild(card);
            });

            container.querySelectorAll('.btn-neural-accept').forEach(btn => {
                btn.addEventListener('click', () => generateNeuralOS(
                    parseInt(btn.dataset.assetId, 10), btn.dataset.suggestion, btn.dataset.risk, false
                ));
            });

            container.querySelectorAll('.btn-neural-reject').forEach(btn => {
                btn.addEventListener('click', () => {
                    const assetId = parseInt(btn.dataset.assetId, 10);
                    rejectNeuralSuggestion(assetId);
                    const card = btn.closest('.neural-suggestion-card');
                    if (card) card.remove();
                    if (!container.querySelector('.neural-suggestion-card')) {
                        container.innerHTML = `<div class="neural-dash-placeholder">Nenhuma sugestão pendente de aprovação.</div>`;
                        updateDashNeuralCount(0);
                    } else {
                        updateDashNeuralCount(container.querySelectorAll('.neural-suggestion-card').length);
                    }
                    if (typeof showToast === 'function') {
                        showToast('Sugestão recusada. Nenhuma O.S. foi criada.', 'info');
                    }
                });
            });

            if (typeof lucide !== 'undefined') lucide.createIcons();
            updateDashNeuralCount(list.length);
        } else {
            const msg = data.message || 'Nenhuma sugestão pendente.';
            container.innerHTML = `<div class="neural-dash-placeholder">${escapeHtml(msg)}</div>`;
            updateDashNeuralCount(0);
        }
    } catch (err) {
        if (requestId !== neuralInsightsRequestId) return;
        const isTimeout = err && err.name === 'AbortError';
        container.innerHTML = `<div class="neural-dash-placeholder" style="color:#991b1b;">
            ${isTimeout ? 'Motor Neural demorou. Clique em Atualizar.' : 'Falha ao consultar o Motor Neural.'}
        </div>`;
        updateDashNeuralCount(0);
    }
}

/** Campo de texto do dashboard — pergunta ao Motor Neural */
async function sendDashNeuralQuery() {
    const input = document.getElementById('neural-dash-input');
    const responseBox = document.getElementById('neural-dash-response');
    const sendBtn = document.getElementById('neural-dash-send-btn');
    if (!input || !responseBox) return;

    const msg = input.value.trim();
    if (!msg) {
        input.focus();
        if (typeof showToast === 'function') showToast('Digite sua pergunta no campo acima.', 'warning');
        return;
    }

    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.style.opacity = '0.7';
    }
    responseBox.style.display = 'block';
    responseBox.innerHTML = '<span style="color:#64748b;">Analisando sua pergunta...</span>';

    try {
        const payload = {
            prompt: msg,
            page: 'dash',
            history: [],
            selected_asset: (window.editingNode && window.editingNode.nome) ? window.editingNode.nome : null
        };

        let res = null;
        if (typeof api === 'function') {
            res = await api('ask_neural', payload);
        } else {
            const response = await fetchWithTimeout('api.php?action=ask_neural', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, 30000);
            res = await response.json();
        }

        const text = (res && (res.text || (res.data && res.data.text))) || null;
        if (text) {
            responseBox.innerHTML = formatNeuralText(text);
            if (res.source === 'local' && res.hint) {
                responseBox.innerHTML += '<br><small style="color:#94a3b8;">' + escapeHtml(res.hint) + '</small>';
            }
        } else {
            responseBox.innerHTML = '<span style="color:#991b1b;">Não consegui processar. Tente reformular ou use o botão AI no canto inferior direito.</span>';
        }
    } catch (e) {
        const isTimeout = e && e.name === 'AbortError';
        responseBox.innerHTML = isTimeout
            ? '<span style="color:#991b1b;">Demorou demais. Tente uma pergunta mais curta.</span>'
            : '<span style="color:#991b1b;">Erro de conexão. Verifique sua internet.</span>';
    } finally {
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.style.opacity = '1';
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

window.loadNeuralInsights = loadNeuralInsights;
window.sendDashNeuralQuery = sendDashNeuralQuery;

window.generateNeuralOS = async function (assetId, suggestion, risk, isSilentMode = false) {
    // Criação automática desabilitada: só cria O.S. com aceite explícito do gestor.
    if (isSilentMode) return;
    if (!confirm('Aceitar esta sugestão da Lúbria e criar a O.S. preditiva?')) return;

    try {
        if (typeof showToast === 'function') showToast('Criando O.S. a partir da sugestão...', 'info');

        const payload = { asset_id: assetId, suggestion: suggestion, risk: risk };
        let data = null;

        if (typeof api === 'function') {
            data = await api('neural_generate_os', payload);
        } else {
            const response = await fetch('api.php?action=neural_generate_os', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            data = await response.json();
        }

        if (data && (data.ok !== false) && (data.success || data.os_id || data.message)) {
            if (typeof showToast === 'function') showToast(data.message || 'O.S. criada com sucesso!', 'success');
            if (typeof loadDash === 'function') loadDash();
            loadNeuralInsights();
        } else {
            if (typeof showToast === 'function') showToast((data && data.error) || 'Erro ao gerar OS', 'error');
        }
    } catch (err) {
        if (typeof showToast === 'function') showToast('Erro de rede ao gerar OS.', 'error');
    }
};

function isDashActive() {
    const dash = document.getElementById('view-dash');
    return dash && dash.classList.contains('active');
}

function updateDashNeuralCount(n) {
    const el = document.getElementById('neural-dash-count');
    if (!el) return;
    const c = Number(n) || 0;
    el.textContent = c === 0 ? '· sem sugestões' : (c === 1 ? '· 1 sugestão' : '· ' + c + ' sugestões');
}

function applyDashNeuralCollapsed(collapsed) {
    const section = document.getElementById('neural-insights-section');
    const btn = document.getElementById('neural-dash-toggle');
    if (!section) return;
    section.classList.toggle('is-collapsed', !!collapsed);
    if (btn) btn.textContent = collapsed ? 'Mostrar' : 'Recolher';
}

function toggleDashNeuralStrip() {
    const section = document.getElementById('neural-insights-section');
    const next = !(section && section.classList.contains('is-collapsed'));
    try { sessionStorage.setItem('dash_neural_collapsed', next ? '1' : '0'); } catch (e) { /* ignore */ }
    applyDashNeuralCollapsed(next);
}
window.toggleDashNeuralStrip = toggleDashNeuralStrip;

function initNeuralDashboard() {
    try {
        applyDashNeuralCollapsed(sessionStorage.getItem('dash_neural_collapsed') === '1');
    } catch (e) { /* ignore */ }

    if (window.AppEvents) {
        window.AppEvents.on('view:changed', function (viewId) {
            if (viewId === 'dash') {
                loadNeuralInsights();
            }
        });
    }

    if (isDashActive()) {
        loadNeuralInsights();
    }

    setTimeout(function () {
        const ph = document.getElementById('neural-insights-placeholder');
        if (ph && isDashActive()) {
            loadNeuralInsights();
        }
    }, 2000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNeuralDashboard);
} else {
    initNeuralDashboard();
}

window.addEventListener('load', function () {
    if (isDashActive()) loadNeuralInsights();
});
