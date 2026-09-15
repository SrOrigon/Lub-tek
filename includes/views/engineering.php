<div id="view-calc" class="view-container active view-block"
    style="padding:15px; background:#f8fafc;">
    <div class="flex-between" style="margin-bottom:20px;">
        <div>
            <h1 style="margin:0; font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px;">Engenharia Preditiva</h1>
            <p style="color:var(--text-muted); margin-top:5px; font-size: 0.95rem;">Catálogo ISO 15 / ISO 355 / DIN 618 e cálculos SKF · ASTM D341.</p>
        </div>
    </div>
    <div style="display:flex; flex:1; min-height:400px; gap:30px;">

        <!-- SIDEBAR MENU (Wider & More Readable) -->
        <div style="width:320px; flex-shrink:0;">
            <div class="calc-menu-item active" onclick="switchCalc('rolamentos', this)">
                <i data-lucide="circle-dot" style="width:18px;"></i> Cálculo Rolamentos
            </div>
            <div class="calc-menu-item" onclick="switchCalc('viscosidade', this)">
                <i data-lucide="droplet" style="width:18px;"></i> Viscosidade (ASTM)
            </div>
            <div class="calc-menu-item" onclick="switchCalc('kappa', this)">
                <i data-lucide="sigma" style="width:18px;"></i> Fator Kappa (κ)
            </div>
            <div class="calc-menu-item" onclick="switchCalc('bucha', this)">
                <i data-lucide="cylinder" style="width:18px;"></i> Cálculo de Bucha
            </div>
            <div class="calc-menu-item" onclick="switchCalc('fatordn', this)">
                <i data-lucide="gauge" style="width:18px;"></i> Cálculo Fator DN
            </div>
            <!-- Keep style block -->
            <style>
                .calc-menu-item {
                    padding: 15px 20px;
                    border-radius: 12px;
                    cursor: pointer;
                    color: var(--text-muted);
                    font-weight: 700;
                    font-size: 0.95rem;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    transition: all 0.2s;
                    margin-bottom: 8px;
                    border: 1px solid transparent;
                }

                .calc-menu-item:hover {
                    background: rgba(0, 0, 0, 0.05);
                    color: var(--primary);
                }

                .calc-menu-item.active {
                    background: rgba(14, 165, 233, 0.1);
                    color: var(--primary);
                    border-left: 3px solid var(--primary);
                }
            </style>
        </div>

        <!-- MAIN CONTENT AREA -->
        <div style="flex:1;">

            <!-- 1. ROLAMENTOS CALCULATOR (Enhanced) -->
            <div id="calc-panel-rolamentos" class="calc-panel">
                <div class="calc-card-synth">
                    <div class="ccs-header">
                        <h2 class="ccs-title">Cálculo Avançado de Relubrificação</h2>
                        <p class="ccs-subtitle">Modelo Físico Avançado (SKF/Schaeffler) considerando fatores ambientais.
                        </p>
                    </div>
                    <div class="ccs-body">
                        <!-- Primary Inputs -->
                        <div class="ccs-grid-4" style="margin-bottom:15px;">
                            <div>
                                <label class="ccs-label"># Código ISO (catálogo global)</label>
                                <input id="synth-rol-code" class="ccs-input" list="eng-bearing-codes"
                                    placeholder="Ex: 6205, 22210, NU2208, HK2016" autocomplete="off"
                                    oninput="onEngBearingCodeInput(this)">
                                <datalist id="eng-bearing-codes"></datalist>
                                <div id="eng-bearing-hint" style="font-size:0.75rem;color:var(--text-muted);margin-top:6px;font-weight:600;">
                                    Digite o código — dimensões oficiais ISO 15 / ISO 355 / DIN 618.
                                </div>
                            </div>
                            <div>
                                <label class="ccs-label">Temp (°C)</label>
                                <input id="synth-rol-temp" class="ccs-input" placeholder="Ex: 70" value="70">
                            </div>
                            <div>
                                <label class="ccs-label">RPM</label>
                                <input id="synth-rol-rpm" class="ccs-input" placeholder="Ex: 1750" value="1750">
                            </div>
                            <div>
                                <label class="ccs-label">Regime (Horas/Dia)</label>
                                <select id="synth-rol-hours" class="ccs-input">
                                    <option value="24" selected>24h/dia (Contínuo - 3 Turnos)</option>
                                    <option value="16">16h/dia (2 Turnos)</option>
                                    <option value="12">12h/dia (Turno Estendido)</option>
                                    <option value="8">8h/dia (1 Turno Comum)</option>
                                </select>
                            </div>
                            <div>
                                <label class="ccs-label">ISO VG (óleo / óleo-base)</label>
                                <select id="synth-rol-vg" class="ccs-input">
                                    <option value="">Auto (κ ≈ 1)</option>
                                    <option value="32">VG 32</option>
                                    <option value="46">VG 46</option>
                                    <option value="68" selected>VG 68</option>
                                    <option value="100">VG 100</option>
                                    <option value="150">VG 150</option>
                                    <option value="220">VG 220</option>
                                    <option value="320">VG 320</option>
                                    <option value="460">VG 460</option>
                                </select>
                            </div>
                        </div>

                        <!-- Environmental Factors -->
                        <div
                            style="background:#f8fafc; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #e2e8f0;">
                            <label class="ccs-label" style="margin-bottom:10px; color:var(--primary)!important;">Fatores
                                de Correção Ambiental</label>
                            <div class="ccs-grid-4">
                                <div>
                                    <label class="ccs-label">Vibração</label>
                                    <select id="synth-rol-vib" class="ccs-input">
                                        <option value="low">Baixa (&lt;2 mm/s)</option>
                                        <option value="mid">Média (2-5 mm/s)</option>
                                        <option value="high">Alta (&gt;5 mm/s)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ccs-label">Contaminação</label>
                                    <select id="synth-rol-cont" class="ccs-input">
                                        <option value="clean">Sala Limpa/Lab</option>
                                        <option value="normal" selected>Industrial Normal</option>
                                        <option value="dirty">Poeira/Sujo</option>
                                        <option value="extreme">Extrema (Lama)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ccs-label">Umidade</label>
                                    <select id="synth-rol-moist" class="ccs-input">
                                        <option value="dry">Seco (&lt;60%)</option>
                                        <option value="humid">Úmido (&gt;60%)</option>
                                        <option value="wet">Água Direta</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ccs-label">Posição Eixo</label>
                                    <select id="synth-rol-pos" class="ccs-input">
                                        <option value="horiz">Horizontal</option>
                                        <option value="vert">Vertical</option>
                                        <option value="outer_rot">Anel Ext. Rotativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <button class="ccs-btn" onclick="runSynthRolCalc()">Calcular Relubrificação (Modelo IA)</button>
                    </div>
                </div>
                <div id="synth-rol-result" class="ccs-result-area">
                    <i data-lucide="brain-circuit"
                        style="width:48px; height:48px; opacity:0.3; margin-bottom:10px;"></i>
                    <p>Preencha os dados acima para processar o algoritmo.</p>
                </div>
            </div>

            <!-- 2. VISCOSITY CALCULATOR (ASTM D341) -->
            <div id="calc-panel-viscosidade" class="calc-panel" style="display:none;">
                <h2 style="color:var(--primary); margin-bottom:20px; font-size:1.5rem;">Calculadora de Viscosidade</h2>
                <div class="calc-card-synth">
                    <div class="ccs-header" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                        <h2 class="ccs-title">Viscosidade Operacional (ASTM D341)</h2>
                        <p class="ccs-subtitle">Estime ν na temperatura de trabalho (ASTM D341). Informe o rolamento e o RPM para obter κ = ν / ν₁.</p>
                    </div>
                    <div class="ccs-body">
                        <div class="ccs-grid-3">
                            <div>
                                <label class="ccs-label">ISO VG (a 40°C)</label>
                                <select id="synth-visc-vg" class="ccs-input">
                                    <option value="22">VG 22</option>
                                    <option value="32">VG 32</option>
                                    <option value="46" selected>VG 46</option>
                                    <option value="68">VG 68</option>
                                    <option value="100">VG 100</option>
                                    <option value="150">VG 150</option>
                                    <option value="220">VG 220</option>
                                    <option value="320">VG 320</option>
                                    <option value="460">VG 460</option>
                                    <option value="680">VG 680</option>
                                    <option value="1000">VG 1000</option>
                                    <option value="1500">VG 1500</option>
                                </select>
                            </div>
                            <div>
                                <label class="ccs-label">Temperatura Real (°C)</label>
                                <input id="synth-visc-temp" class="ccs-input" placeholder="Ex: 85">
                            </div>
                            <div>
                                <label class="ccs-label">Índice Viscosidade (VI)</label>
                                <input id="synth-visc-vi" class="ccs-input" value="95" placeholder="Min: 95, Sint: 150">
                            </div>
                        </div>
                        <div class="ccs-grid-3">
                            <div>
                                <label class="ccs-label">Código ISO (opcional)</label>
                                <input id="synth-visc-code" class="ccs-input" list="eng-bearing-codes" placeholder="Ex: 6205"
                                    oninput="onEngBearingCodeInput(this)">
                            </div>
                            <div>
                                <label class="ccs-label">d × D (mm)</label>
                                <div style="display:flex;gap:8px;">
                                    <input id="synth-visc-d" class="ccs-input" placeholder="d">
                                    <input id="synth-visc-D" class="ccs-input" placeholder="D">
                                </div>
                            </div>
                            <div>
                                <label class="ccs-label">RPM do mancal</label>
                                <input id="synth-visc-rpm" class="ccs-input" placeholder="Ex: 1750">
                            </div>
                        </div>
                        <button class="ccs-btn" onclick="runSynthViscCalc()"
                            style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">Calcular Viscosidade
                            Real</button>
                    </div>
                </div>
                <div id="synth-visc-result" class="ccs-result-area">
                    <i data-lucide="droplets" style="width:48px; height:48px; opacity:0.3; margin-bottom:10px;"></i>
                    <p>Resultado da análise do fluido.</p>
                </div>
            </div>

            <!-- 2b. FATOR KAPPA -->
            <div id="calc-panel-kappa" class="calc-panel" style="display:none;">
                <h2 style="color:var(--primary); margin-bottom:20px; font-size:1.5rem;">Fator Kappa (κ)</h2>
                <div class="calc-card-synth">
                    <div class="ccs-header" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);">
                        <h2 class="ccs-title">Relação de viscosidade SKF</h2>
                        <p class="ccs-subtitle">κ = ν / ν₁ — viscosidade real do óleo na temperatura de trabalho dividida pela viscosidade nominal do rolamento (dm e RPM).</p>
                    </div>
                    <div class="ccs-body">
                        <div class="ccs-grid-4">
                            <div>
                                <label class="ccs-label">Código ISO</label>
                                <input id="synth-kap-code" class="ccs-input" list="eng-bearing-codes" placeholder="Ex: 6205"
                                    oninput="onEngBearingCodeInput(this)">
                            </div>
                            <div>
                                <label class="ccs-label">d interno (mm)</label>
                                <input id="synth-kap-d" class="ccs-input" placeholder="25">
                            </div>
                            <div>
                                <label class="ccs-label">D externo (mm)</label>
                                <input id="synth-kap-D" class="ccs-input" placeholder="52">
                            </div>
                            <div>
                                <label class="ccs-label">B largura (mm)</label>
                                <input id="synth-kap-B" class="ccs-input" placeholder="15">
                            </div>
                        </div>
                        <div class="ccs-grid-4">
                            <div>
                                <label class="ccs-label">RPM</label>
                                <input id="synth-kap-rpm" class="ccs-input" placeholder="1750" value="1750">
                            </div>
                            <div>
                                <label class="ccs-label">Temp. trabalho (°C)</label>
                                <input id="synth-kap-temp" class="ccs-input" placeholder="70" value="70">
                            </div>
                            <div>
                                <label class="ccs-label">ISO VG (óleo / óleo-base)</label>
                                <select id="synth-kap-vg" class="ccs-input">
                                    <option value="32">VG 32</option>
                                    <option value="46">VG 46</option>
                                    <option value="68" selected>VG 68</option>
                                    <option value="100">VG 100</option>
                                    <option value="150">VG 150</option>
                                    <option value="220">VG 220</option>
                                    <option value="320">VG 320</option>
                                    <option value="460">VG 460</option>
                                    <option value="680">VG 680</option>
                                </select>
                            </div>
                            <div>
                                <label class="ccs-label">Índice VI</label>
                                <input id="synth-kap-vi" class="ccs-input" value="95" placeholder="Mineral 95 · PAO 140">
                            </div>
                        </div>
                        <p style="font-size:0.8rem;color:#64748b;margin:0 0 16px;line-height:1.45;">
                            <strong>κ 1–4:</strong> filme ideal (próximo de 4 = vida máxima).
                            <strong>κ &lt; 1:</strong> filme fino — aditivos EP.
                            <strong>κ &gt; 4:</strong> viscosidade excessiva — cisalhamento e calor.
                            ν₁ usa o diagrama SKF; ν usa ASTM D341. A quantidade de graxa segue Gp = 0,005·D·B.
                        </p>
                        <button class="ccs-btn" onclick="runSynthKappaCalc()"
                            style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);">Calcular fator Kappa</button>
                    </div>
                </div>
                <div id="synth-kap-result" class="ccs-result-area">
                    <i data-lucide="sigma" style="width:48px; height:48px; opacity:0.3; margin-bottom:10px;"></i>
                    <p>Informe o rolamento, a rotação e o ISO VG para obter κ, ν, ν₁ e a dose de graxa.</p>
                </div>
            </div>

            <!-- 3. BUCHAS CALCULATOR -->
            <div id="calc-panel-bucha" class="calc-panel" style="display:none;">
                <h2 style="color:var(--primary); margin-bottom:20px; font-size:1.5rem;">Cálculo de Bucha</h2>
                <div class="calc-card-synth">
                    <div class="ccs-header">
                        <h2 class="ccs-title">Cálculo de Relubrificação de Buchas</h2>
                        <p class="ccs-subtitle">Insira os dados da bucha para obter o cálculo de relubrificação.</p>
                    </div>
                    <div class="ccs-body">
                        <div class="ccs-grid-3">
                            <div>
                                <label class="ccs-label">Diâmetro (mm)</label>
                                <input id="synth-buc-d" class="ccs-input" placeholder="Ex: 50">
                            </div>
                            <div>
                                <label class="ccs-label">Comprimento L (mm)</label>
                                <input id="synth-buc-l" class="ccs-input" placeholder="Ex: 50">
                            </div>
                            <div>
                                <label class="ccs-label">Velocidade (RPM)</label>
                                <input id="synth-buc-rpm" class="ccs-input" placeholder="Ex: 100">
                            </div>
                        </div>
                        <div class="ccs-grid-2">
                            <div>
                                <label class="ccs-label">Fator de serviço k</label>
                                <select id="synth-buc-k" class="ccs-input">
                                    <option value="0.5">0,5 — leve / limpo</option>
                                    <option value="1" selected>1,0 — industrial normal</option>
                                    <option value="2">2,0 — poeira / carga</option>
                                    <option value="3">3,0 — choque / contaminado</option>
                                </select>
                            </div>
                            <div>
                                <label class="ccs-label">Tipo de Graxa</label>
                                <input id="synth-buc-grease" class="ccs-input" placeholder="Ex: NLGI 2 EP">
                            </div>
                        </div>
                        <button class="ccs-btn" onclick="runSynthBucCalc()">Calcular</button>
                    </div>
                </div>
                <div id="synth-buc-result" class="ccs-result-area">
                    <i data-lucide="brain-circuit"
                        style="width:48px; height:48px; opacity:0.3; margin-bottom:10px;"></i>
                    <p>O resultado do seu cálculo aparecerá aqui.</p>
                </div>
            </div>

            <!-- 4. FATOR DN CALCULATOR -->
            <div id="calc-panel-fatordn" class="calc-panel" style="display:none;">
                <h2 style="color:var(--primary); margin-bottom:20px; font-size:1.5rem;">Cálculo Fator DN</h2>
                <div class="calc-card-synth">
                    <div class="ccs-header">
                        <h2 class="ccs-title">Cálculo de Fator DN</h2>
                        <p class="ccs-subtitle">Insira as medidas do rolamento e a rotação para obter o Fator DN.</p>
                    </div>
                    <div class="ccs-body">
                        <div class="ccs-grid-3">
                            <div>
                                <label class="ccs-label">Código ISO (opcional)</label>
                                <input id="synth-dn-code" class="ccs-input" list="eng-bearing-codes" placeholder="Ex: 6208">
                            </div>
                            <div>
                                <label class="ccs-label">Diâmetro Interno (d) mm</label>
                                <input id="synth-dn-d" class="ccs-input" placeholder="Ex: 50">
                            </div>
                            <div>
                                <label class="ccs-label">Diâmetro Externo (D) mm</label>
                                <input id="synth-dn-D" class="ccs-input" placeholder="Ex: 90">
                            </div>
                        </div>
                        <div style="margin-bottom:20px;">
                            <label class="ccs-label">Rotação (RPM)</label>
                            <input id="synth-dn-rpm" class="ccs-input" placeholder="Ex: 2000">
                        </div>
                        <button class="ccs-btn" onclick="runSynthDNCalc()">Calcular Fator DN</button>
                    </div>
                </div>
                <div id="synth-dn-result" class="ccs-result-area">
                    <i data-lucide="brain-circuit"
                        style="width:48px; height:48px; opacity:0.3; margin-bottom:10px;"></i>
                    <p>O resultado do seu cálculo aparecerá aqui.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- SYNTH STYLE DEFINITIONS -->
    <style>
        .calc-card-synth {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            /* Soft Shadow */
            border: 1px solid var(--border);
        }

        .ccs-header {
            background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
            /* Strong Blue Gradient */
            padding: 24px;
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .ccs-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: white !important;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .ccs-subtitle {
            margin: 8px 0 0 0;
            font-size: 0.95rem;
            opacity: 0.9;
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .ccs-body {
            padding: 30px;
            background: white;
        }

        .ccs-label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted) !important;
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .ccs-input {
            display: block;
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #f8fafc !important;
            color: var(--text-main) !important;
            font-size: 1.1rem;
            transition: all 0.2s;
            font-weight: 700;
        }

        .ccs-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
            background: white !important;
        }

        /* Grid Utilities */
        .ccs-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .ccs-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .ccs-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .ccs-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(14, 165, 233, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .ccs-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(14, 165, 233, 0.3);
        }

        .ccs-result-area {
            margin-top: 30px;
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            color: var(--text-muted);
            background: #f1f5f9;
            /* Slate 100 */
            transition: all 0.3s;
        }

        /* Responsive Engineering */
        @media (max-width: 900px) {
            .ccs-grid-4 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {

            .ccs-grid-2,
            .ccs-grid-3,
            .ccs-grid-4 {
                grid-template-columns: 1fr;
            }

            .ccs-result-area {
                padding: 20px;
            }

            .calc-menu-item {
                padding: 10px 12px;
                font-size: 0.85rem;
            }
        }
    </style>
</div>


<!-- REPORTS & EXPORTS (SAAS Feature) -->