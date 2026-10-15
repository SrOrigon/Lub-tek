<div id="view-dash" class="view-container active view-flex dash-workspace" role="region" aria-labelledby="dash-page-title">

    <!-- TOP BAR: COMPACT KPI & ACTIONS -->
    <div class="dash-toolbar"
        style="display:flex; justify-content:space-between; align-items:center; background:white; padding:12px 20px; border-radius:12px; border:1px solid var(--border); box-shadow:0 2px 5px rgba(0,0,0,0.02);">
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="display:flex; flex-direction:column;">
                <h1 id="dash-page-title" style="font-size:1.4rem; margin:0; font-weight:800; color:var(--primary); letter-spacing: -0.5px;">Ordens de Serviço</h1>
                <span
                    style="font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing: 0.5px;">Painel de Manutenção</span>
            </div>

            <div
                style="display:flex; gap:25px; border-left:1px solid var(--border); padding-left:25px; margin-left:15px;"
                aria-live="polite" aria-atomic="true" aria-label="Resumo de ordens de serviço">
                <div class="mini-stat card-kpi">
                    <span class="label">PENDENTES</span>
                    <span class="val" id="dash-pending-count" style="color:var(--warning); font-size: 1.5rem;">0</span>
                </div>
                <div class="mini-stat card-kpi">
                    <span class="label">CRÍTICAS</span>
                    <span class="val" id="dash-crit-count" style="color:var(--danger); font-size: 1.5rem;">0</span>
                </div>
                <div class="mini-stat card-kpi">
                    <span class="label">CONCLUÍDAS</span>
                    <span class="val" id="dash-done-count" style="color:var(--success); font-size: 1.5rem;">0</span>
                </div>
            </div>
        </div>

        <div class="dash-toolbar-actions" style="display:flex; gap:12px;">
            <div style="position:relative;">
                <label for="os-search" class="sr-only">Localizar ordem de serviço</label>
                <i data-lucide="search" aria-hidden="true"
                    style="position:absolute; left:12px; top:12px; width:16px; color:var(--text-muted);"></i>
                <input type="search" id="os-search" placeholder="Localizar O.S..." oninput="filterOSText(this.value)"
                    autocomplete="off" aria-describedby="os-search-hint"
                    style="padding:10px 10px 10px 38px; font-size:0.9rem; width:220px; border-radius:10px; height:42px; border:1px solid var(--border);">
                <span id="os-search-hint" class="sr-only">Filtra a tabela de ordens enquanto você digita</span>
            </div>
            <button type="button" class="btn btn-sm btn-action" onclick="createNewOS()" data-gestor-only
                aria-label="Criar nova ordem de serviço"
                style="padding:0 25px; height:42px; font-size:0.85rem; background:var(--primary); font-weight: 700; border-radius: 10px;">
                <i data-lucide="plus" style="margin-right: 8px;"></i> Nova Ordem
            </button>
            <button type="button" class="btn btn-sm btn-outline" onclick="generateScheduledOrders()" 
                style="padding:0 15px; height:42px; border-radius: 10px; border-color: var(--success); color: var(--success); display: flex; align-items: center; gap: 6px; font-weight: 700;" 
                title="Gera ordens das tarefas fixas vencidas hoje (cadastro + período)"
                aria-label="Gerar ordens preventivas das tarefas fixas vencidas"
                data-power-only data-admin-only>
                <i data-lucide="calendar" style="width:16px;"></i> Preventivas
            </button>
            
            <!-- SAP ERP (admin) -->
            <div style="position:relative; display:inline-block;" id="sap-sync-dropdown-container" data-admin-only>
                <button type="button" class="btn btn-sm btn-outline" onclick="toggleSAPDropdown()" 
                    aria-haspopup="true" aria-expanded="false" id="sap-sync-toggle"
                    aria-label="Menu de integração SAP ERP"
                    style="padding:0 15px; height:42px; border-radius: 10px; border-color: #1e3a8a; color: #1e3a8a; display: flex; align-items: center; gap: 6px; font-weight: 700; background: #f0f7ff;">
                    <i data-lucide="refresh-cw" style="width:16px;"></i> SAP ERP <i data-lucide="chevron-down" style="width:14px;"></i>
                </button>
                <div id="sap-sync-dropdown" style="display:none; position:absolute; right:0; top:48px; background:white; border:1px solid var(--border); border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.1); width:200px; z-index:100; overflow:hidden;">
                    <a href="?page=sap&tab=import" style="display:flex; align-items:center; gap:10px; padding:12px 15px; text-decoration:none; color:var(--text-main); font-size:0.85rem; font-weight:600; border-bottom:1px solid var(--border); transition:background 0.2s;">
                        <i data-lucide="upload" style="width:14px; color:var(--primary);"></i> Importar O.S. (IW39)
                    </a>
                    <a href="?page=sap&tab=export" style="display:flex; align-items:center; gap:10px; padding:12px 15px; text-decoration:none; color:var(--text-main); font-size:0.85rem; font-weight:600; transition:background 0.2s;">
                        <i data-lucide="download" style="width:14px; color:var(--primary);"></i> Exportar Retorno (IW41)
                    </a>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-outline" onclick="loadDash()" style="padding:0 15px; height:42px; border-radius: 10px;"
                aria-label="Atualizar painel de ordens de serviço">
                <i data-lucide="refresh-cw" style="width:18px;" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="dash-body">
        <div class="dash-layout-grid">

        <div class="dash-intel-column dash-column-scroll">
        <!-- MOCKUPS SECTION (New) -->
        <div id="mockups-section" style="display:none; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0 10px;">
                <h3 style="margin:0; font-size:0.9rem; color:var(--primary); font-weight:700; text-transform:uppercase;">
                    <i data-lucide="box" style="width:16px; margin-right:5px; vertical-align:text-bottom;"></i> Maquetes 3D & Plantas
                </h3>
            </div>
            <div id="mockups-container" style="display:flex; gap:15px; overflow-x:auto; padding-bottom:10px;">
                <!-- Mockup Cards Rendered Here -->
            </div>
        </div>

        <!-- NEURAL INSIGHTS — faixa compacta para a listagem de O.S. ficar visível -->
        <div id="neural-insights-section" class="neural-dash-strip" aria-expanded="true" aria-controls="neural-dash-extra">
            <div class="neural-dash-bar">
                <h3 class="neural-dash-title">
                    <i data-lucide="brain" style="width:14px; color:var(--success);"></i>
                    Motor Neural
                    <span id="neural-dash-count" class="neural-dash-count"></span>
                </h3>
                <div class="neural-dash-bar-actions">
                    <button type="button" class="btn btn-sm btn-outline neural-dash-mini-btn" onclick="if(typeof loadNeuralInsights === 'function') loadNeuralInsights()"
                        aria-label="Atualizar sugestões do motor neural">
                        Atualizar
                    </button>
                    <button type="button" id="neural-dash-toggle" class="btn btn-sm btn-outline neural-dash-mini-btn" onclick="toggleDashNeuralStrip()"
                        aria-expanded="true" aria-controls="neural-dash-extra">
                        Recolher
                    </button>
                </div>
            </div>

            <div id="neural-dash-extra">
                <div id="neural-dash-query-box" class="neural-dash-query">
                    <label for="neural-dash-input" class="sr-only">Pergunta à Lúbria no dashboard</label>
                    <input type="text" id="neural-dash-input"
                        placeholder="Perguntar à Lúbria…"
                        autocomplete="off"
                        aria-describedby="neural-dash-response"
                        onkeydown="if(event.key==='Enter'){event.preventDefault();if(typeof sendDashNeuralQuery==='function')sendDashNeuralQuery();}">
                    <button type="button" id="neural-dash-send-btn" onclick="if(typeof sendDashNeuralQuery==='function')sendDashNeuralQuery()"
                        aria-label="Enviar pergunta à Lúbria">
                        <i data-lucide="send" style="width:14px;" aria-hidden="true"></i>
                    </button>
                </div>
                <div id="neural-dash-response"></div>
                <div id="neural-insights-container">
                    <div id="neural-insights-placeholder" class="neural-dash-placeholder">
                        Iniciando análise preditiva...
                    </div>
                </div>
            </div>
        </div>

        <!-- Telemetria IA + feedback (gestor) -->
        <div id="ai-ops-panel-section" class="ai-ops-strip" data-gestor-only aria-expanded="true" aria-controls="ai-ops-panel-extra">
            <div class="ai-ops-bar">
                <h3 class="ai-ops-title">
                    <i data-lucide="activity" style="width:14px;"></i>
                    Operação dos Agentes (IA)
                </h3>
                <div style="display:flex;gap:6px;">
                    <button type="button" class="btn btn-sm btn-outline os-agent-mini-btn" onclick="if(typeof loadAiOpsPanel==='function')loadAiOpsPanel()">
                        Atualizar
                    </button>
                    <button type="button" id="ai-ops-toggle" class="btn btn-sm btn-outline os-agent-mini-btn" onclick="if(typeof toggleAiOpsPanel==='function')toggleAiOpsPanel()"
                        aria-expanded="true" aria-controls="ai-ops-panel-extra">
                        Recolher
                    </button>
                </div>
            </div>
            <div id="ai-ops-panel-extra" class="ai-ops-columns">
                <div>
                    <div style="font-size:0.68rem;font-weight:700;color:#64748b;margin-bottom:4px;text-transform:uppercase;">Uso Gemini (7 dias)</div>
                    <div id="ai-ops-usage"></div>
                </div>
                <div>
                    <div style="font-size:0.68rem;font-weight:700;color:#64748b;margin-bottom:4px;text-transform:uppercase;">Feedback operadores (30 dias)</div>
                    <div id="ai-ops-feedback"></div>
                </div>
            </div>
        </div>

        <!-- AGENTE DE GERAÇÃO O.S. — relatório de rotina -->
        <div id="os-agent-report-section" class="os-agent-strip" data-gestor-only aria-expanded="true" aria-controls="os-agent-report-extra">
            <div class="os-agent-bar">
                <h3 class="os-agent-title">
                    <i data-lucide="bot" style="width:14px; color:#0d9488;"></i>
                    Agente de Geração O.S.
                    <span id="os-agent-meta" class="os-agent-meta">Carregando…</span>
                </h3>
                <div class="os-agent-bar-actions">
                    <button type="button" class="btn btn-sm btn-outline os-agent-mini-btn" onclick="simulateOsAgent()" title="Simula sem gravar">
                        Simular
                    </button>
                    <button type="button" class="btn btn-sm btn-outline os-agent-mini-btn" onclick="refreshOsAgentAiBrief()" title="Resumo com Lúbria/Gemini">
                        Resumo IA
                    </button>
                    <button type="button" class="btn btn-sm btn-outline os-agent-mini-btn" onclick="runOsAgentAndRefresh()" style="border-color:var(--success);color:var(--success);">
                        Executar
                    </button>
                    <button type="button" id="os-agent-toggle" class="btn btn-sm btn-outline os-agent-mini-btn" onclick="toggleOsAgentPanel()"
                        aria-expanded="true" aria-controls="os-agent-report-extra">
                        Recolher
                    </button>
                </div>
            </div>
            <div id="os-agent-report-extra">
                <div id="os-agent-loading" class="os-agent-loading" style="display:none;" role="status" aria-live="polite">
                    <i data-lucide="loader-circle" style="width:18px; animation: spin 1s linear infinite;" aria-hidden="true"></i>
                    Analisando cadastro…
                </div>
                <div id="os-agent-stats" class="os-agent-stats"></div>
                <div id="os-agent-briefing" class="os-agent-briefing card"></div>
                <div class="os-agent-table-wrap">
                    <div style="font-size:0.72rem;font-weight:700;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;">
                        Decisões do agente (vencidos / fila)
                    </div>
                    <div id="os-agent-empty" class="os-agent-empty" style="display:none;">
                        Nenhuma tarefa vencida ou pendente de geração no momento.
                    </div>
                    <div class="os-agent-decisions-scroll">
                        <table class="lub-table os-agent-table">
                            <thead>
                                <tr>
                                    <th>Ponto</th>
                                    <th>TAG</th>
                                    <th>Rota</th>
                                    <th>Serviço</th>
                                    <th>Ação</th>
                                    <th>Prio</th>
                                    <th>Score</th>
                                    <th>Atraso</th>
                                    <th class="col-motivo">Motivo</th>
                                </tr>
                            </thead>
                            <tbody id="os-agent-decisions-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div><!-- /.dash-intel-column -->

        <div class="dash-orders-column">
        <!-- SECTION 1: OS DATA GRID (The Table) -->
        <div id="os-table-view-wrapper">
        <div class="card dash-table-panel"
            style="padding:0; display:flex; flex-direction:column; border-radius:12px; border:1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div
                style="background:var(--bg-input); padding:10px 15px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
                <span id="os-list-heading" style="font-size:0.75rem; font-weight:700; color:var(--text-muted);">LISTAGEM DE ORDENS</span>
                <div class="filter-group view-toggle-group" style="display:flex; gap:5px;" role="group" aria-labelledby="os-list-heading">
                    <button type="button" class="filter-btn os-view-toggle active" onclick="switchOSView('table', this)" aria-pressed="true" aria-label="Visualização em lista">Lista</button>
                    <button type="button" class="filter-btn os-view-toggle" onclick="switchOSView('kanban', this)" aria-pressed="false" aria-label="Visualização em kanban">Kanban</button>
                    <button type="button" class="filter-btn os-status-filter active" onclick="filterOS('all', this)" aria-pressed="true">Todas</button>
                    <button type="button" class="filter-btn os-status-filter" onclick="filterOS('Pendente', this)" aria-pressed="false">Pendentes</button>
                    <button type="button" class="filter-btn os-status-filter" onclick="filterOS('Concluído', this)" aria-pressed="false">Concluídas</button>
                    <button type="button" class="filter-btn" onclick="exportTasksExcel()" title="Baixar Excel"
                        aria-label="Exportar ordens de serviço para Excel"
                        style="border-left:1px solid var(--border); margin-left:5px; padding-left:10px;">
                        <i data-lucide="download" style="width:12px;" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="dash-table-scroll" aria-labelledby="os-list-heading" aria-busy="false">
                    <table class="lub-table" id="os-table"
                        style="width:100%; border-collapse: collapse;">
                        <thead
                            style="position: sticky; top: 0; z-index: 20; background: var(--bg-card); box-shadow: 0 1px 0 var(--border);">
                            <tr style="text-align: left;">
                                <th scope="col" style="width:55px;">O.S</th>
                                <th scope="col" style="width:55px;">IP</th>
                                <th scope="col" style="width:80px;">Cód. Serv</th>
                                <th scope="col" style="min-width:180px;">Serviço / Ativo</th>
                                <th scope="col" style="width:115px;">Situação</th>
                                <th scope="col" style="width:85px;">Emissão</th>
                                <th scope="col" style="width:85px;">Dt. Prog.</th>
                                <th scope="col" style="width:85px;">Dt. Exec.</th>
                            </tr>
                        </thead>
                        <tbody id="os-table-body">
                            <!-- JS renders rows here -->
                        </tbody>
                    </table>
            </div>
        </div>
        </div>
        <div id="os-kanban-view-wrapper" style="display:none;" role="region" aria-label="Quadro kanban de ordens de serviço">
            <div id="kanban-board-container" class="kanban-board"></div>
        </div>
        </div><!-- /.dash-orders-column -->

        </div><!-- /.dash-layout-grid -->
    </div>

        <!-- OFFCANVAS OVERLAY -->
        <div id="offcanvas-overlay" onclick="if(typeof closeOSForm === 'function') closeOSForm()" aria-hidden="true"></div>

        <!-- SECTION 2: OS DETAIL FORM (The Editor) — Offcanvas Panel -->
        <div class="card" id="os-form-container" aria-labelledby="form-title" aria-hidden="true"
            style="padding:0; flex-direction:column; background:#ffffff; box-shadow: -10px 0 30px rgba(0,0,0,0.1);">
            <div
                style="background:var(--primary); color:white; padding:15px 20px; border-radius:20px 0 0 0; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
                <span id="form-title" style="font-size:1.1rem; font-weight:800; text-transform:uppercase; letter-spacing: 0.5px;">Emissão / Retorno de O.S.</span>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="printCurrentOS()"
                        style="background:transparent; border:none; color:white; cursor:pointer;" title="Imprimir O.S."
                        aria-label="Imprimir ordem de serviço">
                        <i data-lucide="printer" style="width:18px;" aria-hidden="true"></i>
                    </button>
                    <button type="button" onclick="closeOSForm()"
                        style="background:transparent; border:none; color:white; cursor:pointer;"
                        aria-label="Fechar formulário de ordem de serviço">
                        <i data-lucide="x" style="width:18px;" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="os-form-scroll" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:20px;">

                <!-- ROW 1: HEADER INFO (Optimized Grid) -->
                <div class="lub-form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">O.S.</label>
                        <input type="text" id="f-os-id" readonly
                            style="background:#f1f5f9; color:#1e293b; font-weight:800; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1.1rem; width: 100%;">
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">IP</label>
                        <input type="text" id="f-os-ip" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%;">
                    </div>
                </div>
                <div class="field-item">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Ponto da Lubrificação / Ativo</label>
                    <select id="f-os-ativo" onchange="syncAssetData(this.value)" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; height: 48px; font-weight: 600;">
                        <option value="">Selecione o Ativo...</option>
                    </select>
                </div>
                <div class="lub-form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Serviço (Cód / Nome)</label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" id="f-os-codserv" style="width:60px; text-align:center; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem;">
                            <input type="text" id="f-os-serv" placeholder="Ex: TROCA" style="flex:1; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem;">
                        </div>
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Rota</label>
                        <input type="text" id="f-os-rota" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%;">
                    </div>
                </div>

                <!-- ROW 2: TECHNICAL DETAILS -->
                <div class="lub-form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="field-item" style="grid-column: span 2;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Material (Lubrificante)</label>
                        <select id="f-os-material" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; height: 48px; font-weight: 600;">
                            <option value="">Selecione o Produto...</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Prioridade</label>
                        <select id="f-os-prio" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; height: 48px; font-weight: 700;">
                            <option value="Baixa">🟢 BAIXA</option>
                            <option value="Média">🟡 MÉDIA</option>
                            <option value="Alta">🔴 ALTA</option>
                            <option value="Crítica">🔥 CRÍTICA</option>
                        </select>
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Situação</label>
                        <select id="f-os-situacao" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; height: 48px; font-weight: 700; background: #fffbeb;">
                            <option value="Pendente">⏳ PENDENTE</option>
                            <option value="Em Andamento">⚙️ EM ANDAMENTO</option>
                            <option value="Aguardando Material">🛒 AGUARDANDO MAT.</option>
                            <option value="Concluído">✅ CONCLUÍDO</option>
                            <option value="Cancelado">❌ CANCELADO</option>
                        </select>
                    </div>
                    <div class="field-item" style="grid-column: span 2;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Condição de Serviço</label>
                        <select id="f-os-condicao" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; height: 48px; font-weight: 600;">
                            <option value="Rodando">RODANDO</option>
                            <option value="Parada">PARADA</option>
                            <option value="Risco Alto">RISCO ALTO</option>
                        </select>
                    </div>
                </div>

                <!-- ROW 3: PLANNING & SUPPLY (Optimized) -->
                <div class="lub-form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Controle SAP</label>
                        <input type="number" id="f-os-sap" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; font-weight: 700;">
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Reserva Almox.</label>
                        <input type="text" id="f-os-reserva" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%;">
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Nº Pontos</label>
                        <input type="number" id="f-os-pontos" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; text-align: center;">
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Complemento</label>
                        <input type="text" id="f-os-complemento" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%;">
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Data Emissão</label>
                        <input type="date" id="f-os-dt-emissao" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%;">
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Data Programada</label>
                        <input type="date" id="f-os-dt-prog" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%;">
                    </div>
                </div>

                <!-- ROW 4: EXECUTION (RETURN) -->
                <div style="background:#f8fafc; padding:20px; border-radius:16px; border:1px solid #e2e8f0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                    <div
                        style="font-size:0.9rem; font-weight:900; color:var(--primary); margin-bottom:20px; text-transform:uppercase; letter-spacing: 1px; display: flex; align-items:center; gap: 10px;">
                        <i data-lucide="check-circle" style="width:18px;"></i> Retorno de Execução Técnica
                    </div>
                    <div class="lub-form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="field-item">
                            <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Data Execução</label>
                            <input type="date" id="f-os-dt-exec" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%;">
                        </div>
                        <div class="field-item">
                            <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Qtd Material</label>
                            <input type="text" id="f-os-qtd-real" placeholder="Ex: 5L" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; font-weight: 700;">
                        </div>
                        <div class="field-item">
                            <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Tempo (HH:MM)</label>
                            <div style="display:flex; gap:8px;">
                                <input type="number" id="f-os-horas" placeholder="HH" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; text-align: center;">
                                <input type="number" id="f-os-minutos" placeholder="MM" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; text-align: center;">
                            </div>
                        </div>
                        <div class="field-item">
                            <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Resp. Técnico</label>
                            <input type="text" id="f-os-resp" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%;">
                        </div>
                        <div class="field-item" style="grid-column: span 2;">
                            <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Cód. Execução</label>
                            <select id="f-os-codexec" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; height: 48px; font-weight: 700; background: #ecfdf5; color: #065f46;">
                                <option value="1">1 - EXECUTADA</option>
                                <option value="2">2 - FALHA TÉCNICA</option>
                                <option value="3">3 - EQUIP. INDISPONÍVEL</option>
                                <option value="4">4 - OUTROS</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ROW 5: ANALYSIS & OBS (Expanded) -->
                <div class="lub-form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Conc. (%)</label>
                        <input type="number" step="0.1" id="f-os-conc" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; text-align: center;">
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">PH</label>
                        <input type="number" step="0.1" id="f-os-ph" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; text-align: center;">
                    </div>
                    <div class="field-item">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Água (L)</label>
                        <input type="number" step="0.1" id="f-os-agua" style="padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 1rem; width: 100%; text-align: center;">
                    </div>
                    <div class="field-item" style="grid-column: span 2;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Observações de Execução</label>
                        <textarea id="f-os-obsexec" style="width:100%; height:80px; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.95rem; resize: none;"></textarea>
                    </div>
                    <div class="field-item" style="grid-column: span 2;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Motivos / Ocorrências</label>
                        <textarea id="f-os-motivos" style="width:100%; height:80px; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.95rem; resize: none;"></textarea>
                    </div>
                </div>
            </div>

            <!-- FOOTER: ACTIONS -->
            <div
                style="background:var(--bg-input); padding:15px 20px; border-top:1px solid var(--border); display:flex; flex-direction:column; gap:15px; border-radius: 0 0 0 20px;">
                <div style="display:flex; gap:10px; width: 100%;">
                    <button class="btn btn-outline" style="flex:1; padding:15px; font-weight: 700; border-radius: 12px; justify-content:center;" onclick="closeOSForm()">CANCELAR</button>
                    <button class="btn" style="flex:1; padding:15px; background:var(--success); font-weight: 800; border-radius: 12px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); justify-content:center;" onclick="saveOS()">SALVAR O.S.</button>
                </div>
                <button class="btn btn-danger" id="btn-del-os" style="width:100%; padding:15px; font-weight: 700; border-radius: 12px; display:none; justify-content:center;"
                    onclick="deleteSelectedOS()">EXCLUIR O.S.</button>
            </div>
        </div>

</div>

<style>
    /* DASH WORKSPACE — scroll único, conteúdo termina no fim dos dados */
    .dash-workspace {
        flex-direction: column;
        gap: 10px;
        padding: 10px 12px 16px;
        background: #f8fafc;
        min-height: 0;
        overflow-x: hidden;
        overscroll-behavior: contain;
    }
    #view-dash.dash-workspace.active {
        display: block !important;
        height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        padding: 10px 12px 16px;
    }
    .dash-toolbar {
        flex-shrink: 0;
        padding: 10px 16px !important;
        position: sticky;
        top: 0;
        z-index: 40;
        background: white;
        flex-wrap: wrap;
        gap: 12px;
    }
    .dash-toolbar h1 { font-size: 1.15rem !important; margin: 0 !important; }
    .dash-toolbar .mini-stat .val { font-size: 1.35rem !important; line-height: 1.1; }
    .dash-toolbar .mini-stat .label { font-size: 0.65rem; letter-spacing: 0.06em; }
    .dash-toolbar-actions {
        flex-wrap: wrap;
        justify-content: flex-end;
        align-items: center;
    }
    @media (min-width: 1200px) {
        .dash-toolbar {
            flex-wrap: nowrap;
        }
        .dash-toolbar-actions input[type="text"] {
            width: min(220px, 18vw) !important;
        }
    }
    .dash-body {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        gap: 10px;
        overflow: visible;
        min-height: 0;
    }
    .dash-layout-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: stretch;
        width: 100%;
    }
    .dash-column-scroll {
        scrollbar-gutter: stable;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }
    .dash-column-scroll::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    .dash-column-scroll::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 6px;
        border: 2px solid #f8fafc;
    }
    @media (min-width: 1200px) {
        #view-dash.dash-workspace.active {
            overflow: visible !important;
            height: auto !important;
            max-height: none !important;
            padding-bottom: 24px;
        }
        .dash-body {
            display: block;
            height: auto;
            overflow: visible;
        }
        .dash-layout-grid {
            display: grid;
            grid-template-columns: minmax(360px, 42fr) minmax(0, 58fr);
            gap: 14px;
            align-items: start;
            height: auto;
            max-height: none;
        }
        .dash-intel-column {
            display: flex;
            flex-direction: column;
            gap: 10px;
            height: auto;
            max-height: none;
            overflow: visible;
            padding-right: 6px;
            padding-bottom: 16px;
        }
        .dash-orders-column {
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: visible;
        }
        .dash-orders-column .dash-table-panel {
            flex: 1 1 auto;
            min-height: 0;
            overflow: visible;
        }
        .dash-orders-column .dash-table-scroll {
            width: 100%;
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
        }
        .dash-intel-column .ai-ops-columns {
            grid-template-columns: 1fr;
        }
        .dash-intel-column .ai-ops-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .os-agent-decisions-scroll {
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        .dash-intel-column .os-agent-table th:nth-child(2),
        .dash-intel-column .os-agent-table th:nth-child(3),
        .dash-intel-column .os-agent-table td:nth-child(2),
        .dash-intel-column .os-agent-table td:nth-child(3) {
            display: none;
        }
    }
    @media (min-width: 1400px) {
        .dash-layout-grid {
            grid-template-columns: minmax(400px, 40fr) minmax(0, 60fr);
        }
        .dash-intel-column .os-agent-table th:nth-child(2),
        .dash-intel-column .os-agent-table td:nth-child(2) {
            display: table-cell;
        }
    }
    @media (min-width: 1600px) {
        .dash-layout-grid {
            grid-template-columns: minmax(420px, 38fr) minmax(0, 62fr);
        }
        .dash-intel-column .os-agent-table th:nth-child(3),
        .dash-intel-column .os-agent-table td:nth-child(3) {
            display: table-cell;
        }
    }
    @media (max-width: 1199px) {
        #view-dash.dash-workspace.active {
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }
    }

    .neural-dash-strip {
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 12px 10px;
        overflow: visible;
    }
    .neural-dash-strip.is-collapsed {
        padding-bottom: 8px;
    }
    .neural-dash-strip.is-collapsed #neural-dash-extra {
        display: none;
    }
    .neural-dash-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        min-height: 28px;
    }
    .neural-dash-title {
        margin: 0;
        font-size: 0.72rem;
        color: var(--primary);
        font-weight: 800;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.3px;
    }
    .neural-dash-count {
        font-weight: 700;
        color: var(--text-muted);
        text-transform: none;
        font-size: 0.72rem;
    }
    .neural-dash-bar-actions { display: flex; gap: 6px; }
    .neural-dash-mini-btn {
        padding: 2px 8px !important;
        height: 26px !important;
        font-size: 0.7rem !important;
        border-radius: 6px !important;
    }
    .neural-dash-query {
        display: flex;
        gap: 6px;
        align-items: stretch;
    }
    .neural-dash-query input {
        flex: 1;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 0.8rem;
        outline: none;
        background: #f8fafc;
        height: 32px;
        min-height: 32px;
    }
    .neural-dash-query button {
        background: var(--primary);
        color: white;
        border: none;
        width: 36px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    #neural-dash-response {
        display: none;
        padding: 8px 10px;
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 8px;
        font-size: 0.78rem;
        color: #0f172a;
        line-height: 1.4;
        max-height: 72px;
        overflow: auto;
    }
    #neural-insights-container {
        display: flex;
        flex-wrap: nowrap;
        gap: 10px;
        overflow-x: auto;
        overflow-y: visible;
        padding: 2px 2px 6px;
        scrollbar-width: thin;
        min-height: 120px;
        align-items: stretch;
    }
    .neural-dash-placeholder,
    #neural-insights-placeholder {
        background: transparent;
        padding: 6px 4px !important;
        border: none !important;
        font-size: 0.75rem;
        color: var(--text-muted);
        text-align: left;
        flex: 1;
        white-space: nowrap;
    }
    .neural-suggestion-card {
        flex: 0 0 272px;
        max-width: 300px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-height: 118px;
        overflow: visible;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }
    .neural-suggestion-card h4 { font-size: 0.78rem !important; }
    .neural-suggestion-card p {
        font-size: 0.72rem !important;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1 1 auto;
        margin: 0;
    }
    .neural-suggestion-actions {
        display: flex;
        gap: 8px;
        padding-top: 6px;
        flex-shrink: 0;
        margin-top: auto;
    }
    .neural-suggestion-actions .btn-neural-accept,
    .neural-suggestion-actions .btn-neural-reject {
        min-height: 30px;
        padding: 6px 8px !important;
        font-size: 0.72rem !important;
    }

    .admin-os-overview {
        margin-bottom: 14px;
        padding: 14px 18px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }
    .admin-os-overview-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }
    .admin-os-overview-head h2 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .admin-os-overview-hint {
        margin: 0 0 10px;
        font-size: 0.78rem;
        color: var(--text-muted);
        line-height: 1.45;
    }
    .admin-os-overview-scroll {
        overflow-x: auto;
    }
    .admin-os-overview-table td,
    .admin-os-overview-table th {
        font-size: 0.78rem;
        padding: 8px 10px;
    }
    .admin-os-overview-table tr.is-current {
        background: #f0f9ff;
    }
    .admin-os-overview-table .tag-current {
        font-size: 0.65rem;
        font-weight: 800;
        color: #0369a1;
        margin-left: 6px;
    }

    .os-agent-strip {
        background: linear-gradient(135deg, #f0fdfa 0%, #ffffff 55%);
        border: 1px solid #99f6e4;
        border-radius: 12px;
        padding: 10px 14px;
        margin-bottom: 8px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .os-agent-strip.is-collapsed #os-agent-report-extra {
        display: none !important;
    }
    .os-agent-table-wrap {
        display: flex;
        flex-direction: column;
        min-height: 0;
        flex: 1 1 auto;
    }
    .os-agent-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .os-agent-title {
        margin: 0;
        font-size: 0.82rem;
        font-weight: 800;
        color: #0f766e;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .os-agent-meta {
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--text-muted);
    }
    .os-agent-bar-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .os-agent-mini-btn {
        padding: 4px 10px !important;
        font-size: 0.72rem !important;
        min-height: 28px !important;
    }
    .os-agent-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 10px 0;
    }
    .os-agent-chip {
        font-size: 0.72rem;
        padding: 4px 10px;
        border-radius: 999px;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }
    .os-agent-chip.warn { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
    .os-agent-chip.ok { background: #ecfdf5; border-color: #6ee7b7; color: #047857; }
    .os-agent-chip.muted { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
    .os-agent-briefing {
        padding: 12px 14px !important;
        margin-bottom: 10px;
        border-left: 4px solid #14b8a6 !important;
        background: #fff !important;
        max-height: min(140px, 22vh);
        overflow-y: auto;
        overscroll-behavior: contain;
    }
    .os-agent-briefing-head {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #0d9488;
        margin-bottom: 8px;
    }
    .os-agent-src {
        margin-left: auto;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: none;
    }
    .os-agent-briefing p {
        margin: 0;
        font-size: 0.85rem;
        line-height: 1.55;
        color: #334155;
    }
    .os-agent-loading {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        color: var(--text-muted);
        padding: 8px 0;
    }
    .os-agent-empty {
        font-size: 0.8rem;
        color: var(--text-muted);
        padding: 12px;
        text-align: center;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
    }
    .os-agent-decisions-scroll {
        max-height: none;
        overflow-x: auto;
        overflow-y: visible;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    .os-agent-table {
        font-size: 0.75rem;
        width: 100%;
        table-layout: fixed;
    }
    .os-agent-table td,
    .os-agent-table th {
        word-break: break-word;
        overflow-wrap: anywhere;
        vertical-align: top;
        padding: 6px 8px !important;
    }
    .os-agent-table th { font-size: 0.68rem; text-transform: uppercase; color: var(--text-muted); }
    .os-agent-action {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 0.65rem;
    }
    .os-agent-action-created, .os-agent-action-would_create { background: #d1fae5; color: #047857; }
    .os-agent-action-skip { background: #f1f5f9; color: #64748b; }
    .os-agent-table tr.row-highlight { background: #f0fdf4; }

    .dash-table-panel {
        flex: 0 1 auto;
        min-height: auto;
        display: flex;
        flex-direction: column;
    }
    .dash-table-scroll {
        overflow-x: auto;
        overflow-y: visible;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }
    .dash-workspace .card:hover {
        transform: none;
    }

    /* OFFCANVAS OS FORM (NEW UX) */
    #os-form-container {
        position: fixed;
        top: 0;
        right: -600px; /* Hidden by default */
        width: 100%;
        max-width: 600px;
        height: 100vh;
        z-index: 99999;
        background: #ffffff;
        box-shadow: -10px 0 30px rgba(0,0,0,0.1);
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        border-radius: 20px 0 0 20px !important;
        border-left: 1px solid var(--border) !important;
        padding: 0 !important;
    }

    #os-form-container.os-form-visible {
        right: 0 !important; /* Slided in */
        display: flex !important;
        max-height: 100vh !important;
    }

    /* OVERLAY FOR OFFCANVAS */
    #offcanvas-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        z-index: 99998;
        opacity: 0;
        transition: opacity 0.3s;
    }

    #offcanvas-overlay.active {
        display: block;
        opacity: 1;
    }

    /* LUB-TEK OPERATIONAL THEME */
    .mini-stat {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .mini-stat .label {
        font-size: 0.6rem;
        font-weight: 800;
        color: var(--text-muted);
        letter-spacing: 0.5px;
    }

    .mini-stat .val {
        font-family: 'Outfit', sans-serif;
        font-size: 1.2rem;
        font-weight: 800;
    }

    /* TABLE STYLES */
    .lub-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .lub-table thead th {
        position: sticky;
        top: 0;
        background: #f8fafc;
        padding: 8px 12px;
        text-align: left;
        color: var(--text-muted);
        font-weight: 700;
        text-transform: uppercase;
        border-bottom: 2px solid var(--border);
        z-index: 1;
        font-size: 0.7rem;
    }

    .lub-table tbody tr {
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        transition: 0.1s;
    }

    .lub-table tbody tr:hover {
        background: rgba(2, 132, 199, 0.03);
    }

    .lub-table tbody tr.selected {
        background: rgba(2, 132, 199, 0.08);
        border-left: 4px solid var(--primary);
    }

    .lub-table td {
        padding: 7px 12px;
    }

    /* FORM STYLES */
    .lub-form-row {
        display: grid;
        gap: 15px;
        align-items: end;
    }

    .field-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .field-item label {
        font-size: 0.68rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        margin: 0;
    }

    .field-item input,
    .field-item select,
    .field-item textarea {
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 6px;
        height: 34px;
        background: white;
    }

    .field-item textarea {
        height: auto;
    }

    /* FILTER BUTTONS */
    .filter-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 10px;
        cursor: pointer;
        border-radius: 4px;
    }

    .filter-btn.active {
        background: white;
        color: var(--primary);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    /* ANIMATIONS */
    @keyframes slideUp {
        from {
            transform: translateY(100%);
        }

        to {
            transform: translateY(0);
        }
    }

    #os-form-container {
        /* animation: slideUp 0.3s cubic-bezier(0, 1, 0, 1); */
        box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.05);
    }

    /* STATUS COLORS — definidos globalmente em assets/css/responsive.css (.st-pill) */

    /* RESPONSIVE DASHBOARD */
    @media (max-width: 1199px) {
        .dash-intel-column,
        .dash-orders-column {
            max-height: none;
            overflow: visible;
            position: static;
            display: block;
        }
        .dash-orders-column .dash-table-scroll,
        .os-agent-decisions-scroll {
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
        }
        .dash-toolbar {
            position: sticky;
            top: 0;
        }
    }

    @media (max-width: 900px) {
        #view-dash {
            height: auto !important;
            overflow-y: auto !important;
        }

        #os-form-container {
            height: auto !important;
            max-height: none !important;
        }
    }

    @media (max-width: 600px) {
        .mini-stat .val {
            font-size: 1rem;
        }

        .mini-stat .label {
            font-size: 0.55rem;
        }

        .lub-table {
            font-size: 0.7rem;
        }

        .lub-table thead th {
            padding: 8px 6px;
            font-size: 0.65rem;
        }

        .lub-table tbody td {
            padding: 8px 6px;
        }
    }

    @media (max-width: 960px) {
        .dash-toolbar > div:first-child { flex-wrap: wrap; gap: 12px; }
        .dash-toolbar > div:last-child { flex-wrap: wrap; }
        #os-form-container.os-form-visible { max-height: 65vh; }
    }

    #sap-sync-dropdown a:hover {
        background: #f8fafc !important;
        color: var(--primary) !important;
    }
</style>

<script>
    function toggleSAPDropdown() {
        const dd = document.getElementById('sap-sync-dropdown');
        if (dd) {
            dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
        }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const container = document.getElementById('sap-sync-dropdown-container');
        const dd = document.getElementById('sap-sync-dropdown');
        if (container && dd && !container.contains(e.target)) {
            dd.style.display = 'none';
        }
    });
</script>