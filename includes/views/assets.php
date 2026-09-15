<div id="view-assets" class="view-container active view-block assets-workspace">
    <style>
        .assets-workspace {
            display: none; /* Fallback override */
            flex-direction: column;
            height: 100%;
            max-height: 100%;
            overflow: hidden;
            padding: 12px 16px 16px;
            box-sizing: border-box;
        }
        #view-assets.assets-workspace.active {
            display: flex !important;
            overflow: hidden !important;
            padding: 12px 16px 16px !important;
        }
        .assets-page-toolbar {
            flex-shrink: 0;
            margin-bottom: 12px;
        }
        .assets-main-layout {
            flex: 1;
            min-height: 0;
            display: flex;
            gap: 16px;
            align-items: stretch;
        }
        /* Painel esquerdo — flutuante, expansivo, scroll proprio */
        #asset-tree-panel {
            flex: 0 0 clamp(280px, 26vw, 380px);
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 100%;
            padding: 0;
            overflow: hidden;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.09), 0 2px 10px rgba(15, 23, 42, 0.04);
            position: relative;
            z-index: 2;
        }
        #asset-tree-container {
            flex: 1 1 auto;
            min-height: 60px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px 12px 16px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        #asset-tree-container::-webkit-scrollbar { width: 6px; }
        #asset-tree-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        /* Painel direito — maior, estatico, scroll interno */
        .asset-detail-panel {
            flex: 1 1 auto;
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        #asset-empty {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            overflow-y: auto;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: radial-gradient(circle at center, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }
        #asset-form {
            flex: 1;
            min-height: 0;
            display: none;
            flex-direction: column;
            overflow: hidden;
            padding: 0;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.05);
        }
        .asset-form-header-block {
            flex-shrink: 0;
            background: #fff;
            border-bottom: 1px solid var(--border, #e2e8f0);
            z-index: 5;
        }
        .asset-form-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 transparent;
        }
        .asset-form-scroll::-webkit-scrollbar { width: 8px; }
        .asset-form-scroll::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 8px; }
        .asset-sticky-header {
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(8px);
        }
        @media (max-width: 960px) {
            .assets-main-layout { flex-direction: column; }
            #asset-tree-panel {
                flex: 0 0 auto;
                max-height: min(42vh, 380px);
                width: 100%;
            }
            .asset-detail-panel { min-height: 50vh; }
        }
    </style>
    <!-- HEADER -->
    <div class="flex-between assets-page-toolbar" style="flex-wrap:wrap; gap:15px;">
        <div style="min-width:300px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h1 style="margin:0; font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px;">Máquinas e Equipamentos</h1>
                <span style="background: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 0.75rem;">Visão Geral da Planta</span>
            </div>
            <p style="color:var(--text-muted); margin:5px 0 0 0; font-size:0.9rem;">Clique em um item da árvore estrutural à esquerda para ver os detalhes técnicos, plano de lubrificação e histórico.</p>
        </div>

        <!-- Barra simplificada — ferramentas avançadas ficam no menu admin -->
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <button class="btn btn-outline" onclick="document.getElementById('excel-import-input').click()"
                style="padding:8px 16px; font-size:0.85rem; font-weight:600;"
                title="Importar planilha Excel">
                <i data-lucide="upload" style="width:16px; margin-right:6px;"></i> Importar
            </button>
            <button class="btn btn-outline" onclick="exportAssetsExcelRobust()"
                style="padding:8px 16px; font-size:0.85rem; font-weight:600;"
                title="Baixar backup em Excel">
                <i data-lucide="download" style="width:16px; margin-right:6px;"></i> Baixar Excel
            </button>
            <button class="btn btn-outline" onclick="saveSystemSnapshot()" title="Salvar backup do sistema"
                style="padding:8px 12px; font-weight:600; color:var(--primary); border-color:var(--primary);">
                <i data-lucide="save" style="width:16px; margin-right:4px;"></i> Salvar Estado
            </button>
            <button class="btn btn-outline" onclick="wipeDatabase()" title="Apagar tudo e reiniciar"
                style="padding:8px 12px; font-weight:600; color:var(--danger); border-color:var(--danger);">
                <i data-lucide="trash-2" style="width:16px; margin-right:4px;"></i> Limpar Tudo
            </button>
            <button class="btn btn-outline" onclick="loadTree(true)" title="Atualizar lista"
                style="padding:8px 12px;">
                <i data-lucide="refresh-cw" style="width:16px;"></i>
            </button>

            <!-- Menu Ferramentas (só admin/developer) -->
            <div style="position:relative;" data-admin-only>
                <button class="btn btn-outline" onclick="toggleAdminToolsMenu()"
                    style="padding:8px 14px; font-size:0.85rem; font-weight:600; color:var(--text-muted);">
                    <i data-lucide="settings" style="width:16px; margin-right:6px;"></i> Ferramentas
                    <i data-lucide="chevron-down" style="width:14px; margin-left:4px;"></i>
                </button>
                <div id="admin-tools-menu"
                    style="display:none; position:absolute; top:100%; right:0; margin-top:6px; background:white; border:1px solid var(--border); border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.12); z-index:200; flex-direction:column; min-width:220px; padding:8px; gap:4px;">
                    <button class="btn btn-ghost" onclick="bulkAutoIP(); toggleAdminToolsMenu();"
                        style="justify-content:flex-start; width:100%; text-align:left; padding:10px 12px; font-size:0.85rem;">
                        <i data-lucide="hash" style="width:16px;"></i> Gerar códigos IP
                    </button>
                    <button class="btn btn-ghost" onclick="cleanupUnnamed(); toggleAdminToolsMenu();"
                        style="justify-content:flex-start; width:100%; text-align:left; padding:10px 12px; font-size:0.85rem;">
                        <i data-lucide="eraser" style="width:16px;"></i> Limpar itens vazios
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden Input for Import -->
        <input type="file" id="excel-import-input" accept=".xlsx,.xls,.csv" style="display:none;"
            onchange="importAssetsFromExcel(this)">
    </div>

    <div class="assets-main-layout">
        <!-- TREE PANEL — flutuante, scroll independente -->
        <div id="asset-tree-panel" class="card">

            <!-- SEARCH BAR (Strategic Navigation) -->
            <div style="padding:12px; border-bottom:1px solid var(--border); background:white;">
                <div style="position:relative;">
                    <i data-lucide="search"
                        style="position:absolute; left:10px; top:10px; width:16px; color:var(--text-muted);"></i>
                    <input type="text" id="tree-search-input" placeholder="Buscar unidade, tag ou equipamento..."
                        oninput="filterTree(this.value)"
                        style="width:100%; padding:8px 8px 8px 36px; border:1px solid var(--border); border-radius:6px; font-size:0.9rem;">
                </div>
            </div>

            <div style="padding:12px 16px; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.02);">
                <div class="flex-between">
                    <h3
                        style="margin:0; color:var(--text-main); font-size:0.9rem; text-transform:uppercase; letter-spacing:0.5px;">
                        Estrutura da Planta</h3>
                    <button class="btn btn-primary" style="padding:6px 12px; font-size:0.75rem; background:linear-gradient(135deg, var(--primary), var(--primary-dark)); border:none; display:flex; align-items:center; gap:6px; font-weight:700; border-radius:6px; box-shadow:0 2px 4px rgba(2, 132, 199, 0.1);" onclick="addNode(null)">
                        <i data-lucide="plus-circle" style="width:14px; height:14px;"></i> Nova Fábrica
                    </button>
                </div>
            </div>
            <div id="asset-tree-container"
                ondragover="e = event; e.preventDefault(); e.dataTransfer.dropEffect = 'move';"
                ondrop="handleAssetDrop(event, null)">
                <!-- JS renders tree here -->
            </div>
        </div>

        <!-- PAINEL DIREITO — formulario estatico com scroll proprio -->
        <div class="asset-detail-panel">

        <!-- DETAILS PANEL (Premium Empty State) -->
        <div class="card" id="asset-empty">
            <div style="padding:40px; border-radius:50%; background:rgba(14,165,233,0.03); margin-bottom:20px; border:1px solid rgba(14,165,233,0.08);">
                <i data-lucide="network" style="width:80px; height:80px; opacity:0.4; color:var(--primary); filter: drop-shadow(0 0 10px rgba(14,165,233,0.2));"></i>
            </div>
            <h2 style="color:var(--text-main); font-weight:800; font-size:1.6rem; margin-top:10px; letter-spacing:-0.5px;">Selecione um ativo</h2>
            <p style="color:var(--text-muted); max-width:420px; line-height:1.6; font-size:0.95rem;">Escolha uma fábrica, equipamento ou ponto de lubrificação na lista ao lado. Os dados aparecerão aqui para consulta ou edição.</p>
            <p style="color:var(--text-muted); max-width:420px; line-height:1.6; font-size:0.8rem; margin-top:12px;">Use o botão <strong>Início</strong> no topo para voltar ao painel principal.</p>
            
            <div data-power-hint style="display:none; margin-top:20px; padding:12px 18px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; font-size:0.85rem; color:#1e40af; max-width:420px;">
                <strong>Dica:</strong> Use a busca acima da lista para encontrar equipamentos pelo nome ou TAG.
            </div>
        </div>

        <!-- FORM PANEL -->
        <div class="card" id="asset-form">

            <div class="asset-form-header-block">
            <!-- STICKY HEADER -->
            <div class="asset-sticky-header"
                style="display:flex; align-items:center; gap:15px; padding:12px; background:rgba(255,255,255,0.95); border-bottom:1px solid var(--border);">
                <!-- Thumbnail Container (Small & Clickable) -->
                <div
                    style="width:60px; height:60px; min-width:60px; border-radius:8px; overflow:hidden; border:1px solid var(--border); position:relative; background:var(--bg-dark);">
                    <img id="ash-header-img" src="<?php echo htmlspecialchars($companyLogo ?? 'assets/img/system/img_69581d7fcdbbf.jpeg'); ?>"
                        style="width:100%; height:100%; object-fit:cover; display:block; cursor:zoom-in;"
                        onclick="openImageModal(this.src)">

                    <!-- Camera Overlay -->
                    <div style="position:absolute; bottom:0; left:0; right:0; height:20px; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; cursor:pointer;"
                        onclick="triggerUpload('af-img-input')" title="Alterar Imagem">
                        <i data-lucide="camera" style="width:12px; height:12px; color:white;"></i>
                    </div>
                </div>

                <!-- Info Container -->
                <div style="flex:1; display:flex; flex-direction:column; justify-content:center;">
                    <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">Localização
                        / Ativo</span>
                    <div id="ash-path-display" class="ash-path"
                        style="font-size:1rem; font-weight:700; color:var(--text-main); line-height:1.2;">
                        CARREGANDO...
                    </div>
                </div>

                <!-- Print Button -->
                <div style="display:flex; gap:10px;">
                    <button class="ash-btn-print" onclick="analyzeAssetAI()"
                        title="Verificar cadastro com IA"
                        style="background:#8b5cf6; color:white; border:none; border-radius:6px; padding:8px 12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <i data-lucide="sparkles" style="width:16px;"></i> <span style="font-size:0.8rem;">Verificar IA</span>
                    </button>
                    <button class="ash-btn-print" onclick="printQuickReport()"
                        style="background:var(--primary); color:white; border:none; border-radius:6px; padding:8px 12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <i data-lucide="printer" style="width:16px;"></i> <span style="font-size:0.8rem;">Imprimir</span>
                    </button>
                </div>
            </div>

            <!-- TOOLBAR / HEADER -->
            <div style="padding:16px; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.05);">
                <div class="flex-between" style="flex-wrap:wrap; gap:10px;">
                    <h2 id="af-title" style="margin:0; color:var(--primary); font-size:1.2rem; white-space:nowrap;">
                        Editar Item</h2>
                    <div style="display:flex; gap:12px; flex-wrap:wrap; justify-content:flex-end; align-items:center;">

                        <!-- GROUP: SYSTEM OPS -->
                        <div
                            style="display:flex; gap:4px; padding:4px; background:rgba(0,0,0,0.03); border-radius:8px;">
                            <button id="btn-toggle-asset-full" class="btn btn-outline" onclick="toggleFullWidth()"
                                title="Foco Total" style="border:none; padding:8px;"><i data-lucide="maximize-2"
                                    style="width:18px;"></i></button>
                            <button class="btn btn-outline" onclick="if(typeof duplicateAssetPoint==='function'&&typeof selectedNodeId!=='undefined')duplicateAssetPoint(selectedNodeId)" title="Duplicar Ponto"
                                style="border:none; color:#10b981; padding:8px;"><i data-lucide="copy"
                                    style="width:18px;"></i></button>
                            <button class="btn btn-outline" onclick="open3DView()" title="Engenharia 3D"
                                style="border:none; color:#0ea5e9; padding:8px;"><i data-lucide="box"
                                    style="width:18px;"></i></button>
                            <button class="btn btn-outline" onclick="deleteCurrentNode()" title="Excluir"
                                style="border:none; color:var(--danger); padding:8px;"><i data-lucide="trash-2"
                                    style="width:18px;"></i></button>
                        </div>

                        <!-- GROUP: ADVANCED ACTIONS (Dropdown) -->
                        <div style="position:relative;">
                            <button class="btn btn-outline"
                                onclick="document.getElementById('adv-actions-menu').style.display = document.getElementById('adv-actions-menu').style.display === 'flex' ? 'none' : 'flex'"
                                title="Ações Avançadas" style="gap:8px;">
                                <i data-lucide="more-horizontal"></i> <span>Ações Avançadas</span>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="adv-actions-menu"
                                style="display:none; position:absolute; top:45px; right:0; background:white; border:1px solid var(--border); border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:100; flex-direction:column; min-width:220px; padding:8px; gap:4px;">

                                <div
                                    style="font-size:0.7rem; color:var(--text-muted); padding:4px 8px; font-weight:700;">
                                    FINANCEIRO & DADOS</div>
                                <button class="btn btn-ghost" onclick="exportDetailedPlanExcel()"
                                    style="justify-content:flex-start; width:100%; text-align:left; padding:8px;">
                                    <i data-lucide="file-spreadsheet" style="width:16px;"></i> Financeiro XLSX
                                </button>
                                <button class="btn btn-ghost" onclick="openLubricationPlanModal()"
                                    style="justify-content:flex-start; width:100%; text-align:left; padding:8px; color:#0284c7; font-weight:700;">
                                    <i data-lucide="file-text" style="width:16px;"></i> Plano de Lubrificação PDF
                                </button>
                                <button class="btn btn-ghost" onclick="exportLabelSheet()"
                                    style="justify-content:flex-start; width:100%; text-align:left; padding:8px;">
                                    <i data-lucide="tags" style="width:16px;"></i> Etiquetas XLSX
                                </button>

                                <div style="border-top:1px solid var(--border); margin:4px 0;"></div>

                                <div
                                    style="font-size:0.7rem; color:var(--text-muted); padding:4px 8px; font-weight:700;">
                                    RELATÓRIOS & MAPAS</div>
                                <button class="btn btn-ghost" onclick="printQuickReport()"
                                    style="justify-content:flex-start; width:100%; text-align:left; padding:8px;">
                                    <i data-lucide="printer" style="width:16px;"></i> Laudo Técnico
                                </button>
                                <button class="btn btn-ghost" onclick="exportChecklistExcel('Quinzenal')"
                                    style="justify-content:flex-start; width:100%; text-align:left; padding:8px;">
                                    <i data-lucide="check-square" style="width:16px;"></i> Checklist Quinzenal
                                </button>
                                <button class="btn btn-ghost" onclick="exportChecklistExcel('Mensal')"
                                    style="justify-content:flex-start; width:100%; text-align:left; padding:8px;">
                                    <i data-lucide="check-square" style="width:16px;"></i> Checklist Mensal
                                </button>
                                <button class="btn btn-ghost" onclick="printAssetMap()"
                                    style="justify-content:flex-start; width:100%; text-align:left; padding:8px;">
                                    <i data-lucide="map" style="width:16px;"></i> Mapa de Ativos
                                </button>
                            </div>
                        </div>

                        <button class="btn btn-primary" onclick="saveToCloudSingle()"
                            style="padding:10px 24px; border-radius:10px;"><i data-lucide="save"></i>
                            <b>Salvar</b></button>
                    </div>
                </div>

                <!-- RELIABILITY & AI MINI-DASHBOARD (Real-Time Data) -->
                <!-- RELIABILITY & AI MINI-DASHBOARD (Compact) -->
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-top:10px;">
                    <!-- Card 1: Saúde do Ativo -->
                    <div class="card" id="reliability-card"
                        style="padding:8px 12px; border-left:3px solid #10b981; display:flex; align-items:center; gap:10px;">
                        <div style="background:rgba(16,185,129,0.1); padding:6px; border-radius:8px;">
                            <i data-lucide="heart-pulse" style="color:#10b981; width:18px;"></i>
                        </div>
                        <div>
                            <div style="font-size:0.6rem; color:var(--text-muted); text-transform:uppercase;">Saúde do Ativo</div>
                            <div id="reliability-status" style="font-weight:700; font-size:0.8rem; line-height:1.2;">Carregando...</div>
                        </div>
                    </div>

                    <!-- Card 2: IA Predict -->
                    <div class="card"
                        style="padding:8px 12px; border-left:3px solid #8b5cf6; display:flex; align-items:center; gap:10px;">
                        <div style="background:rgba(139,92,246,0.1); padding:6px; border-radius:8px;">
                            <i data-lucide="brain-circuit" style="color:#8b5cf6; width:18px;"></i>
                        </div>
                        <div>
                            <div style="font-size:0.6rem; color:var(--text-muted); text-transform:uppercase;">Previsão IA — Próxima Falha</div>
                            <div id="next-failure" style="font-weight:700; font-size:0.8rem; line-height:1.2;">Calculando...</div>
                        </div>
                    </div>

                    <!-- Card 3: Impacto Sustentável -->
                    <div class="card"
                        style="padding:8px 12px; border-left:3px solid #0ea5e9; display:flex; align-items:center; gap:10px;">
                        <div style="background:rgba(14,165,233,0.1); padding:6px; border-radius:8px;">
                            <i data-lucide="leaf" style="color:#0ea5e9; width:18px;"></i>
                        </div>
                        <div>
                            <div style="font-size:0.6rem; color:var(--text-muted); text-transform:uppercase;">Impacto
                                Sustentável</div>
                            <div id="co2-impact" style="font-weight:700; font-size:0.8rem; line-height:1.2;">--.-- kg CO2 / Ano</div>
                        </div>
                    </div>
                </div>

                <!-- TABS -->
                <div style="display:flex; gap:20px; margin-top:20px; border-bottom:1px solid transparent; flex-wrap:wrap;">
                    <button class="tab-btn active asset-tab" onclick="setAssetTab('dados', this)">Dados do Ativo</button>
                    <button class="tab-btn asset-tab" onclick="setAssetTab('lub', this)">Lubrificação</button>
                    <button class="tab-btn asset-tab" onclick="setAssetTab('market_tab', this)">Mercado</button>
                    <button class="tab-btn asset-tab" onclick="setAssetTab('monitor', this)">Análise de Óleo</button>
                    <button class="tab-btn asset-tab" onclick="setAssetTab('hist', this)">Histórico</button>
                    <button class="btn btn-sm btn-outline" style="margin-left:auto; padding:4px 8px; font-size:0.8rem;"
                        onclick="generateAssetQRCode()">
                        <i data-lucide="qr-code"></i> Etiqueta
                    </button>
                </div>
                <style>
                    .tab-btn {
                        background: none;
                        border: none;
                        color: var(--text-muted);
                        padding-bottom: 8px;
                        cursor: pointer;
                        font-weight: 700;
                        /* Bold as requested */
                        border-bottom: 2px solid transparent;
                    }

                    .tab-btn.active {
                        color: var(--primary);
                        border-color: var(--primary);
                    }

                    .lub-label {
                        display: block;
                        color: var(--text-muted);
                        font-size: 0.8rem;
                        margin-bottom: 4px;
                        font-weight: 700;
                        /* Bold as requested */
                    }

                    .lub-row {
                        display: grid;
                        gap: 16px;
                        margin-bottom: 16px;
                        align-items: end;
                        /* Responsive Magic */
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    }

                    /* Override specific inline styles on mobile to prevent overflow */
                    @media (max-width: 1000px) {
                        .lub-row {
                            grid-template-columns: 1fr !important;
                        }
                    }

                    .lub-group {
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        padding: 16px;
                        margin-bottom: 20px;
                        position: relative;
                    }

                    .lub-group-title {
                        position: absolute;
                        top: -10px;
                        left: 10px;
                        background: var(--bg-panel);
                        padding: 0 8px;
                        font-size: 0.8rem;
                        color: var(--primary);
                        font-weight: 600;
                    }

                    .mic-btn {
                        cursor: pointer;
                        color: var(--primary);
                        padding: 5px;
                        border-radius: 50%;
                        transition: all 0.3s;
                    }

                    .mic-btn:hover {
                        background: rgba(14, 165, 233, 0.1);
                    }

                    .pulse-mic {
                        color: var(--danger) !important;
                        animation: pulse-red 1.5s infinite;
                    }

                    @keyframes pulse-red {
                        0% {
                            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
                        }

                        70% {
                            box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
                        }

                        100% {
                            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
                        }
                    }

                    .action-btn-pro {
                        border-color: #e2e8f0 !important;
                        color: #64748b !important;
                        font-size: 0.65rem !important;
                        font-weight: 800 !important;
                        gap: 6px !important;
                        padding: 6px 12px !important;
                        display: flex !important;
                        align-items: center !important;
                        background: white !important;
                        border-radius: 8px !important;
                        transition: all 0.2s ease !important;
                    }

                    .action-btn-pro:hover {
                        border-color: var(--primary) !important;
                        color: var(--primary) !important;
                        background: var(--primary-glow) !important;
                        transform: translateY(-1px);
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                    }

                    .action-btn-pro i {
                        width: 14px !important;
                    }
                </style>
            </div>
            </div><!-- /.asset-form-header-block -->

            <div class="asset-form-scroll">

                <!-- TAB: DADOS CADASTRAIS (Structural Base) -->
                <div id="tab-dados" class="asset-tab-content">
                    <div class="lub-group">
                        <span class="lub-group-title">Identificação Estrutural</span>
                        <div class="lub-row" style="grid-template-columns: 2fr 1fr 1fr;">
                            <div><span class="lub-label">Nome do Ativo / Local</span><input id="af-name"
                                    oninput="updateLocalNode()" onblur="autoFormatName(this); checkLubSuggestion()">
                            </div>
                            <div><span class="lub-label">TAG / Código</span><input id="af-tag"
                                    oninput="updateLocalNode(); if(typeof handleBearingCodeAutoFill==='function')handleBearingCodeAutoFill(this)"></div>
                            <div>
                                <span class="lub-label">Tipo</span>
                                <select id="af-type" onchange="updateLocalNode()" style="padding:10px;">
                                    <option value="unidade">🏢 Empresa / Projeto</option>
                                    <option value="setor">🏭 Setor</option>
                                    <option value="equipamento">⚙️ Equipamento</option>
                                    <option value="componente">🔩 Componente</option>
                                    <option value="ponto">🎯 Ponto</option>
                                </select>
                            </div>
                        </div>

                        <!-- NEW ROW: SPECIFICS -->
                        <div class="lub-row" style="grid-template-columns: 1fr 1fr 1fr;">
                            <div><span class="lub-label">Fabricante</span><input id="af-fabricante"
                                    oninput="updateLocalNode()"></div>
                            <div><span class="lub-label">Modelo</span><input id="af-modelo" oninput="updateLocalNode()">
                            </div>
                            <div><span class="lub-label">Nº Série</span><input id="af-num_serie"
                                    oninput="updateLocalNode()"></div>
                        </div>

                        <!-- IMAGE UPLOAD BOX -->
                        <div class="lub-row" style="grid-template-columns: 3fr 1fr;">
                            <div>
                                <span class="lub-label">Descrição Completa / Observações</span>
                                <input id="af-obs" oninput="updateLocalNode()">
                            </div>
                            <div style="text-align:center; display:flex; flex-direction:column; align-items:center;">
                                <label
                                    style="display:block; color:var(--text-muted); font-size:0.8rem; margin-bottom:4px;">Imagem</label>
                                <div id="af-img-preview"
                                    style="width:120px; height:120px; min-width:120px; max-width:120px; background:rgba(0,0,0,0.3); border:2px dashed var(--border); border-radius:12px; display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden;">
                                    <span id="af-img-placeholder"
                                        style="color:var(--text-muted); font-size:0.7rem; text-align:center;">Sem
                                        Foto</span>
                                    <!-- Image acts as trigger for Lightbox -->
                                    <img id="af-img-display"
                                        style="width:100%; height:100%; object-fit:cover; display:none; cursor:zoom-in;"
                                        onclick="openImageModal(this.src)">

                                    <!-- OVERLAY CONTROLS -->
                                    <div
                                        style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.7); padding:5px; display:flex; justify-content:center; gap:10px;">
                                        <i data-lucide="camera" style="width:16px; color:white; cursor:pointer;"
                                            onclick="triggerUpload('af-img-input')" title="Alterar Foto"></i>
                                        <i id="af-zoom-btn" data-lucide="maximize"
                                            style="width:16px; color:white; cursor:pointer;"
                                            onclick="openImageModal(document.getElementById('af-img-display').src)"
                                            title="Expandir"></i>
                                    </div>

                                    <input type="file" id="af-img-input" accept="image/*" style="display:none;"
                                        onchange="uploadImage(this, 'af-img-preview', 'af_img_val')">
                                </div>
                                <input id="af_img_val" type="hidden" onchange="updateLocalNode()">
                            </div>
                        </div>
                    </div>

                    <!-- DETALHES DA FÁBRICA / PROJETO (CONDICIONAL) -->
                    <div class="lub-group" id="af-factory-group" style="display:none; border-color:var(--primary); background:rgba(14,165,233,0.02);">
                        <span class="lub-group-title" style="color:var(--primary);">Detalhes da Fábrica / Projeto</span>
                        <div class="lub-row" style="grid-template-columns: 1fr 1fr;">
                            <div>
                                <span class="lub-label">Clientes Cadastrados</span>
                                <input id="af-clientes" class="af-tech" oninput="updateLocalNode()" placeholder="Ex: AMBEV, COCA-COLA, SHELL...">
                            </div>
                            <div>
                                <span class="lub-label">Profissionais Responsáveis</span>
                                <input id="af-profissionais" class="af-tech" oninput="updateLocalNode()" placeholder="Ex: Rodrigo (Eng.), João (Lub.)...">
                            </div>
                        </div>
                    </div>

                    <div class="lub-group">
                        <span class="lub-group-title">Hierarquia</span>
                        <div class="lub-row" style="grid-template-columns: 1fr auto;">
                            <div><span class="lub-label">Pai (ID Estrutural)</span><input id="af-parent" disabled
                                    style="opacity:0.5;"></div>
                            <button class="btn btn-outline" onclick="addNode(editingNode.id)" style="height:42px;">+
                                Adicionar Filho</button>
                        </div>
                    </div>
                </div>

                <!-- TAB: PLANO DE LUBRIFICAÇÃO (The Lub-it Style - Enhanced) -->
                <div id="tab-lub" class="asset-tab-content" style="display:none;">

                    <!-- ENGINEERING HEADER -->
                    <div
                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:15px;">
                        <div>
                            <h4 style="margin:0; color:var(--text-main);">Definição Técnica do Ponto</h4>
                            <p style="margin:0; font-size:0.8rem; color:var(--text-muted);">Configure os parâmetros
                                exatos para a ordem de serviço.</p>
                        </div>
                        <button class="btn"
                            style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); border:none; box-shadow: 0 4px 15px rgba(99,102,241,0.4);"
                            onclick="magicCalc()">
                            <i data-lucide="sparkles" style="margin-right:8px;"></i> Engenharia IA
                        </button>
                    </div>

                    <!-- ROW 1: IDENTITY & METHOD -->
                    <div class="lub-group">
                        <span class="lub-group-title">Identidade & Método</span>
                        <div class="lub-row" style="grid-template-columns: 80px 1fr 1fr 1fr 80px;">
                            <div>
                                <span class="lub-label">IP</span>
                                <input id="af-ip" class="af-tech" disabled
                                    style="opacity:0.7; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text-muted); font-weight:bold; width:100%;">
                            </div>
                            <div>
                                <span class="lub-label">Local do Ponto</span>
                                <input id="af-ponto_lub" class="af-tech" placeholder="Ex: Rolamento Dianteiro">
                            </div>
                            <div>
                                <span class="lub-label">Serviço (Ação)</span>
                                <select id="af-servico" class="af-tech" style="padding:10px;">
                                    <option value="">Selecione...</option>
                                    <option value="Lubrificar">Lubrificar (Graxa)</option>
                                    <option value="Trocar Oleo">Trocar Óleo</option>
                                    <option value="Verificar Nivel">Verificar Nível</option>
                                    <option value="Inspecionar">Inspecionar</option>
                                    <option value="Amostragem">Coleta de Amostra</option>
                                    <option value="Filtragem">Filtragem (Dialise)</option>
                                </select>
                            </div>
                            <div>
                                <span class="lub-label">Método Aplicação</span>
                                <select id="af-metodo" class="af-tech" style="padding:10px;">
                                    <option value="Manual">Manual (Pistola)</option>
                                    <option value="Automatico">Automático (Central)</option>
                                    <option value="Copo">Copo Stauffer</option>
                                    <option value="Banho">Banho de Óleo (Cárter)</option>
                                    <option value="Spray">Spray / Aerossol</option>
                                    <option value="Pincel">Pincel / Trincha</option>
                                </select>
                            </div>
                            <div>
                                <span class="lub-label">Seq. Rota</span>
                                <input id="af-ordem_rota" type="number" class="af-tech" placeholder="10">
                            </div>
                            <div>
                                <span class="lub-label" title="Posição horizontal do marcador no plano PDF (0–100%)">Marcador X (%)</span>
                                <input id="af-marker_x" type="number" min="0" max="100" step="1" class="af-tech" placeholder="Ex: 45">
                            </div>
                            <div>
                                <span class="lub-label" title="Posição vertical do marcador no plano PDF (0–100%)">Marcador Y (%)</span>
                                <input id="af-marker_y" type="number" min="0" max="100" step="1" class="af-tech" placeholder="Ex: 60">
                            </div>
                        </div>
                    </div>

                    <!-- ROW 2: CAPACITY & CONSUMPTION (The Critical Part) -->
                    <div class="lub-group" style="border-color:#3b82f6;">
                        <span class="lub-group-title" style="color:#3b82f6;">Engenharia de Lubrificante</span>

                        <div class="lub-row" style="grid-template-columns: 2fr 1fr 1fr 1fr;">
                            <div>
                                <span class="lub-label">Lubrificante (Produto)</span>
                                <input id="af-material" class="af-tech" list="lubricant-list"
                                    placeholder="Busque no Catálogo...">
                                <datalist id="lubricant-list"></datalist>
                                <!-- MARKETPLACE SUGGESTIONS WIDGET -->
                                <div id="market-suggestion-box"
                                    style="margin-top:10px; display:none; background: linear-gradient(to right, #f0f9ff, #e0f2fe); border:1px solid #bae6fd; border-radius:12px; padding:15px; animation: slideIn 0.3s;">
                                    <div class="flex-between">
                                        <h4
                                            style="margin:0; color:#0284c7; font-size:0.9rem; display:flex; align-items:center; gap:8px;">
                                            <i data-lucide="shopping-cart" style="width:16px;"></i> Sugestão de Compra
                                            (Parceiro)
                                        </h4>
                                        <span
                                            style="font-size:0.75rem; background:#fff; padding:2px 8px; border-radius:10px; color:#0284c7; font-weight:bold;">OFERTAS</span>
                                    </div>
                                    <div id="market-offers-list"
                                        style="margin-top:10px; display:flex; flex-direction:column; gap:8px;"></div>
                                </div>
                            </div>
                            <div>
                                <span class="lub-label" title="Quantidade a aplicar na relubrificação">Qtd. Relub
                                    (g/ml)</span>
                                <input id="af-qtd_material" type="number" class="af-tech" oninput="validateCapacity()">
                            </div>
                            <div>
                                <span class="lub-label" title="Capacidade Total do Cárter ou Rolamento">Cap. Total
                                    (Max)</span>
                                <input id="af-capacidade" type="number" class="af-tech" oninput="validateCapacity()">
                            </div>
                            <div>
                                <span class="lub-label">Unidade</span>
                                <select id="af-unid_material" class="af-tech" style="padding:10px;">
                                    <option value="g">Gramas (g)</option>
                                    <option value="ml">Mililitros (ml)</option>
                                    <option value="kg">Quilos (kg)</option>
                                    <option value="l">Litros (L)</option>
                                    <option value="oz">Onças (oz)</option>
                                </select>
                            </div>
                        </div>
                        <div id="cap-warning"
                            style="display:none; color:#f59e0b; font-size:0.8rem; margin-top:-10px; margin-bottom:10px;">
                            <i data-lucide="alert-triangle" style="width:12px;"></i> Atenção: Quantidade de
                            relubrificação excede 50% da capacidade total. Verifique se isso está correto (Risco de
                            Sobrepressão).
                        </div>
                    </div>


                    <!-- ROW 3: CONTROL & LOGISTICS (Lub-IT Specific) -->
                    <div class="lub-group">
                        <span class="lub-group-title">Logística & Controle de Origem</span>
                        <div class="lub-row" style="grid-template-columns: 1fr 1fr 1fr 2fr;">
                            <div>
                                <span class="lub-label">Controle SAP (Plano)</span>
                                <input id="af-sap" class="af-tech" placeholder="Ex: 1040050">
                            </div>
                            <div>
                                <span class="lub-label">Reserva Almox.</span>
                                <input id="af-almox" class="af-tech" placeholder="Ex: AX-99">
                            </div>
                            <div>
                                <span class="lub-label">Código Serviço</span>
                                <input id="af-cod_serv" class="af-tech" placeholder="Ex: LUB-01">
                            </div>
                            <div>
                                <span class="lub-label">Complemento Técnico</span>
                                <input id="af-complemento" class="af-tech" placeholder="Ex: Lado Acoplamento...">
                            </div>
                        </div>
                    </div>

                    <!-- ROW 4: FREQUENCY & CONDITION -->
                    <div class="lub-row" style="grid-template-columns: 1fr 1fr 1fr 1fr 1fr;">
                        <div>
                            <span class="lub-label">Frequência</span>
                            <select id="af-periodo" class="af-tech" style="padding:10px;">
                                <option value="Diario">Diário</option>
                                <option value="Semanal">Semanal</option>
                                <option value="Quinzenal">Quinzenal</option>
                                <option value="Mensal">Mensal</option>
                                <option value="Bimestral">Bimestral</option>
                                <option value="Trimestral">Trimestral</option>
                                <option value="Semestral">Semestral</option>
                                <option value="Anual">Anual</option>
                            </select>
                        </div>
                        <div>
                            <span class="lub-label">Rotação (RPM)</span>
                            <input id="af-rpm" type="number" class="af-tech" placeholder="Ex: 1750">
                        </div>
                        <div>
                            <span class="lub-label">Condição</span>
                            <select id="af-condicao" class="af-tech" style="padding:10px;">
                                <option value="Funcionando">Rodando</option>
                                <option value="Parada">Parada</option>
                            </select>
                        </div>
                        <div>
                            <span class="lub-label">Tempo (Min)</span>
                            <input id="af-duracao_m" type="number" class="af-tech" placeholder="15">
                        </div>
                        <div>
                            <span class="lub-label">Prioridade</span>
                            <select id="af-prioridade" class="af-tech" style="padding:10px;">
                                <option value="Rotina">Rotina</option>
                                <option value="Critica">Crítica</option>
                            </select>
                        </div>
                    </div>

                    <!-- ROW 4: DETAILED INSTRUCTIONS -->
                    <div class="lub-group">
                        <span class="lub-group-title">Procedimento Operacional Padrão (POP)</span>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="lub-label">Instruções Passo-a-Passo</span>
                                <div class="mic-btn" onclick="initVoiceInput('af-instrucao')" title="Ditar Instruções">
                                    <i data-lucide="mic"></i>
                                </div>
                            </div>
                            <textarea id="af-instrucao" class="af-tech" rows="4"
                                style="width:100%; background:rgba(0,0,0,0.2); border:1px solid var(--border); color:white; padding:10px; border-radius:6px; font-family:monospace;"
                                placeholder="- Limpar graxeira... (Fale para digitar)"></textarea>
                        </div>
                    </div>

                    <!-- ROW 5: BEARING SPECS (Hidden unless relevant) -->
                    <div class="lub-group" id="af-bushing-group">
                        <span class="lub-group-title">Engenharia de Buchas / Rolamentos</span>
                        <div class="lub-row" style="grid-template-columns: 1fr 1fr 2fr;">
                            <div><span class="lub-label">Diâmetro (mm)</span><input id="af-bushing_d" type="number"
                                    class="af-tech"></div>
                            <div><span class="lub-label">Largura (mm)</span><input id="af-bushing_l" type="number"
                                    class="af-tech"></div>
                            <div>
                                <span class="lub-label">Regime (Fator K)</span>
                                <select id="af-bushing_k" class="af-tech" style="padding:10px;">
                                    <option value="1.0">Normal</option>
                                    <option value="4.0">Oscilante/Severo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- HIDDEN / LEGACY FIELDS SUPPORT -->
                    <input type="hidden" id="af-sistema_lub" class="af-tech">
                    <input type="hidden" id="af-duracao_h" class="af-tech">
                    <input type="hidden" id="af-rota" class="af-tech"><!-- Replaced by Order -->
                    <input type="hidden" id="af-procedimento" class="af-tech"><!-- Replaced by TextArea -->

                </div>

                <!-- TAB: MERCADO INTELIGENTE (Strategic Procurement Intelligence) -->
                <div id="tab-market_tab" class="asset-tab-content" style="display:none; padding:0;">

                    <!-- STRATEGIC HEADER -->
                    <div
                        style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); padding:25px; color:white; border-radius:12px 12px 0 0; margin-bottom:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <h3 style="margin:0; font-size:1.4rem; display:flex; align-items:center; gap:12px;">
                                    <i data-lucide="trending-down" style="width:28px;"></i>
                                    Mercado Inteligente
                                </h3>
                                <p style="margin:5px 0 0 0; opacity:0.9; font-size:0.85rem;">Precificação estratégica em
                                    tempo real para: <b><span class="af-mat-preview">Produto não selecionado</span></b>
                                </p>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.7rem; opacity:0.8; text-transform:uppercase;">Economia Potencial
                                </div>
                                <div style="font-size:2rem; font-weight:800;">R$ <span
                                        id="market-savings-total">0</span></div>
                            </div>
                        </div>
                    </div>

                    <div style="padding:0 20px 20px 20px;">
                        <!-- MAIN GRID LAYOUT -->
                        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:25px;">

                            <!-- LEFT COLUMN: OFFERS & COMPARISON -->
                            <div style="display:flex; flex-direction:column; gap:20px;">

                                <!-- PRICE COMPARISON CARDS -->
                                <div class="lub-group" style="border-color:#0ea5e9; background:rgba(14,165,233,0.03);">
                                    <span class="lub-group-title" style="color:#0ea5e9;">
                                        <i data-lucide="bar-chart-3" style="width:14px;"></i> Comparativo de
                                        Fornecedores
                                    </span>

                                    <div id="tab-market-offers-list"
                                        style="display:flex; flex-direction:column; gap:12px; margin-top:15px;">
                                        <!-- Default State -->
                                        <div id="market-empty-state"
                                            style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                                            <i data-lucide="shopping-cart"
                                                style="width:64px; height:64px; opacity:0.2; color:#0ea5e9;"></i>
                                            <h4 style="margin-top:20px; color:var(--text-muted);">Aguardando Produto
                                            </h4>
                                            <p style="font-size:0.85rem;">Defina um lubrificante na aba "Plano de
                                                Lubrificação" para ver ofertas inteligentes.</p>
                                            <button class="btn btn-outline btn-sm"
                                                onclick="setAssetTab('lub', document.querySelector('.asset-tab[onclick*=lub]'))"
                                                style="margin-top:15px;">
                                                <i data-lucide="arrow-right"></i> Ir para Plano
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- PRICE TREND CHART -->
                                <div class="lub-group">
                                    <span class="lub-group-title">📈 Tendência de Preços (90 dias)</span>
                                    <div style="height:220px; padding:15px;">
                                        <canvas id="market-price-trend"></canvas>
                                    </div>
                                    <div
                                        style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--text-muted); margin-top:10px;">
                                        <span>Melhor: <span id="market-trend-best">—</span></span>
                                        <span>Pior: <span id="market-trend-worst">—</span></span>
                                        <span>Média: <span id="market-trend-avg">—</span></span>
                                    </div>
                                </div>

                                <!-- BULK PURCHASE OPTIMIZER -->
                                <div class="lub-group" style="border-color:#10b981;">
                                    <span class="lub-group-title" style="color:#10b981;">
                                        <i data-lucide="package-plus" style="width:14px;"></i> Otimizador de Compra em
                                        Volume
                                    </span>
                                    <div
                                        style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-top:10px;">
                                        <div>
                                            <label
                                                style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:5px;">Quantidade
                                                (un)</label>
                                            <input type="number" id="bulk-qty" value="1" min="1"
                                                oninput="calculateBulkPrice()"
                                                style="width:100%; padding:10px; border-radius:6px; border:1px solid var(--border);">
                                        </div>
                                        <div
                                            style="background:rgba(16,185,129,0.1); padding:15px; border-radius:8px; text-align:center;">
                                            <div style="font-size:0.7rem; color:#10b981; font-weight:700;">💰 PREÇO
                                                UNITÁRIO</div>
                                            <div style="font-size:1.3rem; font-weight:800; color:#10b981;"
                                                id="bulk-unit-price">R$ 0,00</div>
                                        </div>
                                        <div
                                            style="background:rgba(14,165,233,0.1); padding:15px; border-radius:8px; text-align:center;">
                                            <div style="font-size:0.7rem; color:#0ea5e9; font-weight:700;">📦 TOTAL
                                            </div>
                                            <div style="font-size:1.3rem; font-weight:800; color:#0ea5e9;"
                                                id="bulk-total-price">R$ 0,00</div>
                                        </div>
                                    </div>
                                    <div id="bulk-discount-alert"
                                        style="display:none; margin-top:12px; background:linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding:12px; border-radius:8px; border:1px solid #fbbf24;">
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <i data-lucide="sparkles" style="width:20px; color:#f59e0b;"></i>
                                            <div>
                                                <div style="font-weight:700; color:#92400e; font-size:0.85rem;">
                                                    Oportunidade de Desconto!</div>
                                                <div style="font-size:0.75rem; color:#78350f;">Compre 10+ unidades e
                                                    economize até 15%</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: INTELLIGENCE & ALERTS -->
                            <div style="display:flex; flex-direction:column; gap:20px;">

                                <!-- MARKET INTELLIGENCE SUMMARY -->
                                <div class="lub-group">
                                    <span class="lub-group-title">🎯 Inteligência Estratégica</span>
                                    <div style="display:flex; flex-direction:column; gap:12px; margin-top:10px;">

                                        <div
                                            style="background:linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); padding:15px; border-radius:10px; border:1px solid #10b981;">
                                            <div
                                                style="font-size:0.7rem; color:#065f46; text-transform:uppercase; font-weight:700;">
                                                Economia (Ano)</div>
                                            <div
                                                style="font-size:1.6rem; font-weight:800; color:#10b981; margin:5px 0;">
                                                R$ <span id="market-savings-year">0</span></div>
                                            <div style="font-size:0.7rem; color:#065f46;">vs. média das ofertas</div>
                                        </div>

                                        <div
                                            style="background:var(--bg-body); padding:12px; border-radius:8px; border:1px solid var(--border);">
                                            <div
                                                style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px;">
                                                Status da Cadeia</div>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <div
                                                    style="width:10px; height:10px; border-radius:50%; background:#10b981;">
                                                </div>
                                                <span style="font-weight:700; font-size:0.9rem;">Oferta Estável</span>
                                            </div>
                                            <div style="font-size:0.7rem; color:var(--text-muted); margin-top:5px;">
                                                Disponibilidade: 98%</div>
                                        </div>

                                        <div
                                            style="background:var(--bg-body); padding:12px; border-radius:8px; border:1px solid var(--border);">
                                            <div
                                                style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px;">
                                                Tempo Médio Entrega</div>
                                            <div style="font-weight:700; font-size:1.2rem; color:var(--primary);">1.8
                                                dias</div>
                                            <div style="display:flex; gap:5px; margin-top:5px;">
                                                <span
                                                    style="background:#10b981; color:white; font-size:0.65rem; padding:2px 6px; border-radius:4px;">24h
                                                    disponível</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ALERTS & NOTIFICATIONS -->
                                <div class="lub-group" style="border-color:#f59e0b;">
                                    <span class="lub-group-title" style="color:#f59e0b;">
                                        <i data-lucide="bell" style="width:14px;"></i> Alertas Inteligentes
                                    </span>
                                    <div style="display:flex; flex-direction:column; gap:10px; margin-top:10px;">
                                        <div
                                            style="background:linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding:12px; border-radius:8px; border-left:4px solid #f59e0b;">
                                            <div
                                                style="font-weight:700; font-size:0.8rem; color:#92400e; margin-bottom:3px;">
                                                🔥 Promoção Ativa</div>
                                            <div style="font-size:0.75rem; color:#78350f;">15% OFF até amanhã na Amazon
                                                Business</div>
                                        </div>

                                        <div
                                            style="background:#f1f5f9; padding:12px; border-radius:8px; border-left:4px solid #64748b;">
                                            <div
                                                style="font-weight:700; font-size:0.8rem; color:#334155; margin-bottom:3px;">
                                                📊 Preço em Queda</div>
                                            <div style="font-size:0.75rem; color:#475569;">-8% nas últimas 2 semanas
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- QUICK ACTIONS -->
                                <div class="lub-group">
                                    <span class="lub-group-title">⚡ Ações Rápidas</span>
                                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:10px;">
                                        <button class="btn btn-outline btn-sm" onclick="exportMarketReport()"
                                            style="justify-content:flex-start;">
                                            <i data-lucide="file-spreadsheet"></i> Exportar Análise (XLSX)
                                        </button>
                                        <button class="btn btn-outline btn-sm" onclick="setMarketAlert()"
                                            style="justify-content:flex-start;">
                                            <i data-lucide="bell-plus"></i> Criar Alerta de Preço
                                        </button>
                                        <button class="btn btn-outline btn-sm" onclick="shareMarketInsights()"
                                            style="justify-content:flex-start;">
                                            <i data-lucide="share-2"></i> Compartilhar Insights
                                        </button>
                                    </div>
                                </div>

                                <!-- VENDOR RATINGS -->
                                <div class="lub-group">
                                    <span class="lub-group-title">⭐ Top Fornecedores</span>
                                    <div
                                        style="display:flex; flex-direction:column; gap:10px; margin-top:10px; font-size:0.8rem;">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <span>Amazon Business</span>
                                            <div style="display:flex; align-items:center; gap:5px;">
                                                <span style="color:#fbbf24;">★</span>
                                                <span style="font-weight:700;">4.9</span>
                                            </div>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <span>Mercado Livre Pro</span>
                                            <div style="display:flex; align-items:center; gap:5px;">
                                                <span style="color:#fbbf24;">★</span>
                                                <span style="font-weight:700;">4.8</span>
                                            </div>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <span>LubParts Express</span>
                                            <div style="display:flex; align-items:center; gap:5px;">
                                                <span style="color:#fbbf24;">★</span>
                                                <span style="font-weight:700;">4.5</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: MONITORAMENTO (World Class Feature) -->
                <!-- TAB: MONITORAMENTO (World Class Feature) -->
                <div id="tab-monitor" class="asset-tab-content" style="display:none; padding:10px;">
                    <div style="display:grid; grid-template-columns: 1fr 400px; gap:20px;">
                        <!-- Left: Input & Chart -->
                        <div>
                            <div class="lub-group" style="border-color:#10b981;">
                                <span class="lub-group-title" style="color:#10b981;"><i data-lucide="microscope"
                                        style="width:14px;"></i> Registro de Nova Amostra</span>
                                <div class="lub-row" style="grid-template-columns: 1fr 1fr 1fr 1fr;">
                                    <div><span class="lub-label">Data</span><input type="date" id="mon-date"
                                            class="af-tech"></div>
                                    <div><span class="lub-label">Lab</span><select id="mon-lab" class="af-tech">
                                            <option>ALS</option>
                                            <option>SGS</option>
                                            <option>Interno</option>
                                        </select></div>
                                    <div><span class="lub-label">Laudo</span><select id="mon-laudo" class="af-tech">
                                            <option>Normal</option>
                                            <option>Alerta</option>
                                            <option>Critico</option>
                                        </select></div>
                                    <div><button class="btn" style="width:100%; margin-top:20px; background:#10b981;"
                                            onclick="saveOilAnalysis()"><i data-lucide="save"></i> Salvar</button></div>
                                </div>
                                <div class="lub-row" style="margin-top:8px;">
                                    <div>
                                        <span class="lub-label">Importar CSV de laboratório (não apaga laudos já salvos)</span>
                                        <input type="file" id="mon-csv" accept=".csv,text/csv" class="af-tech"
                                            onchange="importAnalysisCsv(this)">
                                    </div>
                                </div>
                                <div class="lub-row"
                                    style="grid-template-columns: 1fr 1fr 1fr; margin-top:10px; border-bottom:1px dashed #e2e8f0; padding-bottom:15px; margin-bottom:15px;">
                                    <div><span class="lub-label" style="color:#0ea5e9; font-weight:800;">Visc. 40°C
                                            (cSt)*</span><input id="mon-visc40" type="number" step="0.1" class="af-tech"
                                            required placeholder="Ex: 68.0" style="border-color:#bae6fd;"></div>
                                    <div><span class="lub-label" style="color:#0ea5e9; font-weight:800;">Visc. 100°C
                                            (cSt)*</span><input id="mon-visc100" type="number" step="0.1"
                                            class="af-tech" required placeholder="Ex: 8.7"
                                            style="border-color:#bae6fd;"></div>
                                    <div><span class="lub-label" style="color:#0ea5e9; font-weight:800;">Acidez
                                            (TAN)*</span><input id="mon-acidez" type="number" step="0.01"
                                            class="af-tech" required placeholder="Ex: 0.5"
                                            style="border-color:#bae6fd;"></div>
                                </div>
                                <div class="lub-row"
                                    style="grid-template-columns: 1fr 1fr 1fr 1fr 1fr; margin-top:10px;">
                                    <div><span class="lub-label">ISO 4406</span><input id="mon-iso"
                                            placeholder="19/17/14" class="af-tech"></div>
                                    <div><span class="lub-label">Água (ppm)</span><input id="mon-water" type="number"
                                            class="af-tech"></div>
                                    <div><span class="lub-label">Fe (ppm)</span><input id="mon-fe" type="number"
                                            class="af-tech"></div>
                                    <div><span class="lub-label">Cu (ppm)</span><input id="mon-cu" type="number"
                                            class="af-tech"></div>
                                    <div><span class="lub-label">Si (ppm)</span><input id="mon-si" type="number"
                                            class="af-tech"></div>
                                </div>
                            </div>
                            <div class="lub-group">
                                <span class="lub-group-title">Tendência de Desgaste</span>
                                <div style="height:250px;"><canvas id="monitor-chart"></canvas></div>
                            </div>
                        </div>

                        <!-- Right: Virtual Lab Report (The WOW Factor) -->
                        <div id="virtual-lab-report"
                            style="background:white; border-radius:12px; border:1px solid #e2e8f0; padding:25px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); position:relative; overflow:hidden;">
                            <div style="position:absolute; top:10px; right:10px; opacity:0.05;"><i
                                    data-lucide="shield-check" style="width:120px; height:120px;"></i></div>
                            <div
                                style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                                <div
                                    style="font-size:0.6rem; color:var(--text-muted); font-weight:800; text-transform:uppercase;">
                                    Relatório Técnico IA-LUB</div>
                                <div style="font-size:0.7rem; color:var(--success); font-weight:700;">VERIFICADO</div>
                            </div>
                            <div style="border-bottom:2px solid #f1f5f9; padding-bottom:15px; margin-bottom:15px;">
                                <div style="font-size:1.1rem; font-weight:800; color:#1e293b;">Certificado de Condição
                                </div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Assinado por: <b>Felipe
                                        Rodrigues (Lead Eng.)</b></div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                <div style="display:flex; justify-content:space-between;"><span
                                        style="font-size:0.8rem; color:var(--text-muted);">ISO 4406:</span><span
                                        style="font-weight:700;" id="v-lab-iso">--/--/--</span></div>
                                <div style="display:flex; justify-content:space-between;"><span
                                        style="font-size:0.8rem; color:var(--text-muted);">Umidade:</span><span
                                        style="font-weight:700;" id="v-lab-water">-- ppm</span></div>
                                <div style="display:flex; justify-content:space-between;"><span
                                        style="font-size:0.8rem; color:var(--text-muted);">Fe (ppm):</span><span
                                        style="font-weight:700;" id="v-lab-fe">--</span></div>
                                <hr style="border:0; border-top:1px dashed #e2e8f0;">
                                <div style="display:flex; justify-content:space-between;"><span
                                        style="font-size:0.8rem; color:var(--text-muted);">Status IA:</span><span
                                        style="font-weight:700; color:var(--success);">DENTRO DO LIMITE</span></div>
                            </div>
                            <div
                                style="margin-top:25px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0;">
                                <div style="font-size:0.65rem; color:#64748b; font-weight:800; margin-bottom:5px;">
                                    PARECER TÉCNICO IA</div>
                                <p style="font-size:0.75rem; color:#475569; margin:0; line-height:1.4;">Baseado no
                                    histórico do ativo, a condição é estável. Manter intervalo de 500h.</p>
                            </div>
                            <button class="btn btn-outline" style="width:100%; margin-top:20px; border-radius:10px;"
                                onclick="window.print()"><i data-lucide="printer"></i> Imprimir Laudo</button>
                        </div>
                    </div>
                </div>

                <!-- TAB: HISTÓRICO -->
                <!-- TAB: HISTÓRICO -->
                <div id="tab-hist" class="asset-tab-content" style="display:none; padding:20px;">
                    <div id="timeline-container" style="display:flex; flex-direction:column; gap:20px;">
                        <!-- Timeline items injected via JS -->
                        <div style="text-align:center; color:var(--text-muted);">Carregando histórico...</div>
                    </div>
                </div>

            </div><!-- /.asset-form-scroll -->
        </div><!-- /#asset-form -->
        </div><!-- /.asset-detail-panel -->
    </div><!-- /.assets-main-layout -->
</div><!-- /#view-assets -->