/**
 * Painel — Relatório do Agente de Geração de O.S. (rotina)
 */
(function () {
    const ACTION_LABELS = {
        created: 'Gerada',
        would_create: 'Simular: criaria',
        create: 'Criará',
        skip: 'Ignorada',
        not_due: 'Em dia',
    };

    const REASON_LABELS = {
        not_due_yet: 'Ainda no prazo',
        planned_in_future: 'Data futura no cadastro',
        daily_already_queued_or_done: 'Diária já na fila/hoje',
        open_routine_exists: 'O.S. de rotina aberta',
        overdue_backlog: 'Backlog vencido',
        due_today: 'Vence hoje',
        insert_failed: 'Falha ao gravar',
    };

    function esc(s) {
        if (typeof escapeHtml === 'function') return escapeHtml(s);
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function formatRanAt(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString('pt-BR');
        } catch (e) {
            return iso;
        }
    }

    function isGestorPanel() {
        const el = document.getElementById('os-agent-report-section');
        if (!el) return false;
        if (typeof isTrabalhadorUser === 'function' && isTrabalhadorUser()) return false;
        if (typeof isClienteUser === 'function' && isClienteUser()) return false;
        if (typeof isPowerUser === 'function') return isPowerUser();
        return true;
    }

    function renderReport(data) {
        const section = document.getElementById('os-agent-report-section');
        if (!section || !data) return;

        const statsEl = document.getElementById('os-agent-stats');
        const briefEl = document.getElementById('os-agent-briefing');
        const metaEl = document.getElementById('os-agent-meta');
        const tbody = document.getElementById('os-agent-decisions-body');
        const emptyEl = document.getElementById('os-agent-empty');

        const scanned = data.scanned_points ?? 0;
        const due = data.due_points ?? 0;
        const created = data.generated_orders_count ?? 0;
        const skipped = data.skipped_count ?? 0;
        const incomplete = data.cadastro_incomplete_count ?? 0;
        const version = data.agent_version || '—';
        const dry = data.dry_run ? ' (simulação)' : '';

        if (statsEl) {
            statsEl.innerHTML = `
                <span class="os-agent-chip">Pontos: <strong>${scanned}</strong></span>
                <span class="os-agent-chip warn">Vencidos: <strong>${due}</strong></span>
                <span class="os-agent-chip ok">Geradas: <strong>${created}</strong></span>
                <span class="os-agent-chip muted">Ignoradas: <strong>${skipped}</strong></span>
                ${incomplete > 0 ? `<span class="os-agent-chip warn" title="Material ou frequência faltando no cadastro">Cadastro incompleto: <strong>${incomplete}</strong></span>` : ''}
            `;
        }

        if (briefEl) {
            const src = data.ai_briefing_source || 'local';
            const srcLabel = src.includes('gemini') ? 'Lúbria / Gemini' : 'Motor local';
            briefEl.innerHTML = `
                <div class="os-agent-briefing-head">
                    <i data-lucide="sparkles" style="width:14px;"></i>
                    <span>Plano operacional</span>
                    <span class="os-agent-src">${esc(srcLabel)}</span>
                </div>
                <p>${esc(data.ai_briefing || data.insight || 'Execute uma simulação para ver o plano do dia.')}</p>
            `;
        }

        if (metaEl) {
            metaEl.textContent = `Agente v${version}${dry} · ${formatRanAt(data.ran_at)}`;
        }

        if (!tbody) return;

        const rows = (data.decisions || []).filter(d => (d.action || '') !== 'not_due');
        if (rows.length === 0) {
            tbody.innerHTML = '';
            if (emptyEl) emptyEl.style.display = 'block';
            return;
        }
        if (emptyEl) emptyEl.style.display = 'none';

        tbody.innerHTML = rows.slice(0, 40).map(d => {
            const action = d.action || '';
            const actionLabel = ACTION_LABELS[action] || action;
            const reason = REASON_LABELS[d.reason] || d.reason || '—';
            const prio = d.priority || '—';
            const score = d.score != null ? d.score : '—';
            const overdue = d.overdue_days != null ? d.overdue_days : '—';
            const rowClass = action === 'created' || action === 'would_create' ? 'row-highlight' : '';
            return `<tr class="${rowClass}">
                <td>${esc(d.asset_name || '—')}</td>
                <td>${esc(d.tag || '')}</td>
                <td>${esc(d.rota || '—')}</td>
                <td>${esc(d.servico || '—')}</td>
                <td><span class="os-agent-action os-agent-action-${esc(action)}">${esc(actionLabel)}</span></td>
                <td>${esc(prio)}</td>
                <td>${esc(score)}</td>
                <td>${esc(overdue)}</td>
                <td style="font-size:0.75rem;color:var(--text-muted);">${esc(reason)}</td>
            </tr>`;
        }).join('');

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    async function fetchReport(params = {}) {
        if (!isGestorPanel() || typeof api !== 'function') return null;
        try {
            return await api('get_work_order_agent_report', params);
        } catch (e) {
            return null;
        }
    }

    window.loadOsAgentReport = async function loadOsAgentReport(options = {}) {
        const section = document.getElementById('os-agent-report-section');
        if (!section || !isGestorPanel()) return;
        const loading = document.getElementById('os-agent-loading');
        if (loading) loading.style.display = 'flex';

        const params = {};
        if (options.simulate) params.simulate = 1;
        if (options.aiBrief) params.ai_brief = 1;

        const data = await fetchReport(params);
        if (loading) loading.style.display = 'none';

        if (data && data.success !== false) {
            renderReport(data);
        }
    };

    window.simulateOsAgent = function () {
        loadOsAgentReport({ simulate: true });
    };

    window.refreshOsAgentAiBrief = function () {
        loadOsAgentReport({ aiBrief: true });
    };

    window.toggleOsAgentPanel = function () {
        const section = document.getElementById('os-agent-report-section');
        if (!section) return;
        const next = section.classList.toggle('is-collapsed');
        if (window.LubtekA11y && window.LubtekA11y.saveCollapsedPref) {
            window.LubtekA11y.saveCollapsedPref('lubtek_dash_os_agent_collapsed', next);
        }
        if (window.LubtekA11y && window.LubtekA11y.setSectionExpanded) {
            window.LubtekA11y.setSectionExpanded('os-agent-report-section', 'os-agent-toggle', next);
        } else {
            const btn = document.getElementById('os-agent-toggle');
            if (btn) btn.textContent = next ? 'Expandir' : 'Recolher';
        }
    };

    window.runOsAgentAndRefresh = async function () {
        if (typeof generateScheduledOrders === 'function') {
            await generateScheduledOrders();
        }
        await loadOsAgentReport({});
        if (typeof loadAdminOsOverview === 'function') loadAdminOsOverview(true);
    };

    function formatOverviewDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString('pt-BR');
        } catch (e) {
            return iso;
        }
    }

    window.loadAdminOsOverview = async function loadAdminOsOverview(silent) {
        const section = document.getElementById('admin-os-overview-section');
        const tbody = document.getElementById('admin-os-overview-body');
        if (!section || !tbody) return;
        if (typeof isDeveloperUser === 'function' && !isDeveloperUser()) {
            section.style.display = 'none';
            return;
        }
        if (!silent) {
            tbody.innerHTML = '<tr><td colspan="6">Carregando panorama…</td></tr>';
        }
        try {
            const data = await api('get_tenant_os_overview', {});
            const rows = (data && data.tenants) ? data.tenants : (Array.isArray(data) ? data : []);
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6">Nenhuma empresa encontrada.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(function (r) {
                if (r.error) {
                    return `<tr><td>${esc(r.label || r.tenant_key)}</td><td colspan="5">${esc(r.error)}</td></tr>`;
                }
                const cur = r.is_current ? '<span class="tag-current">sessão atual</span>' : '';
                const agent = formatOverviewDate(r.agent_ran_at);
                const gen = `${r.agent_generated ?? 0} / ${r.agent_due ?? 0} venc.`;
                return `<tr class="${r.is_current ? 'is-current' : ''}">
                    <td><strong>${esc(r.label || r.tenant_key || '—')}</strong>${cur}</td>
                    <td>${esc(r.pending_os ?? 0)}</td>
                    <td>${esc(r.critical_os ?? 0)}</td>
                    <td>${esc(r.done_today ?? 0)}</td>
                    <td>${esc(agent)}${r.agent_version ? ' · v' + esc(r.agent_version) : ''}</td>
                    <td>${esc(gen)} · ${esc(r.agent_scanned ?? 0)} pts</td>
                </tr>`;
            }).join('');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="6">Não foi possível carregar o panorama administrativo.</td></tr>';
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            if (typeof loadAdminOsOverview === 'function') loadAdminOsOverview(true);
        }, 1200);
    });
})();
