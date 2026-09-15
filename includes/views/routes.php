    <div id="view-routes" class="view-container view-flex routes-workspace" style="flex-direction:column; gap:16px; padding:0;">

        <div class="routes-header">
            <div>
                <h1 style="margin:0; font-size:1.4rem; font-weight:800; color:var(--primary);">Rotas de Lubrificação</h1>
                <span style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">Checklist de Campo — toque em Concluir quando terminar</span>
            </div>
            <div style="display:flex; gap:8px;">
                <button class="btn btn-sm btn-primary btn-action" onclick="openQrScannerModal()" title="Escanear QR Code da TAG" style="min-height:44px; display:inline-flex; align-items:center; gap:6px;">
                    <i data-lucide="qr-code" style="width:18px;height:18px;"></i> Escanear TAG
                </button>
                <button class="btn btn-sm btn-outline btn-action" onclick="loadRoutes()" title="Atualizar lista" style="min-height:44px;">
                    <i data-lucide="refresh-cw" style="width:16px;"></i>
                </button>
            </div>
        </div>

        <!-- Barra de progresso motivadora -->
        <div class="route-progress-panel">
            <div class="route-progress-label">
                <span id="route-progress-text">Carregando...</span>
                <span id="route-progress-pct" class="route-progress-pct">0%</span>
            </div>
            <div class="route-progress-track">
                <div id="route-progress-fill" class="route-progress-fill" style="width:0%;"></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
            <select id="route-sector-filter" onchange="renderRoutes()" class="route-sector-select">
                <option value="all">Todos os Setores</option>
            </select>
            <select id="route-status-visibility-filter" onchange="renderRoutes()" class="route-sector-select" style="font-weight:700;">
                <option value="pending">Exibir apenas pendentes (padrão)</option>
                <option value="all">Exibir todas</option>
            </select>
        </div>

        <div id="route-list" class="route-list">
            <div class="route-loading">Carregando pontos de lubrificação...</div>
        </div>

        <input type="file" id="anomaly-cam" capture="environment" accept="image/*" style="display:none;"
            onchange="handleAnomalyPhoto(this)">

        <!-- Modal Scanner QR Code -->
        <div id="qr-scanner-overlay" class="route-alert-overlay" onclick="if(event.target===this)closeQrScannerModal()">
            <div class="route-alert-modal" style="max-width:480px; text-align:center;">
                <div class="route-alert-modal-header">
                    <i data-lucide="qr-code" style="width:22px;height:22px;color:var(--primary);"></i>
                    <h2>Scanner de TAG / QR Code</h2>
                    <button type="button" class="route-alert-close" onclick="closeQrScannerModal()">&times;</button>
                </div>
                <p style="font-size:0.85rem; color:#64748b; margin-bottom:12px;">Aponte a câmera para a TAG do ponto ou máquina (ex: LA-CENTRAL ou ID do ponto).</p>
                <div id="qr-reader-container" style="width:100%; min-height:240px; background:#000; border-radius:12px; overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center;">
                    <video id="qr-video" style="width:100%; height:100%; object-fit:cover;" playsinline></video>
                    <div id="qr-video-placeholder" style="color:#fff; font-size:0.85rem; padding:20px;">Iniciando câmera...</div>
                </div>
                <div style="margin-top:14px; display:flex; gap:10px;">
                    <input type="text" id="manual-qr-tag" placeholder="Ou digite a TAG/ID..." style="flex:1; padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-weight:700;">
                    <button type="button" class="btn btn-primary" onclick="processScannedTag(document.getElementById('manual-qr-tag').value)">Buscar</button>
                </div>
            </div>
        </div>

        <!-- Modal de alerta (sem obrigar foto) -->
        <div id="route-alert-overlay" class="route-alert-overlay" onclick="if(event.target===this)closeRouteAlertModal()">
            <div class="route-alert-modal" role="dialog" aria-labelledby="route-alert-title">
                <div class="route-alert-modal-header">
                    <i data-lucide="alert-triangle" style="width:22px;height:22px;color:#dc2626;"></i>
                    <h2 id="route-alert-title">Reportar Problema</h2>
                    <button type="button" class="route-alert-close" onclick="closeRouteAlertModal()" aria-label="Fechar">&times;</button>
                </div>
                <p id="route-alert-subtitle" class="route-alert-subtitle"></p>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label for="route-alert-motivo" class="route-alert-label" style="margin:0;">O que você encontrou?</label>
                    <button type="button" class="btn btn-sm btn-outline" onclick="startVoiceInput('route-alert-motivo')" title="Ditar por voz" style="padding:2px 8px; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;">
                        <i data-lucide="mic" style="width:14px;height:14px;color:var(--primary);"></i> Ditar por Voz
                    </button>
                </div>
                <textarea id="route-alert-motivo" class="route-alert-textarea" rows="3"
                    placeholder="Ex: Vazamento de óleo, barulho estranho, alta temperatura..."></textarea>
                <p class="route-alert-hint">Uma Ordem de Serviço com prioridade Alta será criada automaticamente.</p>
                <div class="route-alert-actions">
                    <button type="button" class="route-alert-btn-cancel" onclick="closeRouteAlertModal()">Cancelar</button>
                    <button type="button" class="route-alert-btn-submit" onclick="submitRouteAlert()">
                        <i data-lucide="send" style="width:16px;height:16px;"></i> Registrar Alerta
                    </button>
                </div>
                <button type="button" class="route-alert-photo-opt" onclick="attachOptionalAlertPhoto()">
                    Anexar foto (opcional)
                </button>
            </div>
        </div>

        <style>
            .routes-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: white;
                padding: 15px 20px;
                border-radius: 12px;
                border: 1px solid var(--border);
                box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            }
            .route-progress-panel {
                background: white;
                padding: 16px 20px;
                border-radius: 12px;
                border: 1px solid var(--border);
            }
            .route-progress-label {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                font-size: 0.9rem;
                font-weight: 700;
                color: #1e293b;
            }
            .route-progress-pct {
                color: #10b981;
                font-size: 1rem;
            }
            .route-progress-track {
                height: 12px;
                background: #e2e8f0;
                border-radius: 999px;
                overflow: hidden;
            }
            .route-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #10b981, #059669);
                border-radius: 999px;
                transition: width 0.4s ease;
            }
            .route-sector-select {
                padding: 14px 16px;
                background: white;
                border: 1px solid var(--border);
                color: var(--text-main);
                border-radius: 10px;
                width: 100%;
                font-size: 1rem;
                font-weight: 600;
                min-height: 48px;
            }
            .route-list {
                max-width: 720px;
                width: 100%;
                margin: 0 auto;
                padding-bottom: 50px;
                display: flex;
                flex-direction: column;
                gap: 14px;
            }
            .route-loading, .route-empty {
                padding: 40px 24px;
                text-align: center;
                color: var(--text-muted);
                background: white;
                border-radius: 12px;
                border: 1px solid var(--border);
            }
            .route-card {
                position: relative;
                background: white;
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 20px;
                overflow: hidden;
                touch-action: pan-y;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            }
            .route-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
            .route-card .route-point-title {
                font-weight: 900;
                font-size: 1.15rem;
                color: #0f172a;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                line-height: 1.2;
            }
            .route-card .route-equipment {
                font-size: 0.85rem;
                color: #64748b;
                margin-top: 4px;
                font-weight: 600;
            }
            .route-card .route-action-chips {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin: 14px 0 16px;
            }
            .route-chip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                border-radius: 8px;
                font-size: 0.88rem;
                font-weight: 800;
                background: #f0f9ff;
                color: #0369a1;
                border: 1px solid #bae6fd;
            }
            .route-chip.material {
                background: #ecfdf5;
                color: #047857;
                border-color: #a7f3d0;
            }
            .route-card-actions {
                display: flex;
                gap: 10px;
                align-items: stretch;
            }
            .btn-route-done {
                flex: 1;
                min-height: 52px;
                border: none;
                border-radius: 12px;
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
                font-weight: 800;
                font-size: 1rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }
            .btn-route-done:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(16, 185, 129, 0.4); }
            .btn-route-done:disabled {
                background: #d1fae5;
                color: #059669;
                box-shadow: none;
                cursor: default;
                transform: none;
            }
            .btn-route-alert-icon {
                width: 52px;
                min-height: 52px;
                border: 2px solid #fecaca;
                border-radius: 12px;
                background: #fff;
                color: #dc2626;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                transition: background 0.15s ease;
            }
            .btn-route-alert-icon:hover { background: #fef2f2; }
            .route-status-badge {
                font-size: 0.68rem;
                font-weight: 800;
                padding: 4px 10px;
                border-radius: 8px;
                text-transform: uppercase;
            }
            .route-status-ok { border-left: 5px solid #10b981; }
            .route-status-ok .route-status-badge { background: rgba(16,185,129,0.15); color: #10b981; }
            .route-status-alert { border-left: 5px solid #ef4444; }
            .route-status-alert .route-status-badge { background: rgba(239,68,68,0.15); color: #ef4444; }
            .route-status-pending { border-left: 5px solid #f59e0b; }
            .route-status-pending .route-status-badge { background: rgba(245,158,11,0.15); color: #f59e0b; }
            .route-card .swipe-overlay {
                position: absolute;
                top: 0; bottom: 0;
                width: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                font-size: 1.1rem;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.15s;
            }
            .route-card .swipe-ok { left: 0; background: rgba(16,185,129,0.12); color: #059669; }
            .route-card .swipe-alert { right: 0; background: rgba(239,68,68,0.12); color: #dc2626; }

            /* Modal de alerta de campo */
            .route-alert-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
                z-index: 10050;
                align-items: center;
                justify-content: center;
                padding: 16px;
            }
            .route-alert-overlay.active { display: flex; }
            .route-alert-modal {
                background: #fff;
                border-radius: 16px;
                width: 100%;
                max-width: 420px;
                padding: 22px 24px 20px;
                box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            }
            .route-alert-modal-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 8px;
            }
            .route-alert-modal-header h2 {
                margin: 0;
                flex: 1;
                font-size: 1.15rem;
                font-weight: 800;
                color: #0f172a;
            }
            .route-alert-close {
                border: none;
                background: #f1f5f9;
                width: 32px;
                height: 32px;
                border-radius: 8px;
                font-size: 1.25rem;
                cursor: pointer;
                color: #64748b;
                line-height: 1;
            }
            .route-alert-subtitle {
                margin: 0 0 16px;
                font-size: 0.88rem;
                color: #64748b;
                font-weight: 600;
            }
            .route-alert-label {
                display: block;
                font-size: 0.78rem;
                font-weight: 800;
                text-transform: uppercase;
                color: #475569;
                margin-bottom: 8px;
            }
            .route-alert-textarea {
                width: 100%;
                box-sizing: border-box;
                padding: 14px;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                font-size: 1rem;
                font-family: inherit;
                resize: vertical;
                min-height: 88px;
            }
            .route-alert-textarea:focus {
                outline: none;
                border-color: #f59e0b;
            }
            .route-alert-hint {
                margin: 10px 0 16px;
                font-size: 0.78rem;
                color: #94a3b8;
            }
            .route-alert-actions {
                display: flex;
                gap: 10px;
            }
            .route-alert-btn-cancel {
                flex: 1;
                min-height: 48px;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                background: #fff;
                font-weight: 700;
                cursor: pointer;
                color: #64748b;
            }
            .route-alert-btn-submit {
                flex: 2;
                min-height: 48px;
                border: none;
                border-radius: 12px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: #fff;
                font-weight: 800;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            .route-alert-photo-opt {
                width: 100%;
                margin-top: 12px;
                border: none;
                background: transparent;
                color: #64748b;
                font-size: 0.8rem;
                font-weight: 600;
                cursor: pointer;
                text-decoration: underline;
                padding: 8px;
            }
        </style>
    </div>
