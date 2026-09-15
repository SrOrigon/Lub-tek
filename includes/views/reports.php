<div id="view-reports" class="view-container active view-block"
    style="padding: 24px; background: #f8fafc;">
    
    <div class="flex-between" style="margin-bottom:30px;">
        <div>
            <h1 style="margin:0; font-size: 2.2rem; color: #0f172a; font-weight: 800; letter-spacing: -1px;">Exportar Documentos</h1>
            <p style="color:var(--text-muted); margin-top:5px; font-size: 0.95rem;">Extração e exportação de dados para suporte à decisão estratégica.</p>
        </div>
    </div>

    <!-- Responsive Grid of Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; width: 100%;">
        
        <!-- Card 1: Catálogo de Itens -->
        <div class="card" style="padding: 25px; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; background: white;">
            <div>
                <div style="display:flex; gap:15px; align-items:center; margin-bottom:20px;">
                    <div style="background:rgba(16,185,129,0.1); padding:12px; border-radius:12px;">
                        <i data-lucide="package" style="color:var(--success); width:28px; height:28px;"></i>
                    </div>
                    <h3 style="margin:0; font-weight: 800; font-size: 1.2rem; color: #0f172a; text-transform: none; letter-spacing: normal;">Catálogo de Itens</h3>
                </div>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:25px; line-height: 1.6;">Lista completa de peças, rolamentos e lubrificantes com estoque atual.</p>
            </div>
            <div class="flex-between" style="gap: 12px;">
                <button class="btn btn-outline" style="flex:1; padding: 12px; font-weight: 700; border-radius: 10px;" onclick="exportCatalog('excel')"><i
                        data-lucide="file-spreadsheet" style="margin-right: 8px;"></i> EXCEL</button>
                <button class="btn btn-outline" style="flex:1; padding: 12px; font-weight: 700; border-radius: 10px;" onclick="exportCatalog('pdf')"><i
                        data-lucide="file-text" style="margin-right: 8px;"></i> PDF</button>
            </div>
        </div>

        <!-- Card 2: Ordens de Serviço -->
        <div class="card" style="padding: 25px; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; background: white;">
            <div>
                <div style="display:flex; gap:15px; align-items:center; margin-bottom:20px;">
                    <div style="background:rgba(245,158,11,0.1); padding:12px; border-radius:12px;">
                        <i data-lucide="clipboard-list" style="color:var(--warning); width:28px; height:28px;"></i>
                    </div>
                    <h3 style="margin:0; font-weight: 800; font-size: 1.2rem; color: #0f172a; text-transform: none; letter-spacing: normal;">Ordens de Serviço</h3>
                </div>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:25px; line-height: 1.6;">Relatório de tarefas pendentes e concluídas com responsáveis.</p>
            </div>
            <div class="flex-between" style="gap: 12px;">
                <button class="btn btn-outline" style="flex:1; padding: 12px; font-weight: 700; border-radius: 10px;" onclick="exportTasks('excel')"><i
                        data-lucide="file-spreadsheet" style="margin-right: 8px;"></i> EXCEL</button>
                <button class="btn btn-outline" style="flex:1; padding: 12px; font-weight: 700; border-radius: 10px;" onclick="exportTasks('pdf')"><i
                        data-lucide="file-text" style="margin-right: 8px;"></i> PDF</button>
            </div>
        </div>

        <!-- Card 3: Estrutura da Planta -->
        <div class="card" style="padding: 25px; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; background: white;">
            <div>
                <div style="display:flex; gap:15px; align-items:center; margin-bottom:20px;">
                    <div style="background:rgba(14,165,233,0.1); padding:12px; border-radius:12px;">
                        <i data-lucide="network" style="color:var(--primary); width:28px; height:28px;"></i>
                    </div>
                    <h3 style="margin:0; font-weight: 800; font-size: 1.2rem; color: #0f172a; text-transform: none; letter-spacing: normal;">Estrutura da Planta</h3>
                </div>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:25px; line-height: 1.6;">Hierarquia completa de equipamentos e ativos cadastrados.</p>
            </div>
            <button class="btn btn-outline" style="width:100%; padding: 12px; font-weight: 700; border-radius: 10px;" onclick="printPage()"><i data-lucide="printer" style="margin-right: 8px;"></i>
                IMPRIMIR DIGITAL TWIN</button>
        </div>

        <!-- Card 4: Conector SAP ERP (Highlighted Module) -->
        <div class="card" style="padding: 25px; border-radius: 16px; border: 2px solid rgba(2, 132, 199, 0.15); background: linear-gradient(to bottom, #ffffff, #f0f9ff); display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 10px 30px rgba(2, 132, 199, 0.05); transition: all 0.2s;">
            <div>
                <div style="display:flex; gap:15px; align-items:center; margin-bottom:20px;">
                    <div style="background:rgba(2, 132, 199, 0.1); padding:12px; border-radius:12px;">
                        <i data-lucide="refresh-cw" style="color:var(--primary); width:28px; height:28px;"></i>
                    </div>
                    <h3 style="margin:0; font-weight: 800; font-size: 1.2rem; color: var(--primary); text-transform: none; letter-spacing: normal;">Conector SAP ERP</h3>
                </div>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:25px; line-height: 1.6;">Importação e exportação de dados estruturados para SAP PM (IW39/IH01) e MM (MM60).</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <button class="btn" style="width:100%; padding: 12px; font-weight: 700; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; display: flex; align-items: center; justify-content: center;" onclick="window.location.href='?page=sap&tab=import'">
                    <i data-lucide="upload" style="width:16px; margin-right: 8px;"></i> IMPORTAR DO SAP
                </button>
                <button class="btn btn-outline" style="width:100%; padding: 12px; font-weight: 700; border-radius: 10px; border-color: var(--primary); color: var(--primary); background: transparent; display: flex; align-items: center; justify-content: center;" onclick="window.location.href='?page=sap&tab=export'">
                    <i data-lucide="download" style="width:16px; margin-right: 8px;"></i> EXPORTAR PARA O SAP
                </button>
            </div>
        </div>

        <!-- Card 5: Consumo por equipamento (novo — não altera os demais) -->
        <div class="card" style="padding: 25px; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; background: white; border: 1px solid rgba(14,165,233,0.25);">
            <div>
                <div style="display:flex; gap:15px; align-items:center; margin-bottom:20px;">
                    <div style="background:rgba(14,165,233,0.1); padding:12px; border-radius:12px;">
                        <i data-lucide="fuel" style="color:var(--primary); width:28px; height:28px;"></i>
                    </div>
                    <h3 style="margin:0; font-weight: 800; font-size: 1.2rem; color: #0f172a; text-transform: none; letter-spacing: normal;">Consumo por Equipamento</h3>
                </div>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:25px; line-height: 1.6;">Demonstrativo individual de lubrificantes (dose × frequência) por máquina, com relatório em tela, Excel e PDF.</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <button class="btn" style="width:100%; padding: 12px; font-weight: 700; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border:none; color:#fff;" onclick="openLubricantConsumptionReport()">
                    <i data-lucide="bar-chart-3" style="width:16px; margin-right:8px;"></i> VER RELATÓRIO
                </button>
                <div class="flex-between" style="gap:12px;">
                    <button class="btn btn-outline" style="flex:1; padding: 12px; font-weight: 700; border-radius: 10px;" onclick="exportLubricantConsumption('excel')"><i data-lucide="file-spreadsheet" style="margin-right:8px;"></i> EXCEL</button>
                    <button class="btn btn-outline" style="flex:1; padding: 12px; font-weight: 700; border-radius: 10px;" onclick="exportLubricantConsumption('pdf')"><i data-lucide="file-text" style="margin-right:8px;"></i> PDF</button>
                </div>
            </div>
        </div>

        <!-- Card 6: Plano de Lubrificação Executivo -->
        <div class="card" style="padding: 25px; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; background: white; border: 2px solid rgba(220, 38, 38, 0.12);">
            <div>
                <div style="display:flex; gap:15px; align-items:center; margin-bottom:20px;">
                    <div style="background:rgba(220,38,38,0.08); padding:12px; border-radius:12px;">
                        <i data-lucide="book-open" style="color:#dc2626; width:28px; height:28px;"></i>
                    </div>
                    <h3 style="margin:0; font-weight: 800; font-size: 1.2rem; color: #0f172a;">Plano de Lubrificação</h3>
                </div>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:25px; line-height: 1.6;">
                    Documento executivo com fotos, equipamentos, componentes e todos os pontos catalogados — sem limite de itens.
                </p>
            </div>
            <button class="btn" style="width:100%; padding: 12px; font-weight: 700; border-radius: 10px; background: linear-gradient(135deg, #dc2626, #b91c1c); border:none; color:#fff;" onclick="window.location.href='?page=assets'">
                <i data-lucide="file-text" style="width:16px; margin-right:8px;"></i> IR PARA MEUS ATIVOS
            </button>
        </div>
    </div>

    <div id="lub-consumption-panel" class="lub-cons-panel" style="display:none;" aria-live="polite">
        <div class="lub-cons-toolbar">
            <div>
                <h2 style="margin:0;font-size:1.15rem;font-weight:800;color:#0f172a;">Relatório de consumo de lubrificantes</h2>
                <p id="lub-cons-meta" style="margin:4px 0 0;font-size:0.82rem;color:#64748b;font-weight:600;"></p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <select id="lub-cons-period" class="lub-cons-select" onchange="openLubricantConsumptionReport(true)">
                    <option value="month">Estimativa mensal</option>
                    <option value="year">Estimativa anual</option>
                </select>
                <input type="search" id="lub-cons-filter" class="lub-cons-select" placeholder="Filtrar equipamento ou lubrificante..." oninput="filterLubricantConsumptionTable()" style="min-width:220px;">
                <button type="button" class="btn btn-outline" style="min-height:40px;" onclick="printLubricantConsumption()">Imprimir</button>
            </div>
        </div>
        <div id="lub-cons-kpis" class="lub-cons-kpis"></div>
        <div id="lub-cons-body"></div>
    </div>

    <style>
        .lub-cons-panel {
            margin-top: 28px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(15,23,42,0.04);
        }
        .lub-cons-toolbar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .lub-cons-select {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            font-weight: 700;
            color: #0369a1;
            min-height: 40px;
            background: #f8fafc;
        }
        .lub-cons-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }
        .lub-cons-kpi {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
        }
        .lub-cons-kpi span { display:block; font-size:0.68rem; font-weight:800; color:#64748b; text-transform:uppercase; }
        .lub-cons-kpi strong { font-size:1.2rem; font-weight:900; color:#0f172a; }
        .lub-cons-table-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:20px; }
        .lub-cons-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
        .lub-cons-table th { background:#0f172a; color:#fff; text-align:left; padding:10px 12px; font-size:0.7rem; letter-spacing:0.3px; text-transform:uppercase; }
        .lub-cons-table td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#334155; }
        .lub-cons-table tr:hover td { background:#f0f9ff; }
        .lub-cons-eq { font-weight:800; color:#0f172a; }
        .lub-cons-h { font-size:0.85rem; font-weight:800; color:#0369a1; margin:0 0 10px; text-transform:uppercase; }
        @media print {
            .flex-between, .lub-cons-toolbar button, .lub-cons-select { display: none !important; }
            .lub-cons-panel { box-shadow:none; border:none; }
        }
    </style>
</div>