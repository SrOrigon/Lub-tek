<?php
// Protection: Only allow including this file through index.php
if (!defined('BUILD_VER') && !isset($auth)) {
    http_response_code(403);
    die('Acesso negado.');
}

// Access Guard
$auth->requirePermission('audit_logs');
?>
<div id="view-audit" class="view-container active view-flex" style="padding:20px; flex-direction:column; gap:20px; background:#f8fafc;">
    
    <!-- TOP TOOLBAR -->
    <div style="display:flex; justify-content:space-between; align-items:center; background:white; padding:16px 20px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 12px rgba(15,23,42,0.02); flex-wrap:wrap; gap:16px;">
        <div style="display:flex; flex-direction:column;">
            <h1 style="font-size:1.5rem; margin:0; font-weight:800; color:#0f172a; letter-spacing:-0.5px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="shield-check" style="color:#0284c7; width:26px; height:26px;"></i>
                Auditoria & Compliance
            </h1>
            <span style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px;">Rastreamento e segurança de dados</span>
        </div>
        
        <!-- Action Buttons -->
        <div style="display:flex; gap:10px;">
            <button class="btn btn-sm btn-outline" onclick="loadAuditLogs()" style="border-radius:10px; height:42px; display:flex; align-items:center; gap:6px;">
                <i data-lucide="refresh-cw" style="width:16px;"></i> Atualizar
            </button>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div style="background:white; padding:20px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 12px rgba(15,23,42,0.02); display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
        <div style="display:flex; flex-direction:column; gap:6px; flex:1; min-width:180px;">
            <label style="font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase;">Usuário</label>
            <input type="text" id="audit-filter-user" placeholder="Filtrar por nome..." oninput="debounceAuditSearch()" style="padding:10px 14px; border:1px solid #cbd5e1; border-radius:10px; font-size:0.9rem; width:100%; height:42px;">
        </div>
        <div style="display:flex; flex-direction:column; gap:6px; width:160px; min-width:140px;">
            <label style="font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase;">Data Inicial</label>
            <input type="date" id="audit-filter-start" onchange="loadAuditLogs()" style="padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:0.9rem; width:100%; height:42px;">
        </div>
        <div style="display:flex; flex-direction:column; gap:6px; width:160px; min-width:140px;">
            <label style="font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase;">Data Final</label>
            <input type="date" id="audit-filter-end" onchange="loadAuditLogs()" style="padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:0.9rem; width:100%; height:42px;">
        </div>
        <button class="btn btn-sm btn-outline" onclick="clearAuditFilters()" style="height:42px; border-radius:10px; color:#ef4444; border-color:#fca5a5; display:flex; align-items:center; gap:4px; font-weight:600; padding:0 15px;">
            Limpar
        </button>
    </div>

    <!-- LOGS DATA PANEL -->
    <div class="card" style="padding:0; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 15px rgba(15,23,42,0.03); display:flex; flex-direction:column; flex:1; overflow:hidden; background:#fff;">
        <div style="background:#f8fafc; padding:12px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Registros de Auditoria (Últimos 500)</span>
            <span id="audit-count-badge" style="background:#e0f2fe; color:#0369a1; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:700;">0 itens</span>
        </div>
        
        <div style="overflow-x:auto; flex:1; min-height:350px;">
            <table class="lub-table" style="width:100%; min-width:850px; border-collapse:collapse; text-align:left;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:14px 20px; font-size:0.8rem; font-weight:700; color:#475569; width:100px;">Data/Hora</th>
                        <th style="padding:14px 20px; font-size:0.8rem; font-weight:700; color:#475569; width:150px;">Usuário</th>
                        <th style="padding:14px 20px; font-size:0.8rem; font-weight:700; color:#475569; width:120px;">Ação</th>
                        <th style="padding:14px 20px; font-size:0.8rem; font-weight:700; color:#475569; width:180px;">Alvo</th>
                        <th style="padding:14px 20px; font-size:0.8rem; font-weight:700; color:#475569;">Detalhes / Alterações</th>
                    </tr>
                </thead>
                <tbody id="audit-logs-tbody">
                    <tr>
                        <td colspan="5" style="padding:40px; text-align:center; color:#94a3b8; font-size:0.9rem;">
                            <div style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                                <div style="width:30px; height:30px; border:3px solid #e2e8f0; border-top:3px solid #0284c7; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
                                Carregando dados de auditoria...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- AUDIT DETAIL MODAL -->
<div id="audit-detail-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.4); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border:1px solid #e2e8f0; max-width:700px; width:90%; border-radius:24px; padding:30px; box-shadow:0 20px 40px rgba(0,0,0,0.15); display:flex; flex-direction:column; gap:20px; font-family:'Inter',sans-serif; max-height:85vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.3rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i data-lucide="shield-check" style="color:#0284c7; width:22px; height:22px;"></i>
                Detalhes da Alteração
            </h3>
            <button type="button" style="background:none; border:none; color:#94a3b8; cursor:pointer;" onclick="closeAuditModal()">
                <i data-lucide="x" style="width:20px; height:20px;"></i>
            </button>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; background:#f8fafc; padding:16px; border-radius:12px; border:1px solid #e2e8f0; font-size:0.85rem;">
            <div><strong>Usuário:</strong> <span id="audit-modal-user"></span></div>
            <div><strong>Data:</strong> <span id="audit-modal-date"></span></div>
            <div><strong>Ação:</strong> <span id="audit-modal-action" style="padding:2px 8px; border-radius:6px; font-weight:700; font-size:0.75rem;"></span></div>
            <div><strong>Alvo:</strong> <span id="audit-modal-target"></span></div>
        </div>

        <!-- Diff Viewer -->
        <div style="display:flex; flex-direction:column; gap:12px; flex:1;">
            <div id="audit-modal-diff-container" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <span style="font-size:0.75rem; font-weight:700; color:#ef4444; text-transform:uppercase; display:flex; align-items:center; gap:4px;"><i data-lucide="minus" style="width:12px;"></i> Estado Anterior</span>
                    <pre id="audit-modal-old-state" style="background:#fef2f2; border:1px solid #fca5a5; padding:12px; border-radius:10px; font-family:monospace; font-size:0.8rem; overflow-x:auto; margin:0; max-height:250px; color:#991b1b; white-space:pre-wrap;"></pre>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <span style="font-size:0.75rem; font-weight:700; color:#10b981; text-transform:uppercase; display:flex; align-items:center; gap:4px;"><i data-lucide="plus" style="width:12px;"></i> Novo Estado</span>
                    <pre id="audit-modal-new-state" style="background:#ecfdf5; border:1px solid #a7f3d0; padding:12px; border-radius:10px; font-family:monospace; font-size:0.8rem; overflow-x:auto; margin:0; max-height:250px; color:#065f46; white-space:pre-wrap;"></pre>
                </div>
            </div>
            
            <div id="audit-modal-error-container" style="display:none; flex-direction:column; gap:6px;">
                <span style="font-size:0.75rem; font-weight:700; color:#ef4444; text-transform:uppercase;">Detalhes do Erro</span>
                <pre id="audit-modal-error-content" style="background:#fef2f2; border:1px solid #fca5a5; padding:12px; border-radius:10px; font-family:monospace; font-size:0.8rem; overflow-x:auto; margin:0; color:#991b1b; white-space:pre-wrap;"></pre>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:10px;">
            <button type="button" onclick="closeAuditModal()" style="padding:10px 20px; border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:10px; cursor:pointer; font-weight:600; font-size:0.9rem;">Fechar</button>
        </div>
    </div>
</div>

<script>
    let auditSearchTimeout;
    let auditLoadInFlight = false;
    window.loadAuditLogs = loadAuditLogs;

    function auditEscapeHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAuditLogs);
    } else {
        loadAuditLogs();
    }

    function debounceAuditSearch() {
        clearTimeout(auditSearchTimeout);
        auditSearchTimeout = setTimeout(loadAuditLogs, 400);
    }

    function clearAuditFilters() {
        document.getElementById('audit-filter-user').value = '';
        document.getElementById('audit-filter-start').value = '';
        document.getElementById('audit-filter-end').value = '';
        loadAuditLogs();
    }

    async function loadAuditLogs() {
        const tbody = document.getElementById('audit-logs-tbody');
        const badge = document.getElementById('audit-count-badge');
        if (!tbody || auditLoadInFlight) return;
        auditLoadInFlight = true;
        
        const user = document.getElementById('audit-filter-user').value.trim();
        const start = document.getElementById('audit-filter-start').value;
        const end = document.getElementById('audit-filter-end').value;

        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="padding:40px; text-align:center; color:#94a3b8; font-size:0.9rem;">
                    <div style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                        <div style="width:30px; height:30px; border:3px solid #e2e8f0; border-top:3px solid #0284c7; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
                        Filtrando registros...
                    </div>
                </td>
            </tr>
        `;

        try {
            const res = await api('get_audit_logs', {
                user: user,
                start_date: start,
                end_date: end
            });

            if (!res || !res.ok) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="padding:30px; text-align:center; color:#ef4444; font-weight:600;">
                            Falha ao carregar registros de auditoria: ${auditEscapeHtml(res?.error || 'Erro desconhecido')}
                        </td>
                    </tr>
                `;
                badge.textContent = '0 itens';
                return;
            }

            const data = res.data || res.logs || [];
            badge.textContent = `${data.length} itens`;

            if (data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="padding:40px; text-align:center; color:#94a3b8; font-size:0.9rem;">
                            Nenhum registro de auditoria encontrado para os filtros selecionados.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = '';
            data.forEach(log => {
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid #f1f5f9';
                tr.style.cursor = 'pointer';
                tr.className = 'table-row-hover';
                
                let actionBadgeColor = 'background:#f1f5f9; color:#475569;';
                const action = (log.acao || '').toUpperCase();
                if (action.includes('CREATE') || action.includes('INSERT') || action.includes('ADD') || action.includes('SEED')) {
                    actionBadgeColor = 'background:#ecfdf5; color:#059669;';
                } else if (action.includes('UPDATE') || action.includes('EDIT') || action.includes('MOD')) {
                    actionBadgeColor = 'background:#fef3c7; color:#d97706;';
                } else if (action.includes('DELETE') || action.includes('REMOVE') || action.includes('WIPE')) {
                    actionBadgeColor = 'background:#fef2f2; color:#dc2626;';
                } else if (action.includes('ERROR') || action.includes('FAIL')) {
                    actionBadgeColor = 'background:#fff5f5; color:#e53e3e; border:1px solid #feb2b2;';
                }

                let dateStr = log.data;
                try {
                    const d = new Date(log.data);
                    if (!isNaN(d.getTime())) {
                        dateStr = d.toLocaleString('pt-BR');
                    }
                } catch(e) {}

                let previewText = 'Ver detalhes da transação';
                if (log.dados_novos && log.dados_novos !== 'null' && log.dados_novos !== '""') {
                    try {
                        const parsed = JSON.parse(log.dados_novos);
                        if (parsed && typeof parsed === 'object') {
                            previewText = Object.keys(parsed).slice(0, 3).map(k => `${k}: ${typeof parsed[k] === 'object' ? '...' : parsed[k]}`).join(', ');
                            if (previewText.length > 80) previewText = previewText.substring(0, 80) + '...';
                        }
                    } catch(e) {}
                } else if (log.detalhes_erro) {
                    previewText = log.detalhes_erro.substring(0, 80);
                }

                tr.innerHTML = `
                    <td style="padding:14px 20px; font-size:0.85rem; color:#64748b; font-weight:500;">${auditEscapeHtml(dateStr)}</td>
                    <td style="padding:14px 20px; font-size:0.9rem; color:#0f172a; font-weight:600;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <i data-lucide="user" style="width:14px; color:#94a3b8;"></i>
                            <span>${auditEscapeHtml(log.usuario)}</span>
                        </div>
                    </td>
                    <td style="padding:14px 20px; font-size:0.85rem;">
                        <span style="padding:4px 8px; border-radius:6px; font-weight:700; font-size:0.72rem; text-transform:uppercase; ${actionBadgeColor}">
                            ${auditEscapeHtml(log.acao)}
                        </span>
                    </td>
                    <td style="padding:14px 20px; font-size:0.85rem; color:#475569; font-weight:600;">${auditEscapeHtml(log.alvo)}</td>
                    <td style="padding:14px 20px; font-size:0.8rem; color:#94a3b8; font-style:italic; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        ${auditEscapeHtml(previewText)}
                    </td>
                `;

                tr.onclick = () => openAuditModal(log);
                tbody.appendChild(tr);
            });
            
            if (typeof lucide !== 'undefined') lucide.createIcons();

        } catch (e) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="padding:30px; text-align:center; color:#ef4444; font-weight:600;">
                        Erro ao conectar na API: ${auditEscapeHtml(e.message)}
                    </td>
                </tr>
            `;
        } finally {
            auditLoadInFlight = false;
        }
    }

    function openAuditModal(log) {
        document.getElementById('audit-modal-user').textContent = log.usuario;
        
        let dateStr = log.data;
        try {
            const d = new Date(log.data);
            if (!isNaN(d.getTime())) dateStr = d.toLocaleString('pt-BR');
        } catch(e) {}
        document.getElementById('audit-modal-date').textContent = dateStr;
        
        const actionSpan = document.getElementById('audit-modal-action');
        actionSpan.textContent = log.acao;
        actionSpan.className = '';
        
        const action = (log.acao || '').toUpperCase();
        if (action.includes('CREATE') || action.includes('INSERT') || action.includes('ADD')) {
            actionSpan.style.background = '#ecfdf5';
            actionSpan.style.color = '#059669';
        } else if (action.includes('UPDATE') || action.includes('EDIT')) {
            actionSpan.style.background = '#fef3c7';
            actionSpan.style.color = '#d97706';
        } else if (action.includes('DELETE') || action.includes('REMOVE')) {
            actionSpan.style.background = '#fef2f2';
            actionSpan.style.color = '#dc2626';
        } else {
            actionSpan.style.background = '#f1f5f9';
            actionSpan.style.color = '#475569';
        }

        document.getElementById('audit-modal-target').textContent = log.alvo;

        const diffContainer = document.getElementById('audit-modal-diff-container');
        const errContainer = document.getElementById('audit-modal-error-container');
        const oldState = document.getElementById('audit-modal-old-state');
        const newState = document.getElementById('audit-modal-new-state');
        const errContent = document.getElementById('audit-modal-error-content');

        if (log.detalhes_erro) {
            diffContainer.style.display = 'none';
            errContainer.style.display = 'flex';
            errContent.textContent = log.detalhes_erro;
        } else {
            diffContainer.style.display = 'grid';
            errContainer.style.display = 'none';
            
            oldState.textContent = formatJSON(log.dados_antigos);
            newState.textContent = formatJSON(log.dados_novos);
        }

        document.getElementById('audit-detail-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeAuditModal() {
        document.getElementById('audit-detail-modal').style.display = 'none';
    }

    function formatJSON(str) {
        if (!str || str === 'null' || str === '""') return 'Nenhum dado';
        try {
            const parsed = JSON.parse(str);
            return JSON.stringify(parsed, null, 2);
        } catch (e) {
            return str;
        }
    }
</script>

<style>
    .table-row-hover:hover {
        background-color: #f8fafc !important;
    }
    #audit-logs-tbody td {
        vertical-align: middle;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
