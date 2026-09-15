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
    <div style="display: flex; gap: 15px; border-bottom: 1px solid var(--border); padding-bottom: 1px; margin-bottom: 10px;">
        <button id="tab-sap-import" onclick="switchSAPTab('import')" class="tab-btn active-tab" 
            style="border: none; background: none; font-size: 1rem; font-weight: 700; padding: 10px 20px; border-bottom: 3px solid var(--primary); color: var(--primary); cursor: pointer;">
            <i data-lucide="upload" style="width: 16px; margin-right: 8px; vertical-align: middle;"></i>Importar do SAP
        </button>
        <button id="tab-sap-export" onclick="switchSAPTab('export')" class="tab-btn" 
            style="border: none; background: none; font-size: 1rem; font-weight: 700; padding: 10px 20px; border-bottom: 3px solid transparent; color: var(--text-muted); cursor: pointer;">
            <i data-lucide="download" style="width: 16px; margin-right: 8px; vertical-align: middle;"></i>Exportar para o SAP
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
        } else {
            document.getElementById('tab-sap-export').classList.add('active-tab');
            document.getElementById('sap-content-export').style.display = 'block';
            toggleExportFilters();
        }
        lucide.createIcons();
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
        if (tab === 'export' || tab === 'import') {
            // Wait slightly for DOM to be fully ready
            setTimeout(() => switchSAPTab(tab), 50);
        }
    })();
</script>
