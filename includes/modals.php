    <div id="img-modal" class="lubtek-overlay-modal" role="dialog" aria-modal="true" aria-label="Visualização ampliada"
        onclick="if(event.target === this && window.lubtekCloseLightbox){ lubtekCloseLightbox('img-modal'); }">
        <div class="lubtek-modal-body" style="display:flex; flex-direction:column; align-items:center; gap:16px;">
            <img id="img-modal-content" src="" alt="Imagem ampliada"
                style="max-width:min(96vw,1200px); max-height:calc(85dvh - 100px); border-radius:12px; box-shadow:0 0 20px rgba(0,0,0,0.5); object-fit:contain;">
            <button type="button" class="btn btn-outline" style="color:white; border-color:white;"
                onclick="if(window.lubtekCloseLightbox){lubtekCloseLightbox('img-modal');}">Fechar (Esc)</button>
        </div>
    </div>

    <!-- LIGHTBOX MODAL (For Asset Images) -->
    <div id="lightbox-modal" class="lubtek-overlay-modal" role="dialog" aria-modal="true" aria-label="Zoom da foto do ponto"
        onclick="if(event.target === this && window.lubtekCloseLightbox){ lubtekCloseLightbox('lightbox-modal'); }">
        <div class="lubtek-modal-body" style="display:flex; flex-direction:column; align-items:center; gap:16px;">
            <img id="lightbox-img" class="lub-point-photo-thumb" alt="Foto do ponto em alta definição"
                style="max-width:min(96vw,1400px); max-height:calc(85dvh - 100px); width:auto; height:auto; border:2px solid #333; box-shadow:0 0 30px rgba(0,0,0,0.8); border-radius:8px;">
            <div style="display:flex; gap:15px;">
                <button type="button" class="btn" style="background:white; color:black; font-weight:800;"
                    onclick="if(window.lubtekCloseLightbox){lubtekCloseLightbox('lightbox-modal');}">
                    <i data-lucide="x"></i> FECHAR
                </button>
            </div>
        </div>
    </div>

    <!-- ASSET HEALTH & RELIABILITY DETAIL MODAL -->
    <div id="asset-health-modal" class="lubtek-overlay-modal" role="dialog" aria-modal="true" aria-label="Diagnóstico de Saúde e Confiabilidade do Ativo"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center; padding:16px;"
        onclick="if(event.target === this && window.closeAssetHealthModal){ closeAssetHealthModal(); }">
        <div class="card" style="width:100%; max-width:680px; max-height:90vh; overflow-y:auto; background:var(--bg-card, #ffffff); border:1px solid var(--border, #cbd5e1); border-radius:20px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); padding:0; display:flex; flex-direction:column;"
            onclick="event.stopPropagation()">
            
            <!-- Modal Header -->
            <div style="padding:20px 24px; border-bottom:1px solid var(--border, #e2e8f0); display:flex; justify-content:space-between; align-items:flex-start; background:rgba(0,0,0,0.02);">
                <div style="display:flex; gap:12px; align-items:center;">
                    <div id="ahm-icon-box" style="width:44px; height:44px; border-radius:12px; background:rgba(16,185,129,0.12); display:flex; align-items:center; justify-content:center; color:#10b981;">
                        <i data-lucide="heart-pulse" style="width:24px; height:24px;"></i>
                    </div>
                    <div>
                        <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--text-main, #0f172a); display:flex; align-items:center; gap:8px;">
                            Saúde & Confiabilidade do Ativo
                        </h3>
                        <div style="display:flex; align-items:center; gap:8px; margin-top:2px;">
                            <span id="ahm-asset-name" style="font-size:0.85rem; color:var(--text-muted, #64748b); font-weight:600;">Carregando ativo...</span>
                            <span id="ahm-asset-tag" style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; font-family:monospace;">TAG</span>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="closeAssetHealthModal()" style="background:none; border:none; color:var(--text-muted, #94a3b8); cursor:pointer; padding:4px;" title="Fechar">
                    <i data-lucide="x" style="width:22px; height:22px;"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div style="padding:24px; display:flex; flex-direction:column; gap:20px;">

                <!-- 3 Top Metric Cards -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
                    <!-- Health Score -->
                    <div style="background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.2); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:6px;">
                        <span style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#059669; letter-spacing:0.5px;">Score Geral de Saúde</span>
                        <div style="display:flex; align-items:baseline; gap:8px;">
                            <span id="ahm-score" style="font-size:2rem; font-weight:900; color:#10b981; line-height:1;">--%</span>
                            <span id="ahm-status" style="font-size:0.95rem; font-weight:800; color:#10b981;">--</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px; margin-top:4px;">
                            <span id="ahm-risk-badge" style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:6px; font-size:0.72rem; font-weight:700;">Risco Baixo</span>
                        </div>
                    </div>

                    <!-- AI Predict -->
                    <div style="background:rgba(139,92,246,0.06); border:1px solid rgba(139,92,246,0.2); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:6px;">
                        <span style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#7c3aed; letter-spacing:0.5px;">Previsão IA — Próxima Falha</span>
                        <div id="ahm-next-failure" style="font-size:1.4rem; font-weight:900; color:#8b5cf6; line-height:1.2;">+-- Dias</div>
                        <span style="font-size:0.75rem; color:var(--text-muted, #64748b);">Estimativa MTBF baseada no ciclo operacional</span>
                    </div>

                    <!-- Sustainable Impact (ESG) -->
                    <div style="background:rgba(14,165,233,0.06); border:1px solid rgba(14,165,233,0.2); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:6px;">
                        <span style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#0284c7; letter-spacing:0.5px;">Impacto Sustentável (ESG)</span>
                        <div id="ahm-co2" style="font-size:1.4rem; font-weight:900; color:#0ea5e9; line-height:1.2;">--.-- kg CO2/ano</div>
                        <span style="font-size:0.75rem; color:var(--text-muted, #64748b);">Redução de atrito e ganho em eficiência</span>
                    </div>
                </div>

                <!-- Engineering Diagnostics & Recommendations -->
                <div style="background:rgba(248,250,252,0.8); border:1px solid var(--border, #e2e8f0); border-radius:14px; padding:18px; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i data-lucide="sparkles" style="width:18px; height:18px; color:#8b5cf6;"></i>
                        <h4 style="margin:0; font-size:0.9rem; font-weight:800; color:var(--text-main, #0f172a); text-transform:uppercase; letter-spacing:0.5px;">
                            Diagnóstico do Sistema & Recomendações
                        </h4>
                    </div>
                    <div id="ahm-summary" style="font-size:0.9rem; color:var(--text-main, #1e293b); line-height:1.5; font-weight:500;">
                        Carregando diagnóstico...
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:10px; background:rgba(2,132,199,0.06); border-left:3px solid #0284c7; border-radius:8px; padding:12px 14px;">
                        <i data-lucide="arrow-right-circle" style="width:18px; height:18px; color:#0284c7; min-width:18px; margin-top:1px;"></i>
                        <div style="font-size:0.85rem; color:#0369a1; line-height:1.4;">
                            <strong>Ação Recomendada:</strong> <span id="ahm-next-action">Carregando ação preventiva...</span>
                        </div>
                    </div>
                </div>

                <!-- Engineering Breakdown (Progress Bars) -->
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <h4 style="margin:0; font-size:0.8rem; font-weight:800; text-transform:uppercase; color:var(--text-muted, #64748b); letter-spacing:0.5px;">
                        Detalhamento de Confiabilidade
                    </h4>

                    <!-- Status Score -->
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.8rem; font-weight:700;">
                            <span style="color:var(--text-main, #334155);">Integridade Operacional (Status e O.S.)</span>
                            <span id="ahm-val-status" style="color:#10b981;">--%</span>
                        </div>
                        <div style="width:100%; height:8px; background:rgba(0,0,0,0.06); border-radius:999px; overflow:hidden;">
                            <div id="ahm-bar-status" style="width:0%; height:100%; background:#10b981; border-radius:999px; transition:width 0.6s ease;"></div>
                        </div>
                    </div>

                    <!-- Maintenance Score -->
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.8rem; font-weight:700;">
                            <span style="color:var(--text-main, #334155);">Aderência do Plano de Lubrificação</span>
                            <span id="ahm-val-maint" style="color:#0ea5e9;">--%</span>
                        </div>
                        <div style="width:100%; height:8px; background:rgba(0,0,0,0.06); border-radius:999px; overflow:hidden;">
                            <div id="ahm-bar-maint" style="width:0%; height:100%; background:#0ea5e9; border-radius:999px; transition:width 0.6s ease;"></div>
                        </div>
                    </div>

                    <!-- Oil / Lubricant Score -->
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.8rem; font-weight:700;">
                            <span style="color:var(--text-main, #334155);">Qualidade e Condição do Lubrificante</span>
                            <span id="ahm-val-oil" style="color:#8b5cf6;">--%</span>
                        </div>
                        <div style="width:100%; height:8px; background:rgba(0,0,0,0.06); border-radius:999px; overflow:hidden;">
                            <div id="ahm-bar-oil" style="width:0%; height:100%; background:#8b5cf6; border-radius:999px; transition:width 0.6s ease;"></div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer (Actions) -->
            <div style="padding:16px 24px; border-top:1px solid var(--border, #e2e8f0); display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.02);">
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-outline" onclick="closeAssetHealthModal(); if(typeof setAssetTab==='function')setAssetTab('hist');" style="font-size:0.8rem; padding:8px 12px; gap:6px;">
                        <i data-lucide="history" style="width:14px; height:14px;"></i> Ver Histórico
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeAssetHealthModal(); if(typeof setAssetTab==='function')setAssetTab('monitor');" style="font-size:0.8rem; padding:8px 12px; gap:6px;">
                        <i data-lucide="droplet" style="width:14px; height:14px;"></i> Análise de Óleo
                    </button>
                    <button type="button" class="btn btn-primary" onclick="closeAssetHealthModal(); if(typeof openQuickWorkOrder==='function')openQuickWorkOrder();" style="font-size:0.8rem; padding:8px 14px; gap:6px; background:#0284c7; border:none; color:white; font-weight:700;">
                        <i data-lucide="wrench" style="width:14px; height:14px;"></i> Nova O.S. Preventiva
                    </button>
                </div>
                <button type="button" class="btn btn-outline" onclick="closeAssetHealthModal()" style="font-size:0.8rem; padding:8px 16px;">
                    Fechar
                </button>
            </div>

        </div>
    </div>

    <!-- BRANDING CONFIGURATION MODAL (White-Label settings) -->
    <div id="branding-modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.4); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:rgba(255,255,255,0.95); border:1px solid #e2e8f0; max-width:450px; width:90%; border-radius:24px; padding:30px; box-shadow:0 20px 40px rgba(0,0,0,0.15); display:flex; flex-direction:column; gap:20px; font-family:'Inter',sans-serif;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1.4rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="palette" style="color:#0284c7; width:22px; height:22px;"></i>
                    Personalizar Marca
                </h3>
                <button type="button" style="background:none; border:none; color:#94a3b8; cursor:pointer;" onclick="closeBrandingModal()">
                    <i data-lucide="x" style="width:20px; height:20px;"></i>
                </button>
            </div>
            
            <p style="margin:0; font-size:0.85rem; color:#64748b; line-height:1.4;">
                Substitua a identidade visual padrão do LUB-TEK pela marca da sua empresa. As alterações serão salvas imediatamente para todos os usuários deste tenant.
            </p>

            <form id="branding-form" onsubmit="saveBranding(event)" style="display:flex; flex-direction:column; gap:16px;">
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase;">Nome da Empresa</label>
                    <input type="text" id="brand-company-name" required placeholder="Ex: Coca-Cola" style="width:100%; padding:12px 14px; border:1px solid #cbd5e1; border-radius:12px; font-size:0.95rem; color:#0f172a; background:#f8fafc; font-weight:500;">
                </div>

                <div style="display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase;">Logotipo (Imagem)</label>
                    <div style="display:flex; align-items:center; gap:12px; margin-top:4px;">
                        <img id="brand-logo-preview" src="assets/img/system/img_69581d7fcdbbf.jpeg" loading="lazy" decoding="async" style="width:55px; height:55px; border-radius:12px; border:1px solid #e2e8f0; object-fit:contain; background:#fff; padding:4px;">
                        <div style="display:flex; flex-direction:column; gap:4px; flex:1;">
                            <input type="file" id="brand-logo-file" accept="image/*" onchange="uploadBrandLogo(event)" style="display:none;">
                            <button type="button" onclick="document.getElementById('brand-logo-file').click()" class="topbar-eco-btn" style="color:#0284c7; border-color:#cbd5e1; align-self:flex-start; padding:8px 12px;">
                                <i data-lucide="upload" style="width:12px; height:12px;"></i> Selecionar Imagem
                            </button>
                            <input type="hidden" id="brand-logo-path">
                            <span id="brand-logo-status" style="font-size:0.7rem; color:#94a3b8;">Formatos aceitos: JPG, PNG, WEBP (Máx 5MB)</span>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:12px; margin-top:10px; justify-content:flex-end;">
                    <button type="button" onclick="closeBrandingModal()" style="padding:12px 18px; border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:12px; cursor:pointer; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="submit" id="brand-save-btn" style="padding:12px 24px; border:none; background:linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color:#fff; border-radius:12px; cursor:pointer; font-weight:700; font-size:0.9rem; box-shadow:0 4px 12px rgba(2,132,199,0.2);">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openBrandingModal() {
            document.getElementById('brand-company-name').value = <?php echo isset($companyName) ? json_encode($companyName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '"LUB-TEK"'; ?>;
            document.getElementById('brand-logo-preview').src = <?php echo isset($companyLogo) ? json_encode($companyLogo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '"assets/img/system/img_69581d7fcdbbf.jpeg"'; ?>;
            document.getElementById('brand-logo-path').value = <?php echo isset($companyLogo) ? json_encode($companyLogo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '"assets/img/system/img_69581d7fcdbbf.jpeg"'; ?>;
            document.getElementById('branding-modal').style.display = 'flex';
            lucide.createIcons();
        }

        function closeBrandingModal() {
            document.getElementById('branding-modal').style.display = 'none';
        }

        async function uploadBrandLogo(event) {
            const file = event.target.files[0];
            if (!file) return;

            const statusLabel = document.getElementById('brand-logo-status');
            const previewImg = document.getElementById('brand-logo-preview');
            const pathInput = document.getElementById('brand-logo-path');
            const saveBtn = document.getElementById('brand-save-btn');

            statusLabel.textContent = 'Enviando...';
            statusLabel.style.color = '#0284c7';
            saveBtn.disabled = true;

            const formData = new FormData();
            formData.append('file', file);

            try {
                const headers = { 'X-Requested-With': 'XMLHttpRequest' };
                if (window.csrfToken) headers['X-CSRF-TOKEN'] = window.csrfToken;
                let result;
                if (typeof LubtekApi !== 'undefined' && LubtekApi.upload) {
                    result = await LubtekApi.upload('upload_image', formData);
                } else {
                    const response = await fetch('api.php?action=upload_image', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: headers,
                        body: formData
                    });
                    result = await response.json();
                }

                if (result.ok || result.success) {
                    const newPath = result.path;
                    previewImg.src = newPath;
                    pathInput.value = newPath;
                    statusLabel.textContent = 'Enviado com sucesso!';
                    statusLabel.style.color = '#10b981';
                } else {
                    statusLabel.textContent = result.error || 'Erro ao enviar.';
                    statusLabel.style.color = '#ef4444';
                }
            } catch (err) {
                statusLabel.textContent = 'Falha na conexão.';
                statusLabel.style.color = '#ef4444';
            } finally {
                saveBtn.disabled = false;
            }
        }

        async function saveBranding(event) {
            event.preventDefault();
            const name = document.getElementById('brand-company-name').value.trim();
            const logo = document.getElementById('brand-logo-path').value;
            const saveBtn = document.getElementById('brand-save-btn');

            saveBtn.disabled = true;
            saveBtn.textContent = 'Gravando...';

            try {
                const response = await fetch('api.php?action=save_branding', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ company_name: name, company_logo: logo })
                });
                const result = await response.json();

                if (result.ok || result.success) {
                    closeBrandingModal();
                    if (typeof showToast === 'function') {
                        showToast('Identidade visual atualizada com sucesso!', 'success');
                    } else {
                        alert('Identidade visual atualizada com sucesso!');
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    alert(result.error || 'Erro ao salvar branding.');
                }
            } catch (err) {
                alert('Erro de conexão ao salvar branding.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Salvar Alterações';
            }
        }
    </script>

    <!-- API KEY CONFIGURATION MODAL (For Sensors IoT) -->
    <div id="api-key-modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.4); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:rgba(255,255,255,0.95); border:1px solid #e2e8f0; max-width:480px; width:90%; border-radius:24px; padding:30px; box-shadow:0 20px 40px rgba(0,0,0,0.15); display:flex; flex-direction:column; gap:20px; font-family:'Inter',sans-serif;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1.4rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="key" style="color:#0284c7; width:22px; height:22px;"></i>
                    Chave de API (IoT)
                </h3>
                <button type="button" style="background:none; border:none; color:#94a3b8; cursor:pointer;" onclick="closeApiKeyModal()">
                    <i data-lucide="x" style="width:20px; height:20px;"></i>
                </button>
            </div>
            
            <p style="margin:0; font-size:0.85rem; color:#64748b; line-height:1.4;">
                Use esta chave de API para autenticar gateways industriais e sensores sem a necessidade de cookies ou sessões ativas do navegador.
            </p>

            <div style="display:flex; flex-direction:column; gap:6px;">
                <label style="font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase;">Chave de API Ativa</label>
                <div style="display:flex; gap:8px; align-items:center; position:relative;">
                    <input type="password" id="api-key-input" readonly value="lt_key_loading..." style="flex:1; padding:12px 40px 12px 14px; border:1px solid #cbd5e1; border-radius:12px; font-family:monospace; font-size:0.95rem; color:#0f172a; background:#f8fafc; font-weight:600;">
                    <button type="button" onclick="toggleApiKeyVisibility()" style="position:absolute; right:50px; background:none; border:none; color:#64748b; cursor:pointer;" title="Mostrar/Ocultar chave">
                        <i id="api-key-eye-icon" data-lucide="eye" style="width:18px; height:18px;"></i>
                    </button>
                    <button type="button" onclick="copyApiKey()" class="topbar-eco-btn" style="background:#e0f2fe; color:#0284c7; border-color:#cbd5e1; padding:12px;" title="Copiar chave">
                        <i data-lucide="copy" style="width:16px; height:16px;"></i>
                    </button>
                </div>
            </div>

            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:12px; padding:12px; font-size:0.8rem; color:#0369a1; line-height:1.4;">
                <strong>Integração Externa:</strong> envie a chave no cabeçalho HTTP <code>X-API-KEY</code> em suas requisições REST de telemetria.
            </div>

            <div style="display:flex; gap:12px; margin-top:10px; justify-content:space-between; align-items:center;">
                <button type="button" onclick="rotateApiKey()" class="topbar-eco-btn" style="color:#ef4444; border-color:#fca5a5; background:#fff5f5; padding:10px 15px; font-weight:700;" title="Gerar uma nova chave de API (invalida a chave atual)">
                    <i data-lucide="refresh-cw" style="width:14px; height:14px;"></i> Rotacionar Chave
                </button>
                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="closeApiKeyModal()" style="padding:12px 20px; border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:12px; cursor:pointer; font-weight:600; font-size:0.9rem;">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function openApiKeyModal() {
            const input = document.getElementById('api-key-input');
            input.value = 'Carregando...';
            input.type = 'password';
            document.getElementById('api-key-eye-icon').setAttribute('data-lucide', 'eye');
            
            document.getElementById('api-key-modal').style.display = 'flex';
            if (typeof lucide !== 'undefined') lucide.createIcons();

            try {
                const response = await fetch('api.php?action=get_tenant_api_key');
                const result = await response.json();
                if (result.ok || result.success) {
                    input.value = result.api_key;
                } else {
                    input.value = 'Erro ao carregar chave.';
                }
            } catch (err) {
                input.value = 'Falha de conexão.';
            }
        }

        function closeApiKeyModal() {
            document.getElementById('api-key-modal').style.display = 'none';
        }

        function toggleApiKeyVisibility() {
            const input = document.getElementById('api-key-input');
            const icon = document.getElementById('api-key-eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function copyApiKey() {
            const input = document.getElementById('api-key-input');
            if (input.value.startsWith('lt_key_')) {
                navigator.clipboard.writeText(input.value);
                if (typeof showToast === 'function') {
                    showToast('Chave de API copiada para a área de transferência!', 'success');
                } else {
                    alert('Chave de API copiada!');
                }
            }
        }

        async function rotateApiKey() {
            if (!confirm('Tem certeza de que deseja rotacionar a chave de API? A chave antiga deixará de funcionar imediatamente.')) {
                return;
            }

            const input = document.getElementById('api-key-input');
            input.value = 'Rotacionando...';
            
            try {
                const response = await fetch('api.php?action=rotate_tenant_api_key', { method: 'POST' });
                const result = await response.json();
                if (result.ok || result.success) {
                    input.value = result.api_key;
                    if (typeof showToast === 'function') {
                        showToast('Chave de API rotacionada com sucesso!', 'success');
                    } else {
                        alert('Chave rotacionada com sucesso!');
                    }
                } else {
                    input.value = 'Erro ao rotacionar.';
                }
            } catch (err) {
                input.value = 'Falha de conexão.';
            }
        }
    </script>

