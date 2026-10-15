/**
 * Painel gestor — telemetria IA + feedback dos agentes.
 */
(function () {
    'use strict';

    function esc(s) {
        if (typeof escapeHtml === 'function') return escapeHtml(s);
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    async function fetchStats() {
        if (typeof api === 'function') {
            const usage = await api('get_ai_usage_summary', { days: 7 });
            const fb = await api('get_agent_feedback_stats', { days: 30 });
            return { usage, fb };
        }
        if (typeof LubtekApi !== 'undefined') {
            const usage = await LubtekApi.post('get_ai_usage_summary', { days: 7 });
            const fb = await LubtekApi.post('get_agent_feedback_stats', { days: 30 });
            return { usage, fb };
        }
        return null;
    }

    function renderUsage(container, raw) {
        const s = raw.summary || raw;
        if (!s || !container) return;
        const budgetWarn = s.budget_daily > 0 && s.budget_used_percent >= 85;
        container.innerHTML = `
            <div class="ai-ops-grid">
                <div class="ai-ops-metric"><span>Chamadas (7d)</span><strong>${esc(s.calls)}</strong></div>
                <div class="ai-ops-metric"><span>Tokens (7d)</span><strong>${esc(s.tokens_total)}</strong></div>
                <div class="ai-ops-metric"><span>Cache hit</span><strong>${esc(s.cache_hit_rate)}%</strong></div>
                <div class="ai-ops-metric${budgetWarn ? ' warn' : ''}"><span>Hoje / budget</span><strong>${esc(s.tokens_today)} / ${esc(s.budget_daily)} (${esc(s.budget_used_percent)}%)</strong></div>
            </div>
            <ul class="ai-ops-features">${(s.by_feature || []).map(function (f) {
                return '<li><code>' + esc(f.feature) + '</code> — ' + esc(f.tokens) + ' tok (' + esc(f.calls) + 'x)</li>';
            }).join('') || '<li class="muted">Sem uso registrado ainda.</li>'}</ul>`;
    }

    function renderFeedback(container, raw) {
        const s = raw.stats || raw;
        if (!s || !container) return;
        const rate = s.accept_rate != null ? s.accept_rate + '% aceite' : 'sem dados';
        let agentsHtml = '';
        Object.keys(s.by_agent || {}).forEach(function (agent) {
            const a = s.by_agent[agent];
            agentsHtml += '<li><strong>' + esc(agent) + '</strong>: +' + esc(a.accept) + ' / −' + esc(a.reject) + '</li>';
        });
        container.innerHTML = `
            <div class="ai-ops-grid">
                <div class="ai-ops-metric ok"><span>Aceites (30d)</span><strong>${esc(s.accept)}</strong></div>
                <div class="ai-ops-metric danger"><span>Recusas (30d)</span><strong>${esc(s.reject)}</strong></div>
                <div class="ai-ops-metric"><span>Taxa</span><strong>${esc(rate)}</strong></div>
            </div>
            <ul class="ai-ops-features">${agentsHtml || '<li class="muted">Nenhum feedback ainda.</li>'}</ul>`;
    }

    async function loadAiOpsPanel() {
        const section = document.getElementById('ai-ops-panel-section');
        if (!section || section.offsetParent === null) return;
        const usageEl = document.getElementById('ai-ops-usage');
        const fbEl = document.getElementById('ai-ops-feedback');
        if (usageEl) usageEl.innerHTML = '<span class="muted">Carregando…</span>';
        try {
            const data = await fetchStats();
            if (!data) return;
            renderUsage(usageEl, data.usage);
            renderFeedback(fbEl, data.fb);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } catch (e) {
            if (usageEl) usageEl.innerHTML = '<span class="muted">Indisponível</span>';
        }
    }

    window.loadAiOpsPanel = loadAiOpsPanel;

    window.toggleAiOpsPanel = function () {
        const section = document.getElementById('ai-ops-panel-section');
        if (!section) return;
        const next = section.classList.toggle('is-collapsed');
        if (window.LubtekA11y && window.LubtekA11y.saveCollapsedPref) {
            window.LubtekA11y.saveCollapsedPref('lubtek_dash_ai_ops_collapsed', next);
        }
        if (window.LubtekA11y && window.LubtekA11y.setSectionExpanded) {
            window.LubtekA11y.setSectionExpanded('ai-ops-panel-section', 'ai-ops-toggle', next);
        } else {
            const btn = document.getElementById('ai-ops-toggle');
            if (btn) btn.textContent = next ? 'Expandir' : 'Recolher';
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(loadAiOpsPanel, 800);
    });
})();
