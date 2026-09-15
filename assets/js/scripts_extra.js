    // --- COMPLEMENTARY SCRIPTS (ROUTES, KPI, UTILS) ---
    // Note: API_URL and isOfflineMode are declared in scripts_main.php but we reference them here
    // We use window properties to avoid const redeclaration conflicts
    if (typeof API_URL === 'undefined') {
        window.API_URL = 'api.php';
    }
    if (typeof isOfflineMode === 'undefined') {
        window.isOfflineMode = false;
    }

    // --- IMAGE MODAL LOGIC ---
    // Keeps only one definition
    function openImageModal(src) {
        // Validate Source
        if (!src || src.includes('undefined') || src.trim() === '') return;

        const lb = document.getElementById('lightbox-modal');
        const img = document.getElementById('lightbox-img');
        if (lb && img) {
            img.src = src;
            lb.style.display = 'flex';
            lb.style.alignItems = 'center';
            lb.style.justifyContent = 'center';
            lb.style.flexDirection = 'column';
        }
    }

    // Close on Escape
    document.addEventListener('keydown', function (event) {
        if (event.key === "Escape") {
            const lb = document.getElementById('lightbox-modal');
            if (lb) lb.style.display = 'none';
        }
    });

    // --- EXPORT & PRINT LOGIC ---
    // Functions 'openImageModal' and 'triggerExcelImport' retained.
    // 'importAssetsFromExcel' removed (Moved to scripts_main.php)

    function triggerExcelImport() {
        const inp = document.getElementById('excel-import-input');
        if (inp) inp.click();
    }

    async function exportFullAssetTreeExcel() {
        showToast('Gerando relatorio completo...', 'info');
        try {
            // Need flattened tree. `assetsList` pode não existir como global nesta view
            // (não é declarada em nenhum outro script) — nesse caso caímos direto no fallback.
            const localTree = (typeof assetsList !== 'undefined') ? assetsList : null;
            const list = localTree ? flattenTree(localTree) : null;
            if (!list || list.length === 0) {
                // Fallback if local tree var is empty, fetch fresh
                const fresh = await api('get_tree');
                return exportFullAssetTreeExcel_Process(flattenTree(fresh));
            }
            exportFullAssetTreeExcel_Process(list);
        } catch (e) {
            console.error(e);
            showToast('Erro ao gerar Excel.', 'error');
        }
    }

    function flattenTree(nodes, parentId = null, depth = 0, res = []) {
        if (!nodes) return res;
        nodes.forEach(n => {
            res.push({
                ID: n.id,
                Nivel: depth,
                Nome: n.nome,
                Tag: n.tag,
                Tipo: n.tipo,
                Pai_ID: parentId,
                Status: n.status || 'Ok',
                Fabricante: n.fabricante || '',
                Lubrificante: n.dados_tecnicos?.material || '',
                Qtd: n.dados_tecnicos?.qtd_material || '',
                Freq: n.dados_tecnicos?.periodo || ''
            });
            if (n.children) flattenTree(n.children, n.id, depth + 1, res);
        });
        return res;
    }

    function exportFullAssetTreeExcel_Process(data) {
        if (typeof XLSX === 'undefined') return showToast("Erro: Lib Excel faltando.");

        const ws = XLSX.utils.json_to_sheet(data);
        // Basic Width Adjust
        const wscols = [
            { wch: 6 }, { wch: 6 }, { wch: 30 }, { wch: 15 }, { wch: 15 }, { wch: 8 }, { wch: 10 }, { wch: 15 }, { wch: 20 }, { wch: 8 }, { wch: 10 }
        ];
        ws['!cols'] = wscols;

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Ativos_Completo");
        XLSX.writeFile(wb, `LUBTEK_Ativos_${new Date().toISOString().slice(0, 10)}.xlsx`);
        showToast('Download iniciado!', 'success');
    }


    async function exportTasks(format) {
        showToast('Funcionalidade em desenvolvimento para ' + format, 'info');
    }

    function printPage() {
        try {
            window.print();
        } catch (e) {
            console.error(e);
            alert("Erro ao tentar imprimir. Tente Ctrl+P.");
        }
    }

    // --- TAB LOGIC ---
    // (Consolidated in scripts_main.php)

    // --- CALCULATOR UI SWITCHER ---
    function switchCalc(id, btn) {
        document.querySelectorAll('.calc-panel').forEach(p => p.style.display = 'none');
        const target = document.getElementById(`calc-panel-${id}`);
        if (target) target.style.display = 'block';

        document.querySelectorAll('.calc-menu-item').forEach(i => i.classList.remove('active'));
        if (btn) btn.classList.add('active');
    }

    // --- 3D VIEWER LOGIC (ADVANCED AI) ---
    // Note: 3D Logic is handled by includes/views/view_3d_revolutionary.php
    async function load3DAssetsLegacy() {
        return;
    }


    function filter3DAssets() {
        const query = document.getElementById('search-3d').value.toLowerCase();
        const list = document.getElementById('list-3d-assets');
        list.innerHTML = '';

        const filtered = assets3DList.filter(n => (n.nome || '').toLowerCase().includes(query) || (n.tag && n.tag.toLowerCase().includes(query)));

        if (filtered.length === 0) {
            list.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">Nenhum ativo encontrado.</div>';
            return;
        }

        filtered.forEach(n => {
            const has3D = n.imagem_3d ? true : false;
            const item = document.createElement('div');
            item.className = 'list-item';
            item.style.padding = '10px';
            item.style.cursor = 'pointer';
            item.style.marginBottom = '5px';
            item.style.borderRadius = '6px';
            item.onclick = () => select3DAsset(n.id);
            if (selected3DNode && selected3DNode.id == n.id) item.classList.add('active-item');

            item.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-weight:600;">${escapeHtml(n.nome)}</div>
                    ${has3D ? '<i data-lucide="box" style="width:14px; color:var(--primary);"></i>' : ''}
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted);">${escapeHtml(n.tag) || 'Sem TAG'}</div>
            `;
            list.appendChild(item);
        });
        lucide.createIcons();
    }

    function select3DAsset(id) {
        selected3DNode = assets3DList.find(n => n.id == id);
        if (!selected3DNode) return;

        filter3DAssets(); // Re-render

        document.getElementById('3d-title').innerText = selected3DNode.nome;

        // Buttons Visibility
        // Buttons Visibility
        const btnImport = document.getElementById('btn-import-3d');
        const btnGen = document.getElementById('btn-gen-3d');
        const btnExport = document.getElementById('btn-export-3d');
        const btnRemove = document.getElementById('btn-remove-img-3d');

        if (btnImport) btnImport.style.display = 'inline-flex';

        const hasImg = !!selected3DNode.imagem_3d;
        if (btnGen) btnGen.style.display = hasImg ? 'inline-flex' : 'none';
        if (btnExport) btnExport.style.display = hasImg ? 'inline-flex' : 'none';
        if (btnRemove) btnRemove.style.display = hasImg ? 'inline-flex' : 'none';

        const container = document.getElementById('container-3d');
        const empty = document.getElementById('empty-3d');
        const img = document.getElementById('img-3d');

        if (hasImg) {
            if (img) img.src = selected3DNode.imagem_3d;
            if (container) container.style.display = 'block';
            if (empty) empty.style.display = 'none';
        } else {
            if (container) container.style.display = 'none';
            if (empty) {
                empty.style.display = 'block';
                empty.innerHTML = `<i data-lucide="box" style="width:64px; height:64px; margin-bottom:10px;"></i><p>Este ativo nao possui vista 3D.</p><p style="font-size:0.8rem; color:var(--primary);">Importe uma imagem 2D para começar.</p>`;
            }
        }

        // Refresh Icons (Important for new buttons)
        lucide.createIcons();

        // Reset Logic
        is3DGenerated = false;
        resetZoom3D();
    }

    function trigger3DUpload() {
        document.getElementById('upload-3d-input').click();
    }

    async function upload3DImage() {
        const input = document.getElementById('upload-3d-input');
        if (input.files.length === 0 || !selected3DNode) return;

        const fd = new FormData();
        fd.append('action', 'upload_image');
        fd.append('file', input.files[0]);

        try {
            showToast('Enviando imagem 2D...', 'info');
            const res = await fetch('api.php?action=upload_image', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.ok) {
                selected3DNode.imagem_3d = json.path;
                const saveRes = await api('save_3d_view', { id: selected3DNode.id, path: json.path });

                select3DAsset(selected3DNode.id);

                if (saveRes && saveRes.ok !== false) {
                    showToast('Upload concluido.', 'success');
                    generate3DFromImage();
                } else {
                    showToast((saveRes && saveRes.error) || 'Imagem enviada, mas falha ao vincular ao ativo (permissão?).', 'warning');
                }

            } else {
                showToast('Erro no upload.', 'error');
            }
        } catch (e) { console.error(e); }
        input.value = '';
    }

    async function remove3DImage() {
        if (!selected3DNode) return;

        if (!confirm('Tem certeza que deseja remover a imagem deste ativo?')) return;

        try {
            // Remove from object
            selected3DNode.imagem_3d = null;
            // Update backend
            await api('save_3d_view', { id: selected3DNode.id, path: '' });

            // Update UI
            select3DAsset(selected3DNode.id);
            showToast('Imagem removida com sucesso.', 'info');

        } catch (e) {
            console.error(e);
            showToast('Erro ao remover imagem.', 'error');
        }
    }

    function generate3DFromImage() {
        if (!selected3DNode || !selected3DNode.imagem_3d) return;

        showToast('<i data-lucide="loader-2" class="spin"></i> Processando Extrusao IA...', 'info');
        lucide.createIcons();

        // Simulate Processing
        setTimeout(() => {
            is3DGenerated = true;
            current3DRotation = { x: -25, y: -25 }; // Initial angle to show depth

            // GENERATE LAYERS FOR VOLUMETRIC EFFECT
            const container = document.getElementById('container-3d');
            const mainImgSrc = selected3DNode.imagem_3d;

            // LOCK DIMENSIONS BASED ON CURRENT IMAGE to prevent collapse
            const currentImg = document.getElementById('img-3d');
            if (currentImg) {
                container.style.width = currentImg.offsetWidth + 'px';
                container.style.height = currentImg.offsetHeight + 'px';
                // Reset max-width issues for container
                container.style.maxWidth = '80vw';
                container.style.maxHeight = '80vh';
            }

            // Clear and rebuild
            container.innerHTML = '';

            // Create 12 layers for thickness
            for (let i = 0; i < 12; i++) {
                const img = document.createElement('img');
                img.src = mainImgSrc;
                img.className = 'layer-3d';
                // Style handled in CSS or inline
                img.style.position = 'absolute';
                img.style.top = '0';
                img.style.left = '0';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'contain';
                img.style.pointerEvents = 'none';

                // Z-Index magic
                // We space them out by 1px or 2px
                img.style.transform = `translateZ(-${i * 2}px)`;

                // Darken back layers to simulate shadow/side
                if (i > 0) {
                    img.style.filter = `brightness(${1 - (i * 0.05)})`;
                }

                container.appendChild(img);
            }

            showToast('Modelo 3D Gerado com Sucesso! Use o mouse para rotacionar.', 'success');
            updateTransform3D();
        }, 1000);
    }

    function export3Dmodel() {
        if (!selected3DNode) return;

        const element = document.getElementById('viewport-3d');
        if (!element) return;

        showToast('<i data-lucide="camera" class="spin"></i> Renderizando imagem 3D...', 'info');
        lucide.createIcons();

        // Use html2canvas to screenshot the rendered view
        html2canvas(element, {
            useCORS: true,       // Allow loading images
            allowTaint: true,    // Allow dirty canvas
            backgroundColor: null, // Preserves gradient or transparency if set
            ignoreElements: (node) => {
                // Ignore empty state or controls if they are inside the viewport div
                return node.id === 'empty-3d' && node.style.display !== 'none';
            }
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = `LUBTEK_3D_${selected3DNode.tag || 'View'}.png`;
            link.href = canvas.toDataURL('image/png');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast('Captura 3D salva com sucesso!', 'success');
        }).catch(err => {
            console.error("3D Export Error:", err);
            showToast('Erro ao renderizar imagem. Tente novamente.', 'error');
        });
    }


    // --- ZOOM & PAN & ROTATE ---
    function resetZoom3D() {
        current3DScale = 1;
        current3DTranslate = { x: 0, y: 0 };
        current3DRotation = { x: 0, y: 0 };
        is3DGenerated = false;

        // Reset to single simple image
        const container = document.getElementById('container-3d');
        // UNLOCK DIMENSIONS
        if (container) {
            container.style.width = '';
            container.style.height = '';
            container.style.maxWidth = '';
            container.style.maxHeight = '';
        }

        if (selected3DNode && selected3DNode.imagem_3d) {
            container.innerHTML = `<img id="img-3d" src="${escapeAttr(selected3DNode.imagem_3d)}" style="max-width:80vw; max-height:80vh; pointer-events:none; border-radius:4px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">`;
        }

        updateTransform3D();
    }

    function updateTransform3D() {
        const el = document.getElementById('container-3d');
        if (el) {
            // Apply scale to the whole container
            // Note: We need preserve-3d on the container for the layers to work
            el.style.transformStyle = 'preserve-3d';

            let transform = `translate(${current3DTranslate.x}px, ${current3DTranslate.y}px) scale(${current3DScale})`;

            if (is3DGenerated) {
                // Add Rotation
                transform += ` perspective(1000px) rotateY(${current3DRotation.x}deg) rotateX(${-current3DRotation.y}deg)`;
            }

            el.style.transform = transform;
        }
    }

    const vp = document.getElementById('viewport-3d');
    if (vp) {
        vp.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY * -0.001;
            const newScale = Math.min(Math.max(.2, current3DScale + delta), 8);
            current3DScale = newScale;
            updateTransform3D();
        }, { passive: false });

        vp.addEventListener('mousedown', (e) => {
            isDragging3D = true;
            // Record start pos relative to current translation/rotation
            startDrag3D = { x: e.clientX, y: e.clientY };
            vp.style.cursor = 'grabbing';
            e.preventDefault();
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging3D) return;
            e.preventDefault();

            const dx = e.clientX - startDrag3D.x;
            const dy = e.clientY - startDrag3D.y;

            if (is3DGenerated) {
                // ROTATION MODE
                // Sensitivity 0.5
                current3DRotation.x += dx * 0.5;
                current3DRotation.y += dy * 0.5;
            } else {
                // PAN MODE
                current3DTranslate.x += dx;
                current3DTranslate.y += dy;
            }

            startDrag3D = { x: e.clientX, y: e.clientY }; // Reset start for incremental update
            updateTransform3D();
        });

        window.addEventListener('mouseup', () => {
            isDragging3D = false;
            vp.style.cursor = 'grab';
        });

        // Double click to toggle generate?
        vp.addEventListener('dblclick', () => {
            // Optional
        });
    }

    // Estado do Visualizador 3D (declarado explicitamente para evitar ReferenceError
    // caso estas funções legadas sejam invocadas antes de qualquer atribuição)
    if (typeof window.selected3DNode === 'undefined') window.selected3DNode = null;
    if (typeof window.assets3DList === 'undefined') window.assets3DList = [];
    if (typeof window.current3DScale === 'undefined') window.current3DScale = 1;
    if (typeof window.current3DTranslate === 'undefined') window.current3DTranslate = { x: 0, y: 0 };
    if (typeof window.current3DRotation === 'undefined') window.current3DRotation = { x: 0, y: 0 };
    if (typeof window.is3DGenerated === 'undefined') window.is3DGenerated = false;
    if (typeof window.startDrag3D === 'undefined') window.startDrag3D = { x: 0, y: 0 };

    // --- ROTAS DE LUBRIFICAÇÃO (Checklist de Campo) ---
    let routePoints = [];
    let currentAnomalyId = null;
    let lastRouteAlertOsId = null;
    let pendingAlertPhotoPath = null;

    function parseTechData(raw) {
        if (!raw) return {};
        try { return typeof raw === 'string' ? JSON.parse(raw) : raw; } catch (e) { return {}; }
    }

    function flattenTreeForRoutes(nodes, parentChain, result) {
        if (!nodes) return result;
        nodes.forEach(node => {
            const chain = [...parentChain, node];
            if (node.tipo === 'ponto') {
                const tech = parseTechData(node.dados_tecnicos);
                const setor = chain.find(n => n.tipo === 'setor') || chain.find(n => n.tipo === 'equipamento') || chain[0];
                const equip = chain.find(n => n.tipo === 'equipamento');
                result.push({
                    id: node.id,
                    name: node.nome,
                    tag: node.tag || '',
                    sector: setor ? setor.nome : 'Geral',
                    equipment: equip ? equip.nome : '',
                    material: tech.material || tech.ponto_lub || '—',
                    servico: tech.servico || 'Lubrificar',
                    periodo: tech.periodo || '—',
                    condicao: tech.condicao || '—',
                    status: node.status || 'Pendente'
                });
            }
            if (node.children && node.children.length) {
                flattenTreeForRoutes(node.children, chain, result);
            }
        });
        return result;
    }

    async function loadRoutes() {
        const list = document.getElementById('route-list');
        if (list) {
            list.innerHTML = '<div class="route-loading">Carregando pontos de lubrificação...</div>';
        }

        try {
            const raw = await api('get_tree');
            const tree = Array.isArray(raw) ? raw : (raw && Array.isArray(raw.data) ? raw.data : []);
            routePoints = flattenTreeForRoutes(tree, [], []);

            const sectors = [...new Set(routePoints.map(p => p.sector))].sort();
            const filter = document.getElementById('route-sector-filter');
            if (filter) {
                const current = filter.value;
                filter.innerHTML = '<option value="all">Todos os Setores</option>';
                sectors.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    filter.appendChild(opt);
                });
                if (sectors.includes(current)) filter.value = current;
            }

            renderRoutes();
            if (typeof syncRoutes === 'function') syncRoutes();
        } catch (e) {
            if (list) {
                list.innerHTML = '<div style="padding:30px;text-align:center;color:#991b1b;background:#fee2e2;border-radius:12px;border:1px solid #fca5a5;">Erro ao carregar rotas. Verifique a conexão e tente novamente.</div>';
            }
        }
    }

    /** Status de tarefa concluída na rota (NÃO inclui OK — OK é status saudável do ativo) */
    function isRoutePointDone(status) {
        const s = String(status || '');
        return s === 'Concluido' || s === 'Concluído';
    }

    function renderRoutes() {
        const list = document.getElementById('route-list');
        const filter = document.getElementById('route-sector-filter');
        if (!list) return;

        const sector = filter ? filter.value : 'all';
        const filtered = sector === 'all' ? routePoints : routePoints.filter(p => p.sector === sector);

        const stats = document.getElementById('route-stats');
        const progressText = document.getElementById('route-progress-text');
        const progressPct = document.getElementById('route-progress-pct');
        const progressFill = document.getElementById('route-progress-fill');

        const done = filtered.filter(p => isRoutePointDone(p.status)).length;
        const total = filtered.length;
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;

        if (stats) stats.textContent = `${done}/${total} concluídos`;
        if (progressText) progressText.textContent = `${done} de ${total} pontos concluídos`;
        if (progressPct) progressPct.textContent = `${pct}%`;
        if (progressFill) progressFill.style.width = `${pct}%`;

        if (filtered.length === 0) {
            list.innerHTML = `<div class="route-empty">
                <i data-lucide="map-pin" style="width:36px;margin-bottom:12px;opacity:0.4;"></i><br>
                <strong>Nenhum ponto encontrado</strong><br>
                <span style="font-size:0.9rem;">Cadastre pontos de lubrificação em Meus Ativos.</span>
            </div>`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }

        list.innerHTML = '';
        filtered.forEach(p => {
            const isDone = isRoutePointDone(p.status);
            const isAlert = p.status === 'Alerta' || p.status === 'Crítico';
            const statusClass = isDone ? 'route-status-ok'
                : isAlert ? 'route-status-alert' : 'route-status-pending';
            const statusLabel = isDone ? 'Concluído' : isAlert ? 'Alerta' : 'Pendente';
            const servico = p.servico && p.servico !== '—' ? p.servico : 'Inspecionar';
            const material = p.material && p.material !== '—' ? p.material : 'Ver ficha do ponto';

            const card = document.createElement('div');
            card.className = `route-card ${statusClass}`;
            card.dataset.id = p.id;
            card.innerHTML = `
                <div class="swipe-overlay swipe-ok">✓</div>
                <div class="swipe-overlay swipe-alert">⚠</div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                    <div>
                        <div class="route-point-title">${escapeRouteHtml(p.name)}</div>
                        <div class="route-equipment">${escapeRouteHtml(p.equipment || 'Equipamento')}${p.tag ? ' · ' + escapeRouteHtml(p.tag) : ''}</div>
                    </div>
                    <span class="route-status-badge">${statusLabel}</span>
                </div>
                <div class="route-action-chips">
                    <span class="route-chip">${escapeRouteHtml(servico)}</span>
                    <span class="route-chip material">${escapeRouteHtml(material)}</span>
                </div>
                <div class="route-card-actions">
                    <button type="button" class="btn-route-done" ${isDone ? 'disabled' : ''}>
                        <i data-lucide="check-circle" style="width:20px;height:20px;"></i>
                        ${isDone ? 'Tarefa Concluída' : 'Concluir Tarefa'}
                    </button>
                    <button type="button" class="btn-route-alert-icon" title="Reportar problema" aria-label="Reportar alerta">
                        <i data-lucide="alert-triangle" style="width:22px;height:22px;"></i>
                    </button>
                </div>
            `;

            const btnDone = card.querySelector('.btn-route-done');
            if (btnDone && !isDone) btnDone.onclick = () => handleSwipeAction(p.id, 'ok');
            card.querySelector('.btn-route-alert-icon').onclick = (e) => { e.stopPropagation(); openRouteAlertModal(p.id); };
            initSwipe(card, p.id);
            list.appendChild(card);
        });

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function escapeRouteHtml(str) {
        const d = document.createElement('span');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function initSwipe(el, id) {
        let startX = 0;
        let currentX = 0;
        const threshold = 100;
        let isDragging = false;

        el.addEventListener('touchstart', e => {
            startX = e.touches[0].clientX;
            el.style.transition = 'none';
            isDragging = true;
        }, { passive: true });

        el.addEventListener('touchmove', e => {
            if (!isDragging) return;
            currentX = e.touches[0].clientX;
            const delta = currentX - startX;

            // Visual Feedback
            const overlays = el.querySelectorAll('.swipe-overlay');

            if (delta > 0) { // Dragging Right -> OK
                el.style.transform = `translateX(${delta}px)`;
                overlays[0].style.opacity = Math.min(delta / threshold, 1);
                overlays[1].style.opacity = 0;
            } else { // Dragging Left -> Anomaly
                el.style.transform = `translateX(${delta}px)`;
                overlays[0].style.opacity = 0;
                overlays[1].style.opacity = Math.min(Math.abs(delta) / threshold, 1);
            }
        }, { passive: true });

        el.addEventListener('touchend', e => {
            if (!isDragging) return;
            isDragging = false;
            el.style.transition = 'transform 0.2s';
            const delta = currentX - startX;

            // Reset visual always
            const overlays = el.querySelectorAll('.swipe-overlay');
            overlays.forEach(o => o.style.opacity = 0);
            el.style.transform = 'translateX(0)';

            if (Math.abs(delta) > threshold) {
                if (delta > 0) {
                    handleSwipeAction(id, 'ok');
                } else {
                    openRouteAlertModal(id);
                }
            }
        });
    }

    function openRouteAlertModal(id) {
        const p = routePoints.find(x => x.id === id);
        if (!p) return;

        currentAnomalyId = id;
        pendingAlertPhotoPath = null;
        lastRouteAlertOsId = null;
        const overlay = document.getElementById('route-alert-overlay');
        const subtitle = document.getElementById('route-alert-subtitle');
        const motivo = document.getElementById('route-alert-motivo');

        if (subtitle) {
            subtitle.textContent = `${p.name} · ${p.equipment || 'Equipamento'}`;
        }
        if (motivo) {
            motivo.value = '';
            setTimeout(() => motivo.focus(), 100);
        }
        if (overlay) overlay.classList.add('active');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeRouteAlertModal() {
        const overlay = document.getElementById('route-alert-overlay');
        if (overlay) overlay.classList.remove('active');
        currentAnomalyId = null;
    }

    function attachOptionalAlertPhoto() {
        const cam = document.getElementById('anomaly-cam');
        if (cam) cam.click();
    }

    async function submitRouteAlert() {
        const id = currentAnomalyId;
        const p = routePoints.find(x => x.id === id);
        if (!p) return;

        const motivoEl = document.getElementById('route-alert-motivo');
        const motivo = (motivoEl?.value || '').trim();
        if (!motivo) {
            showToast('Descreva o problema encontrado.', 'warning');
            motivoEl?.focus();
            return;
        }

        const submitBtn = document.querySelector('.route-alert-btn-submit');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Registrando...'; }

        try {
            if (isOfflineMode) {
                p.status = 'Alerta';
                saveRouteStatus(id, 'Alerta');
                queueRouteAlert(id, motivo);
                showToast('Alerta salvo offline. Será sincronizado quando voltar online.', 'warning');
                closeRouteAlertModal();
                renderRoutes();
                return;
            }

            const res = await api('report_route_alert', {
                id: id,
                ativo_id: id,
                motivo: motivo,
                equipamento: p.equipment || '',
                photo_path: pendingAlertPhotoPath || ''
            });

            if (isApiSuccess(res)) {
                p.status = 'Alerta';
                lastRouteAlertOsId = res.os_id || null;
                pendingAlertPhotoPath = null;
                notificarSucessoCampo(`🚨 Alerta registrado! Ordem de Serviço criada para ${p.name}.`, 'warning');
                closeRouteAlertModal();
                renderRoutes();
                if (typeof Events !== 'undefined') Events.emit('data_changed', { action: 'route_alert' });
            } else {
                showToast(res?.error || 'Não foi possível registrar o alerta.', 'error');
            }
        } catch (e) {
            console.error('Erro ao gerar OS de alerta:', e);
            showToast('Erro ao registrar alerta. Tente novamente.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="send" style="width:16px;height:16px;"></i> Registrar Alerta';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }
    }

    function queueRouteAlert(id, motivo) {
        let queue = JSON.parse(localStorage.getItem('lub_route_alerts_queue') || '[]');
        queue = queue.filter(x => x.id !== id);
        queue.push({ id, motivo, time: Date.now() });
        localStorage.setItem('lub_route_alerts_queue', JSON.stringify(queue));
    }

    async function syncRouteAlerts() {
        let queue = JSON.parse(localStorage.getItem('lub_route_alerts_queue') || '[]');
        if (queue.length === 0) return;

        const remaining = [];
        for (const item of queue) {
            try {
                const res = await api('report_route_alert', { id: item.id, ativo_id: item.id, motivo: item.motivo });
                if (!isApiSuccess(res)) throw new Error('Sync failed');
            } catch (e) {
                remaining.push(item);
            }
        }
        localStorage.setItem('lub_route_alerts_queue', JSON.stringify(remaining));
    }

    async function handleSwipeAction(id, type) {
        const p = routePoints.find(x => x.id === id);
        if (!p) return;

        if (type === 'ok') {
            const typed = window.prompt('TAG do ponto (opcional — deixe em branco para concluir sem QR):', p.tag || '');
            p.status = 'Concluido';
            notificarSucessoCampo(`✅ Tarefa do ${p.name} concluída com sucesso!`, 'success');
            saveRouteStatus(id, 'Concluido', typed || '');
            renderRoutes();
        } else {
            openRouteAlertModal(id);
        }
    }

    async function handleAnomalyPhoto(input) {
        if (!input.files || !input.files[0] || !currentAnomalyId) return;

        showToast('Enviando foto (opcional)...', 'info');
        const fd = new FormData();
        fd.append('action', 'upload_image');
        fd.append('file', input.files[0]);
        if (lastRouteAlertOsId) {
            fd.append('os_id', String(lastRouteAlertOsId));
        }
        fd.append('watermark', '1');
        const p = routePoints.find(x => x.id === currentAnomalyId);
        if (p) fd.append('watermark_tag', p.tag || p.name || '');
        if (window.CURRENT_USER && window.CURRENT_USER.nome) {
            fd.append('watermark_user', window.CURRENT_USER.nome);
        }
        input.value = '';

        try {
            if (isOfflineMode) {
                showToast('Foto não enviada (offline).', 'warning');
                return;
            }
            const res = await fetch('api.php?action=upload_image', { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (json.ok) {
                const path = json.path || '';
                if (path) {
                    pendingAlertPhotoPath = path;
                }
                if (lastRouteAlertOsId) {
                    showToast('Foto anexada à Ordem de Serviço do alerta.', 'success');
                } else {
                    showToast('Foto pronta. Será anexada ao registrar o alerta.', 'success');
                }
            } else {
                showToast(json.error || 'Não foi possível enviar a foto.', 'error');
            }
        } catch (e) {
            showToast('Não foi possível enviar a foto.', 'error');
        }
    }

    async function saveRouteStatus(id, status, qrTag) {
        const p = routePoints.find(x => x.id === id);
        if (p) p.status = status;

        let queue = JSON.parse(localStorage.getItem('lub_routes_queue') || '[]');
        queue = queue.filter(x => x.id !== id);
        queue.push({ id, status, qr_tag: qrTag || '', time: Date.now() });
        localStorage.setItem('lub_routes_queue', JSON.stringify(queue));

        if (!isOfflineMode) {
            syncRoutes();
        }
    }

    function isApiSuccess(res) {
        if (res === null || res === undefined) return false;
        if (Array.isArray(res)) return true;
        if (res.ok === false) return false;
        return res.ok === true || res.updated === true || res.success === true;
    }

    async function syncRoutes() {
        let queue = JSON.parse(localStorage.getItem('lub_routes_queue') || '[]');
        if (queue.length === 0) return;

        const remaining = [];
        for (let item of queue) {
            try {
                const payload = { id: item.id, status: item.status };
                if (item.qr_tag) payload.qr_tag = item.qr_tag;
                const res = await api('set_asset_status', payload);
                if (!isApiSuccess(res)) throw new Error('Sync failed');
            } catch (e) {
                remaining.push(item);
            }
        }

        localStorage.setItem('lub_routes_queue', JSON.stringify(remaining));
        if (remaining.length === 0 && queue.length > 0) {
            showToast('Sincronização concluída!', 'success');
        }
        await syncRouteAlerts();
    }

    // --- VISUAL MANAGEMENT (KPIs) LOGIC ---
    let kpiChartsExtra = { adherence: null, consumption: null, causes: null, health: null, backlog: null };

    async function loadKpiView(assetId = null) {
        const mainTitle = document.querySelector('#view-kpi h1');
        const originalTitle = 'Resumo da Fábrica';
        const emptyState = document.getElementById('kpi-empty-state');
        const chartsSection = document.getElementById('kpi-charts-section');

        function showKpiEmptyState(title, msg) {
            if (emptyState) {
                emptyState.style.display = 'block';
                const t = document.getElementById('kpi-empty-title');
                const m = document.getElementById('kpi-empty-msg');
                if (t) t.textContent = title || 'Tudo em dia na unidade!';
                if (m) m.textContent = msg || 'Não há histórico suficiente para montar os gráficos neste período.';
            }
            if (chartsSection) chartsSection.style.display = 'none';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function hideKpiEmptyState() {
            if (emptyState) emptyState.style.display = 'none';
            if (chartsSection) chartsSection.style.display = 'block';
        }

        function updateSummaryCards(kpis, dist) {
            const healthVal = document.getElementById('kpi-summary-health-val');
            const healthHint = document.getElementById('kpi-summary-health-hint');
            const pendingVal = document.getElementById('kpi-summary-pending-val');
            const pendingHint = document.getElementById('kpi-summary-pending-hint');
            const financialEl = document.getElementById('label-financial');

            const total = kpis.total_equipamentos || 0;
            const pct = total > 0 && kpis.health_pct !== undefined ? kpis.health_pct : 0;
            if (healthVal) {
                healthVal.textContent = total > 0 ? `${pct}% Equipamentos em Dia` : 'Sem equipamentos';
                healthVal.style.color = total === 0 ? '#94a3b8' : (pct >= 70 ? '#10b981' : (pct >= 40 ? '#f59e0b' : '#ef4444'));
            }
            if (healthHint) {
                healthHint.textContent = total > 0 ? `${total} máquinas cadastradas` : 'Cadastre equipamentos em Meus Ativos';
            }

            const overdue = kpis.pending_overdue || 0;
            const today = kpis.pending_today || 0;
            if (pendingVal) {
                pendingVal.textContent = overdue > 0 ? `${overdue} Tarefa(s) Atrasada(s)` : `${today} para Hoje`;
                pendingVal.style.color = overdue > 0 ? '#ef4444' : (today > 0 ? '#f59e0b' : '#94a3b8');
            }
            if (pendingHint) {
                pendingHint.textContent = overdue > 0 ? 'Clique para ver as ordens' : (today > 0 ? 'Clique para ver as ordens' : 'Nenhuma pendência urgente');
            }

            if (financialEl) {
                const fin = Number(kpis.financial_total || 0);
                financialEl.innerText = fin.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            }
            const costHint = document.getElementById('kpi-summary-cost-hint');
            if (costHint) {
                costHint.textContent = kpis.financial_hint
                    || (Number(kpis.financial_total || 0) > 0
                        ? 'Estimativa pelos pontos cadastrados'
                        : 'Sem preço/quantidade nos pontos');
            }
            const mtbfVal = document.getElementById('kpi-summary-mtbf-val');
            const mtbfHint = document.getElementById('kpi-summary-mtbf-hint');
            if (mtbfVal) {
                if (kpis.mtbf_hours) {
                    mtbfVal.textContent = Math.round(kpis.mtbf_hours) + ' h';
                } else {
                    mtbfVal.textContent = 'Sem amostra';
                }
            }
            if (mtbfHint) {
                const mttr = kpis.mttr_hours != null ? (' · MTTR ' + kpis.mttr_hours + ' h') : '';
                mtbfHint.textContent = kpis.reliability_hint || ('Falhas 12m: ' + (kpis.failures_12m || 0) + mttr);
            }
        }

        function resetKpiUiState(msg) {
            if (mainTitle) mainTitle.innerText = originalTitle;
            const sb = document.getElementById('ai-severity-badge');
            if (sb) {
                sb.innerText = msg || 'SEM DADOS';
                sb.style.background = '#e2e8f0';
                sb.style.color = '#64748b';
            }
            showKpiEmptyState('Não foi possível carregar os dados', 'Verifique sua conexão e tente novamente.');
        }

        try {
            if (typeof api !== 'function') {
                resetKpiUiState('API INDISPONÍVEL');
                return;
            }

            if (mainTitle) {
                mainTitle.innerHTML = `${originalTitle} <span style="font-size:0.85rem; opacity:0.45; margin-left:8px;">Atualizando...</span>`;
            }

            const advContainer = document.getElementById('ai-advisor-container');
            if (advContainer) advContainer.style.display = 'none';

            const focusSel = document.getElementById('kpi-focus-selector');
            if (focusSel && focusSel.options.length === 1) {
                const tree = await api('get_tree');
                if (tree && Array.isArray(tree)) {
                    function findUnits(nodes) {
                        if (!Array.isArray(nodes)) return;
                        nodes.forEach(n => {
                            if (n.tipo === 'unidade' || n.tipo === 'setor') {
                                const opt = document.createElement('option');
                                opt.value = n.id;
                                opt.innerText = n.nome;
                                focusSel.appendChild(opt);
                            }
                            if (n.children) findUnits(n.children);
                        });
                    }
                    findUnits(tree);
                }
            }

            const res = await api('get_kpis', assetId ? { asset_id: assetId } : null);
            if (mainTitle) mainTitle.innerText = originalTitle;

            if (!res || !res.ok) {
                resetKpiUiState('FALHA AO CARREGAR');
                return;
            }

            let kpis = {};
            let dist = {};
            let hasData = res.has_data;

            if (res.kpis) {
                kpis = res.kpis;
                dist = res.distribution || {};
            } else if (res.data && res.data.kpis) {
                kpis = res.data.kpis;
                dist = res.data.distribution || {};
                hasData = res.data.has_data;
            } else {
                // Resposta legada achatada pelo api.php
                const { ok, has_data, kpis: _k, distribution: _d, ai_advisor, ...flat } = res;
                if (res.total_equipamentos !== undefined) {
                    kpis = { ...flat };
                    dist = {};
                }
            }

            if (hasData === undefined) {
                hasData = Object.keys(kpis).length > 0 || Object.keys(dist).length > 0;
            }

            updateSummaryCards(kpis, dist);

            const totalAtividade = (kpis.completed_month || 0)
                + (kpis.pending_today || 0)
                + (kpis.pending_overdue || 0);

            const noChartData = totalAtividade === 0
                && (kpis.adherence || 0) === 0
                && Object.values(dist.health || {}).every(v => !v)
                && Object.values(dist.backlog || {}).every(v => !v);

            if (noChartData && Number(kpis.financial_total || 0) <= 0) {
                showKpiEmptyState(
                    'Nenhuma atividade registrada este mês',
                    'Assim que as primeiras rotas de lubrificação forem concluídas no campo, os gráficos de saúde dos ativos e custos aparecerão automaticamente aqui.'
                );
            } else {
                hideKpiEmptyState();
            }

            const advisor = res.ai_advisor || (res.data ? res.data.ai_advisor : null);
            if (advContainer && advisor && !noChartData) {
                advContainer.style.display = 'block';
                document.getElementById('ai-insight-text').innerText = advisor.insight || 'Dados analisados.';
                document.getElementById('ai-recommendation-text').innerText = advisor.recommendation || '';
                const sb = document.getElementById('ai-severity-badge');
                if (sb) {
                    sb.innerText = advisor.severity === 'optimal' ? 'BOM' : (advisor.severity === 'warning' ? 'ATENÇÃO' : 'URGENTE');
                    sb.style.background = advisor.severity === 'optimal' ? '#10b981' : (advisor.severity === 'warning' ? '#f59e0b' : '#ef4444');
                    sb.style.color = 'white';
                }
            } else if (advContainer) {
                advContainer.style.display = 'none';
            }

            if (noChartData && Number(kpis.financial_total || 0) <= 0) return;

            const healthData = dist.health || {};
            const healthEntries = Object.entries(healthData).filter(([, v]) => v > 0);
            renderChart('health', 'doughnut', {
                labels: healthEntries.length ? healthEntries.map(x => x[0]) : ['Sem cadastro'],
                datasets: [{
                    data: healthEntries.length ? healthEntries.map(x => x[1]) : [1],
                    backgroundColor: healthEntries.length ? ['#10b981', '#f59e0b', '#ef4444'] : ['#f1f5f9'],
                    borderWidth: 0,
                    cutout: '60%'
                }]
            }, {
                plugins: { legend: { position: 'bottom', labels: { color: '#64748b', font: { size: 11 } } } }
            });

            const adherence = kpis.adherence !== undefined ? kpis.adherence : (kpis.preventiva_compliance || 0);
            renderChart('adherence', 'doughnut', {
                labels: ['No prazo', 'Fora do prazo'],
                datasets: [{
                    data: [adherence, Math.max(0, 100 - adherence)],
                    backgroundColor: ['#10b981', '#e2e8f0'],
                    borderWidth: 0,
                    circumference: 180,
                    rotation: 270,
                    cutout: '85%'
                }]
            });
            const lblAd = document.getElementById('label-adherence');
            if (lblAd) {
                lblAd.innerText = adherence + '%';
                lblAd.style.color = adherence > 80 ? '#10b981' : (adherence > 50 ? '#f59e0b' : '#64748b');
            }

            const backlogData = dist.backlog || {};
            const backlogLabels = Object.keys(backlogData);
            const backlogValues = Object.values(backlogData);
            const totalBacklog = backlogValues.reduce((a, b) => a + b, 0);

            renderChart('backlog', 'bar', {
                labels: totalBacklog ? backlogLabels : ['Nada pendente'],
                datasets: [{
                    label: 'Pendentes',
                    data: totalBacklog ? backlogValues : [0],
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#94a3b8'],
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            });

            const consumption = dist.consumption || {};
            const consLabels = Object.keys(consumption);
            const consValues = Object.values(consumption);
            const totalCons = consValues.reduce((a, b) => a + b, 0);

            renderChart('consumption', 'bar', {
                labels: totalCons ? consLabels : ['Sem registro'],
                datasets: [{
                    label: 'Quantidade',
                    data: totalCons ? consValues : [0],
                    backgroundColor: '#0ea5e9',
                    borderRadius: 6
                }]
            });

            const causesData = dist.causes || {};
            const causesFiltered = Object.entries(causesData).filter(([, v]) => v > 0);

            renderChart('causes', 'bar', {
                labels: causesFiltered.length ? causesFiltered.map(x => x[0]) : ['Nenhum problema'],
                datasets: [{
                    label: 'Ocorrências',
                    data: causesFiltered.length ? causesFiltered.map(x => x[1]) : [0],
                    backgroundColor: '#ef4444',
                    borderRadius: 6
                }]
            }, { indexAxis: 'y' });

            if (typeof lucide !== 'undefined') lucide.createIcons();

        } catch (e) {
            console.error("KPI Load Error", e);
            resetKpiUiState('ERRO');
            if (mainTitle) mainTitle.innerText = originalTitle;
        }
    }


    // --- OFFLINE SYNC MANAGER (LUBE-IT Style) ---
    const SYNC_QUEUE_KEY = 'lub_api_queue';

    // Check connection initially
    window.addEventListener('load', () => {
        if (typeof updateOnlineStatus === 'function') updateOnlineStatus();

        window.addEventListener('online', () => {
            if (typeof updateOnlineStatus === 'function') updateOnlineStatus();
            if (typeof processSyncQueue === 'function') processSyncQueue();
        });

        window.addEventListener('offline', () => {
            if (typeof updateOnlineStatus === 'function') updateOnlineStatus();
        });
    });

    function updateOnlineStatus() {
        window.isOfflineMode = !navigator.onLine;

        // 1. Atualiza a Badge de Conectividade na TopBar
        const connBadge = document.getElementById('connectivity-badge');
        if (connBadge) {
            if (navigator.onLine) {
                connBadge.className = 'connectivity-badge online';
                connBadge.innerHTML = '<span class="dot"></span> Online';
            } else {
                connBadge.className = 'connectivity-badge offline';
                connBadge.innerHTML = '<span class="dot"></span> Offline';
            }
        }

        // 2. Atualiza a Barra de Aviso Global
        const indicator = document.getElementById('offline-status');
        if (!indicator) return;

        if (navigator.onLine) {
            indicator.className = 'online';
            indicator.innerHTML = '<i data-lucide="wifi"></i> SYSTEM ONLINE';
            setTimeout(() => { if (indicator.className === 'online') indicator.style.display = 'none'; }, 3000);
        } else {
            indicator.style.display = 'block';
            indicator.className = ''; // Default offline warning
            indicator.innerHTML = '<i data-lucide="wifi-off"></i> OFFLINE MODE (Changes Queued for Sync)';
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    let syncQueueRunning = false;

    async function processSyncQueue() {
        if (syncQueueRunning) return;
        syncQueueRunning = true;

        const queueRaw = (window.LubtekOfflineQueue && window.LubtekOfflineQueue.loadSync())
            ? JSON.stringify(window.LubtekOfflineQueue.loadSync())
            : localStorage.getItem(SYNC_QUEUE_KEY);
        if (!queueRaw) {
            syncQueueRunning = false;
            return;
        }

        const queue = JSON.parse(queueRaw);
        if (queue.length === 0) {
            syncQueueRunning = false;
            return;
        }

        const indicator = document.getElementById('offline-status');
        if (indicator) {
            indicator.style.display = 'block';
            indicator.className = 'syncing';
            indicator.innerHTML = `<i data-lucide="refresh-cw" class="spin"></i> SYNCING ${queue.length} CHANGES...`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        const newQueue = [];
        for (const item of queue) {
            try {
                // Retry API Call
                // console.log(`Syncing: ${item.action}`, item.payload);
                const url = `${API_URL}?action=${item.action}`;
                const opts = item.payload ? {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(item.payload)
                } : { credentials: 'same-origin' };

                const res = await fetch(url, opts);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json().catch(() => null);
                if (!json || json.ok === false) {
                    throw new Error(json && json.error ? json.error : 'API rejected sync item');
                }
                // Success! item removed from queue implicitly
            } catch (e) {
                // Sync Failed - keeping in queue for retry
                newQueue.push(item); // Keep it to try later
            }
        }

        if (window.LubtekOfflineQueue) window.LubtekOfflineQueue.save(newQueue);
        else localStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(newQueue));

        if (newQueue.length === 0) {
            showToast('Sincronização concluída com sucesso!', 'success');
            if (indicator) indicator.style.display = 'none'; // Back to normal
        } else {
            showToast(`Sincronização parcial (${newQueue.length} pendentes). Verifique conexão.`, 'warning');
        }
        syncQueueRunning = false;
    }

    // api() canônica vive em scripts_main.js (__apiMain). Sempre priorizar.
    if (typeof window.__apiMain === 'function') {
        window.api = window.__apiMain;
    } else if (typeof window.api !== 'function' || window.api.__isExtraStub) {
        async function apiExtraFallback(action, payload = null) {
            if (!navigator.onLine) {
                const isRead = action.startsWith('get_') || action.startsWith('calc_');
                if (!isRead) {
                    const queue = (window.LubtekOfflineQueue && window.LubtekOfflineQueue.loadSync()) || JSON.parse(localStorage.getItem(SYNC_QUEUE_KEY) || '[]');
                    queue.push({ action, payload, time: Date.now() });
                    if (window.LubtekOfflineQueue) window.LubtekOfflineQueue.save(queue);
                    else localStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(queue));
                    showToast('Salvo em modo OFFLINE. Será sincronizado quando retomar conexão.', 'info');
                    return { ok: true, offline: true };
                }
            }

            try {
                const url = `${API_URL}?action=${action}`;
                const opts = payload ? {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                } : { credentials: 'same-origin' };

                const res = await fetch(url, opts);
                const contentType = res.headers.get('content-type');
                if (contentType && contentType.indexOf('application/json') === -1) {
                    throw new Error('Server returned non-JSON data');
                }
                if (res.status === 401) {
                    showToast('Sessão expirada. Redirecionando...', 'warning');
                    setTimeout(() => window.location.href = 'login.php', 1500);
                    throw new Error('HTTP 401 Session Expired');
                }
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();
                if (json.ok && json.data && Array.isArray(json.data) && Object.keys(json).length <= 2) {
                    return json.data;
                }
                return json;
            } catch (e) {
                console.error(`API Error (${action}):`, e);
                if (['get_tree', 'get_catalog', 'get_tasks', 'get_market_data'].includes(action)) return [];
                return null;
            }
        }
        apiExtraFallback.__isExtraStub = true;
        window.api = apiExtraFallback;
    }

    function renderChart(id, type, data, extraOptions = {}) {
        const el = document.getElementById('chart-' + id);
        if (!el) return;
        const ctx = el.getContext('2d');

        if (window.kpiChartsExtra && window.kpiChartsExtra[id]) {
            window.kpiChartsExtra[id].destroy();
        }
        if (!window.kpiChartsExtra) window.kpiChartsExtra = {};

        const CommonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 800, easing: 'easeOutQuart' },
            plugins: {
                legend: { 
                    display: type === 'doughnut' && id === 'health',
                    position: 'bottom',
                    labels: { boxWidth: 10, font: { size: 10, weight: '600' } }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: type === 'bar' ? {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(226, 232, 240, 0.4)', drawBorder: false },
                    ticks: { color: '#64748b', font: { family: "'Inter', sans-serif", size: 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { family: "'Inter', sans-serif", size: 10 } }
                }
            } : {}
        };

        window.kpiChartsExtra[id] = new Chart(ctx, {
            type: type,
            data: data,
            options: { ...CommonOptions, ...extraOptions }
        });
    }

    // --- UNIFIED CALCULATOR LOGIC (API) ---
    function runUnifiedCalc() {
        const modelInp = document.getElementById('uni-model');
        const rpmInp = document.getElementById('uni-rpm');
        const resultBox = document.getElementById('uni-result');
        const loading = document.getElementById('uni-loading');

        const model = modelInp.value.trim();
        const rpmInput = rpmInp.value.trim();
        const rpm = parseFloat(rpmInput);

        // Validation
        if (!model) {
            if (resultBox) resultBox.style.display = 'none';
            return;
        }
        if (isNaN(rpm) || rpm <= 0) {
            if (resultBox) resultBox.style.display = 'none';
            return;
        }

        if (loading) loading.style.display = 'block';
        if (resultBox) resultBox.style.display = 'none';

        // 1. Get Geometry & Suggestion using Promise
        api('suggest_lubrication', {
            name: model,
            rpm: rpm
        }).then(res => {
            if (loading) loading.style.display = 'none';

            if (!res || (!res.found && !res.d)) {
                if (resultBox) {
                    resultBox.style.display = 'block';
                    resultBox.innerHTML = `
                            <div class="card" style="border:2px solid var(--danger); background:rgba(239, 68, 68, 0.1); padding:20px; text-align:center;">
                                <h3 style="color:var(--danger);">Modelo nao reconhecido</h3>
                                <p style="color:var(--text-muted); font-size:0.9rem;">Verifique o código ou insira dimensões manualmente no banco de dados.</p>
                            </div>
                         `;
                }
                return;
            }

            if (resultBox) resultBox.style.display = 'block';
            renderUnifiedResults(res, rpm);

        }).catch(e => {
            console.error(e);
            if (loading) loading.style.display = 'none';
            if (resultBox) {
                resultBox.style.display = 'block';
                resultBox.innerHTML = '<div style="color:red; text-align:center;">Erro no cálculo. Tente novamente.</div>';
            }
            showToast('Erro ao processar cálculo.', 'error');
        });
    }

    function renderUnifiedResults(res, rpm) {
        const resultBox = document.getElementById('uni-result');
        const dn = res.dn || 0;
        const isDanger = dn > 500000;
        const isWarning = dn > 300000 && !isDanger;

        // Colors equivalent to Tailwind 300/500 series for High Contrast
        let color = '#10b981'; // Green-500
        let bg = '#86efac';    // Green-300
        let statusText = 'OPERACAO SEGURA';

        if (isDanger) {
            color = '#ef4444'; // Red-500
            bg = '#fca5a5';    // Red-300
            statusText = 'CONDICAO CRITICA';
        } else if (isWarning) {
            color = '#f59e0b'; // Amber-500
            bg = '#fcd34d';    // Amber-300
            statusText = 'ATENCAO (LIMITE)';
        }

        resultBox.innerHTML = `
            <div class="card" style="background:rgba(255,255,255,0.05); border:2px solid ${color}; padding:30px; text-align:center;">
                <h1 style="color:${color}; margin-bottom:10px; font-size:2rem; font-weight:800;">${statusText}</h1>
                <p style="color:var(--text-muted); margin-bottom:30px; font-size:1.1rem;">Análise Técnica para: <span style="color:white; font-weight:bold;">${escapeHtml(res.model)}</span> @ ${rpm} RPM</p>
                
                <div class="grid-3" style="margin-top:20px; gap:20px;">
                    <!-- QUANTO -->
                    <div style="padding:20px; background:${bg}; border-radius:12px; color:#000000; box-shadow:0 4px 6px rgba(0,0,0,0.2);">
                        <div style="font-size:0.8rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px; opacity:0.8; color:#000000;">Quanto? (Volume)</div>
                        <div style="font-size:2.5rem; font-weight:800; color:#000000;">${res.grams}<span style="font-size:1.2rem; font-weight:600; margin-left:5px;">g</span></div>
                    </div>
                    <!-- QUANDO -->
                    <div style="padding:20px; background:${bg}; border-radius:12px; color:#000000; box-shadow:0 4px 6px rgba(0,0,0,0.2);">
                        <div style="font-size:0.8rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px; opacity:0.8; color:#000000;">Quando? (Intervalo)</div>
                        <div style="font-size:2.5rem; font-weight:800; color:#000000;">${res.days}<span style="font-size:1.2rem; font-weight:600; margin-left:5px;">Dias</span></div>
                    </div>
                    <!-- FATOR DN -->
                    <div style="padding:20px; background:${bg}; border-radius:12px; color:#000000; box-shadow:0 4px 6px rgba(0,0,0,0.2);">
                        <div style="font-size:0.8rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px; opacity:0.8; color:#000000;">Fator DN</div>
                        <div style="font-size:2.5rem; font-weight:800; color:#000000;">${dn}</div>
                        <div style="font-size:0.8rem; font-weight:800; color:#000000; margin-top:5px;">${res.freq_label || ''}</div>
                    </div>
                </div>
                
                <!-- ACTION BUTTONS -->
                <div style="margin-top:30px; display:flex; justify-content:center; gap:15px;">
                    <button class="btn" style="background:${color}; color:white;" onclick="downloadTechnicalReport()">
                        <i data-lucide="file-check-2"></i> Download Laudo Técnico
                    </button>
                    <button class="btn btn-outline" style="color:var(--text-main); border-color:var(--text-main);" onclick="document.getElementById('slr-details-uni').style.display = (document.getElementById('slr-details-uni').style.display == 'none' ? 'block' : 'none')">
                        Ver Ficha Técnica
                    </button>
                </div>

                <!-- HIDDEN DETAILS -->
                <div id="slr-details-uni" style="display:none; margin-top:20px; text-align:center; font-size:0.9rem; color:var(--text-muted); background:rgba(0,0,0,0.2); padding:15px; border-radius:10px;">
                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px;">
                        <div>Diâmetro Int (d): <strong>${res.d}</strong> mm</div>
                        <div>Diâmetro Ext (D): <strong>${res.D}</strong> mm</div>
                        <div>Largura (B): <strong>${res.B}</strong> mm</div>
                        <div>Diametro Medio (dm): <strong>${res.dm}</strong> mm</div>
                    </div>
                </div>
            </div>
            `;
        // Internal reference for reports
        window.lastSmartCalcResult = res;
        lucide.createIcons();
    }

    async function magicCalc() {
        const bD = document.getElementById('af-bushing_d').value;
        const bL = document.getElementById('af-bushing_l').value;
        const bK = document.getElementById('af-bushing_k').value;
        const assetContextId = (typeof editingNode !== 'undefined' && editingNode) ? editingNode.id : null;

        if (bD && bL) {
            // BUSHING MODE
            try {
                const res = await api('calc_bushing', { d: bD, l: bL, k: bK, asset_id: assetContextId });
                if (res) {
                    document.getElementById('af-qtd_material').value = res.qty_cm3_monthly;
                    document.getElementById('af-unid_material').value = 'cm3';
                    showToast(`Engenharia de Buchas: ${res.qty_cm3_monthly} cm3/mês calculado.`, 'success');
                    updateLocalNode();
                }
            } catch (e) {
                showToast('Erro ao calcular engenharia de buchas.', 'error');
            }
        } else {
            // BEARING MODE (Smart Search based on tag or input)
            const assetName = document.getElementById('af-name').value;
            const rpmVal = document.getElementById('af-rpm') ? document.getElementById('af-rpm').value : null;
            const realRpm = (rpmVal && !isNaN(rpmVal)) ? parseFloat(rpmVal) : 1750;

            const res = await api('suggest_lubrication', { name: assetName, rpm: realRpm, asset_id: assetContextId });
            if (res && res.found) {
                const qtyEl = document.getElementById('af-qtd_material');
                const freqEl = document.getElementById('af-periodo');
                const hasQty = qtyEl && String(qtyEl.value || '').trim() !== '';
                if (hasQty && !confirm('Sugestão SKF: ' + res.grams + ' g / ' + (res.freq_label || '') + '.\nO plano atual NÃO será sobrescrito sem confirmação.\nAplicar a sugestão neste ponto?')) {
                    showToast('Sugestão mantida só na engenharia. Plano do ponto inalterado.', 'info');
                    return;
                }
                if (qtyEl) qtyEl.value = res.grams;
                document.getElementById('af-unid_material').value = 'g';
                showToast('Sugestão SKF: ' + res.grams + ' g. Confira antes de salvar o ativo.', 'info');
                updateLocalNode();
            } else {
                showToast('Dados insuficientes para cálculo automático. Preencha dimensões ou nome do rolamento.', 'warning');
            }
        }
    }

    async function downloadTechnicalReport() {
        if (!window.lastSmartCalcResult) return showToast('Realize um cálculo primeiro.', 'warning');

        const res = window.lastSmartCalcResult;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Styles
        const primaryColor = [14, 165, 233]; // #0ea5e9
        const dangerColor = [239, 68, 68];   // #ef4444
        const blackColor = [0, 0, 0];

        const logoBase64 = "data:image/jpeg;base64,..."; // PLACEHOLDER IF NEEDED OR KEEP IT

        // For now, simple text fallback if logo is too big for this script update
        // or we assume it works as is. The user didn't complain about PDF.

        doc.setFontSize(16);
        doc.setTextColor(0, 0, 0);
        doc.setFont("helvetica", "bold");
        doc.text("LAUDO TÉCNICO DE ENGENHARIA", 105, 25, { align: 'center' });

        doc.setFontSize(10);
        doc.setFont("helvetica", "normal");
        const projectName = (typeof editingNode !== 'undefined' && editingNode) ? editingNode.nome.toUpperCase() : "GERAL";
        doc.text(`PROJETO: ${projectName}`, 105, 32, { align: 'center' });

        doc.setFontSize(9);
        doc.text(`EMISSAO: ${new Date().toLocaleDateString()}`, 190, 25, { align: 'right' });

        // Divider
        doc.setDrawColor(0, 0, 0);
        doc.setLineWidth(0.5);
        doc.line(20, 48, 190, 48);

        // Asset Info
        doc.setFontSize(12);
        doc.setFont("helvetica", "bold");
        doc.text(`Rolamento Analisado: ${res.model || 'N/A'}`, 20, 60);

        // Dimensions Box
        doc.setFillColor(245, 245, 245);
        doc.rect(20, 65, 170, 25, 'F');
        doc.setFontSize(10);
        doc.setFont("helvetica", "normal");
        doc.setTextColor(0, 0, 0); // Black text
        doc.text("Dimensões Identificadas:", 25, 72);
        doc.text(`d(Int): ${res.d} mm | D(Ext): ${res.D} mm | B(Largura): ${res.B} mm`, 25, 82);

        // RESULTS
        doc.setFont("helvetica", "bold");
        doc.text("PARÓMETROS CALCULADOS:", 20, 130);

        doc.setFont("helvetica", "normal");
        doc.text(`Volume de Graxa(Relubrificação): `, 20, 140);
        doc.setFont("helvetica", "bold");
        doc.text(`${res.grams} GRAMAS`, 120, 140);

        doc.setFont("helvetica", "normal");
        doc.text(`INTERVALO(FREQUÊNCIA): `, 20, 150);
        doc.setFont("helvetica", "bold");
        doc.text(`${res.days} DIAS`, 120, 150);

        doc.setFont("helvetica", "normal");
        doc.text(`Fator Velocidade(DN): `, 20, 160);

        // DN Logic for Highlight
        const dn = res.dn || 0;
        let statusMsg = "Operação Normal";
        if (dn > 300000) {
            doc.setTextColor(...dangerColor); // Red
            statusMsg = "ALERTA: CRÍTICO (Limite de Graxa)";
            if (dn > 500000) statusMsg = "PERIGO: SUBSTITUIR POR ÓLEO";
        }

        doc.setFont("helvetica", "bold");
        doc.text(`${dn}(${statusMsg})`, 120, 160);
        doc.setTextColor(...blackColor); // Reset

        // Recommendation Box
        if (dn > 300000) {
            doc.setDrawColor(...dangerColor);
            doc.setLineWidth(1);
            doc.rect(20, 175, 170, 40);

            doc.setTextColor(...dangerColor);
            doc.setFontSize(10);
            doc.text("RECOMENDACAO TECNICA:", 25, 185);

            const recText = (dn > 500000)
                ? "A velocidade tangencial (Fator DN) excede o limite de segurança da maioria das graxas minerais. Há risco iminente de ruptura do filme lubrificante e superaquecimento. Recomenda-se migração para Óleo Sintético com viscosidade adequada ou sistema de névoa (Oil Mist)."
                : "O equipamento opera próximo ao limite de rotação para graxas de lítio padrão. Recomenda-se monitoramento de temperatura ou uso de graxas de complexo de lítio/sintéticas de alto desempenho.";

            const splitText = doc.splitTextToSize(recText, 160);
            doc.text(splitText, 25, 195);
            doc.setTextColor(...blackColor);
        }

        // FOOTER / SIGNATURE
        doc.setDrawColor(0);
        doc.line(20, 260, 90, 260);
        doc.line(110, 260, 180, 260);
        doc.setFontSize(8);
        doc.text("Engenheiro Responsável", 35, 265);
        doc.text("Gerente de Manutenção", 125, 265);

        doc.save(`Laudo_Lubrificacao_${res.model || 'Item'}.pdf`);
        showToast("Laudo Gerado com Sucesso!", "success");
    }
    // --- VOICE DICTATION SYSTEM (Hands-Free Mode) ---
    function initVoiceInput(targetId) {
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            showToast('Seu navegador nao suporta reconhecimento de voz.', 'warning');
            return;
        }

        const input = document.getElementById(targetId);
        if (!input) return;

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();

        recognition.lang = 'pt-BR';
        recognition.continuous = false;
        recognition.interimResults = false;

        recognition.onstart = function () {
            showToast('Ouvindo... Pode falar!', 'info');
            const btn = input.parentElement.querySelector('.mic-btn');
            if (btn) btn.classList.add('pulse-mic');
        };

        recognition.onend = function () {
            const btn = input.parentElement.querySelector('.mic-btn');
            if (btn) btn.classList.remove('pulse-mic');
        };

        recognition.onresult = function (event) {
            const transcript = event.results[0][0].transcript;

            // Smart Append: If input has text, add space + text
            if (input.value.trim() !== "") {
                input.value += " " + transcript;
            } else {
                input.value = transcript;
            }

            // Trigger events
            input.dispatchEvent(new Event('input'));
            input.dispatchEvent(new Event('change'));
            showToast('Texto capturado!', 'success');
        };

        recognition.onerror = function (event) {
            console.error(event.error);
            showToast('Erro ao capturar áudio.', 'error');
            const btn = input.parentElement.querySelector('.mic-btn');
            if (btn) btn.classList.remove('pulse-mic');
        };

        recognition.start();
    }
    // --- SYNTH CALCULATOR LOGIC (Specific Tabs) ---
    // As implementações canônicas de runSynthRolCalc/runSynthViscCalc/runSynthBucCalc/runSynthDNCalc
    // vivem em scripts_main.js (já usam os campos corretos da API e escapeHtml contra XSS).
    // Não redeclarar aqui: como scripts_extra.js carrega DEPOIS de scripts_main.js, uma redeclaração
    // com o mesmo nome sobrescreve (via hoisting) a função global correta e quebra a calculadora
    // (era exatamente o bug encontrado: runSynthRolCalc virava um no-op e as demais usavam nomes de
    // campo que não existem na resposta da API). Sempre priorizar scripts_main.js, como já é feito
    // para a função api() logo abaixo.

    window.openImageModal = openImageModal;
    window.switchCalc = switchCalc;
    window.loadKpiView = loadKpiView;
    window.loadRoutes = loadRoutes;
    window.renderRoutes = renderRoutes;
    window.openRouteAlertModal = openRouteAlertModal;
    window.closeRouteAlertModal = closeRouteAlertModal;
    window.submitRouteAlert = submitRouteAlert;
    window.handleAnomalyPhoto = handleAnomalyPhoto;
    window.triggerExcelImport = triggerExcelImport;
    // Calculadoras Synth (runSynthRolCalc/Visc/Buc/DN): expostas nativamente por scripts_main.js.
