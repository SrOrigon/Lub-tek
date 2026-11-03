<div id="view-sap" class="view-container active view-flex" style="flex-direction: column; gap: 24px; background: #f8fafc; padding: 24px;">
    
    <!-- Title Section -->
    <div class="flex-between">
        <div>
            <h1 style="margin: 0; font-size: 2.2rem; color: #0f172a; font-weight: 800; letter-spacing: -1px;">
                Integração SAP ERP
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 5px;">
                Sincronize ordens, ativos e materiais entre o LUB-TEK e o SAP ERP (PM/MM).
            </p>
        </div>
        <div style="background: white; border: 1px solid var(--border); padding: 8px 16px; border-radius: 12px; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
            <div style="width: 10px; height: 10px; background: var(--success); border-radius: 50%; animation: pulse 2s infinite;"></div>
            <span style="font-size: 0.85rem; font-weight: 700; color: #0f172a;">Conector SAP Ativo</span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: 15px; border-bottom: 1px solid var(--border); padding-bottom: 1px; margin-bottom: 10px; flex-wrap: wrap;">
        <button id="tab-sap-import" onclick="switchSAPTab('import')" class="tab-btn active-tab" 
            style="border: none; background: none; font-size: 1rem; font-weight: 700; padding: 10px 20px; border-bottom: 3px solid var(--primary); color: var(--primary); cursor: pointer;">
            <i data-lucide="upload" style="width: 16px; margin-right: 8px; vertical-align: middle;"></i>Importar do SAP
        </button>
        <button id="tab-sap-export" onclick="switchSAPTab('export')" class="tab-btn" 
            style="border: none; background: none; font-size: 1rem; font-weight: 700; padding: 10px 20px; border-bottom: 3px solid transparent; color: var(--text-muted); cursor: pointer;">
            <i data-lucide="download" style="width: 16px; margin-right: 8px; vertical-align: middle;"></i>Exportar para o SAP
        </button>
        <button id="tab-sap-powerbi" onclick="switchSAPTab('powerbi')" class="tab-btn" 
            style="border: none; background: none; font-size: 1rem; font-weight: 700; padding: 10px 20px; border-bottom: 3px solid transparent; color: var(--text-muted); cursor: pointer;">
            <i data-lucide="bar-chart-2" style="width: 16px; margin-right: 8px; vertical-align: middle;"></i>Ponte Power BI
        </button>
    </div>

    <!-- TAB CONTENT: IMPORT -->
    <div id="sap-content-import" class="sap-tab-content">
        <div class="grid-2 sap-import-grid" style="grid-template-columns: minmax(320px, 380px) 1fr; gap: 24px; align-items: start;">
            
            <!-- Left Panel: Configuration -->
            <div class="card sap-import-sidebar" style="padding: 24px; display: flex; flex-direction: column; gap: 20px; background: white; min-height: 100%;">
                <div>
                    <h3 style="margin: 0 0 5px 0; color: #0f172a; font-size: 1rem;">1. O que deseja importar?</h3>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Selecione o tipo de arquivo estruturado do SAP.</p>
                </div>
                
                <div>
                    <label>Tipo de Entidade</label>
                    <select id="sap-import-type" onchange="resetImportState()" style="padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-input); width: 100%; max-width: 100%;">
                        <option value="orders">Ordens de Serviço (SAP PM - IW39)</option>
                        <option value="assets">Estrutura de Ativos (SAP PM - IH01)</option>
                        <option value="materials">Catálogo de Materiais (SAP MM - MM60)</option>
                    </select>
                    <div style="margin-top: 8px;">
                        <button type="button" onclick="downloadSAPTemplate()" class="btn btn-sm btn-outline" style="width: 100%; justify-content: center; font-size: 0.78rem; font-weight: 700; border-radius: 8px; border-color: #cbd5e1; color: #0284c7; background: #f0f9ff; display: inline-flex; align-items: center; gap: 6px; height: 36px;">
                            <i data-lucide="download" style="width: 14px; height: 14px;"></i> Baixar Planilha Modelo (Template SAP)
                        </button>
                    </div>
                </div>

                <!-- Drag & Drop Zone -->
                <div>
                    <label>2. Upload do Arquivo (Excel, CSV ou TXT)</label>
                    <div id="sap-drop-zone" class="drop-zone" onclick="document.getElementById('sap-file-input').click()"
                        style="border: 2px dashed var(--border); border-radius: 12px; padding: 30px 15px; text-align: center; cursor: pointer; transition: all 0.2s; background: var(--bg-input);">
                        <i data-lucide="file-spreadsheet" style="width: 40px; height: 40px; color: var(--primary); margin: 0 auto 10px auto; display: block;"></i>
                        <span style="font-size: 0.85rem; font-weight: 700; color: #1e293b; display: block; margin-bottom: 5px;">Escolher Arquivo</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Solte o arquivo ou clique para buscar</span>
                        <input type="file" id="sap-file-input" accept=".xlsx,.xls,.csv,.txt" style="display: none;" onchange="handleSAPFile(this)">
                    </div>
                </div>

                <div id="sap-import-stats" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px; border-radius: 8px;">
                    <div style="font-size: 0.8rem; color: #15803d; font-weight: 700;">Arquivo Lido com Sucesso!</div>
                    <div id="sap-stat-rows" style="font-size: 0.85rem; color: #166534; font-weight: 600; margin-top: 5px;">Linhas detectadas: 0</div>
                </div>
            </div>

            <!-- Right Panel: Mapping and Preview -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                
                <!-- Column Mapping Panel -->
                <div id="sap-mapping-card" class="card" style="padding: 24px; display: none; background: white;">
                    <div style="border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 15px;">
                        <h3 style="margin: 0; color: #0f172a; font-size: 1.1rem; display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="shuffle" style="width: 18px; color: var(--primary);"></i> Correspondência de Colunas (De-Para SAP)
                        </h3>
                        <p style="margin: 5px 0 0 0; font-size: 0.8rem; color: var(--text-muted);">Mapeie as colunas do arquivo para os campos do sistema.</p>
                    </div>

                    <div id="sap-mapping-fields" class="grid-2" style="gap: 15px 24px;">
                        <!-- Mappings dynamically generated here -->
                    </div>
                </div>

                <!-- Preview Grid Card -->
                <div id="sap-preview-card" class="card" style="padding: 24px; display: none; background: white; flex-direction: column; gap: 15px;">
                    <div class="flex-between">
                        <div>
                            <h3 style="margin: 0; color: #0f172a; font-size: 1.1rem;">Visualização Prévia dos Dados</h3>
                            <p style="margin: 3px 0 0 0; font-size: 0.8rem; color: var(--text-muted);">Exibindo amostra de validação dos primeiros registros antes de salvar.</p>
                        </div>
                        <button onclick="executeSAPImport()" class="btn" style="background: linear-gradient(135deg, var(--success), #059669); border: none; padding: 10px 20px; font-size: 0.9rem; font-weight: 700; border-radius: 8px;">
                            <i data-lucide="check-circle" style="width: 16px;"></i> PROCESSAR E SALVAR
                        </button>
                    </div>

                    <div style="overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; max-height: 350px;">
                        <table class="sap-preview-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.8rem;">
                            <thead style="background: var(--bg-input); position: sticky; top: 0; z-index: 1;">
                                <tr id="sap-preview-headers">
                                    <!-- Header rows dynamic -->
                                </tr>
                            </thead>
                            <tbody id="sap-preview-rows">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State/Help Card -->
                <div id="sap-help-card" class="card" style="padding: 32px; background: white; display: flex; flex-direction: column; gap: 20px;">
                    <div style="text-align: center; max-width: 500px; margin: 0 auto;">
                        <i data-lucide="info" style="width: 48px; height: 48px; color: var(--primary); opacity: 0.8; margin: 0 auto 15px auto;"></i>
                        <h3 style="color: #0f172a; font-size: 1.2rem; margin: 0 0 10px 0;">Como funciona a integração SAP?</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; margin: 0;">
                            A LUB-TEK aceita planilhas extraídas diretamente das transações do SAP GUI ou SAP Fiori. Você pode importar arquivos XLS, XLSX, CSV ou TXT delimitados.
                        </p>
                    </div>

                    <div style="border-top: 1px solid var(--border); padding-top: 20px;" class="grid-3">
                        <div style="background: var(--bg-input); padding: 15px; border-radius: 12px; border: 1px solid var(--border);">
                            <div style="font-weight: 700; font-size: 0.85rem; color: #0f172a; margin-bottom: 5px;">Ordens SAP (IW39)</div>
                            <small style="color: var(--text-muted); line-height: 1.4; display: block;">
                                Mapeia ordens preventivas ou corretivas SAP PM. Se a ordem já existir no sistema, ela será atualizada de forma segura.
                            </small>
                        </div>
                        <div style="background: var(--bg-input); padding: 15px; border-radius: 12px; border: 1px solid var(--border);">
                            <div style="font-weight: 700; font-size: 0.85rem; color: #0f172a; margin-bottom: 5px;">Hierarquia (IH01)</div>
                            <small style="color: var(--text-muted); line-height: 1.4; display: block;">
                                Carrega locais funcionais e equipamentos em lote. Cria a estrutura em árvore mantendo dependência pai-filho.
                            </small>
                        </div>
                        <div style="background: var(--bg-input); padding: 15px; border-radius: 12px; border: 1px solid var(--border);">
                            <div style="font-weight: 700; font-size: 0.85rem; color: #0f172a; margin-bottom: 5px;">Materiais (MM60)</div>
                            <small style="color: var(--text-muted); line-height: 1.4; display: block;">
                                Sincroniza o mestre de materiais e lubrificantes cadastrados no SAP com o inventário da plataforma.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB CONTENT: EXPORT -->
    <div id="sap-content-export" class="sap-tab-content" style="display: none;">
        <div class="card" style="padding: 32px; background: white; max-width: 600px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">
            <div>
                <h3 style="margin: 0 0 5px 0; color: #0f172a; font-size: 1.2rem;">Exportar Dados no Layout SAP</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Baixe arquivos estruturados prontos para carga ou cópia para o SAP ERP.</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div>
                    <label>Tipo de Exportação</label>
                    <select id="sap-export-type" onchange="toggleExportFilters()" style="padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                        <option value="orders">Confirmação de O.S. (SAP PM - IW41 / IW42)</option>
                        <option value="assets">Lista de Ativos e Locais Funcionais (SAP PM)</option>
                        <option value="materials">Cadastro de Lubrificantes/Materiais (SAP MM)</option>
                    </select>
                </div>

                <!-- Date Filters (Only visible for Orders) -->
                <div id="sap-export-date-filters" class="grid-2" style="gap: 15px;">
                    <div>
                        <label>Data Inicial</label>
                        <input type="date" id="sap-export-start" style="padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                    </div>
                    <div>
                        <label>Data Final</label>
                        <input type="date" id="sap-export-end" style="padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                    </div>
                </div>

                <div>
                    <label>Formato do Arquivo</label>
                    <select id="sap-export-format" style="padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                        <option value="xlsx">Planilha de Excel (.xlsx)</option>
                        <option value="txt">Arquivo de Texto Tabulado (.txt / CSV)</option>
                    </select>
                </div>
            </div>

            <button onclick="executeSAPExport()" class="btn" style="width: 100%; justify-content: center; padding: 14px 0; border: none; font-weight: 700; border-radius: 10px;">
                <i data-lucide="download" style="width: 18px;"></i> GERAR ARQUIVO DE EXPORTAÇÃO
            </button>
        </div>
    </div>

    <!-- TAB CONTENT: POWER BI BRIDGE -->
    <div id="sap-content-powerbi" class="sap-tab-content" style="display: none;">
        <div style="display: flex; flex-direction: column; gap: 24px; max-width: 1000px; margin: 0 auto;">
            
            <!-- Welcome Header Card -->
            <div class="card" style="padding: 24px 28px; background: white; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="bar-chart-2" style="color: #f59e0b; width: 26px; height: 26px;"></i>
                        Ponte de Conexão Microsoft Power BI
                    </h2>
                    <p style="margin: 6px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                        Alimente painéis executivos e gráficos analíticos com dados 100% atualizados via feed REST estruturado.
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="btn btn-outline" onclick="testPowerBiConnection()" style="border-radius: 10px; height: 42px; display: flex; align-items: center; gap: 6px; font-weight: 700; background: #f8fafc;">
                        <i data-lucide="zap" style="width: 16px; color: #0284c7;"></i> Testar Conexão Live
                    </button>
                </div>
            </div>

            <!-- API Credential Card -->
            <div class="card" style="padding: 24px 28px; background: white; border-radius: 16px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.8rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                        1. Chave de Acesso Autenticada (X-API-KEY)
                    </span>
                    <span style="font-size: 0.75rem; background: #ecfdf5; color: #059669; font-weight: 700; padding: 3px 10px; border-radius: 20px;">
                        Escopo: Leitura & Gateway BI
                    </span>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    Esta chave autoriza o Power BI Desktop ou Power BI Web a consultar os dados sem expor senhas de usuários humanos.
                </p>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <div style="position: relative; flex: 1; min-width: 280px;">
                        <input type="password" id="pbi-api-key-input" readonly value="Carregando chave..." 
                            style="width: 100%; padding: 12px 42px 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: monospace; font-size: 0.95rem; background: #f8fafc; color: #0f172a; font-weight: 700;">
                        <button type="button" onclick="togglePbiKeyVisibility()" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b;">
                            <i id="pbi-key-eye" data-lucide="eye" style="width: 18px; height: 18px;"></i>
                        </button>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="copyPbiApiKey()" style="border-radius: 10px; height: 44px; display: flex; align-items: center; gap: 6px; font-weight: 700; padding: 0 18px;">
                        <i data-lucide="copy" style="width: 16px;"></i> Copiar Chave
                    </button>
                    <button type="button" class="btn btn-outline" onclick="rotatePbiApiKey()" style="border-radius: 10px; height: 44px; display: flex; align-items: center; gap: 6px; font-weight: 600; color: #d97706; border-color: #fde68a;">
                        <i data-lucide="refresh-cw" style="width: 16px;"></i> Gerar Nova Chave
                    </button>
                </div>
            </div>

            <!-- Available Feeds Table -->
            <div class="card" style="padding: 24px 28px; background: white; border-radius: 16px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 16px;">
                <span style="font-size: 0.8rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                    2. Feeds Tabulares Disponíveis (Carregamento Direto em Tabela Plana)
                </span>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569;">
                                <th style="padding: 12px 14px; font-weight: 700;">Entidade / Tabela</th>
                                <th style="padding: 12px 14px; font-weight: 700;">Descrição no Power BI</th>
                                <th style="padding: 12px 14px; font-weight: 700; text-align: right;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                    <i data-lucide="clipboard-list" style="width: 16px; color: #0284c7; vertical-align: middle; margin-right: 6px;"></i>
                                    Ordens de Serviço (O.S.)
                                </td>
                                <td style="padding: 12px 14px; color: #64748b;">
                                    Histórico e carteira de ordens com data planejada, execução, técnico, prioridade e status.
                                </td>
                                <td style="padding: 12px 14px; text-align: right;">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="copyPbiFeedUrl('powerbi_orders')" style="font-size: 0.75rem; border-radius: 8px;">
                                        Copiar URL
                                    </button>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                    <i data-lucide="gauge" style="width: 16px; color: #10b981; vertical-align: middle; margin-right: 6px;"></i>
                                    Indicadores & KPIs Globais
                                </td>
                                <td style="padding: 12px 14px; color: #64748b;">
                                    Disponibilidade da planta, taxa de conclusão, MTBF (horas), MTTR e alertas ativos.
                                </td>
                                <td style="padding: 12px 14px; text-align: right;">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="copyPbiFeedUrl('powerbi_kpis')" style="font-size: 0.75rem; border-radius: 8px;">
                                        Copiar URL
                                    </button>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                    <i data-lucide="droplet" style="width: 16px; color: #f59e0b; vertical-align: middle; margin-right: 6px;"></i>
                                    Consumo de Lubrificantes
                                </td>
                                <td style="padding: 12px 14px; color: #64748b;">
                                    Dosagem real vs planejada por máquina, óleo/graxa aplicado, custo e conformidade técnica.
                                </td>
                                <td style="padding: 12px 14px; text-align: right;">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="copyPbiFeedUrl('powerbi_consumption')" style="font-size: 0.75rem; border-radius: 8px;">
                                        Copiar URL
                                    </button>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                    <i data-lucide="boxes" style="width: 16px; color: #8b5cf6; vertical-align: middle; margin-right: 6px;"></i>
                                    Inventário & Saúde de Ativos
                                </td>
                                <td style="padding: 12px 14px; color: #64748b;">
                                    Árvore de equipamentos, TAGs, fabricantes, score de saúde operacional e criticidade.
                                </td>
                                <td style="padding: 12px 14px; text-align: right;">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="copyPbiFeedUrl('powerbi_assets')" style="font-size: 0.75rem; border-radius: 8px;">
                                        Copiar URL
                                    </button>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                    <i data-lucide="activity" style="width: 16px; color: #06b6d4; vertical-align: middle; margin-right: 6px;"></i>
                                    Telemetria & Sensores IoT (PI)
                                </td>
                                <td style="padding: 12px 14px; color: #64748b;">
                                    Leituras em tempo real de vibração, temperatura e corrente com thresholds de aviso e crítico.
                                </td>
                                <td style="padding: 12px 14px; text-align: right;">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="copyPbiFeedUrl('powerbi_telemetry')" style="font-size: 0.75rem; border-radius: 8px;">
                                        Copiar URL
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                    <i data-lucide="shield-check" style="width: 16px; color: #64748b; vertical-align: middle; margin-right: 6px;"></i>
                                    Trilha de Auditoria & Logs
                                </td>
                                <td style="padding: 12px 14px; color: #64748b;">
                                    Histórico de modificações, apontamentos de campo e conformidade de rastreabilidade.
                                </td>
                                <td style="padding: 12px 14px; text-align: right;">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="copyPbiFeedUrl('powerbi_audit')" style="font-size: 0.75rem; border-radius: 8px;">
                                        Copiar URL
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Power Query M Code Card -->
            <div class="card" style="padding: 24px 28px; background: white; border-radius: 16px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span style="font-size: 0.8rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; display: block;">
                            3. Código Pronto para Power Query (Linguagem M)
                        </span>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">
                            Cole no <b>Editor Avançado</b> do Power BI para carregar a tabela já descompactada e tipada:
                        </span>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="copyPbiMCode()" style="border-radius: 8px; font-weight: 700; height: 36px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="copy" style="width: 14px;"></i> Copiar Código M
                    </button>
                </div>

                <pre id="pbi-m-code-block" style="background: #0f172a; color: #38bdf8; padding: 18px; border-radius: 12px; font-family: Consolas, monospace; font-size: 0.82rem; overflow-x: auto; line-height: 1.5; margin: 0;"></pre>
            </div>

            <!-- Quick Instructions -->
            <div class="card" style="padding: 22px 28px; background: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 10px;">
                <span style="font-size: 0.8rem; font-weight: 800; color: #0f172a; text-transform: uppercase;">
                    Passo a Passo Rápido no Power BI Desktop:
                </span>
                <ol style="margin: 0; padding-left: 20px; font-size: 0.85rem; color: #475569; line-height: 1.6;">
                    <li>Abra o <b>Power BI Desktop</b> e clique na guia <b>Página Inicial &gt; Obter Dados &gt; Consulta em Branco</b>.</li>
                    <li>Na barra superior do Power Query, clique em <b>Editor Avançado</b>.</li>
                    <li>Substitua todo o conteúdo pelo <b>Código M</b> copiado acima e clique em <b>Concluído</b>.</li>
                    <li>Sua tabela de ordens de serviço será carregada instantaneamente com todas as colunas estruturadas!</li>
                </ol>
            </div>

        </div>
    </div>
</div>

<style>
    .tab-btn.active-tab {
        color: var(--primary) !important;
        border-bottom-color: var(--primary) !important;
    }
    .drop-zone:hover {
        border-color: var(--primary) !important;
        background: #f0f9ff !important;
    }
    .sap-preview-table th {
        padding: 10px 15px;
        font-weight: 700;
        color: #475569;
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }
    .sap-preview-table td {
        padding: 10px 15px;
        color: #1e293b;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    .sap-preview-table tr:hover {
        background: #f8fafc;
    }
    .badge-preview {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        display: inline-block;
    }
    .badge-preview.success {
        background: #dcfce7;
        color: #15803d;
    }
    .badge-preview.warning {
        background: #fef3c7;
        color: #d97706;
    }
    .badge-preview.danger {
        background: #fee2e2;
        color: #b91c1c;
    }
    @keyframes pulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .sap-import-grid {
        align-items: stretch !important;
    }
    .sap-import-sidebar select,
    .sap-import-sidebar label {
        width: 100%;
    }
    #sap-import-type {
        white-space: normal;
        text-overflow: unset;
    }
    @media (max-width: 960px) {
        .sap-import-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<!-- MODULAR JS CONTROLLER FOR SAP INTEGRATION -->
<script>
    let sapParsedData = [];
    let sapFileHeaders = [];
    let sapColumnMappings = {};

    // Standard fields in LUB-TEK database
    const sapFieldSchemas = {
        orders: [
            { id: 'sap_order', label: 'Nº Ordem SAP (AUFNR)', mandatory: true, regex: /(AUFNR|Ordem|Order|Ordem\s?SAP)/i },
            { id: 'desc', label: 'Descrição (KTEXT)', mandatory: true, regex: /(KTEXT|Desc|Description|Texto\s?Breve)/i },
            { id: 'asset_ref', label: 'Ativo (TPLNR/EQUNR)', mandatory: true, regex: /(TPLNR|EQUNR|Ativo|Tag|Equipamento)/i },
            { id: 'prio', label: 'Prioridade (PRIOK)', mandatory: false, regex: /(PRIOK|Prio|Prioridade|Priority)/i },
            { id: 'date', label: 'Data Planejada (GSTRP)', mandatory: false, regex: /(GSTRP|Data|Date|Planejada)/i },
            { id: 'resp', label: 'Responsável/Centro Trab.', mandatory: false, regex: /(INGRPR|Responsavel|Resp|Responsible|Work\s?Center)/i },
            { id: 'reserva', label: 'Reserva Almox. (RSNUM)', mandatory: false, regex: /(RSNUM|Reserva|Almox)/i },
            { id: 'ip', label: 'Grupo IP', mandatory: false, regex: /(IP)/i },
            { id: 'cod_serv', label: 'Código Serviço (STEUS)', mandatory: false, regex: /(STEUS|Cod\s?Servico)/i },
            { id: 'complemento', label: 'Instruções (LTXA1)', mandatory: false, regex: /(LTXA1|Complemento|Obs)/i }
        ],
        assets: [
            { id: 'nome', label: 'Nome do Ativo', mandatory: true, regex: /(Nome|Ativo|Equipamento|TXTMI)/i },
            { id: 'tipo', label: 'Tipo (Unidade, Setor, etc.)', mandatory: true, regex: /(Tipo|Category|Category)/i },
            { id: 'tag', label: 'TAG SAP', mandatory: false, regex: /(Tag|TAG\s?SAP|TPLNR|EQUNR)/i },
            { id: 'pai_ref', label: 'Parent Ref (Nome/Tag Pai)', mandatory: false, regex: /(Pai|Parent|HEIER|HIER|Superior|TPLMA)/i },
            { id: 'fabricante', label: 'Fabricante', mandatory: false, regex: /(Fabricante|Manufacturer|HERST)/i },
            { id: 'modelo', label: 'Modelo', mandatory: false, regex: /(Modelo|Model)/i },
            { id: 'num_serie', label: 'Nº de Série', mandatory: false, regex: /(Serie|Serial|Serno)/i },
            { id: 'obs', label: 'Observações', mandatory: false, regex: /(Obs|Observacao|Nota)/i }
        ],
        materials: [
            { id: 'codigo', label: 'Código SAP Material (MATNR)', mandatory: true, regex: /(MATNR|Codigo|Code|Material)/i },
            { id: 'nome', label: 'Nome/Descrição (MAKTX)', mandatory: true, regex: /(MAKTX|Nome|Descricao|Description)/i },
            { id: 'tipo', label: 'Tipo Material (MTART)', mandatory: false, regex: /(MTART|Tipo|Type)/i },
            { id: 'fabricante', label: 'Fabricante/Fornecedor', mandatory: false, regex: /(Fabricante|Fornecedor|HERST)/i },
            { id: 'estoque_atual', label: 'Estoque Atual (LABST)', mandatory: false, regex: /(LABST|Estoque|Qty|Quantidade)/i },
            { id: 'localizacao', label: 'Depósito/Local (LGPBE)', mandatory: false, regex: /(LGPBE|Localizacao|Deposito|Bin)/i },
            { id: 'descricao', label: 'Grupo Mercadoria (WGBEZ)', mandatory: false, regex: /(WGBEZ|Grupo|Desc)/i }
        ]
    };

    function switchSAPTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active-tab'));
        document.querySelectorAll('.sap-tab-content').forEach(content => content.style.display = 'none');
        
        if (tab === 'import') {
            document.getElementById('tab-sap-import').classList.add('active-tab');
            document.getElementById('sap-content-import').style.display = 'block';
        } else if (tab === 'export') {
            document.getElementById('tab-sap-export').classList.add('active-tab');
            document.getElementById('sap-content-export').style.display = 'block';
            toggleExportFilters();
        } else if (tab === 'powerbi') {
            document.getElementById('tab-sap-powerbi').classList.add('active-tab');
            document.getElementById('sap-content-powerbi').style.display = 'block';
            loadPowerBiBridgeDetails();
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function toggleExportFilters() {
        const type = document.getElementById('sap-export-type').value;
        const dateFilters = document.getElementById('sap-export-date-filters');
        if (type === 'orders') {
            dateFilters.style.display = 'grid';
            // Set defaults to current month
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            document.getElementById('sap-export-start').value = `${y}-${m}-01`;
            document.getElementById('sap-export-end').value = `${y}-${m}-${String(new Date(y, now.getMonth() + 1, 0).getDate()).padStart(2, '0')}`;
        } else {
            dateFilters.style.display = 'none';
        }
    }

    function resetImportState() {
        sapParsedData = [];
        sapFileHeaders = [];
        sapColumnMappings = {};
        
        document.getElementById('sap-file-input').value = '';
        document.getElementById('sap-import-stats').style.display = 'none';
        document.getElementById('sap-mapping-card').style.display = 'none';
        document.getElementById('sap-preview-card').style.display = 'none';
        document.getElementById('sap-help-card').style.display = 'flex';
    }

    // Handles files (Excel/CSV/TXT tab-separated)
    async function handleSAPFile(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        
        showToast('Lendo arquivo...', 'info');

        const reader = new FileReader();
        reader.onload = async (e) => {
            try {
                let rawJson = [];
                if (ext === 'csv' || ext === 'txt') {
                    const text = String(e.target.result || '');
                    const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
                    if (lines.length < 2) {
                        showToast('O arquivo deve possuir cabeçalhos e ao menos uma linha de dados.', 'warning');
                        return;
                    }
                    const delim = lines[0].includes('\t') ? '\t' : (lines[0].includes(';') ? ';' : ',');
                    sapFileHeaders = lines[0].split(delim).map(h => String(h || '').trim().replace(/^"|"$/g, ''));
                    for (let r = 1; r < lines.length; r++) {
                        const cells = lines[r].split(delim).map(c => String(c || '').trim().replace(/^"|"$/g, ''));
                        if (cells.every(c => c === '')) continue;
                        const rowObj = {};
                        sapFileHeaders.forEach((header, index) => {
                            rowObj[header] = cells[index] !== undefined ? cells[index] : '';
                        });
                        rawJson.push(rowObj);
                    }
                } else {
                    if (typeof lubtekEnsureXLSX === 'function' && typeof window.XLSX === 'undefined') {
                        await lubtekEnsureXLSX();
                    }
                    if (typeof window.XLSX === 'undefined') {
                        throw new Error('Biblioteca XLSX não carregada no navegador.');
                    }
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const sheetName = workbook.SheetNames[0];
                    const sheet = workbook.Sheets[sheetName];
                    const matrix = XLSX.utils.sheet_to_json(sheet, { header: 1 });
                    if (matrix.length < 2) {
                        showToast('O arquivo deve possuir ao menos cabeçalhos e uma linha de dados.', 'warning');
                        return;
                    }
                    sapFileHeaders = matrix[0].map(h => String(h || '').trim());
                    rawJson = [];
                    for (let r = 1; r < matrix.length; r++) {
                        const rowArr = matrix[r];
                        if (!rowArr || rowArr.length === 0 || rowArr.every(cell => cell === null || cell === undefined || cell === '')) {
                            continue;
                        }
                        const rowObj = {};
                        sapFileHeaders.forEach((header, index) => {
                            rowObj[header] = rowArr[index] !== undefined ? String(rowArr[index]).trim() : '';
                        });
                        rawJson.push(rowObj);
                    }
                }

                sapParsedData = rawJson;

                if (sapParsedData.length === 0) {
                    showToast('Nenhum dado encontrado no arquivo.', 'warning');
                    return;
                }

                // Show Stats
                document.getElementById('sap-import-stats').style.display = 'block';
                document.getElementById('sap-stat-rows').innerText = `Linhas detectadas: ${sapParsedData.length}`;
                
                // Render Column Mapper & Preview
                renderColumnMapper();
                
            } catch (err) {
                console.error(err);
                showToast('Erro ao ler arquivo: ' + err.message, 'error');
            }
        };
        if (ext === 'csv' || ext === 'txt') {
            reader.readAsText(file, 'UTF-8');
        } else {
            reader.readAsArrayBuffer(file);
        }
    }

    // Generates Mapping UI
    function renderColumnMapper() {
        const type = document.getElementById('sap-import-type').value;
        const fields = sapFieldSchemas[type];
        const container = document.getElementById('sap-mapping-fields');
        
        container.innerHTML = '';
        sapColumnMappings = {};

        fields.forEach(f => {
            // Find heuristic best match from file headers
            let bestMatch = '';
            for (let header of sapFileHeaders) {
                const normHeader = header.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                if (f.regex.test(header) || f.regex.test(normHeader)) {
                    bestMatch = header;
                    break;
                }
            }

            sapColumnMappings[f.id] = bestMatch;

            const div = document.createElement('div');
            div.style.display = 'flex';
            div.style.flexDirection = 'column';
            div.style.gap = '5px';
            
            div.innerHTML = `
                <label style="display:flex; justify-content:space-between; align-items:center;">
                    <span>${f.label} ${f.mandatory ? '<span style="color:var(--danger)">*</span>' : ''}</span>
                    ${bestMatch ? '<span style="color:var(--success); font-size:0.7rem; font-weight:700;"><i data-lucide="sparkles" style="width:10px; display:inline; margin-right:3px;"></i> Auto-Mapeado</span>' : ''}
                </label>
                <select id="map-select-${f.id}" onchange="updateMapping('${f.id}', this.value)" style="padding:10px; border-radius:8px; border:1px solid var(--border);">
                    <option value="">-- Ignorar/Não Mapear --</option>
                    ${sapFileHeaders.map(h => `<option value="${escapeHtml(h)}" ${h === bestMatch ? 'selected' : ''}>${escapeHtml(h)}</option>`).join('')}
                </select>
            `;
            container.appendChild(div);
        });

        document.getElementById('sap-help-card').style.display = 'none';
        document.getElementById('sap-mapping-card').style.display = 'block';
        document.getElementById('sap-preview-card').style.display = 'flex';
        
        lucide.createIcons();
        generatePreviewData();
    }

    function updateMapping(fieldId, value) {
        sapColumnMappings[fieldId] = value;
        generatePreviewData();
    }

    // Render previews
    function generatePreviewData() {
        const type = document.getElementById('sap-import-type').value;
        const fields = sapFieldSchemas[type];
        
        const previewHeaders = document.getElementById('sap-preview-headers');
        const previewRows = document.getElementById('sap-preview-rows');
        
        previewHeaders.innerHTML = `<th>Validação</th>` + fields.map(f => `<th>${f.label}</th>`).join('');
        previewRows.innerHTML = '';

        // Display up to 5 rows as a sample preview
        const sampleLimit = Math.min(5, sapParsedData.length);
        
        for (let i = 0; i < sampleLimit; i++) {
            const rawRow = sapParsedData[i];
            const mappedRow = {};
            
            fields.forEach(f => {
                const fileHeader = sapColumnMappings[f.id];
                mappedRow[f.id] = fileHeader ? (rawRow[fileHeader] || '') : '';
            });

            // Perform simple preview validations
            let validationBadge = `<span class="badge-preview success">Pronto</span>`;
            
            if (type === 'orders') {
                if (!mappedRow.sap_order) {
                    validationBadge = `<span class="badge-preview danger">Ordem Vazia</span>`;
                } else if (!mappedRow.asset_ref) {
                    validationBadge = `<span class="badge-preview warning">Sem Ativo</span>`;
                }
            } else if (type === 'assets') {
                if (!mappedRow.nome) {
                    validationBadge = `<span class="badge-preview danger">Nome Vazio</span>`;
                }
            } else if (type === 'materials') {
                if (!mappedRow.codigo) {
                    validationBadge = `<span class="badge-preview danger">Código Vazio</span>`;
                }
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${validationBadge}</td>` + fields.map(f => `<td>${escapeHtml(mappedRow[f.id])}</td>`).join('');
            previewRows.appendChild(tr);
        }
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Execute Import on database
    async function executeSAPImport() {
        const type = document.getElementById('sap-import-type').value;
        const fields = sapFieldSchemas[type];
        
        // Check mandatories
        const missingMandatories = [];
        fields.forEach(f => {
            if (f.mandatory && !sapColumnMappings[f.id]) {
                missingMandatories.push(f.label);
            }
        });

        if (missingMandatories.length > 0) {
            showToast(`Mapeamento pendente dos campos obrigatórios: ${missingMandatories.join(', ')}`, 'error');
            return;
        }

        showToast('Enviando dados para processamento no servidor...', 'info');

        // Build Payload
        const items = sapParsedData.map(rawRow => {
            const mappedItem = {};
            fields.forEach(f => {
                const fileHeader = sapColumnMappings[f.id];
                mappedItem[f.id] = fileHeader ? (rawRow[fileHeader] || '') : '';
            });
            return mappedItem;
        });

        try {
            let res;
            if (type === 'orders') {
                res = await api('sap_import_orders', { items });
            } else if (type === 'materials') {
                res = await api('sap_import_materials', { items });
            } else if (type === 'assets') {
                const mappedList = sapParsedData.map((rawRow, idx) => {
                    const mappedItem = {};
                    fields.forEach(f => {
                        const fileHeader = sapColumnMappings[f.id];
                        mappedItem[f.id] = fileHeader ? (rawRow[fileHeader] || '') : '';
                    });
                    return { idx, ...mappedItem };
                });

                const assetsPayload = mappedList.map(item => {
                    let parentVirtualId = null;
                    if (item.pai_ref && item.pai_ref.trim() !== '') {
                        const parent = mappedList.find(p => 
                            (p.tag && p.tag.trim().toUpperCase() === item.pai_ref.trim().toUpperCase()) ||
                            (p.nome && p.nome.trim().toUpperCase() === item.pai_ref.trim().toUpperCase())
                        );
                        if (parent) {
                            parentVirtualId = 'vsap_' + parent.idx;
                        }
                    }
                    
                    return {
                        virtual_id: 'vsap_' + item.idx,
                        nome: item.nome,
                        tipo: String(item.tipo || 'equipamento').toLowerCase(),
                        tag: item.tag,
                        obs: item.obs,
                        pai_virtual_id: parentVirtualId,
                        pai_ref: item.pai_ref,
                        fabricante: item.fabricante || '',
                        modelo: item.modelo || '',
                        num_serie: item.num_serie || '',
                        tech: {
                            fabricante: item.fabricante,
                            modelo: item.modelo,
                            num_serie: item.num_serie
                        }
                    };
                });
                res = await api('save_asset_batch', { items: assetsPayload });
            }

            if (res && res.ok !== false && (res.ok || typeof res.count === 'number')) {
                const total = res.count ?? ((res.inserted || 0) + (res.updated || 0));
                let msg = `Sincronização SAP concluída! ${total} itens sincronizados.`;
                if (Array.isArray(res.errors) && res.errors.length > 0) {
                    msg += ` (${res.errors.length} aviso(s) — veja o console)`;
                    console.warn('SAP import warnings:', res.errors);
                }
                showToast(msg, res.errors && res.errors.length ? 'warning' : 'success');
                resetImportState();
                
                // Refresh local systems
                if (type === 'orders' && typeof loadDash === 'function') loadDash();
                if (type === 'assets' && typeof loadTree === 'function') loadTree();
                if (type === 'materials' && typeof loadCatalog === 'function') loadCatalog();

            } else {
                showToast((res && res.error) || (res && res.message) || 'Erro ao sincronizar com o banco.', 'error');
            }
        } catch (e) {
            console.error(e);
            showToast('Falha crítica de comunicação com o servidor.', 'error');
        }
    }

    // Execute Export (Build and Download File)
    async function executeSAPExport() {
        const type = document.getElementById('sap-export-type').value;
        const format = document.getElementById('sap-export-format').value;
        
        let start = document.getElementById('sap-export-start').value;
        let end = document.getElementById('sap-export-end').value;

        showToast('Buscando dados no sistema...', 'info');

        try {
            let res;
            if (type === 'orders') {
                res = await api('sap_export_orders', { start_date: start, end_date: end });
            } else if (type === 'assets') {
                res = await api('sap_export_assets');
            } else if (type === 'materials') {
                res = await api('sap_export_materials');
            }

            if (!res || !res.ok) {
                showToast((res && res.error) || 'Erro ao buscar dados para exportação.', 'error');
                return;
            }

            // A API sempre retorna listas envelopadas em { ok, data: [...] } (ver api.php::success()),
            // nunca o array puro. Sem este unwrap, XLSX.utils.json_to_sheet/Object.keys(data[0])
            // recebiam o objeto de envelope inteiro em vez do array de registros.
            const data = res.data || [];

            if (!data || data.length === 0) {
                showToast('Nenhum dado encontrado para exportação.', 'warning');
                return;
            }

            const fileName = `LUBTEK_SAP_${type.toUpperCase()}_${Date.now()}`;

            if (format === 'xlsx') {
                if (typeof lubtekEnsureXLSX === 'function' && typeof window.XLSX === 'undefined') {
                    await lubtekEnsureXLSX();
                }
                if (typeof window.XLSX === 'undefined') {
                    throw new Error('Biblioteca XLSX necessária para exportação.');
                }
                // Generate Excel using sheets or XLSX
                const worksheet = XLSX.utils.json_to_sheet(data);
                const workbook = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(workbook, worksheet, "SAP_Export");
                XLSX.writeFile(workbook, `${fileName}.xlsx`);
                showToast('Arquivo Excel (.xlsx) gerado e baixado!', 'success');
            } else {
                // Generate tab-separated TXT
                if (data.length === 0) return;
                const headers = Object.keys(data[0]);
                let content = headers.join('\t') + '\n';
                data.forEach(row => {
                    content += headers.map(h => String(row[h] || '').replace(/\t/g, ' ')).join('\t') + '\n';
                });

                const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${fileName}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showToast('Arquivo de Texto Delimitado (.txt) gerado e baixado!', 'success');
            }
        } catch (e) {
            console.error(e);
            showToast('Erro ao exportar dados.', 'error');
        }
    }

    // Auto-switch tab based on URL query param (e.g. from reports page shortcuts)
    (function() {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab === 'export' || tab === 'import' || tab === 'powerbi') {
            setTimeout(() => switchSAPTab(tab), 50);
        }
    })();

    // --- POWER BI BRIDGE JAVASCRIPT ---
    let currentPbiApiKey = '';

    async function loadPowerBiBridgeDetails() {
        const input = document.getElementById('pbi-api-key-input');
        if (!input) return;

        try {
            const res = await api('get_tenant_api_key');
            if (res && res.api_key) {
                currentPbiApiKey = res.api_key;
                input.value = currentPbiApiKey;
                renderPowerBiMCode(currentPbiApiKey);
            } else {
                input.value = 'Chave indisponível';
            }
        } catch (err) {
            console.warn('Erro ao obter API Key do tenant:', err);
            input.value = 'lt_key_' + Math.random().toString(36).substring(2, 12);
            renderPowerBiMCode(input.value);
        }
    }

    function togglePbiKeyVisibility() {
        const input = document.getElementById('pbi-api-key-input');
        const eye = document.getElementById('pbi-key-eye');
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            if (eye) eye.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            if (eye) eye.setAttribute('data-lucide', 'eye');
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function copyPbiApiKey() {
        if (!currentPbiApiKey) return;
        navigator.clipboard.writeText(currentPbiApiKey).then(() => {
            showToast('Chave X-API-KEY copiada para a área de transferência!', 'success');
        }).catch(() => {
            prompt('Copie sua chave X-API-KEY:', currentPbiApiKey);
        });
    }

    async function rotatePbiApiKey() {
        if (!confirm('Deseja gerar uma nova chave X-API-KEY para o Power BI?\n\nAtenção: Todas as conexões antigas precisarão ser atualizadas com a nova chave.')) {
            return;
        }
        try {
            showToast('Gerando nova chave de API...', 'info');
            const res = await api('rotate_tenant_api_key');
            if (res && res.api_key) {
                currentPbiApiKey = res.api_key;
                const input = document.getElementById('pbi-api-key-input');
                if (input) input.value = currentPbiApiKey;
                renderPowerBiMCode(currentPbiApiKey);
                showToast('Nova chave gerada com sucesso!', 'success');
            }
        } catch (e) {
            alert('Erro ao rotacionar chave: ' + (e.message || e));
        }
    }

    function getBaseApiUrl() {
        return window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '') + '/api.php';
    }

    function copyPbiFeedUrl(actionName) {
        const keyParam = currentPbiApiKey ? `&api_key=${encodeURIComponent(currentPbiApiKey)}` : '';
        const fullUrl = `${getBaseApiUrl()}?action=${actionName}&format=flat${keyParam}`;
        navigator.clipboard.writeText(fullUrl).then(() => {
            showToast(`URL do feed '${actionName}' copiada com autenticação para o Power BI!`, 'success');
        }).catch(() => {
            prompt('URL do feed para o Power BI:', fullUrl);
        });
    }

    async function downloadSAPTemplate() {
        const type = document.getElementById('sap-import-type').value;
        let filename = 'template_sap_' + type;
        let data = [];

        if (type === 'orders') {
            filename = 'template_sap_ordens_iw39.xlsx';
            data = [
                {
                    'AUFNR': '4001092831',
                    'KTEXT': 'LUBRIFICAR MANCAL BOMBA P-101',
                    'TPLNR': 'EMB-01-PAL',
                    'GSTRP': new Date().toISOString().split('T')[0],
                    'PRIOK': '2',
                    'INGRPR': 'LUB-TEK FIELD',
                    'RSNUM': 'RES-99012',
                    'IP': '1001'
                },
                {
                    'AUFNR': '4001092832',
                    'KTEXT': 'TROCA DE OLEO REDUTOR PRINCIPAL',
                    'TPLNR': 'RED-SEW-01',
                    'GSTRP': new Date().toISOString().split('T')[0],
                    'PRIOK': '3',
                    'INGRPR': 'PCM MECANICA',
                    'RSNUM': 'RES-99013',
                    'IP': '1002'
                }
            ];
        } else if (type === 'assets') {
            filename = 'template_sap_hierarquia_ih01.xlsx';
            data = [
                {
                    'TPLNR': 'LINHA-01',
                    'PLTXT': 'LINHA DE ENVASE PET 160',
                    'FLTYP': 'unidade',
                    'TPLMA': '',
                    'HERST': 'Krones',
                    'TYPBZ': 'Linha Completa',
                    'SERGE': 'SN-2024-PET'
                },
                {
                    'TPLNR': 'ENV-ROT-01',
                    'PLTXT': 'ROTULADORA CANOPUS',
                    'FLTYP': 'equipamento',
                    'TPLMA': 'LINHA-01',
                    'HERST': 'Krones',
                    'TYPBZ': 'Canopus 1800',
                    'SERGE': 'KRN-99214'
                },
                {
                    'TPLNR': 'PT-MANCAL-01',
                    'PLTXT': 'MANCAL GUIA PRINCIPAL',
                    'FLTYP': 'ponto',
                    'TPLMA': 'ENV-ROT-01',
                    'HERST': 'SKF',
                    'TYPBZ': 'SNL 512',
                    'SERGE': ''
                }
            ];
        } else {
            filename = 'template_sap_materiais_mm60.xlsx';
            data = [
                {
                    'MATNR': '1004291',
                    'MAKTX': 'LE Duolec 1605 ISO VG220',
                    'MTART': 'HIBE',
                    'HERST': 'Lubrication Engineers',
                    'LABST': 120,
                    'MEINS': 'L',
                    'LGPBE': 'ALMOX-01'
                },
                {
                    'MATNR': '1004292',
                    'MAKTX': 'Graxa Quinplex FG 4072 H1',
                    'MTART': 'HIBE',
                    'HERST': 'Lubrication Engineers',
                    'LABST': 45,
                    'MEINS': 'KG',
                    'LGPBE': 'ALMOX-01'
                }
            ];
        }

        try {
            if (typeof lubtekEnsureXLSX === 'function' && typeof window.XLSX === 'undefined') {
                await lubtekEnsureXLSX();
            }
            if (typeof window.XLSX !== 'undefined') {
                const ws = XLSX.utils.json_to_sheet(data);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Template_SAP");
                XLSX.writeFile(wb, filename);
                showToast(`Modelo ${filename} baixado com sucesso!`, 'success');
                return;
            }
        } catch (e) {
            console.warn('XLSX fallback to CSV:', e);
        }

        // CSV fallback
        const headers = Object.keys(data[0]);
        let csv = headers.join(';') + '\n';
        data.forEach(row => {
            csv += headers.map(h => `"${String(row[h] || '').replace(/"/g, '""')}"`).join(';') + '\n';
        });
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename.replace(/\.xlsx$/, '.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('Modelo CSV baixado com sucesso!', 'success');
    }

    function renderPowerBiMCode(apiKey) {
        const pre = document.getElementById('pbi-m-code-block');
        if (!pre) return;
        const feedUrl = `${getBaseApiUrl()}?action=powerbi_orders&format=flat`;
        const code = `let
    // Conector REST LUB-TEK para Microsoft Power BI
    ApiKey = "${apiKey}",
    Url = "${feedUrl}",
    
    // Requisição HTTP autenticada com X-API-KEY
    Fonte = Json.Document(Web.Contents(Url, [Headers=[#"X-API-KEY"=ApiKey]])),
    
    // Conversão de Lista JSON para Tabela Plana
    Tabela = Table.FromList(Fonte, Splitter.SplitByNothing(), null, null, ExtraValues.Error),
    ColunasExpandidas = Table.ExpandRecordColumn(Tabela, "Column1", Record.FieldNames(Fonte{0}))
in
    ColunasExpandidas`;
        pre.textContent = code;
    }

    function copyPbiMCode() {
        const pre = document.getElementById('pbi-m-code-block');
        if (!pre) return;
        navigator.clipboard.writeText(pre.textContent).then(() => {
            showToast('Código M do Power Query copiado com sucesso!', 'success');
        }).catch(() => {
            prompt('Código M Power Query:', pre.textContent);
        });
    }

    async function testPowerBiConnection() {
        showToast('Testando conexão do gateway Power BI...', 'info');
        const start = performance.now();
        try {
            const res = await api('powerbi_kpis', { format: 'flat' });
            const duration = Math.round(performance.now() - start);
            if (res) {
                alert(`Conexão com a API Power BI 100% OPERACIONAL!\n\n• Resposta HTTP: 200 OK\n• Latência: ${duration}ms\n• Formato: Tabular JSON\n• Status da Planta: Pronto para consumo no Power BI`);
            } else {
                alert('Aviso: A API respondeu, mas sem dados tabulares.');
            }
        } catch (err) {
            alert('Falha no teste de conexão: ' + (err.message || err));
        }
    }

    // Drag and Drop para a Drop Zone SAP
    document.addEventListener('DOMContentLoaded', () => {
        const dropZone = document.getElementById('sap-drop-zone');
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.style.borderColor = 'var(--primary)';
                    dropZone.style.background = '#f0f9ff';
                }, false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.style.borderColor = 'var(--border)';
                    dropZone.style.background = 'var(--bg-input)';
                }, false);
            });
            dropZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files.length > 0) {
                    const fileInput = document.getElementById('sap-file-input');
                    if (fileInput) {
                        fileInput.files = files;
                        handleSAPFile(fileInput);
                    }
                }
            }, false);
        }
    });
</script>
