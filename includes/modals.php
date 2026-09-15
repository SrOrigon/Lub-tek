    <div id="img-modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:1000; align-items:center; justify-content:center; flex-direction:column;">
        <img id="img-modal-content" src=""
            style="max-width:90%; max-height:80%; border-radius:8px; box-shadow:0 0 20px rgba(0,0,0,0.5);">
        <button class="btn btn-outline" style="margin-top:20px; color:white; border-color:white;"
            onclick="document.getElementById('img-modal').style.display='none'">Fechar (Esc)</button>
    </div>

    <!-- LIGHTBOX MODAL (For Asset Images) -->
    <div id="lightbox-modal"
        style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.95); z-index:10000; align-items:center; justify-content:center; flex-direction:column;">
        <img id="lightbox-img"
            style="max-width:90%; max-height:80vh; border-radius:8px; box-shadow:0 0 30px rgba(0,0,0,0.8); border:2px solid #333;">
        <div style="margin-top:20px; display:flex; gap:15px;">
            <button class="btn" style="background:white; color:black; font-weight:800;"
                onclick="document.getElementById('lightbox-modal').style.display='none'">
                <i data-lucide="x"></i> FECHAR
            </button>
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
                        <img id="brand-logo-preview" src="assets/img/system/img_69581d7fcdbbf.jpeg" style="width:55px; height:55px; border-radius:12px; border:1px solid #e2e8f0; object-fit:contain; background:#fff; padding:4px;">
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
                const response = await fetch('api.php?action=upload_image', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

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

