<div id="view-3d" class="view-container"
    style="padding:0; overflow:hidden; position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:9999; background:linear-gradient(135deg, #0a0e17 0%, #1a1f2e 100%);">

    <!-- EXTERNAL LIBS (THREE.JS + ADDONS) -->
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
                "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
            }
        }
    </script>

    <!-- FIX: MISSING DEPENDENCIES IN ISOLATED VIEW -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        // Init Icons and Fix Layout
        window.addEventListener('load', () => {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });

        // Standalone API helper since scripts_main.php is not loaded here
        window.api = async function (action, data = {}) {
            const formData = new FormData();
            // Append explicit data to Body
            for (const key in data) {
                formData.append(key, data[key]);
            }

            try {
                // FIXED: Action must be in URL for api.php router
                const response = await fetch('api.php?action=' + action, {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                try {
                    const result = JSON.parse(text);
                    if (result.ok) return result.data || result;
                    console.error('API Error:', result.error);
                    return null;
                } catch (e) {
                    console.error('API Parse Error:', text);
                    return null;
                }
            } catch (error) {
                console.error('Network Error:', error);
                return null;
            }
        };
    </script>

    <!-- IMMERSIVE VIEWPORT WITH INDUSTRIAL GRID -->
    <div id="viewport-3d" style="width:100%; height:100%; position:absolute; top:0; left:0; outline:none; cursor:grab;">
        <canvas id="canvas-3d" style="display:block; width:100%; height:100%;"></canvas>

        <!-- PERFORMANCE STATS OVERLAY -->
        <div id="perf-stats" class="hud-panel"
            style="position:absolute; top:25px; right:25px; padding:20px; min-width:240px; font-family:'Roboto Mono', monospace; font-size:0.85rem;">
            <div
                style="color:#0ea5e9; font-weight:700; margin-bottom:8px; border-bottom:1px solid rgba(14,165,233,0.3); padding-bottom:5px;">
                ⚡ PERFORMANCE</div>
            <div style="color:#cbd5e1; display:grid; grid-template-columns: 1fr auto; gap:8px;">
                <span>FPS:</span><strong id="stat-fps" style="color:#10b981;">60</strong>
                <span>Vertices:</span><strong id="stat-verts" style="color:#f59e0b;">0</strong>
                <span>Tris:</span><strong id="stat-tris" style="color:#f59e0b;">0</strong>
                <span>Calls:</span><strong id="stat-calls" style="color:#64748b;">0</strong>
            </div>
        </div>

        <!-- VIEWPORT MODE SWITCHER -->
        <div class="hud-panel"
            style="position:absolute; top:120px; right:20px; padding:10px; display:flex; flex-direction:column; gap:8px;">
            <button class="btn-mode active" onclick="setViewMode('shaded')" data-mode="shaded">
                <i data-lucide="eye"></i> Shaded
            </button>
            <button class="btn-mode" onclick="setViewMode('wireframe')" data-mode="wireframe">
                <i data-lucide="grid-3x3"></i> Wireframe
            </button>
            <button class="btn-mode" onclick="setViewMode('xray')" data-mode="xray">
                <i data-lucide="scan"></i> X-Ray
            </button>
            <button class="btn-mode" onclick="toggleMeasurements()">
                <i data-lucide="ruler"></i> Medidas
            </button>
        </div>

        <!-- AXIS HELPER INDICATOR -->
        <div id="axis-indicator"
            style="position:absolute; bottom:80px; left:20px; width:80px; height:80px; pointer-events:none;">
            <!-- Will be populated by THREE.js AxesHelper -->
        </div>

        <!-- EMPTY STATE -->
        <div id="empty-state-3d"
            style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center; pointer-events:none; display:none;">
            <div
                style="width:140px; height:140px; border:2px dashed #0ea5e9; border-radius:50%; margin:0 auto 25px; display:flex; align-items:center; justify-content:center; box-shadow:0 0 40px rgba(14, 165, 233, 0.3); animation: rotate 10s linear infinite;">
                <i data-lucide="box" style="width:64px; height:64px; color:#0ea5e9;"></i>
            </div>
            <h2
                style="font-family:'Orbitron', sans-serif; letter-spacing:3px; color:#f8fafc; text-transform:uppercase; text-shadow: 0 0 20px rgba(14,165,233,0.5);">
                SISTEMA PRONTO
            </h2>
            <p style="color:#64748b; font-family:'Roboto Mono', monospace; margin-top:10px;">Selecione um ativo ou
                carregue um modelo</p>
        </div>
    </div>

    <!-- REVOLUTIONARY SIDEBAR (Wider & More Readable) -->
    <div id="sidebar-3d" class="hud-panel"
        style="position:absolute; top:25px; left:25px; bottom:25px; width:350px; display:flex; flex-direction:column; z-index:10; transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);">

        <div style="padding:20px; border-bottom:1px solid rgba(14, 165, 233, 0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3
                    style="margin:0; font-family:'Orbitron', sans-serif; color:#0ea5e9; display:flex; align-items:center; gap:12px; font-size:1.1rem; letter-spacing:1.5px;">
                    <i data-lucide="database" style="width:20px;"></i> ATIVOS 3D
                </h3>
                <button class="btn-hud-icon" onclick="toggle3DSidebar()" title="Minimizar">
                    <i data-lucide="panel-left-close"></i>
                </button>
            </div>
            <div style="margin-top:15px; position:relative;">
                <i data-lucide="search"
                    style="position:absolute; left:12px; top:12px; width:16px; color:rgba(14, 165, 233, 0.5);"></i>
                <input id="search-3d" placeholder="Buscar ativo..." oninput="filter3DAssets()" class="hud-input">
            </div>
        </div>

        <div id="list-3d-assets" class="custom-scrollbar" style="flex:1; overflow-y:auto; padding:12px;">
            <div
                style="padding:40px 20px; text-align:center; color:#475569; font-family:'Roboto Mono'; font-size:0.85rem;">
                <div class="loading-spinner" style="margin:0 auto 15px;"></div>
                <span class="blink">_CARREGANDO ATIVOS...</span>
            </div>
        </div>

        <div style="padding:15px; border-top:1px solid rgba(14, 165, 233, 0.2);">
            <div
                style="display:flex; justify-content:space-between; font-family:'Roboto Mono'; font-size:0.7rem; color:#0ea5e9; margin-bottom:10px;">
                <span>STATUS: <span id="3d-status" style="color:#10b981;">ONLINE</span></span>
                <span>V3.5.0</span>
            </div>
            <div style="display:flex; gap:8px;">
                <button class="btn-hud-sm" onclick="document.getElementById('model-upload').click()" style="flex:1;">
                    <i data-lucide="upload" style="width:14px;"></i> UPLOAD
                </button>
            </div>
            <input type="file" id="model-upload" accept=".obj,.stl,.gltf,.glb" style="display:none;"
                onchange="handleModelUpload(this)">
        </div>
    </div>

    <!-- TOP TOOLBAR -->
    <div id="toolbar-3d" class="hud-panel"
        style="position:absolute; top:25px; left:50%; transform:translateX(-40%); padding:15px 40px; display:flex; gap:35px; align-items:center; z-index:10; height:80px; min-width:700px; justify-content: space-between;">

        <div style="display:flex; align-items:center; gap:25px;">
            <button class="btn-hud-back" onclick="close3DView();">
                <i data-lucide="arrow-left"></i> VOLTAR
            </button>

            <div style="border-left:1px solid rgba(14, 165, 233, 0.2); padding-left:25px;">
                <h2 id="3d-title"
                    style="margin:0; font-family:'Orbitron', sans-serif; font-size:1.2rem; color:#f8fafc; letter-spacing:1px; white-space:nowrap;">
                    VISUALIZADOR TÉCNICO
                </h2>
                <div id="3d-subtitle"
                    style="font-family:'Roboto Mono', monospace; font-size:0.7rem; color:#0ea5e9; text-transform:uppercase; margin-top:2px;">
                    Aguardando seleção...
                </div>
            </div>
        </div>

        <div style="display:flex; gap:15px; align-items:center;">
            <!-- TRANSFORMATION TOOLS -->
            <div class="tool-group">
                <button class="btn-tool active" onclick="setTransformMode('translate')" title="Mover (G)"
                    data-tool="translate">
                    <i data-lucide="move"></i>
                </button>
                <button class="btn-tool" onclick="setTransformMode('rotate')" title="Rotacionar (R)" data-tool="rotate">
                    <i data-lucide="refresh-cw"></i>
                </button>
                <button class="btn-tool" onclick="setTransformMode('scale')" title="Escalar (S)" data-tool="scale">
                    <i data-lucide="maximize-2"></i>
                </button>
                <div style="width:1px; background:rgba(255,255,255,0.1); margin:0 4px;"></div>
                <button class="btn-tool" onclick="toggleAutoRotate()" title="Auto Rotação" id="btn-auto-rotate">
                    <i data-lucide="rotate-3d"></i>
                </button>
            </div>

            <!-- ACTIONS -->
            <div style="height:30px; width:1px; background:rgba(255,255,255,0.1); margin:0 5px;"></div>

            <button id="btn-save-model" class="btn-hud-primary" onclick="window.saveToAsset()"
                title="Vincular este modelo ao ativo selecionado"
                style="display:none; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-color: #10b981;">
                <i data-lucide="save"></i> SALVAR
            </button>

            <button class="btn-hud" onclick="document.getElementById('model-upload').click()"
                title="Carregar Arquivo Local">
                <i data-lucide="upload"></i> CARREGAR
            </button>

            <button class="btn-hud" onclick="captureScreenshot()" title="Capturar Tela">
                <i data-lucide="camera"></i>
            </button>
        </div>
    </div>

    <!-- BOTTOM STATUS BAR -->
    <div
        style="position:absolute; bottom:20px; left:360px; right:20px; display:flex; gap:20px; align-items:flex-end; pointer-events:none;">
        <!-- TELEMETRY -->
        <div class="hud-panel" style="pointer-events:auto; padding:18px; flex:1;">
            <h4
                style="margin:0 0 12px 0; color:#0ea5e9; font-family:'Orbitron'; font-size:0.95rem; border-bottom:1px solid rgba(14,165,233,0.3); padding-bottom:6px; letter-spacing:1px;">
                📡 DADOS DO ATIVO
            </h4>
            <div
                style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; font-family:'Roboto Mono'; font-size:0.85rem; color:#cbd5e1;">
                <div>
                    <span style="color:#64748b; display:block; font-size:0.8rem; font-weight: 700;">TAG</span>
                    <strong id="info-tag" style="color:#f8fafc; font-size:1.4rem;">--</strong>
                </div>
                <div>
                    <span style="color:#64748b; display:block; font-size:0.7rem;">Setor</span>
                    <strong id="info-sector" style="color:#0ea5e9; font-size:1.1rem;">--</strong>
                </div>
                <div>
                    <span style="color:#64748b; display:block; font-size:0.7rem;">Última Visualização</span>
                    <strong id="info-last-view" style="color:#f8fafc; font-size:0.9rem;">Hoje</strong>
                </div>
                <div>
                    <span style="color:#64748b; display:block; font-size:0.7rem;">Status</span>
                    <strong id="info-status" style="color:#10b981; font-size:1.1rem;">ONLINE</strong>
                </div>
            </div>
        </div>

        <!-- CONTROLS GUIDE -->
        <div class="hud-panel" style="pointer-events:auto; padding:20px; width:280px;">
            <small style="color:#64748b; font-family:'Roboto Mono'; font-size:0.8rem; line-height:1.7;">
                <div style="color:#0ea5e9; font-weight:800; margin-bottom:10px; font-size: 0.9rem; letter-spacing: 0.5px;">⌨️ CONTROLES</div>
                <strong style="color: #cbd5e1">LMB</strong> Rotacionar<br>
                <strong style="color: #cbd5e1">RMB</strong> Mover Câmera<br>
                <strong style="color: #cbd5e1">Scroll</strong> Zoom<br>
                <strong style="color: #cbd5e1">G/R/S</strong> Mover/Rotar/Escalar
            </small>
        </div>
    </div>

</div>

<!-- REVOLUTIONARY STYLES -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Roboto+Mono:wght@300;400;500;700&display=swap');

    #view-3d {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    #view-3d * {
        box-sizing: border-box;
    }

    /* GLASSMORPHISM HUD PANELS */
    .hud-panel {
        background: rgba(11, 15, 25, 0.90);
        border: 1px solid rgba(14, 165, 233, 0.35);
        border-radius: 8px;
        box-shadow:
            0 0 30px rgba(0, 0, 0, 0.6),
            inset 0 0 0 1px rgba(255, 255, 255, 0.08),
            0 0 50px rgba(14, 165, 233, 0.15);
        backdrop-filter: blur(16px) saturate(180%);
        -webkit-backdrop-filter: blur(16px) saturate(180%);
    }

    .hud-input {
        width: 100%;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(14, 165, 233, 0.4);
        border-radius: 6px;
        color: #f8fafc;
        padding: 10px 12px 10px 40px;
        font-size: 0.9rem;
        font-family: 'Roboto Mono', monospace;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .hud-input:focus {
        border-color: #0ea5e9;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2), 0 0 15px rgba(14, 165, 233, 0.3);
        outline: none;
        background: rgba(0, 0, 0, 0.6);
    }

    .btn-hud {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 6px;
        color: #cbd5e1;
        padding: 10px 18px;
        font-family: 'Orbitron', sans-serif;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-hud:hover {
        background: rgba(14, 165, 233, 0.2);
        border-color: #0ea5e9;
        color: #0ea5e9;
        box-shadow: 0 0 15px rgba(14, 165, 233, 0.3);
        transform: translateY(-1px);
    }

    .btn-hud-primary {
        background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        border: 1px solid rgba(14, 165, 233, 0.5);
        border-radius: 6px;
        color: #ffffff;
        padding: 10px 20px;
        font-family: 'Orbitron', sans-serif;
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 1px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 0 20px rgba(14, 165, 233, 0.4), 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .btn-hud-primary:hover {
        box-shadow: 0 0 30px rgba(14, 165, 233, 0.6), 0 6px 16px rgba(0, 0, 0, 0.4);
        transform: translateY(-2px);
    }

    .btn-hud-back {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 6px;
        color: #fca5a5;
        padding: 10px 18px;
        font-family: 'Orbitron', sans-serif;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-hud-back:hover {
        background: rgba(239, 68, 68, 0.25);
        border-color: #ef4444;
        color: #ffffff;
        box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
    }

    .btn-hud-icon {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        color: #94a3b8;
        padding: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-hud-icon:hover {
        background: rgba(14, 165, 233, 0.2);
        border-color: #0ea5e9;
        color: #0ea5e9;
    }

    .btn-hud-sm {
        background: rgba(14, 165, 233, 0.1);
        border: 1px solid rgba(14, 165, 233, 0.3);
        border-radius: 6px;
        color: #0ea5e9;
        padding: 8px 12px;
        font-family: 'Roboto Mono', monospace;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        justify-content: center;
    }

    .btn-hud-sm:hover {
        background: rgba(14, 165, 233, 0.2);
        border-color: #0ea5e9;
        box-shadow: 0 0 10px rgba(14, 165, 233, 0.3);
    }

    .btn-mode {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        color: #94a3b8;
        padding: 8px 12px;
        font-family: 'Roboto Mono', monospace;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        justify-content: flex-start;
    }

    .btn-mode:hover {
        background: rgba(14, 165, 233, 0.15);
        border-color: rgba(14, 165, 233, 0.4);
        color: #0ea5e9;
    }

    .btn-mode.active {
        background: rgba(14, 165, 233, 0.25);
        border-color: #0ea5e9;
        color: #0ea5e9;
        box-shadow: 0 0 10px rgba(14, 165, 233, 0.3);
    }

    .tool-group {
        display: flex;
        gap: 4px;
        background: rgba(0, 0, 0, 0.3);
        padding: 4px;
        border-radius: 8px;
        border: 1px solid rgba(14, 165, 233, 0.2);
    }

    .btn-tool {
        background: transparent;
        border: 1px solid transparent;
        border-radius: 6px;
        color: #64748b;
        padding: 8px 12px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-tool:hover {
        background: rgba(14, 165, 233, 0.1);
        color: #0ea5e9;
    }

    .btn-tool.active {
        background: rgba(14, 165, 233, 0.3);
        border-color: #0ea5e9;
        color: #0ea5e9;
        box-shadow: 0 0 10px rgba(14, 165, 233, 0.4);
    }

    .control-group label {
        display: block;
        margin-bottom: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .hud-slider {
        width: 100%;
        height: 4px;
        border-radius: 2px;
        background: rgba(203, 213, 225, 0.2);
        outline: none;
        -webkit-appearance: none;
    }

    .hud-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #0ea5e9;
        cursor: pointer;
        box-shadow: 0 0 10px rgba(14, 165, 233, 0.5);
        transition: all 0.2s;
    }

    .hud-slider::-webkit-slider-thumb:hover {
        transform: scale(1.2);
        box-shadow: 0 0 15px rgba(14, 165, 233, 0.8);
    }

    .hud-list-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-family: 'Roboto Mono', monospace;
        font-size: 0.85rem;
        color: #cbd5e1;
    }

    .hud-list-item:hover {
        background: rgba(14, 165, 233, 0.15);
        border-color: rgba(14, 165, 233, 0.4);
        transform: translateX(4px);
        box-shadow: 0 0 15px rgba(14, 165, 233, 0.2);
    }

    .hud-list-item.active {
        background: rgba(14, 165, 233, 0.25);
        border-color: #0ea5e9;
        color: #0ea5e9;
        box-shadow: 0 0 15px rgba(14, 165, 233, 0.3);
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(14, 165, 233, 0.4);
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(14, 165, 233, 0.6);
    }

    .blink {
        animation: blink 1.5s ease-in-out infinite;
    }

    @keyframes blink {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.3;
        }
    }

    @keyframes rotate {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid rgba(14, 165, 233, 0.2);
        border-top-color: #0ea5e9;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    #viewport-3d:active {
        cursor: grabbing;
    }

    /* TOAST NOTIFICATIONS */
    #toast-container {
        position: fixed;
        bottom: 100px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }

    .toast {
        background: rgba(15, 23, 42, 0.95);
        border: 1px solid rgba(56, 189, 248, 0.3);
        border-left: 4px solid #0ea5e9;
        color: #f1f5f9;
        padding: 12px 20px;
        border-radius: 6px;
        font-family: 'Roboto Mono', monospace;
        font-size: 0.85rem;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        gap: 12px;
        transform: translateX(100%);
        animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        backdrop-filter: blur(8px);
    }

    .toast.success {
        border-left-color: #10b981;
    }

    .toast.error {
        border-left-color: #ef4444;
    }

    .toast.info {
        border-left-color: #3b82f6;
    }

    @keyframes slideIn {
        to {
            transform: translateX(0);
        }
    }

    @keyframes fadeOut {
        to {
            opacity: 0;
            transform: translateY(10px);
        }
    }
</style>

<!-- TOAST CONTAINER -->
<div id="toast-container"></div>

<!-- REVOLUTIONARY 3D ENGINE SCRIPT -->
<script type="module">
    import * as THREE from 'three';
    import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
    import { TransformControls } from 'three/addons/controls/TransformControls.js';
    import { STLLoader } from 'three/addons/loaders/STLLoader.js';
    import { OBJLoader } from 'three/addons/loaders/OBJLoader.js';
    import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
    import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
    import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js'; // Optional but good for optimization

    // 🎯 CORE SCENE SETUP
    let scene, camera, renderer, controls, transformControls;
    let gridHelper, axesHelper, boxHelper; // Added boxHelper
    let currentModel = null;

    let viewMode = 'shaded';
    let showMeasurements = false;
    let autoRotate = false;
    let explosionFactor = 0;
    let clock, stats, mixer; // Added mixer
    let emptyStateDefaultHTML = ''; // Preserva o markup original do placeholder "SISTEMA PRONTO"

    // Restaura/mostra o placeholder padrão (ou um HTML customizado, ex: spinner/erro) sem
    // destruir permanentemente o markup original — antes, loadRemoteModel() sobrescrevia
    // #empty-state-3d com um spinner e nunca restaurava, deixando o placeholder quebrado
    // para sempre após a primeira tentativa de carregar um modelo remoto.
    function showEmptyState(customHtml) {
        const el = document.getElementById('empty-state-3d');
        if (!el) return;
        el.innerHTML = (customHtml !== undefined) ? customHtml : emptyStateDefaultHTML;
        el.style.display = 'block';
    }

    // Performance tracking
    let frameCount = 0;
    let lastTime = performance.now();
    let isRendering = true;

    init3DEngine();
    animate();

    function init3DEngine() {
        // SCENE
        scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0a0e17);
        scene.fog = new THREE.FogExp2(0x0a0e17, 0.002);

        // CAMERA (Professional FOV)
        camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 10000);
        camera.position.set(50, 40, 50);

        // RENDERER (High Quality)
        const canvas = document.getElementById('canvas-3d');
        renderer = new THREE.WebGLRenderer({
            canvas,
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance'
        });
        renderer.setPixelRatio(window.devicePixelRatio);
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.2;

        // LIGHTS & ENVIRONMENT (Studio V2 Setup + RoomEnvironment for PBR)
        const pmremGenerator = new THREE.PMREMGenerator(renderer);
        scene.environment = pmremGenerator.fromScene(new RoomEnvironment(), 0.04).texture;

        const ambientLight = new THREE.AmbientLight(0xffffff, 0.2); // Lower ambient, rely on Environment
        scene.add(ambientLight);

        // Additional Directional for Shadows
        const mainLight = new THREE.DirectionalLight(0xfffaed, 2.0);
        mainLight.position.set(50, 80, 50);
        mainLight.castShadow = true;
        mainLight.shadow.mapSize.width = 2048;
        mainLight.shadow.mapSize.height = 2048;
        mainLight.shadow.bias = -0.0001;
        scene.add(mainLight);

        // Rim Light (Cool, outline)
        const rimLight = new THREE.SpotLight(0x4080ff, 4);
        rimLight.position.set(-50, 50, -50);
        rimLight.lookAt(0, 0, 0);
        scene.add(rimLight);

        // Fill Light (Soft)
        const fillLight = new THREE.PointLight(0xffaa00, 0.5);
        fillLight.position.set(0, 20, 50);
        scene.add(fillLight);

        // INFINITE GRID (Professional)
        const gridSize = 200;
        const gridDivisions = 40;
        gridHelper = new THREE.GridHelper(gridSize, gridDivisions, 0x0ea5e9, 0x1e293b);
        gridHelper.material.opacity = 0.3;
        gridHelper.material.transparent = true;
        scene.add(gridHelper);

        // AXES HELPER (XYZ)
        axesHelper = new THREE.AxesHelper(30);
        axesHelper.material.linewidth = 2;
        scene.add(axesHelper);

        // ORBIT CONTROLS
        controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        controls.minDistance = 5;
        controls.maxDistance = 500;
        controls.maxPolarAngle = Math.PI / 2;
        controls.target.set(0, 0, 0);

        // TRANSFORM CONTROLS (Gizmos)
        transformControls = new TransformControls(camera, renderer.domElement);
        transformControls.addEventListener('dragging-changed', (event) => {
            controls.enabled = !event.value;
        });
        scene.add(transformControls);

        // CLOCK for animations
        clock = new THREE.Clock();

        // WINDOW RESIZE
        window.addEventListener('resize', onWindowResize, false);

        // KEYBOARD SHORTCUTS
        window.addEventListener('keydown', onKeyDown, false);

        console.log('🚀 Revolutionary 3D Engine initialized!');
        emptyStateDefaultHTML = document.getElementById('empty-state-3d').innerHTML;
        document.getElementById('empty-state-3d').style.display = 'block';
    }

    function animate() {
        if (!isRendering) return;
        requestAnimationFrame(animate);

        controls.update();

        // 🔄 AUTO ROTATE LOGIC
        if (autoRotate && currentModel) {
            currentModel.rotation.y += 0.005;
        }

        // 🎬 ANIMATION MIXER
        if (mixer) {
            mixer.update(clock.getDelta());
        }

        // PERFORMANCE STATS
        frameCount++;
        const currentTime = performance.now();
        if (currentTime >= lastTime + 1000) {
            if (document.getElementById('stat-fps')) {
                document.getElementById('stat-fps').textContent = frameCount;
                document.getElementById('stat-calls').textContent = renderer.info.render.calls;
                // FIX: isto é contagem de triângulos, não de vértices — antes sobrescrevia
                // "stat-verts" (calculado corretamente 1x por modelo em updateModelStats())
                // com o valor de triângulos a cada segundo, mostrando o número errado.
                document.getElementById('stat-tris').textContent = renderer.info.render.triangles;
            }
            frameCount = 0;
            lastTime = currentTime;
        }

        renderer.render(scene, camera);
    }

    // 🍞 TOAST NOTIFICATION SYSTEM
    window.showToast = function (message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        // Icons based on type
        let icon = 'info';
        if (type === 'success') icon = 'check-circle';
        if (type === 'error') icon = 'alert-triangle';

        toast.innerHTML = `<i data-lucide="${icon}" style="width:16px;"></i> <span>${message}</span>`;

        container.appendChild(toast);
        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Remove after 3s
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    function onWindowResize() {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }

    function onKeyDown(event) {
        switch (event.key.toLowerCase()) {
            case 'g':
                window.setTransformMode('translate');
                break;
            case 'r':
                window.setTransformMode('rotate');
                break;
            case 's':
                if (!event.ctrlKey) {
                    window.setTransformMode('scale');
                }
                break;
        }
    }

    // Toggle Auto Rotate
    window.toggleAutoRotate = function () {
        autoRotate = !autoRotate;
        if (autoRotate) showToast('Auto-Rotação: Ativada', 'success');
        else showToast('Auto-Rotação: Parada', 'info');
    };

    // 📊 UPDATE MODEL STATISTICS
    function updateModelStats() {
        if (!currentModel) return;

        let totalVerts = 0;
        let totalTris = 0;

        currentModel.traverse((child) => {
            if (child.isMesh) {
                const geometry = child.geometry;
                if (geometry.attributes.position) {
                    totalVerts += geometry.attributes.position.count;
                }
                if (geometry.index) {
                    totalTris += geometry.index.count / 3;
                } else {
                    totalTris += geometry.attributes.position.count / 3;
                }
            }
        });

        document.getElementById('stat-verts').textContent = totalVerts.toLocaleString();
        document.getElementById('stat-tris').textContent = Math.floor(totalTris).toLocaleString();
    }

    // 🔧 TRANSFORM MODE SWITCHER
    window.setTransformMode = function (mode) {
        if (!currentModel) return;

        transformControls.setMode(mode);

        // Update UI
        document.querySelectorAll('.btn-tool').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tool="${mode}"]`).classList.add('active');
    };

    // 💥 EXPLOSION EFFECT
    window.updateExplosion = function (value) {
        explosionFactor = parseFloat(value);

        if (!currentModel) return;

        currentModel.children.forEach((child, index) => {
            if (child.isMesh) {
                const direction = new THREE.Vector3(
                    Math.random() - 0.5,
                    Math.random() * 0.5,
                    Math.random() - 0.5
                ).normalize();

                child.position.copy(direction.multiplyScalar(explosionFactor * 10));
            }
        });
    };

    // 👁️ VIEW MODE SWITCHER
    // 👁️ VIEW MODE SWITCHER
    window.setViewMode = function (mode) {
        viewMode = mode;

        document.querySelectorAll('.btn-mode').forEach(btn => {
            if (!btn.innerHTML.includes('Medidas')) btn.classList.remove('active'); // Don't touch Medidas active state
        });
        if (mode !== 'medidas') document.querySelector(`[data-mode="${mode}"]`).classList.add('active');

        if (!currentModel) return;

        currentModel.traverse((child) => {
            if (child.isMesh) {
                // Ensure we have original state
                if (!child.userData.originalState) {
                    child.userData.originalState = {
                        transparent: child.material.transparent,
                        opacity: child.material.opacity,
                        wireframe: child.material.wireframe
                    };
                }

                switch (mode) {
                    case 'wireframe':
                        child.material.wireframe = true;
                        child.material.transparent = false;
                        child.material.opacity = 1.0;
                        break;
                    case 'xray':
                        child.material.wireframe = false;
                        child.material.transparent = true;
                        child.material.opacity = 0.3;
                        child.material.depthWrite = false;
                        break;
                    default: // shaded
                        child.material.wireframe = child.userData.originalState.wireframe;
                        child.material.transparent = child.userData.originalState.transparent;
                        child.material.opacity = child.userData.originalState.opacity;
                        child.material.depthWrite = true;
                }
            }
        });
    };

    // 📐 TOGGLE MEASUREMENTS
    // 📐 TOGGLE MEASUREMENTS
    window.toggleMeasurements = function () {
        showMeasurements = !showMeasurements;
        const btn = document.querySelectorAll('.btn-mode')[3]; // The 4th button is Medidas

        if (showMeasurements) {
            btn.classList.add('active');
            if (currentModel) {
                if (boxHelper) scene.remove(boxHelper);
                boxHelper = new THREE.BoxHelper(currentModel, 0x0ea5e9);
                scene.add(boxHelper);

                // Calculate dimensions
                const box = new THREE.Box3().setFromObject(currentModel);
                const size = new THREE.Vector3();
                box.getSize(size);

                showToast(`Dimensões: X:${size.x.toFixed(2)} Y:${size.y.toFixed(2)} Z:${size.z.toFixed(2)}`, 'info');

                // Updates stats panel temporarily
                const originalText = document.getElementById('perf-stats').innerHTML;
                document.getElementById('perf-stats').innerHTML = `
                    <div style="color:#0ea5e9; font-weight:700; margin-bottom:8px; border-bottom:1px solid rgba(14,165,233,0.3); padding-bottom:5px;">
                        📐 DIMENSÕES
                    </div>
                    <div style="color:#cbd5e1; font-size:0.8rem; line-height:1.6;">
                        <span style="color:#f8fafc">Largura (X):</span> ${size.x.toFixed(2)} un<br>
                        <span style="color:#f8fafc">Altura (Y):</span> ${size.y.toFixed(2)} un<br>
                        <span style="color:#f8fafc">Profund. (Z):</span> ${size.z.toFixed(2)} un
                    </div>
                `;

                // Auto revert stats after 10s or keep? User might want to see it. 
                // Let's keep it until toggled off.
                window.measurementsViewActive = true;
                window.originalStatsHTML = originalText;
            }
        } else {
            btn.classList.remove('active');
            if (boxHelper) {
                scene.remove(boxHelper);
                boxHelper = null;
            }
            // Restore stats
            if (window.measurementsViewActive && window.originalStatsHTML) {
                document.getElementById('perf-stats').innerHTML = window.originalStatsHTML;
                window.measurementsViewActive = false;
            }
        }
    };

    // 📸 SCREENSHOT CAPTURE
    window.captureScreenshot = function () {
        renderer.render(scene, camera);
        const dataURL = renderer.domElement.toDataURL('image/png');

        const link = document.createElement('a');
        link.download = `LubTek_3D_${Date.now()}.png`;
        link.href = dataURL;
        link.click();

        console.log('📸 Screenshot captured!');
    };

    // 🚀 OPEN 3D VIEW
    window.open3DView = function () {
        const v3d = document.getElementById('view-3d');
        if (v3d) {
            v3d.style.display = 'block';
            setTimeout(() => v3d.classList.add('active'), 10);
            if (typeof onWindowResize === 'function') onWindowResize(); // Force resize to fix aspect ratio

            // Fix: Resume Rendering Loop
            isRendering = true;
            animate();
        }
    };

    // 🔙 CLOSE 3D VIEW
    // 🔙 CLOSE 3D VIEW (REDIRECT)
    window.close3DView = function () {
        const v3d = document.getElementById('view-3d');
        if (v3d) {
            // Redirect to dashboard or previous page
            window.location.href = 'index.php?page=dash';
        }
    };

    // 🎛️ TOGGLE SIDEBAR
    window.toggle3DSidebar = function () {
        const sidebar = document.getElementById('sidebar-3d');
        const toolbar = document.getElementById('toolbar-3d');

        if (sidebar.style.transform === 'translateX(-110%)') {
            sidebar.style.transform = 'translateX(0)';
            toolbar.style.left = '360px';
        } else {
            sidebar.style.transform = 'translateX(-110%)';
            toolbar.style.left = '20px';
        }
    };

    // 📂 HANDLE MODEL UPLOAD


    // 💾 SAVE TO ASSET
    window.saveToAsset = async function () {
        if (!currentModel) {
            alert('Nenhum modelo carregado para salvar.');
            return;
        }

        if (!window.currentFileBlob) {
            alert('Este modelo já está salvo ou não foi carregado via upload local.');
            return;
        }

        if (!window.currentAssetId) {
            alert('Selecione um ativo na lista lateral primeiro para vincular este modelo.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'upload_model');
        formData.append('asset_id', window.currentAssetId);
        formData.append('file', window.currentFileBlob, window.currentFileName || 'model.glb');

        // Show saving state
        const btn = document.getElementById('btn-save-model');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i data-lucide="loader-2" class="spin"></i> SALVANDO...';

        try {
            const response = await fetch('api.php?action=upload_model', {
                method: 'POST',
                body: formData
            });
            // Handle raw response or JSON
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { ok: false, error: text }; }

            if (result.ok) {
                alert('Modelo vinculado com sucesso!');
                window.currentFileBlob = null; // Clear blob to prevent re-upload
                btn.style.display = 'none'; // Hide save button
            } else {
                alert('Erro: ' + (result.error || 'Falha ao salvar.'));
            }
        } catch (e) {
            console.error(e);
            alert('Erro de conexão.');
        } finally {
            btn.innerHTML = originalText;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    };

    // 📂 HANDLE MODEL UPLOAD
    window.handleModelUpload = function (input) {
        const file = input.files[0];
        if (!file) return;

        window.currentFileBlob = file; // Store for saving
        window.currentFileName = file.name;

        const reader = new FileReader();
        const ext = file.name.split('.').pop().toLowerCase();

        reader.onload = function (e) {
            const contents = e.target.result;
            loadModelContent(contents, ext);

            // Show Save Button
            const saveBtn = document.getElementById('btn-save-model');
            if (saveBtn) saveBtn.style.display = 'inline-flex';
        };

        reader.readAsArrayBuffer(file);
    };

    function loadModelContent(contents, ext) {
        let loader;
        if (typeof THREE === 'undefined') return;

        switch (ext) {
            case 'stl': loader = new STLLoader(); break;
            case 'obj': loader = new OBJLoader(); break;
            case 'gltf': case 'glb': loader = new GLTFLoader(); break;
            default: alert('Formato não suportado.'); return;
        }

        try {
            if (ext === 'gltf' || ext === 'glb') {
                // Setup DRACO if needed (optional)
                // const dracoLoader = new DRACOLoader();
                // dracoLoader.setDecoderPath('https://unpkg.com/three@0.160.0/examples/jsm/libs/draco/');
                // loader.setDRACOLoader(dracoLoader);

                loader.parse(contents, '', function (gltf) {
                    const model = gltf.scene || gltf.scenes[0];

                    // HANDLE ANIMATIONS
                    if (gltf.animations && gltf.animations.length > 0) {
                        mixer = new THREE.AnimationMixer(model);
                        gltf.animations.forEach((clip) => {
                            mixer.clipAction(clip).play();
                        });
                        showToast('Animações detectadas e iniciadas', 'success');
                    } else {
                        mixer = null;
                    }

                    replaceSceneModel(model);
                }, (err) => console.error(err));
            }
            else if (ext === 'obj') {
                const text = new TextDecoder().decode(contents);
                const object = loader.parse(text);
                replaceSceneModel(object);
            }
            else { // STL
                const geometry = loader.parse(contents);
                const material = new THREE.MeshStandardMaterial({ color: 0x606060 });
                const mesh = new THREE.Mesh(geometry, material);
                replaceSceneModel(mesh);
            }
        } catch (err) {
            console.error('Error parsing 3D model:', err);
            alert('Erro ao processar arquivo 3D.');
        }
    }

    function replaceSceneModel(object) {
        if (currentModel) scene.remove(currentModel);
        currentModel = object;
        scene.add(currentModel);

        // Auto Scale/Center
        const box = new THREE.Box3().setFromObject(currentModel);
        const center = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const scale = 20 / maxDim; // Normalize to 20 units size

        currentModel.position.sub(center); // Center at 0,0,0
        currentModel.scale.setScalar(scale);
        currentModel.position.y += 0; // Center vertically on grid

        // Save Original Material State for View Modes
        currentModel.traverse((child) => {
            if (child.isMesh) {
                child.userData.originalState = {
                    transparent: child.material.transparent,
                    opacity: child.material.opacity,
                    wireframe: child.material.wireframe || false
                };
            }
        });

        updateModelStats();
        // Reset Measurements if active
        if (showMeasurements) {
            toggleMeasurements(); // turn off
            toggleMeasurements(); // turn on again to update
        }
        // Restaura o markup original do placeholder antes de escondê-lo, para que uma
        // futura exibição (ex: seleção de outro ativo sem modelo) não mostre o spinner
        // ou mensagem de erro residual de um carregamento anterior.
        const emptyStateEl = document.getElementById('empty-state-3d');
        emptyStateEl.innerHTML = emptyStateDefaultHTML;
        emptyStateEl.style.display = 'none';
        transformControls.attach(currentModel);
    }

    // 🔍 FILTER 3D ASSETS
    window.filter3DAssets = function () {
        const searchTerm = document.getElementById('search-3d').value.toLowerCase();
        const listItems = document.querySelectorAll('#list-3d-assets .hud-list-item');

        listItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
        });
    };

    // 📋 LOAD 3D ASSETS LIST
    window.reload3DList = async function () {
        const listContainer = document.getElementById('list-3d-assets');

        if (typeof api === 'function') {
            try {
                const assets = await api('get_tree');
                if (assets && assets.length > 0) {
                    listContainer.innerHTML = '';

                    function flattenAssets(nodes, result = []) {
                        nodes.forEach(node => {
                            result.push(node);
                            if (node.children && node.children.length > 0) {
                                flattenAssets(node.children, result);
                            }
                        });
                        return result;
                    }

                    const flatAssets = flattenAssets(assets);

                    flatAssets.forEach(asset => {
                        const item = document.createElement('div');
                        item.className = 'hud-list-item';
                        item.dataset.id = asset.id;
                        // Check for model
                        let hasModel = false;
                        if (asset.dados_tecnicos) {
                            try {
                                const dt = typeof asset.dados_tecnicos === 'string' ? JSON.parse(asset.dados_tecnicos) : asset.dados_tecnicos;
                                if (dt.model_3d) hasModel = true;
                            } catch (e) { }
                        }

                        // FIX: evita XSS — asset.nome vem do banco (nome do ativo cadastrado pelo
                        // usuário) e antes era injetado direto via innerHTML sem escapar.
                        const iconHtml = hasModel
                            ? '<i data-lucide="box" style="width:14px; color:#10b981; flex-shrink:0;"></i>'
                            : '<i data-lucide="circle" style="width:8px; opacity:0.3; flex-shrink:0;"></i>';
                        const nameWrap = document.createElement('div');
                        nameWrap.style.cssText = 'display:flex; align-items:center; gap:10px; overflow:hidden;';
                        const iconSpan = document.createElement('span');
                        iconSpan.innerHTML = iconHtml;
                        iconSpan.style.flexShrink = '0';
                        iconSpan.style.display = 'inline-flex';
                        const nameSpan = document.createElement('span');
                        nameSpan.style.cssText = 'white-space:nowrap; overflow:hidden; text-overflow:ellipsis;';
                        nameSpan.textContent = asset.nome || 'Sem nome';
                        nameWrap.appendChild(iconSpan);
                        nameWrap.appendChild(nameSpan);
                        item.appendChild(nameWrap);
                        item.onclick = () => {
                            console.log('Selected asset:', asset.nome);
                            window.currentAssetId = asset.id;

                            // UI Updates
                            document.getElementById('3d-title').textContent = (asset.nome || 'Sem nome').toUpperCase();
                            document.getElementById('3d-subtitle').textContent = `ID: ${asset.id} • ${asset.tipo?.toUpperCase() || 'ATIVO'}`;

                            // Update Telemetry with Real Data
                            document.getElementById('info-tag').textContent = asset.tag || '--';
                            document.getElementById('info-sector').textContent = (asset.pai_id && flatAssets.find(f => f.id == asset.pai_id)?.nome) || '--';
                            document.getElementById('info-status').textContent = asset.status || 'ONLINE';

                            // Hide Save Button (Clean slate)
                            const saveBtn = document.getElementById('btn-save-model');
                            if (saveBtn) saveBtn.style.display = 'none';

                            // Check for existing model
                            if (asset.dados_tecnicos) {
                                try {
                                    const dt = typeof asset.dados_tecnicos === 'string' ? JSON.parse(asset.dados_tecnicos) : asset.dados_tecnicos;
                                    if (dt.model_3d) {
                                        loadRemoteModel(dt.model_3d);
                                    } else {
                                        // Reset to empty state if no model, but keep asset selected
                                        // document.getElementById('empty-state-3d').style.display = 'block';
                                        // if(currentModel) scene.remove(currentModel);
                                        // currentModel = null;
                                        // But maybe user wants to upload one? Keep scene as is or clear? 
                                        // Let's clear to avoid confusion.
                                        if (currentModel) {
                                            scene.remove(currentModel);
                                            currentModel = null;
                                            transformControls.detach();
                                        }
                                        showEmptyState();
                                    }
                                } catch (e) { }
                            }
                        };
                        listContainer.appendChild(item);
                    });

                    if (typeof lucide !== 'undefined') lucide.createIcons();

                    // Deep link: ?page=3d&id=123
                    const deepId = new URLSearchParams(window.location.search).get('id');
                    if (deepId) {
                        const target = listContainer.querySelector(`[data-id="${deepId}"]`);
                        if (target) target.click();
                    }
                } else {
                    listContainer.innerHTML = '<div style="padding:20px; text-align:center; color:#64748b;">Nenhum ativo encontrado.</div>';
                }
            } catch (e) {
                console.error('Error loading assets:', e);
                listContainer.innerHTML = '<div style="padding:20px; text-align:center; color:#ef4444;">Erro na conexão API.</div>';
            }
        }
    };

    async function loadRemoteModel(url) {
        showEmptyState('<span class="loading-spinner"></span>');

        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error('Network response was not ok');
            const blob = await res.blob();
            const buffer = await blob.arrayBuffer();
            const ext = url.split('.').pop().toLowerCase();

            loadModelContent(buffer, ext);

            // Mark as saved (it came from server)
            window.currentFileBlob = null;

        } catch (e) {
            console.error(e);
            alert('Erro ao baixar modelo do servidor.');
            showEmptyState('<span style="color:#ef4444">Erro ao carregar</span>');
        }
    }

    // Auto-load assets
    setTimeout(() => {
        if (window.reload3DList) window.reload3DList();
    }, 1000);

    // Make scene globally accessible for debugging
    window.THREE_SCENE = scene;
    window.THREE_CAMERA = camera;
    window.THREE_RENDERER = renderer;

</script>

<!-- MOTOR NEURAL — Assistente também no Visualizador 3D -->
<?php include __DIR__ . '/../neural_assistant.php'; ?>
<script>
    window.API_URL = 'api.php';
    window.__lubtekCurrentPage = '3d';
    <?php if (isset($currentUser)): ?>
    window.currentUser = <?php echo json_encode($currentUser, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    <?php endif; ?>

    // API JSON compatível com o Motor Neural
    window.api = async function (action, payload) {
        const url = 'api.php?action=' + action;
        const opts = payload ? {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        } : { credentials: 'same-origin' };
        const res = await fetch(url, opts);
        if (res.status === 401) { window.location.href = 'login.php'; return null; }
        const json = await res.json();
        if (!json.ok) return json;
        if (Array.isArray(json.data)) return json.data;
        return json;
    };
</script>
<script src="assets/js/neural_assistant.js?v=3.2"></script>
<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
    if (window.NeuralAssistant) NeuralAssistant.onPageChange('3d');
</script>