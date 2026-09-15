<div id="view-kpi" class="view-container active view-block kpi-workspace" style="padding:15px; background:#f8fafc;">

    <div class="kpi-header">
        <div>
            <h1 style="margin:0; font-size:1.75rem; font-weight:800; letter-spacing:-0.5px; color:#0f172a;">Resumo da Fábrica</h1>
            <p style="margin:8px 0 0; font-size:0.9rem; color:#64748b; font-weight:600;">Visão simples do que está em dia e o que precisa de atenção</p>
            <div style="display:flex; align-items:center; gap:12px; margin-top:14px;">
                <span style="font-size:0.8rem; color:var(--text-muted); font-weight:700;">Unidade:</span>
                <select id="kpi-focus-selector" onchange="loadKpiView(this.value)"
                    style="border:1px solid var(--border); background:white; font-size:0.9rem; font-weight:700; color:var(--primary); padding:10px 16px; border-radius:10px; cursor:pointer; min-height:44px;">
                    <option value="">Toda a Planta</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Cartões de resumo diretos -->
    <div class="kpi-summary-grid">
        <div class="kpi-summary-card kpi-summary-health" id="kpi-card-health" onclick="if(typeof nav==='function')nav('assets')">
            <span class="kpi-summary-label">Saúde Geral da Planta</span>
            <span class="kpi-summary-value" id="kpi-summary-health-val">—</span>
            <span class="kpi-summary-hint" id="kpi-summary-health-hint">Cadastre equipamentos em Meus Ativos</span>
        </div>
        <div class="kpi-summary-card kpi-summary-pending" id="kpi-card-pending" onclick="if(typeof nav==='function')nav('dash')">
            <span class="kpi-summary-label">Pendências de Hoje</span>
            <span class="kpi-summary-value" id="kpi-summary-pending-val">—</span>
            <span class="kpi-summary-hint" id="kpi-summary-pending-hint">Clique para ver as ordens</span>
        </div>
        <div class="kpi-summary-card kpi-summary-cost">
            <span class="kpi-summary-label">Custo Estimado do Mês</span>
            <span class="kpi-summary-value">R$ <span id="label-financial">0,00</span></span>
            <span class="kpi-summary-hint" id="kpi-summary-cost-hint">Estimativa pelos pontos cadastrados</span>
        </div>
        <div class="kpi-summary-card">
            <span class="kpi-summary-label">MTBF / MTTR (12 meses)</span>
            <span class="kpi-summary-value" id="kpi-summary-mtbf-val">—</span>
            <span class="kpi-summary-hint" id="kpi-summary-mtbf-hint">Calculado só com O.S. corretivas reais</span>
        </div>
    </div>

    <!-- Estado vazio amigável -->
    <div id="kpi-empty-state" class="kpi-empty-state" style="display:none;">
        <i data-lucide="check-circle-2" style="width:56px; height:56px; color:#10b981;"></i>
        <h3 id="kpi-empty-title">Nenhuma atividade registrada este mês</h3>
        <p id="kpi-empty-msg">Assim que as primeiras rotas de lubrificação forem concluídas no campo, os gráficos de saúde dos ativos e custos aparecerão automaticamente aqui.</p>
        <button type="button" class="btn btn-action" onclick="nav('routes')" style="margin-top:16px; min-height:44px; padding:0 24px; background:var(--primary); color:#fff; border:none; border-radius:10px; font-weight:700; cursor:pointer;">
            Ir para Rotas de Campo
        </button>
    </div>

    <!-- AI ADVISOR BAR -->
    <div id="ai-advisor-container" style="display:none; margin-bottom:24px;">
        <div class="card" style="border-left:5px solid #0ea5e9; background:linear-gradient(90deg,#f0f9ff 0%,#fff 100%); padding:20px 24px; border-radius:14px; display:flex; align-items:center; gap:16px; box-shadow:0 4px 15px rgba(14,165,233,0.08); border:1px solid rgba(14,165,233,0.15);">
            <div style="background:#0ea5e9; color:white; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="lightbulb" style="width:26px; height:26px;"></i>
            </div>
            <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
                    <span style="font-size:0.75rem; font-weight:800; color:#0ea5e9; text-transform:uppercase;">Dica do Sistema</span>
                    <span id="ai-severity-badge" class="tag" style="font-size:0.65rem; padding:3px 10px; border-radius:20px; font-weight:800;">Analisando...</span>
                </div>
                <div id="ai-insight-text" style="font-size:1rem; font-weight:800; color:#1e293b;">Aguardando dados...</div>
                <div id="ai-recommendation-text" style="font-size:0.85rem; color:#64748b; margin-top:4px; font-weight:600;"></div>
            </div>
        </div>
    </div>

    <!-- Gráficos (ocultos quando vazio) -->
    <div id="kpi-charts-section">
        <div class="grid-3" style="margin-bottom:24px; gap:20px;">
            <div class="card" style="padding:24px; border-radius:16px;">
                <h3 class="kpi-chart-title">Máquinas e Equipamentos</h3>
                <div style="height:200px; position:relative;">
                    <canvas id="chart-health"></canvas>
                </div>
            </div>
            <div class="card" style="padding:24px; text-align:center; border-radius:16px;">
                <h3 class="kpi-chart-title">Tarefas Feitas no Prazo</h3>
                <div style="height:200px; position:relative; display:flex; justify-content:center; align-items:center;">
                    <canvas id="chart-adherence"></canvas>
                    <div style="position:absolute; font-size:2.2rem; font-weight:900; color:var(--text-main);">
                        <span id="label-adherence">—</span>
                    </div>
                </div>
            </div>
            <div class="card" style="padding:24px; border-radius:16px;">
                <h3 class="kpi-chart-title">O que Falta Fazer</h3>
                <div style="height:200px;">
                    <canvas id="chart-backlog"></canvas>
                </div>
            </div>
        </div>
        <div class="grid-2" style="gap:20px;">
            <div class="card" style="padding:24px; border-radius:16px;">
                <h3 class="kpi-chart-title">Óleos e Graxas Usados</h3>
                <div style="height:220px;">
                    <canvas id="chart-consumption"></canvas>
                </div>
            </div>
            <div class="card" style="padding:24px; border-radius:16px;">
                <h3 class="kpi-chart-title">Principais Problemas Encontrados</h3>
                <div style="height:220px;">
                    <canvas id="chart-causes"></canvas>
                </div>
            </div>
        </div>
    </div>

    <style>
        .kpi-header { margin-bottom:24px; }
        .kpi-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 1100px) {
            .kpi-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .kpi-summary-card {
            background: white;
            border-radius: 16px;
            padding: 20px 22px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .kpi-summary-card[onclick] { cursor: pointer; }
        .kpi-summary-card[onclick]:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }
        .kpi-summary-label {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
        }
        .kpi-summary-value {
            font-size: 1.65rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.1;
        }
        .kpi-summary-hint { font-size: 0.8rem; color: #94a3b8; font-weight: 600; }
        .kpi-summary-health .kpi-summary-value { color: #10b981; }
        .kpi-summary-pending .kpi-summary-value { color: #ef4444; }
        .kpi-chart-title {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 16px;
            letter-spacing: 0.4px;
        }
        .kpi-empty-state {
            text-align: center;
            padding: 48px 32px;
            background: white;
            border-radius: 16px;
            border: 1px dashed #cbd5e1;
            margin-bottom: 24px;
            color: #64748b;
        }
        .kpi-empty-state h3 {
            margin: 16px 0 8px;
            color: #1e293b;
            font-size: 1.25rem;
        }
        .kpi-empty-state p {
            max-width: 480px;
            margin: 0 auto;
            line-height: 1.55;
            font-size: 0.95rem;
        }
        @media (max-width: 900px) {
            .kpi-summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</div>
