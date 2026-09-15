<div id="view-catalog" class="view-container active view-block catalog-view"
    style="padding:15px; background:#f8fafc;">
    <div class="flex-between catalog-header" style="margin-bottom:20px;">
        <div>
            <h1 style="margin:0; font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px;">Inventário & Especificações</h1>
            <p style="color:var(--text-muted); margin-top:5px; font-size: 0.95rem;">Gestão centralizada de componentes, rolamentos e consumíveis.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <button class="btn" onclick="addNewItem()" style="padding: 12px 25px; border-radius: 10px; font-weight: 700; background: var(--primary);">
                <i data-lucide="plus-circle" style="margin-right: 8px;"></i> CADASTRAR NOVO ITEM
            </button>
        </div>
    </div>

    <div class="catalog-layout" style="display:flex; gap:25px; flex:1; min-height:0;">
        <!-- LEFT LIST PANEL -->
        <div class="card catalog-list-panel" style="width:420px; padding:0; display:flex; flex-direction:column; overflow:hidden; border:1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.04);">
            <div style="padding:20px; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.025);">
                <div class="flex-between" style="margin-bottom:15px; align-items:center;">
                    <h3 style="margin:0; color:var(--text-main); font-size:0.9rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Lista de Insumos</h3>
                    <button class="btn-sm btn-outline" style="background:white; padding:6px 12px; font-size:0.75rem; border-radius: 8px; font-weight: 700;"
                        onclick="exportCatalogExcel()" title="Exportar Lista">
                        <i data-lucide="file-spreadsheet" style="width:14px; margin-right: 6px;"></i> EXCEL
                    </button>
                </div>
                <div style="position:relative; margin-bottom: 12px;">
                   <i data-lucide="search" style="position:absolute; left:12px; top:12px; width:16px; color:var(--text-muted); opacity: 0.5;"></i>
                   <input id="cat-search" placeholder="Buscar por nome, código..."
                    aria-label="Buscar item no inventário por nome ou código"
                    oninput="filterCatalog()" style="width:100%; padding:10px 10px 10px 38px; background:white; border-radius: 10px; border:1px solid var(--border); font-size: 0.9rem;">
                </div>
                
                <div class="flex-between" style="gap: 10px;">
                    <select id="cat-sort" onchange="filterCatalog()" style="flex:1; font-size:0.8rem; padding:10px; border-radius: 8px; border:1px solid var(--border); background: white;">
                        <option value="az">A-Z</option>
                        <option value="za">Z-A</option>
                        <option value="stock_desc">Maior Estoque</option>
                        <option value="stock_asc">Menor Estoque</option>
                    </select>
                    <select id="cat-filter-type" onchange="filterCatalog()"
                        style="flex:1; font-size:0.8rem; padding:10px; border-radius: 8px; border:1px solid var(--border); background: white;">
                        <option value="all">Categorias</option>
                        <option value="Rolamento">Rolamentos</option>
                        <option value="Lubrificante">Lubrificantes</option>
                        <option value="Motor">Motores</option>
                        <option value="Componente">Componentes</option>
                    </select>
                </div>
            </div>
            <div id="cat-grid" style="flex:1; overflow-y:auto; padding:10px; -webkit-overflow-scrolling:touch;">
                <!-- JS List -->
            </div>
        </div>

        <!-- RIGHT DETAIL PANEL -->
        <div class="card catalog-detail-panel" style="flex:1; padding:0; display:flex; flex-direction:column; overflow:hidden; border:1px solid #e2e8f0; background: radial-gradient(circle at center, #ffffff 0%, #f9fafb 100%);">
            <div id="cat-empty"
                style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text-muted); text-align: center; padding: 40px;">
                <div style="padding:30px; border-radius:50%; background:rgba(2,132,199,0.03); margin-bottom:20px; border:1px solid rgba(2,132,199,0.08);">
                    <i data-lucide="package-search" style="width:80px; height:80px; opacity:0.3; color:var(--primary);"></i>
                </div>
                <h2 style="color:var(--text-main); font-weight: 800; font-size: 1.62rem; margin-bottom: 8px;">Ficha Técnica de Insumos</h2>
                <p style="max-width:350px; line-height: 1.6; font-size: 0.95rem;">Selecione um componente no catálogo para visualizar especificações, níveis de estoque e fornecedores.</p>
            </div>

            <div id="cat-detail-container" style="display:none; flex:1; overflow-y:auto; padding:20px; -webkit-overflow-scrolling:touch;">
                <!-- JS Editor -->
            </div>
        </div>
    </div>
</div>
