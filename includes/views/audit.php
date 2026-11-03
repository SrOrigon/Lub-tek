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
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="btn btn-sm btn-outline" onclick="loadAuditLogs()" style="border-radius:10px; height:42px; display:flex; align-items:center; gap:6px;">
                <i data-lucide="refresh-cw" style="width:16px;"></i> Atualizar
            </button>
            <button class="btn btn-sm" onclick="purgeAuditHistory()" style="border-radius:10px; height:42px; display:flex; align-items:center; gap:6px; background:#fff1f2; color:#e11d48; border:1px solid #fecdd3; font-weight:700;" title="Apaga apenas registros anteriores a 30 dias. O último mês permanece 100% preservado.">
                <i data-lucide="trash-2" style="width:16px;"></i> Limpar Histórico (> 30 dias)
            </button>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div style="background:white; padding:20px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 12px rgba(15,23,42,0.02); display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
        <div style="display:flex; flex-direction:column; gap:6px; flex:1; min-width:180px;">
            <label style="font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase;">Funcionário / Usuário</label>
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
        <button class="btn btn-sm btn-outline" onclick="clearAuditFilters()" style="height:42px; border-radius:10px; color:#475569; border-color:#cbd5e1; display:flex; align-items:center; gap:6px; font-weight:600; padding:0 16px;">
            <i data-lucide="filter-x" style="width:14px;"></i> Redefinir Filtros
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

<!-- AUDIT DETAIL MODAL (HUMANIZADO, SEM CÓDIGOS DE PROGRAMAÇÃO) -->
<div id="audit-detail-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.4); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border:1px solid #e2e8f0; max-width:720px; width:92%; border-radius:24px; padding:28px; box-shadow:0 20px 40px rgba(0,0,0,0.15); display:flex; flex-direction:column; gap:18px; font-family:'Inter',sans-serif; max-height:88vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.25rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i data-lucide="shield-check" style="color:#0284c7; width:24px; height:24px;"></i>
                Registro de Atividade do Colaborador
            </h3>
            <button type="button" style="background:none; border:none; color:#94a3b8; cursor:pointer;" onclick="closeAuditModal()">
                <i data-lucide="x" style="width:20px; height:20px;"></i>
            </button>
        </div>

        <!-- Badges de Identificação -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:12px; background:#f8fafc; padding:16px; border-radius:14px; border:1px solid #e2e8f0; font-size:0.85rem;">
            <div>
                <span style="font-size:0.7rem; color:#64748b; text-transform:uppercase; font-weight:700; display:block;">Funcionário</span>
                <span id="audit-modal-user" style="font-weight:700; color:#0f172a; font-size:0.95rem;"></span>
            </div>
            <div>
                <span style="font-size:0.7rem; color:#64748b; text-transform:uppercase; font-weight:700; display:block;">Data e Horário</span>
                <span id="audit-modal-date" style="font-weight:600; color:#334155;"></span>
            </div>
            <div>
                <span style="font-size:0.7rem; color:#64748b; text-transform:uppercase; font-weight:700; display:block;">Tipo de Ação</span>
                <span id="audit-modal-action" style="padding:2px 8px; border-radius:6px; font-weight:700; font-size:0.75rem; display:inline-block; margin-top:2px;"></span>
            </div>
            <div>
                <span style="font-size:0.7rem; color:#64748b; text-transform:uppercase; font-weight:700; display:block;">Alvo da Operação</span>
                <span id="audit-modal-target" style="font-weight:600; color:#334155; word-break:break-word;"></span>
            </div>
        </div>

        <!-- O que o funcionário fez -->
        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:14px; padding:16px;">
            <div style="font-size:0.75rem; font-weight:800; color:#0369a1; text-transform:uppercase; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                <i data-lucide="info" style="width:14px; height:14px;"></i> O que o colaborador realizou:
            </div>
            <div id="audit-modal-summary" style="font-size:0.95rem; color:#0c4a6e; font-weight:600; line-height:1.5;"></div>
        </div>

        <!-- Como foi feito: Lista de alterações -->
        <div style="display:flex; flex-direction:column; gap:8px;">
            <span style="font-size:0.75rem; font-weight:800; color:#475569; text-transform:uppercase;">Detalhamento das Informações Registradas:</span>
            <div id="audit-modal-changes-container" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                <!-- Tabela de campos preenchida dinamicamente -->
            </div>
        </div>

        <!-- Detalhes de erro caso existam -->
        <div id="audit-modal-error-container" style="display:none; flex-direction:column; gap:6px;">
            <span style="font-size:0.75rem; font-weight:700; color:#ef4444; text-transform:uppercase;">Alerta / Detalhes de Falha</span>
            <div id="audit-modal-error-content" style="background:#fef2f2; border:1px solid #fca5a5; padding:12px; border-radius:10px; font-size:0.85rem; color:#991b1b;"></div>
        </div>

        <!-- Dados técnicos brutos colapsados por padrão -->
        <details style="margin-top:4px; border-top:1px dashed #e2e8f0; padding-top:10px;">
            <summary style="font-size:0.75rem; color:#94a3b8; font-weight:700; cursor:pointer;">[+] Exibir dados técnicos avançados (JSON original)</summary>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:8px;">
                <div>
                    <span style="font-size:0.7rem; color:#ef4444; font-weight:700;">Antes:</span>
                    <pre id="audit-modal-old-state" style="background:#fef2f2; padding:10px; border-radius:8px; font-size:0.75rem; color:#991b1b; max-height:150px; overflow:auto;"></pre>
                </div>
                <div>
                    <span style="font-size:0.7rem; color:#10b981; font-weight:700;">Depois:</span>
                    <pre id="audit-modal-new-state" style="background:#ecfdf5; padding:10px; border-radius:8px; font-size:0.75rem; color:#065f46; max-height:150px; overflow:auto;"></pre>
                </div>
            </div>
        </details>

        <div style="display:flex; justify-content:flex-end; margin-top:6px;">
            <button type="button" onclick="closeAuditModal()" style="padding:10px 22px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; border-radius:10px; cursor:pointer; font-weight:700; font-size:0.9rem;">Fechar</button>
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

    async function purgeAuditHistory() {
        if (!confirm("POLÍTICA DE RETENÇÃO INDUSTRIAL:\n\n• Os registros dos últimos 30 dias (1 mês) são mantidos estritamente protegidos e NUNCA serão apagados.\n• Apenas históricos anteriores a 30 dias serão expurgados.\n\nDeseja realizar a limpeza agora?")) {
            return;
        }

        try {
            if (typeof showToast === 'function') showToast('Processando limpeza de histórico antigo...', 'info');
            const res = await api('purge_audit_logs');
            if (res && res.message) {
                alert(res.message);
            } else if (res && res.error) {
                alert(res.error);
            }
            loadAuditLogs();
        } catch (e) {
            alert('Falha na comunicação: ' + (e.message || e));
        }
    }

    function getHumanAuditInfo(log) {
        const acao = (log.acao || '').toUpperCase();
        const alvo = log.alvo || '';
        const user = log.usuario || 'Funcionário';
        let summary = '';
        let actionLabel = log.acao || 'Ação';
        let preview = 'Visualizar detalhes da operação';
        let changes = [];

        let newData = null, oldData = null;
        try { if (log.dados_novos && log.dados_novos !== 'null') newData = JSON.parse(log.dados_novos); } catch(e) { newData = log.dados_novos; }
        try { if (log.dados_antigos && log.dados_antigos !== 'null') oldData = JSON.parse(log.dados_antigos); } catch(e) { oldData = log.dados_antigos; }

        if (acao === 'UPDATE' && (alvo.includes('ativos') || alvo.toLowerCase().includes('ativo'))) {
            actionLabel = 'Atualização de Ativo';
            const nomeAtivo = (newData && typeof newData === 'object' && newData.nome) ? newData.nome : alvo;
            summary = `${user} atualizou os parâmetros e especificações técnicas de ${nomeAtivo}.`;
            preview = `Atualizou especificações do ativo: ${nomeAtivo}`;
        } else if (acao === 'INSERT' && (alvo.includes('ativos') || alvo.toLowerCase().includes('ativo'))) {
            actionLabel = 'Cadastro de Ativo';
            const nomeAtivo = (newData && typeof newData === 'object' && newData.nome) ? newData.nome : alvo;
            summary = `${user} incluiu um novo ativo industrial na planta: ${nomeAtivo}.`;
            preview = `Cadastrou novo ativo: ${nomeAtivo}`;
        } else if (acao.includes('OS_AGENT')) {
            actionLabel = 'Agente Autônomo O.S.';
            const count = (newData && typeof newData === 'object' && newData.created) ? newData.created : '';
            summary = `${user} acionou o Agente Autônomo que gerou ${count ? count + ' ordens de serviço' : 'novas ordens'} preventivas de lubrificação.`;
            preview = `Agente gerou ordens de lubrificação para a fábrica`;
        } else if (acao.includes('USER_CREATE')) {
            actionLabel = 'Cadastro de Colaborador';
            const email = (newData && typeof newData === 'object' && newData.email) ? `(${newData.email})` : '';
            summary = `${user} cadastrou um novo usuário ${email} no sistema.`;
            preview = `Criou usuário ${email}`;
        } else if (acao.includes('USER_UPDATE')) {
            actionLabel = 'Alteração de Usuário';
            summary = `${user} alterou dados cadastrais ou permissões de usuário.`;
            preview = `Atualizou cadastro de usuário`;
        } else if (acao.includes('EXECUTE_OS') || acao.includes('OS_STATUS')) {
            actionLabel = 'Execução de O.S.';
            summary = `${user} apontou a execução de manutenção da ordem de serviço em campo.`;
            preview = `Apontamento de manutenção concluído`;
        } else if (acao.includes('INVENTORY') || acao.includes('CONSUMO')) {
            actionLabel = 'Baixa de Lubrificante';
            summary = `${user} efetuou baixa de lubrificante do almoxarifado para O.S.`;
            preview = `Movimento de estoque para manutenção`;
        } else if (acao.includes('PURGE')) {
            actionLabel = 'Expurgo de Auditoria';
            summary = `${user} efetuou limpeza de histórico mantendo os últimos 30 dias intactos.`;
            preview = `Limpeza de histórico com retenção mensal`;
        } else {
            summary = `${user} realizou a operação ${acao} em ${alvo}.`;
            preview = `${acao}: ${alvo}`;
        }

        const fieldDict = {
            nome: 'Nome do Ativo / Local',
            tipo: 'Tipo / Categoria',
            obs: 'Descrição / Observações',
            fabricante: 'Fabricante',
            modelo: 'Modelo',
            num_serie: 'Nº de Série',
            dados_tecnicos: 'Parâmetros Técnicos',
            tag: 'TAG do Ponto / Máquina',
            pai_id: 'Vínculo Hierárquico Pai',
            email: 'E-mail',
            role: 'Função / Nível de Acesso',
            situacao: 'Situação da O.S.',
            data_planejada: 'Data Programada',
            qtd_real: 'Dose Aplicada',
            materiais: 'Lubrificante',
            reserva_almox: 'Reserva Almoxarifado',
            ip: 'IP Hierárquico do Ponto',
            created: 'Ordens Criadas',
            scanned: 'Pontos Analisados',
            due: 'Ordens em Prazo',
            skipped: 'Pontos Ignorados',
            capacidade: 'Capacidade / Volume',
            lubrificante: 'Lubrificante Homologado',
            frequencia: 'Frequência de Relubrificação'
        };

        if (newData && typeof newData === 'object') {
            for (const key of Object.keys(newData)) {
                if (key === 'pass' || key === 'password' || key === 'senha' || key === 'id' || key === 'pai_id' || key === 'user_id' || key === 'action') continue;
                
                if (key === 'dados_tecnicos') {
                    let dt = newData[key];
                    if (typeof dt === 'string') {
                        try { dt = JSON.parse(dt); } catch(e) {}
                    }
                    if (dt && typeof dt === 'object') {
                        for (const dtKey of Object.keys(dt)) {
                            const dtName = fieldDict[dtKey] || ('Parâmetro: ' + dtKey);
                            let dtVal = dt[dtKey];
                            if (typeof dtVal === 'object') dtVal = JSON.stringify(dtVal);
                            changes.push({
                                name: dtName,
                                oldVal: '—',
                                newVal: String(dtVal ?? '—')
                            });
                        }
                    }
                    continue;
                }

                const fieldName = fieldDict[key] || key;
                let newVal = newData[key];
                if (typeof newVal === 'object') newVal = JSON.stringify(newVal);
                let oldVal = '—';
                if (oldData && typeof oldData === 'object' && oldData[key] !== undefined) {
                    oldVal = oldData[key];
                    if (typeof oldVal === 'object') oldVal = JSON.stringify(oldVal);
                }
                changes.push({
                    name: fieldName,
                    oldVal: String(oldVal ?? '—'),
                    newVal: String(newVal ?? '—')
                });
            }
        }

        return { actionLabel, summary, preview, changes };
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

            const isArr = Array.isArray(res);
            const isSuccessObj = res && typeof res === 'object' && (res.ok === true || Array.isArray(res.data) || Array.isArray(res.logs));

            if (!isArr && !isSuccessObj) {
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

            const data = isArr ? res : (res.data || res.logs || []);
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
                
                const info = getHumanAuditInfo(log);

                let actionBadgeColor = 'background:#f1f5f9; color:#475569;';
                const action = (log.acao || '').toUpperCase();
                if (action.includes('CREATE') || action.includes('INSERT') || action.includes('ADD') || action.includes('SEED')) {
                    actionBadgeColor = 'background:#ecfdf5; color:#059669;';
                } else if (action.includes('UPDATE') || action.includes('EDIT') || action.includes('MOD')) {
                    actionBadgeColor = 'background:#fef3c7; color:#d97706;';
                } else if (action.includes('DELETE') || action.includes('REMOVE') || action.includes('WIPE') || action.includes('PURGE')) {
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

                tr.innerHTML = `
                    <td style="padding:14px 20px; font-size:0.85rem; color:#64748b; font-weight:500;">${auditEscapeHtml(dateStr)}</td>
                    <td style="padding:14px 20px; font-size:0.9rem; color:#0f172a; font-weight:700;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <i data-lucide="user" style="width:14px; color:#0284c7;"></i>
                            <span>${auditEscapeHtml(log.usuario)}</span>
                        </div>
                    </td>
                    <td style="padding:14px 20px; font-size:0.85rem;">
                        <span style="padding:4px 10px; border-radius:6px; font-weight:700; font-size:0.75rem; ${actionBadgeColor}">
                            ${auditEscapeHtml(info.actionLabel)}
                        </span>
                    </td>
                    <td style="padding:14px 20px; font-size:0.85rem; color:#334155; font-weight:600;">${auditEscapeHtml(log.alvo)}</td>
                    <td style="padding:14px 20px; font-size:0.85rem; color:#475569; font-weight:500; max-width:350px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        ${auditEscapeHtml(info.preview)}
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
        const info = getHumanAuditInfo(log);

        document.getElementById('audit-modal-user').textContent = log.usuario;
        
        let dateStr = log.data;
        try {
            const d = new Date(log.data);
            if (!isNaN(d.getTime())) dateStr = d.toLocaleString('pt-BR');
        } catch(e) {}
        document.getElementById('audit-modal-date').textContent = dateStr;
        
        const actionSpan = document.getElementById('audit-modal-action');
        actionSpan.textContent = info.actionLabel;
        actionSpan.className = '';
        
        const action = (log.acao || '').toUpperCase();
        if (action.includes('CREATE') || action.includes('INSERT') || action.includes('ADD')) {
            actionSpan.style.background = '#ecfdf5';
            actionSpan.style.color = '#059669';
        } else if (action.includes('UPDATE') || action.includes('EDIT')) {
            actionSpan.style.background = '#fef3c7';
            actionSpan.style.color = '#d97706';
        } else if (action.includes('DELETE') || action.includes('PURGE')) {
            actionSpan.style.background = '#fef2f2';
            actionSpan.style.color = '#dc2626';
        } else {
            actionSpan.style.background = '#f1f5f9';
            actionSpan.style.color = '#475569';
        }

        document.getElementById('audit-modal-target').textContent = log.alvo;
        document.getElementById('audit-modal-summary').innerHTML = info.summary;

        // Renderiza lista de alterações de forma limpa e direta
        const changesBox = document.getElementById('audit-modal-changes-container');
        if (info.changes.length > 0) {
            let html = `
                <table style="width:100%; border-collapse:collapse; font-size:0.85rem; text-align:left;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569;">
                            <th style="padding:8px 12px; font-weight:700; width:35%;">Informação</th>
                            <th style="padding:8px 12px; font-weight:700; width:30%; color:#991b1b;">Antes</th>
                            <th style="padding:8px 12px; font-weight:700; width:35%; color:#065f46;">Depois</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            info.changes.forEach(c => {
                html += `
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:8px 12px; font-weight:600; color:#1e293b;">${auditEscapeHtml(c.name)}</td>
                        <td style="padding:8px 12px; color:#64748b; background:#fef2f233;">${auditEscapeHtml(c.oldVal)}</td>
                        <td style="padding:8px 12px; font-weight:600; color:#065f46; background:#ecfdf533;">${auditEscapeHtml(c.newVal)}</td>
                    </tr>
                `;
            });
            html += `</tbody></table>`;
            changesBox.innerHTML = html;
            changesBox.style.display = 'block';
        } else {
            changesBox.innerHTML = `<div style="padding:14px; font-size:0.85rem; color:#64748b; font-style:italic;">Registro executado sem alterações diretas de campos secundários.</div>`;
        }

        const errContainer = document.getElementById('audit-modal-error-container');
        const errContent = document.getElementById('audit-modal-error-content');
        if (log.detalhes_erro) {
            errContainer.style.display = 'flex';
            errContent.textContent = log.detalhes_erro;
        } else {
            errContainer.style.display = 'none';
        }

        document.getElementById('audit-modal-old-state').textContent = formatJSON(log.dados_antigos);
        document.getElementById('audit-modal-new-state').textContent = formatJSON(log.dados_novos);

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
