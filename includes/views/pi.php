<div id="view-pi" class="view-container active view-block"
    style="padding:15px; background: #f8fafc;">
    
    <style>
        /* Modern High-Contrast Accessibility Focus Styling */
        #view-pi input:focus-visible,
        #view-pi select:focus-visible,
        #view-pi button:focus-visible,
        #view-pi textarea:focus-visible,
        #view-pi [type="range"]:focus-visible {
            outline: 3px solid var(--primary) !important;
            outline-offset: 1px;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15) !important;
        }
        
        .tr-tag-row {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .tr-tag-row:hover {
            background-color: #f1f5f9;
        }
        
        /* Focus visible for table row accessibility (keyboard arrows/tabs) */
        .tr-tag-row:focus-visible {
            outline: 2px dashed var(--primary) !important;
            outline-offset: -2px;
            background-color: #f8fafc;
        }

        .tr-tag-row.selected-row {
            background-color: #e0f2fe !important;
            border-left: 4px solid var(--primary);
        }
    </style>
    
    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="margin:0; font-size: 2.2rem; font-weight: 800; letter-spacing: -1.5px; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="database" style="color: var(--primary); width: 36px; height: 36px;"></i>
                Plataforma PI System <span style="font-weight: 300; font-size: 1.5rem; opacity: 0.7;">Telemetry</span>
            </h1>
            <p style="margin: 5px 0 0 0; color: #64748b; font-size: 0.95rem; font-weight: 500;">
                Integração industrial em tempo real e Manutenção Baseada em Condição (CBM).
            </p>
        </div>
        
        <!-- Live Status Indicators -->
        <div style="display: flex; gap: 15px; align-items: center;">
            <div style="background: white; border: 1px solid var(--border); padding: 10px 18px; border-radius: 12px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                <div style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; animation: glow-green 1.5s infinite;"></div>
                <span style="font-size: 0.8rem; font-weight: 800; color: #1e293b; letter-spacing: 0.5px;">PI WEB API: <span style="color:#10b981;">CONECTADO</span></span>
            </div>
            
            <button onclick="toggleSimulationMode()" id="btn-simulation-toggle" class="btn-outline" 
                style="background: white; padding: 10px 18px; border-radius: 12px; font-weight: 800; display: flex; align-items: center; gap: 8px; border: 1px solid var(--border); cursor: pointer;">
                <i data-lucide="play" id="sim-icon" style="width: 16px; color: #0ea5e9;"></i>
                <span id="sim-text">Simular Telemetria Real</span>
            </button>
        </div>
    </div>

    <!-- MAIN INTERACTIVE GRID -->
    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 25px; margin-bottom: 30px;" id="pi-main-grid">
        
        <!-- LEFT PANEL: TAGS REGISTER & MONITOR -->
        <div style="display: flex; flex-direction: column; gap: 25px;">
            
            <!-- TAG FORM & TABLE CONTAINER -->
            <div class="card" style="padding: 24px; border-radius: 16px; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="sliders" style="width: 18px; color: var(--primary);"></i>
                        Configuração de PI Tags (Sensores)
                    </h3>
                    <button onclick="openTagForm()" class="btn" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 8px; background: var(--primary);" aria-haspopup="dialog" aria-expanded="false" id="btn-nova-tag">
                        <i data-lucide="plus" style="width: 14px; margin-right: 4px;"></i> Nova Tag
                    </button>
                </div>

                <!-- TAG EDITING MODAL / FORM CONTAINER (INLINE COLLAPSIBLE) -->
                <div id="pi-tag-form-container" style="display: none; background: #f8fafc; border: 1px solid var(--border); padding: 20px; border-radius: 12px; margin-bottom: 20px; animation: slideDown 0.3s ease-out;" role="dialog" aria-labelledby="form-tag-title">
                    <h4 id="form-tag-title" style="margin: 0 0 15px 0; font-size: 0.95rem; font-weight: 800; color: #0f172a;">Cadastrar Novo Sensor</h4>
                    <form id="pi-tag-form" onsubmit="savePITag(event)">
                        <input type="hidden" id="f-tag-id" name="id">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label for="f-tag-name" style="display:block; font-size:0.75rem; font-weight:800; color:#475569; margin-bottom:5px; text-transform:uppercase;">Identificador da Tag (AVEVA PI)</label>
                                <input type="text" id="f-tag-name" placeholder="Ex: LUBTEK.USINA1.MOTO.TEMP" required
                                    style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:0.85rem; font-weight: 600; text-transform: uppercase;">
                            </div>
                            <div>
                                <label for="f-tag-label" style="display:block; font-size:0.75rem; font-weight:800; color:#475569; margin-bottom:5px; text-transform:uppercase;">Descrição amigável</label>
                                <input type="text" id="f-tag-label" placeholder="Ex: Temperatura Mancal L.A." required
                                    style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:0.85rem; font-weight: 600;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label for="f-tag-ativo" style="display:block; font-size:0.75rem; font-weight:800; color:#475569; margin-bottom:5px; text-transform:uppercase;">Ativo Lub-Tek Vinculado</label>
                                <select id="f-tag-ativo" required
                                    style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:0.85rem; font-weight:600; cursor:pointer;">
                                    <option value="">Carregando ativos...</option>
                                </select>
                            </div>
                            <div>
                                <label for="f-tag-unit" style="display:block; font-size:0.75rem; font-weight:800; color:#475569; margin-bottom:5px; text-transform:uppercase;">Unidade de Medida</label>
                                <input type="text" id="f-tag-unit" placeholder="Ex: °C, mm/s, bar" required
                                    style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:0.85rem; font-weight: 600;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div>
                                <label for="f-tag-warning" style="display:block; font-size:0.75rem; font-weight:800; color:#475569; margin-bottom:5px; text-transform:uppercase; color: #d97706;">Limite de Alerta (Warning)</label>
                                <input type="number" step="0.1" id="f-tag-warning" placeholder="Ex: 75.0" required
                                    style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:0.85rem; font-weight: 600;">
                            </div>
                            <div>
                                <label for="f-tag-critical" style="display:block; font-size:0.75rem; font-weight:800; color:#475569; margin-bottom:5px; text-transform:uppercase; color: #dc2626;">Limite Crítico (Autopower O.S.)</label>
                                <input type="number" step="0.1" id="f-tag-critical" placeholder="Ex: 90.0" required
                                    style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:0.85rem; font-weight: 600;">
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px;">
                            <button type="button" onclick="closeTagForm()" class="btn-outline" style="padding:8px 16px; border-radius:8px;">Cancelar</button>
                            <button type="submit" class="btn" style="padding:8px 20px; border-radius:8px; background: var(--primary);">Salvar Tag</button>
                        </div>
                    </form>
                </div>

                <!-- TAGS DATA TABLE -->
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;" id="pi-tags-table">
                        <thead>
                            <tr style="border-bottom: 2px solid #f1f5f9; color: #475569; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                                <th style="padding: 12px 10px;">Tag Name / Sensor</th>
                                <th style="padding: 12px 10px;">Ativo Bound</th>
                                <th style="padding: 12px 10px; text-align: center;">Última Leitura</th>
                                <th style="padding: 12px 10px; text-align: center;">Limites</th>
                                <th style="padding: 12px 10px; text-align: center;">Status</th>
                                <th style="padding: 12px 10px; text-align: right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="pi-tags-body" style="font-size: 0.85rem; font-weight: 600; color: #1e293b;">
                            <tr>
                                <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">
                                    <i class="spin" style="display:inline-block; width:20px; height:20px; border:2px solid var(--primary); border-top-color:transparent; border-radius:50%; margin-bottom: 10px;"></i>
                                    <div>Carregando catálogo de sensores PI...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: REALTIME MONITOR (PI VISION VIEW) -->
        <div style="display: flex; flex-direction: column; gap: 25px;">
            
            <!-- REALTIME GRAPH CARD -->
            <div class="card" style="padding: 24px; border-radius: 16px; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.02); display: flex; flex-direction: column; min-height: 480px;">
                <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="activity" style="width: 18px; color: var(--success);"></i>
                            AVEVA PI Vision Live Chart
                        </h3>
                        <span id="selected-tag-badge" class="tag" style="background:#e0f2fe; color:#0284c7; font-size:0.75rem; font-weight:800;">NENHUM SELECIONADO</span>
                    </div>
                </div>

                <!-- VIEW CHANNELS (EMPTY STATE HANDLER) -->
                <div id="pi-monitor-empty-state" style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding: 40px; color: #64748b;">
                    <i data-lucide="line-chart" style="width: 48px; height: 48px; stroke-width: 1.5; color: #cbd5e1; margin-bottom: 15px;"></i>
                    <h4 style="margin:0 0 8px 0; font-size:1rem; font-weight:800; color:#1e293b;">Selecione um Sensor</h4>
                    <p style="margin:0; font-size:0.85rem; max-width: 250px;">Clique em qualquer linha da tabela à esquerda para carregar os dados gráficos em tempo real.</p>
                </div>

                <!-- DYNAMIC MONITOR CONTENT -->
                <div id="pi-monitor-content" style="display: none; flex:1; flex-direction: column; gap: 20px;" role="region" aria-live="polite" aria-label="Painel de Monitoramento Dinâmico">
                    
                    <!-- Live Stats Banner -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div style="background: #f8fafc; border: 1px solid var(--border); padding: 15px; border-radius: 12px; text-align: center;">
                            <span style="font-size:0.7rem; display:block; color:#64748b; text-transform:uppercase; font-weight:800; margin-bottom:5px;">VALOR EM TEMPO REAL</span>
                            <div style="font-size: 2rem; font-weight: 900; color: #0f172a; letter-spacing: -1px; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <span id="monitor-value">0.0</span>
                                <span id="monitor-unit" style="font-size: 1rem; color: #64748b; font-weight: 700;">°C</span>
                            </div>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid var(--border); padding: 15px; border-radius: 12px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <span style="font-size:0.7rem; display:block; color:#64748b; text-transform:uppercase; font-weight:800; margin-bottom:5px;">CONDIÇÃO OPERACIONAL</span>
                            <span id="monitor-status" class="st-pill" style="font-size: 0.9rem; padding: 6px 16px;">NORMAL</span>
                        </div>
                    </div>

                    <!-- Line Chart Canvas -->
                    <div style="position: relative; flex: 1; min-height: 180px; width: 100%; background: #0f172a; border-radius: 12px; border: 1px solid #1e293b; padding: 15px; display: flex; align-items: center;" role="img" aria-label="Gráfico de linha dinâmico mostrando as tendências temporais recentes das leituras do sensor selecionado com demarcação pontilhada dos limites operacionais.">
                        <canvas id="pi-live-chart" style="width: 100%; height: 180px;" aria-hidden="true"></canvas>
                    </div>

                    <!-- PREDICATIVE INTERVENTION CONTROLS -->
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid #f1f5f9; padding-top: 15px; flex-wrap: wrap; gap: 10px;">
                        <button onclick="requestAiDiagnose()" class="btn" style="background: linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%); color: white; display:flex; align-items:center; gap:8px; padding: 10px 18px; border-radius:10px; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);">
                            <i data-lucide="brain-circuit" style="width:16px;"></i> Diagnosticar com I.A. Predita
                        </button>
                        
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;" id="last-update-time">
                            Atualizado em: --:--:--
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI PREDICTOR DIAGNOSTIC INSIGHT CARD (REALTIME NEURAL ASSISTANT) -->
            <div class="card" id="pi-ai-card" style="display: none; border-left: 5px solid #7c3aed; background: linear-gradient(90deg, #f3e8ff 0%, #ffffff 100%); padding: 24px; border-radius: 16px; box-shadow: 0 4px 20px rgba(124, 58, 237, 0.08); border: 1px solid rgba(124, 58, 237, 0.15); animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
                <div style="display: flex; gap: 18px; align-items: flex-start;">
                    <div style="background: #7c3aed; color: white; width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);">
                        <i data-lucide="brain" style="width: 22px; height: 22px;"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <span style="font-size: 0.75rem; font-weight: 900; color: #7c3aed; text-transform: uppercase; letter-spacing: 1px;">Diagnóstico Neural Preditivo</span>
                            <span id="ai-diag-urgencia" class="tag" style="font-size: 0.65rem; padding: 2px 10px; border-radius: 12px; font-weight: 800;">ALTA</span>
                        </div>
                        <div id="ai-diag-failmode" style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 6px;">Fadiga Mecânica de Rolamento</div>
                        <p id="ai-diag-desc" style="margin: 0; font-size: 0.82rem; color: #475569; line-height: 1.4; font-weight: 600;">O sensor aponta vibração anômala persistente.</p>
                        
                        <div style="background: white; border: 1px dashed rgba(124, 58, 237, 0.3); border-radius: 10px; padding: 12px; margin-top: 12px;">
                            <div style="font-size: 0.75rem; font-weight: 800; color: #7c3aed; text-transform: uppercase; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                                <i data-lucide="check-square" style="width: 12px;"></i> Recomendação Prescritiva da I.A.
                            </div>
                            <div id="ai-diag-reco" style="font-size: 0.8rem; color: #1e293b; line-height: 1.4; font-weight: 600;">Executar plano de lubrificação de segurança.</div>
                        </div>
                        
                        <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #64748b; font-weight: 700; border-top: 1px solid rgba(0,0,0,0.03); padding-top: 10px;">
                            <span>Score Confiabilidade Estimado:</span>
                            <span id="ai-diag-score" style="color: #10b981; font-weight: 800; font-size: 0.85rem;">92%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SIMULATION CONTROL PANEL (WHEN SIMULATION MODE ACTIVE) -->
            <div class="card" id="pi-simulation-panel" style="display: none; padding: 24px; border-radius: 16px; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.02); animation: slideDown 0.3s ease-out;">
                <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="cpu" style="width: 18px; color: #0ea5e9;"></i>
                    Mesa de Teste & Injeção de Sinais PI Web API
                </h3>
                <p style="margin: 0 0 20px 0; font-size: 0.82rem; color: #64748b; font-weight: 600;">
                    Use os sliders abaixo para ajustar os níveis do sensor e enviar uma requisição HTTP REST simulando dados reais vindos da fábrica. Assista LUB-TEK gerar O.S. automáticas se cruzar limites críticos!
                </p>
                
                <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:800; color:#475569; margin-bottom:8px; text-transform:uppercase;">
                            <label for="sim-slider" style="cursor:pointer;">Valor da Telemetria a Injetar</label>
                            <span id="sim-slider-val" style="color: #0ea5e9; font-size: 0.9rem;">50.0</span>
                        </div>
                        <input type="range" id="sim-slider" min="10" max="150" value="50" step="0.5" oninput="updateSimValueLabel(this.value)"
                            style="width: 100%; cursor: pointer;" aria-label="Valor da Telemetria a Injetar" aria-valuemin="10.0" aria-valuemax="150.0" aria-valuenow="50.0">
                        <div style="display:flex; justify-content:space-between; font-size:0.7rem; color:#94a3b8; margin-top:4px;">
                            <span>10.0 Min</span>
                            <span>Warning: <span id="sim-limit-warn">75.0</span></span>
                            <span>Critical: <span id="sim-limit-crit">90.0</span></span>
                            <span>150.0 Max</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="injectTelemetry()" class="btn" style="background: #0ea5e9; padding: 10px 20px; border-radius: 10px; font-weight: 800;" aria-label="Enviar sinal de telemetria simulada para API LUBTEK">
                        <i data-lucide="send" style="width: 14px; margin-right: 6px;"></i> Injetar Sinal de Sensor
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- GLOWING GREEN KEYFRAME STYLING -->
    <style>
        @keyframes glow-green {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .tag-pill {
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        #pi-tags-table th {
            border-bottom: 2px solid #f1f5f9;
        }
        #pi-tags-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .tr-tag-row:hover {
            background-color: #f8fafc;
            cursor: pointer;
        }
        .tr-tag-row.selected-row {
            background-color: #f0f9ff !important;
            border-left: 3px solid var(--primary);
        }
    </style>
</div>

<!-- INLINE VISUALIZER SCRIPT CORE -->
<script>
    let piTags = [];
    let activeTag = null;
    let autoSimInterval = null;
    let liveHistory = [];
    let sparklineChart = null;

    /**
     * Initialize PI Dashboard
     */
    async function loadPiView() {
        await loadAssetsDropdown();
        await loadPITags();
        
        // Expose to window for global access
        window.loadPiView = loadPiView;

        // Register Global Accessibility Keyboard Bindings (A11y)
        if (!window.piA11yRegistered) {
            window.piA11yRegistered = true;
            document.addEventListener('keydown', (e) => {
                // Escape key collapses the modal dialog container
                if (e.key === 'Escape') {
                    const container = document.getElementById('pi-tag-form-container');
                    if (container && container.style.display === 'block') {
                        closeTagForm();
                    }
                }
                
                // ArrowDown and ArrowUp to navigate the custom sensor focus table
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    const active = document.activeElement;
                    if (active && active.classList.contains('tr-tag-row')) {
                        e.preventDefault(); // Stop native page scrolling
                        const rows = Array.from(document.querySelectorAll('.tr-tag-row'));
                        const currentIndex = rows.indexOf(active);
                        if (currentIndex !== -1) {
                            const nextIndex = e.key === 'ArrowDown' ? currentIndex + 1 : currentIndex - 1;
                            if (nextIndex >= 0 && nextIndex < rows.length) {
                                rows[nextIndex].focus();
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Fetch and Populate Assets for Tag Form Dropdown
     */
    async function loadAssetsDropdown() {
        const select = document.getElementById('f-tag-ativo');
        if (!select) return;

        try {
            const res = await api('get_tree');
            const tree = Array.isArray(res) ? res : (res?.data || []);
            
            let html = '<option value="">Selecione o Ativo...</option>';
            
            function traverse(nodes, depth = 0) {
                if (!nodes || !Array.isArray(nodes)) return;
                nodes.forEach(n => {
                    const pad = '&nbsp;'.repeat(depth * 3);
                    const nome = typeof escapeHtml === 'function' ? escapeHtml(n.nome || 'Sem nome') : (n.nome || 'Sem nome');
                    const tag = typeof escapeHtml === 'function' ? escapeHtml(n.tag || 'N/A') : (n.tag || 'N/A');
                    html += `<option value="${n.id}">${pad} ${nome} [${tag}]</option>`;
                    if (n.children) traverse(n.children, depth + 1);
                });
            }
            
            traverse(tree);
            select.innerHTML = html;
        } catch (e) {
            select.innerHTML = '<option value="">Erro ao carregar ativos</option>';
        }
    }

    /**
     * Load PI Tags from SQLite database
     */
    async function loadPITags() {
        const body = document.getElementById('pi-tags-body');
        if (!body) return;

        try {
            const res = await api('get_pi_tags');
            piTags = Array.isArray(res) ? res : (res?.data || []);

            if (piTags.length === 0) {
                body.innerHTML = `
                    <tr>
                        <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">
                            <i data-lucide="database-backup" style="width: 32px; height: 32px; opacity:0.5; margin-bottom: 10px; display:block; margin:0 auto 10px;"></i>
                            <div>Nenhum sensor de telemetria cadastrado.</div>
                            <div style="font-size:0.8rem; margin-top:5px; color:#94a3b8;">Crie uma Tag no botão acima ou ative o Simulador para gerar dados!</div>
                        </td>
                    </tr>
                `;
                lucide.createIcons();
                return;
            }

            body.innerHTML = piTags.map(t => {
                const statusSlug = String(t.current_status || 'OK').toLowerCase();
                let statusBadge = `<span class="st-pill st-concluido tag-pill">NORMAL</span>`;
                
                if (statusSlug === 'critical') {
                    statusBadge = `<span class="st-pill st-critica tag-pill" style="animation: pulse 1s infinite;">CRÍTICO</span>`;
                } else if (statusSlug === 'warning') {
                    statusBadge = `<span class="st-pill st-pendente tag-pill">ALERT</span>`;
                }

                const valueStr = t.current_value !== null ? parseFloat(t.current_value).toFixed(1) : '0.0';
                const warningStr = t.warning_threshold !== null ? parseFloat(t.warning_threshold).toFixed(0) : '-';
                const criticalStr = t.critical_threshold !== null ? parseFloat(t.critical_threshold).toFixed(0) : '-';
                
                const selectedClass = activeTag && activeTag.id === t.id ? 'selected-row' : '';

                // FIX: escapa dados vindos do banco (tag_name/label/ativo_nome/ativo_tag/unit)
                // antes de injetar via innerHTML — evita XSS caso um ativo/tag tenha nome
                // contendo HTML/script.
                const eTagName = escapeHtml(t.tag_name);
                const eLabel = escapeHtml(t.label);
                const eAtivoNome = escapeHtml(t.ativo_nome || 'Não Vinculado');
                const eAtivoTag = escapeHtml(t.ativo_tag || 'N/A');
                const eUnit = escapeHtml(t.unit || '');
                const aTagName = escapeAttr(t.tag_name);
                const aLabel = escapeAttr(t.label);

                return `
                    <tr class="tr-tag-row ${selectedClass}" 
                        tabindex="0" 
                        role="button" 
                        aria-pressed="${activeTag && activeTag.id === t.id ? 'true' : 'false'}" 
                        aria-label="Sensor ${aTagName}: ${aLabel}. Status atual: ${statusSlug.toUpperCase()}. Clique para monitorar." 
                        onclick="selectPITag(${t.id})" 
                        onkeydown="if(event.key === 'Enter' || event.key === ' ') { selectPITag(${t.id}); event.preventDefault(); }" 
                        id="tag-row-${t.id}">
                        <td style="padding: 12px 10px;">
                            <div style="font-weight: 700; color: var(--primary); font-family: monospace;">${eTagName}</div>
                            <div style="font-size: 0.75rem; color:#64748b; font-weight:500;">${eLabel}</div>
                        </td>
                        <td style="padding: 12px 10px;">
                            <div style="font-weight:600; color:#334155;">${eAtivoNome}</div>
                            <div style="font-size: 0.7rem; color:#94a3b8;">Tag Lub-Tek: ${eAtivoTag}</div>
                        </td>
                        <td style="padding: 12px 10px; text-align: center; font-size: 1rem; font-weight: 800; font-family: monospace;">
                            ${valueStr} <span style="font-size: 0.75rem; color: #64748b;">${eUnit}</span>
                        </td>
                        <td style="padding: 12px 10px; text-align: center; font-size: 0.75rem; color: #64748b; font-family: monospace;">
                            W: <span style="color:#d97706; font-weight:700;">${warningStr}</span> | C: <span style="color:#dc2626; font-weight:700;">${criticalStr}</span>
                        </td>
                        <td style="padding: 12px 10px; text-align: center;">
                            ${statusBadge}
                        </td>
                        <td style="padding: 12px 10px; text-align: right;" onclick="event.stopPropagation()">
                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                <button onclick="editTagInline(${t.id})" class="btn-outline" style="padding:4px 8px; border-radius:6px; font-size:0.75rem;" title="Editar" aria-label="Editar Sensor ${aTagName}"><i data-lucide="edit-3" style="width:14px;"></i></button>
                                <button onclick="deletePITag(${t.id})" class="btn-outline" style="padding:4px 8px; border-radius:6px; font-size:0.75rem; color:var(--danger); border-color:rgba(239, 68, 68, 0.2);" title="Excluir" aria-label="Excluir Sensor ${aTagName}"><i data-lucide="trash-2" style="width:14px;"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            lucide.createIcons();
            
            // Re-highlight if active exists
            if (activeTag) {
                const row = document.getElementById(`tag-row-${activeTag.id}`);
                if (row) row.classList.add('selected-row');
            }
        } catch (e) {
            body.innerHTML = `<tr><td colspan="6" style="padding: 30px; text-align: center; color: var(--danger);">Falha ao carregar catálogo PI.</td></tr>`;
        }
    }

    /**
     * Select a tag and trigger timeseries graph load
     */
    async function selectPITag(id) {
        activeTag = piTags.find(t => t.id === id);
        if (!activeTag) return;

        // Visual highlights
        document.querySelectorAll('.tr-tag-row').forEach(r => r.classList.remove('selected-row'));
        const row = document.getElementById(`tag-row-${id}`);
        if (row) row.classList.add('selected-row');

        // Hide Empty state & Show dashboard contents
        document.getElementById('pi-monitor-empty-state').style.display = 'none';
        document.getElementById('pi-monitor-content').style.display = 'flex';
        
        // Set labels
        document.getElementById('selected-tag-badge').innerText = activeTag.tag_name;
        document.getElementById('monitor-value').innerText = parseFloat(activeTag.current_value || 0).toFixed(1);
        document.getElementById('monitor-unit').innerText = activeTag.unit || '°C';
        
        // Status Badge Style
        const stEl = document.getElementById('monitor-status');
        stEl.className = 'st-pill';
        const stSlug = String(activeTag.current_status || 'OK').toLowerCase();
        if (stSlug === 'critical') {
            stEl.classList.add('st-critica');
            stEl.innerText = 'ALERTA CRÍTICO';
            stEl.style.animation = 'pulse 1s infinite';
        } else if (stSlug === 'warning') {
            stEl.classList.add('st-pendente');
            stEl.innerText = 'ADVERTÊNCIA';
            stEl.style.animation = 'none';
        } else {
            stEl.classList.add('st-concluido');
            stEl.innerText = 'CONDIÇÃO NORMAL';
            stEl.style.animation = 'none';
        }

        // Last updated label
        const lastUpTime = activeTag.last_update ? activeTag.last_update.split(' ')[1] : '--:--:--';
        document.getElementById('last-update-time').innerText = `Última atualização: ${lastUpTime}`;

        // Set simulation slider values
        document.getElementById('sim-slider-val').innerText = parseFloat(activeTag.current_value || 50).toFixed(1);
        document.getElementById('sim-slider').value = parseFloat(activeTag.current_value || 50);
        document.getElementById('sim-limit-warn').innerText = activeTag.warning_threshold || '75';
        document.getElementById('sim-limit-crit').innerText = activeTag.critical_threshold || '90';

        // Load timeseries data & draw chart
        await loadTelemetryChart(activeTag.id);

        // Hide AI report card (force re-assessment if needed)
        document.getElementById('pi-ai-card').style.display = 'none';
    }

    /**
     * Render line chart of telemetry readings
     */
    async function loadTelemetryChart(tagId) {
        try {
            const history = await api('get_pi_telemetry', { tag_id: tagId, limit: 20 });
            liveHistory = Array.isArray(history) ? history : (history?.data || []);
            
            drawChart();
        } catch (e) {
            console.error("Failed to load telemetry history chart", e);
        }
    }

    /**
     * HTML Canvas Timeseries Drawer (Ultra-performant, zero-external-dependency fallback)
     */
    function drawChart() {
        const canvas = document.getElementById('pi-live-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const W = canvas.clientWidth;
        const H = canvas.clientHeight;
        canvas.width = W;
        canvas.height = H;

        // Dark industrial background
        ctx.fillStyle = '#0f172a';
        ctx.fillRect(0, 0, W, H);

        if (liveHistory.length === 0) {
            ctx.fillStyle = '#64748b';
            ctx.font = 'bold 12px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sem leituras no banco de dados. Inicie a simulação!', W/2, H/2);
            return;
        }

        const values = liveHistory.map(h => parseFloat(h.value));
        const maxVal = Math.max(...values, activeTag.critical_threshold || 100) * 1.15;
        const minVal = Math.min(...values, 0) * 0.85;
        const range = maxVal - minVal;

        // Draw Grid Lines & Limits
        ctx.strokeStyle = '#1e293b';
        ctx.lineWidth = 1;
        for (let i = 1; i < 4; i++) {
            const y = H - (H / 4) * i;
            ctx.beginPath();
            ctx.moveTo(35, y);
            ctx.lineTo(W - 15, y);
            ctx.stroke();
        }

        // Draw Threshold lines
        if (activeTag.warning_threshold !== null) {
            const yWarn = H - ((activeTag.warning_threshold - minVal) / range) * H;
            ctx.strokeStyle = 'rgba(217, 119, 6, 0.4)';
            ctx.lineWidth = 1.5;
            ctx.setLineDash([5, 5]);
            ctx.beginPath();
            ctx.moveTo(35, yWarn);
            ctx.lineTo(W - 15, yWarn);
            ctx.stroke();
            ctx.setLineDash([]);
        }

        if (activeTag.critical_threshold !== null) {
            const yCrit = H - ((activeTag.critical_threshold - minVal) / range) * H;
            ctx.strokeStyle = 'rgba(220, 38, 38, 0.5)';
            ctx.lineWidth = 1.5;
            ctx.setLineDash([3, 3]);
            ctx.beginPath();
            ctx.moveTo(35, yCrit);
            ctx.lineTo(W - 15, yCrit);
            ctx.stroke();
            ctx.setLineDash([]);
        }

        // Plot Timeseries Line
        const paddingLeft = 40;
        const paddingRight = 15;
        const drawWidth = W - paddingLeft - paddingRight;
        const pointsCount = liveHistory.length;
        
        ctx.beginPath();
        ctx.strokeStyle = activeTag.current_status === 'CRITICAL' ? '#ef4444' : (activeTag.current_status === 'WARNING' ? '#f59e0b' : '#38bdf8');
        ctx.lineWidth = 3;
        
        // FIX: com 1 único ponto de telemetria, (pointsCount - 1) é zero e a divisão
        // gerava NaN/Infinity, quebrando o desenho do gráfico silenciosamente.
        const xForIndex = (index) => pointsCount > 1
            ? paddingLeft + (drawWidth / (pointsCount - 1)) * index
            : paddingLeft + drawWidth / 2;

        liveHistory.forEach((h, index) => {
            const val = parseFloat(h.value);
            const x = xForIndex(index);
            const y = H - ((val - minVal) / range) * H;

            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();

        // Fill area under line with soft gradient
        ctx.lineTo(paddingLeft + drawWidth, H);
        ctx.lineTo(paddingLeft, H);
        ctx.closePath();
        // FIX: gradiente de preenchimento só distinguia CRITICAL x resto (sempre azul para
        // WARNING), deixando a área azul sob uma linha laranja/âmbar. Agora acompanha as
        // mesmas 3 cores da linha (crítico/alerta/normal).
        const grad = ctx.createLinearGradient(0, 0, 0, H);
        const fillTopColor = activeTag.current_status === 'CRITICAL'
            ? 'rgba(239, 68, 68, 0.15)'
            : (activeTag.current_status === 'WARNING' ? 'rgba(245, 158, 11, 0.15)' : 'rgba(56, 189, 248, 0.15)');
        grad.addColorStop(0, fillTopColor);
        grad.addColorStop(1, 'rgba(15, 23, 42, 0)');
        ctx.fillStyle = grad;
        ctx.fill();

        // Draw points
        const pointColor = activeTag.current_status === 'CRITICAL'
            ? '#ef4444'
            : (activeTag.current_status === 'WARNING' ? '#f59e0b' : '#38bdf8');
        liveHistory.forEach((h, index) => {
            const val = parseFloat(h.value);
            const x = xForIndex(index);
            const y = H - ((val - minVal) / range) * H;

            ctx.fillStyle = pointColor;
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fill();

            // Accent last reading
            if (index === pointsCount - 1) {
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.arc(x, y, 6, 0, Math.PI * 2);
                ctx.stroke();
            }
        });

        // Left vertical axis text values
        ctx.fillStyle = '#94a3b8';
        ctx.font = '10px monospace';
        ctx.textAlign = 'right';
        ctx.fillText(maxVal.toFixed(0), 30, 15);
        ctx.fillText(minVal.toFixed(0), 30, H - 5);
        if (activeTag.critical_threshold) {
            const yCrit = H - ((activeTag.critical_threshold - minVal) / range) * H;
            ctx.fillStyle = '#ef4444';
            ctx.fillText('CRIT', 30, yCrit + 4);
        }
    }

    /**
     * CRUD: Open Tag Form
     */
    function openTagForm() {
        const btn = document.getElementById('btn-nova-tag');
        if (btn) btn.setAttribute('aria-expanded', 'true');
        
        const container = document.getElementById('pi-tag-form-container');
        container.style.display = 'block';
        
        document.getElementById('pi-tag-form').reset();
        document.getElementById('f-tag-id').value = '';
        document.getElementById('form-tag-title').innerText = 'Cadastrar Novo Sensor (PI Tag)';
        
        // Dynamic Focus shift for A11y
        setTimeout(() => {
            document.getElementById('f-tag-name').focus();
        }, 50);
    }

    /**
     * CRUD: Close Tag Form
     */
    function closeTagForm() {
        const btn = document.getElementById('btn-nova-tag');
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
            btn.focus();
        }
        document.getElementById('pi-tag-form-container').style.display = 'none';
    }

    /**
     * CRUD: Save PI Tag Configuration
     */
    async function savePITag(e) {
        e.preventDefault();
        
        const payload = {
            id: document.getElementById('f-tag-id').value || null,
            tag_name: document.getElementById('f-tag-name').value,
            label: document.getElementById('f-tag-label').value,
            ativo_id: document.getElementById('f-tag-ativo').value,
            unit: document.getElementById('f-tag-unit').value,
            warning_threshold: document.getElementById('f-tag-warning').value,
            critical_threshold: document.getElementById('f-tag-critical').value
        };

        try {
            const res = await api('save_pi_tag', payload);
            if (res.ok) {
                showToast('Tag PI salva com sucesso!', 'success');
                closeTagForm();
                await loadPITags();
                
                // Keep selected highlight if editing current active tag
                if (payload.id && activeTag && activeTag.id == payload.id) {
                    await selectPITag(parseInt(payload.id));
                }
            } else {
                showToast(res.error || 'Erro ao salvar Tag PI', 'error');
            }
        } catch (err) {
            showToast('Erro técnico ao salvar Tag.', 'error');
        }
    }

    /**
     * CRUD: Edit PI Tag (Populate form)
     */
    function editTagInline(id) {
        const tag = piTags.find(t => t.id === id);
        if (!tag) return;

        openTagForm();
        document.getElementById('form-tag-title').innerText = `Editar Sensor #${tag.id}`;
        document.getElementById('f-tag-id').value = tag.id;
        document.getElementById('f-tag-name').value = tag.tag_name;
        document.getElementById('f-tag-label').value = tag.label;
        document.getElementById('f-tag-ativo').value = tag.ativo_id || '';
        document.getElementById('f-tag-unit').value = tag.unit || '';
        document.getElementById('f-tag-warning').value = tag.warning_threshold;
        document.getElementById('f-tag-critical').value = tag.critical_threshold;
    }

    /**
     * CRUD: Delete PI Tag
     */
    async function deletePITag(id) {
        if (!confirm('Deseja excluir permanentemente este sensor e todo seu histórico de telemetria?')) return;

        try {
            const res = await api('delete_pi_tag', { id });
            if (res.ok) {
                showToast('Tag PI excluída com sucesso.', 'info');
                
                if (activeTag && activeTag.id === id) {
                    activeTag = null;
                    document.getElementById('pi-monitor-empty-state').style.display = 'flex';
                    document.getElementById('pi-monitor-content').style.display = 'none';
                    document.getElementById('pi-ai-card').style.display = 'none';
                }
                
                await loadPITags();
            } else {
                showToast(res.error || 'Falha ao excluir.', 'error');
            }
        } catch (e) {
            showToast('Erro ao realizar exclusão.', 'error');
        }
    }

    /**
     * SIMULATION: Toggle Live Factory Telemetry Feed
     */
    function toggleSimulationMode() {
        const btn = document.getElementById('btn-simulation-toggle');
        const text = document.getElementById('sim-text');
        const icon = document.getElementById('sim-icon');
        const simPanel = document.getElementById('pi-simulation-panel');

        if (autoSimInterval) {
            // STOP
            clearInterval(autoSimInterval);
            autoSimInterval = null;
            btn.style.background = 'white';
            btn.style.borderColor = 'var(--border)';
            text.innerText = 'Simular Telemetria Real';
            icon.setAttribute('data-lucide', 'play');
            icon.style.color = '#0ea5e9';
            simPanel.style.display = 'none';
            showToast('Simulador de telemetria parado.', 'info');
        } else {
            // START
            btn.style.background = 'rgba(14, 165, 233, 0.08)';
            btn.style.borderColor = '#0ea5e9';
            text.innerText = 'Simulador Ativo (PARAR)';
            icon.setAttribute('data-lucide', 'square');
            icon.style.color = '#dc2626';
            simPanel.style.display = 'block';
            
            showToast('Simulação industrial ativa. Ajuste os sliders de injeção!', 'success');

            // Auto Fluctuate Selected tag value and post telemetry in background every 4s
            autoSimInterval = setInterval(async () => {
                if (!activeTag) return;
                
                // Fluctuate slightly (-2 to +2 units)
                let mockVal = parseFloat(activeTag.current_value || 50);
                mockVal += (Math.random() - 0.5) * 4;
                mockVal = Math.max(10, Math.min(150, mockVal)); // Bound limits
                
                // Update slider visuals silently
                document.getElementById('sim-slider-val').innerText = mockVal.toFixed(1);
                document.getElementById('sim-slider').value = mockVal;

                // Push
                await api('receive_pi_telemetry', {
                    points: [{ tag_name: activeTag.tag_name, value: mockVal }]
                });
                
                // Refresh Tags and selected details
                await loadPITags();
                
                // Refetch specific telemetry list and redraw
                const updated = piTags.find(t => t.id === activeTag.id);
                if (updated) {
                    activeTag = updated;
                    document.getElementById('monitor-value').innerText = parseFloat(activeTag.current_value).toFixed(1);
                    
                    const lastUpTime = activeTag.last_update ? activeTag.last_update.split(' ')[1] : '--:--:--';
                    document.getElementById('last-update-time').innerText = `Última atualização: ${lastUpTime}`;
                    
                    // Status Badge Style updates
                    const stEl = document.getElementById('monitor-status');
                    stEl.className = 'st-pill';
                    const stSlug = String(activeTag.current_status || 'OK').toLowerCase();
                    
                    if (stSlug === 'critical') {
                        stEl.classList.add('st-critica');
                        stEl.innerText = 'ALERTA CRÍTICO';
                        stEl.style.animation = 'pulse 1s infinite';
                    } else if (stSlug === 'warning') {
                        stEl.classList.add('st-pendente');
                        stEl.innerText = 'ADVERTÊNCIA';
                        stEl.style.animation = 'none';
                    } else {
                        stEl.classList.add('st-concluido');
                        stEl.innerText = 'CONDIÇÃO NORMAL';
                        stEl.style.animation = 'none';
                    }

                    // Reload timeseries array & redraw chart
                    await loadTelemetryChart(activeTag.id);
                }

            }, 4000);
        }
        lucide.createIcons();
    }

    /**
     * SIMULATION: Slider visual tracker
     */
    function updateSimValueLabel(val) {
        document.getElementById('sim-slider-val').innerText = parseFloat(val).toFixed(1);
    }

    /**
     * SIMULATION: Inject Signal directly via API Webhook
     */
    async function injectTelemetry() {
        if (!activeTag) return showToast('Selecione uma Tag ativa para injetar telemetria.', 'warning');
        
        const injectVal = parseFloat(document.getElementById('sim-slider').value);
        
        try {
            showToast('Injetando sinal de sensor na API...', 'info');
            const res = await api('receive_pi_telemetry', {
                points: [{ tag_name: activeTag.tag_name, value: injectVal }]
            });

            if (res.ok) {
                // Check if O.S. was automatically generated!
                const data = res.data || res;
                if (data.created_orders && data.created_orders.length > 0) {
                    showToast(`⚠️ LIMITE CRÍTICO EXCEDIDO! Ordem de Serviço de Confiabilidade gerada automaticamente para o Ativo!`, 'error');
                } else {
                    showToast('Sinal PI processado com sucesso.', 'success');
                }
                
                // Refresh state
                await loadPITags();
                await selectPITag(activeTag.id);
            } else {
                showToast(res.error || 'Erro na injeção do sinal.', 'error');
            }
        } catch (e) {
            showToast('Erro técnico ao comunicar com a PI Web API.', 'error');
        }
    }

    /**
     * AI: Neural diagnostics mapping
     */
    async function requestAiDiagnose() {
        if (!activeTag) return;

        const card = document.getElementById('pi-ai-card');
        const urgBadge = document.getElementById('ai-diag-urgencia');
        const fmLabel = document.getElementById('ai-diag-failmode');
        const descText = document.getElementById('ai-diag-desc');
        const recoText = document.getElementById('ai-diag-reco');
        const scoreLabel = document.getElementById('ai-diag-score');

        card.style.display = 'block';
        urgBadge.className = 'tag';
        urgBadge.innerText = 'PROCESSANDO...';
        urgBadge.style.background = '#e0e7ff';
        urgBadge.style.color = '#4f46e5';
        fmLabel.innerText = 'Analisando histórico de telemetria...';
        descText.innerText = 'O Core Neural LUB-TEK está avaliando as correlações físicas de tendências e falhas incipientes. Aguarde...';
        recoText.innerText = 'Carregando plano preditivo da I.A...';
        scoreLabel.innerText = '--%';

        try {
            const res = await api('pi_ai_diagnose', { tag_id: activeTag.id });
            const data = res.ok === false ? null : (res.data || res);

            if (data) {
                urgBadge.innerText = String(data.urgencia || 'Média').toUpperCase();
                
                // Color mapping for urgency
                const urg = String(data.urgencia || '').toLowerCase();
                if (urg === 'crítica' || urg === 'alta') {
                    urgBadge.style.background = '#fee2e2';
                    urgBadge.style.color = '#dc2626';
                } else if (urg === 'média') {
                    urgBadge.style.background = '#fef3c7';
                    urgBadge.style.color = '#d97706';
                } else {
                    urgBadge.style.background = '#dcfce7';
                    urgBadge.style.color = '#16a34a';
                }

                fmLabel.innerText = data.modo_falha || 'Anomalia Indeterminada';
                descText.innerText = data.diagnostico || 'Instabilidade detectada.';
                recoText.innerText = data.recomendacao_prescritiva || 'Revisar lubrificação no mancal.';
                scoreLabel.innerText = data.conclusao_ia || '90%';
            } else {
                throw new Error();
            }
        } catch (e) {
            urgBadge.innerText = 'INDISPONÍVEL';
            urgBadge.style.background = '#f1f5f9';
            urgBadge.style.color = '#64748b';
            fmLabel.innerText = 'Sem Insight de IA';
            descText.innerText = 'Não foi possível completar o diagnóstico preditivo neural. Verifique sua chave API do Gemini no arquivo config.php.';
            recoText.innerText = 'Realizar checkup manual de ruído, calor e vibração nas instalações físicas do ativo imediatamente.';
            scoreLabel.innerText = 'N/A';
        }
    }
</script>
