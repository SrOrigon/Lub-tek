// ==========================================
// COMPONENT AUTO-SYNC SYSTEM
// Real-time synchronization for all UI components
// ==========================================

/**
 * DASHBOARD SYNC
 * Auto-updates dashboard stats, KPIs, and charts
 */
class DashboardSync {
    constructor() {
        this.isActive = false;
    }

    start() {
        if (this.isActive) return;
        this.isActive = true;

        // Register auto-refresh for dashboard stats
        window.AutoRefresh.register('dashboard:stats', async () => {
            if (document.getElementById('view-dash')?.classList.contains('active')) {
                await this.refreshStats();
            }
        }, 15000, true); // 15 seconds

        // Register auto-refresh for KPIs
        window.AutoRefresh.register('dashboard:kpis', async () => {
            if (document.getElementById('view-dash')?.classList.contains('active')) {
                await this.refreshKPIs();
            }
        }, 60000, false); // 60 seconds

        // Listen for task changes
        window.AppEvents.on('task:created', () => this.refreshStats());
        window.AppEvents.on('task:completed', () => this.refreshStats());
        window.AppEvents.on('task:deleted', () => this.refreshStats());

        // console.log('✅ Dashboard Auto-Sync active');
    }

    async refreshStats() {
        try {
            // Load dashboard stats
            const d = await api('get_dash_stats');
            if (d) {
                // Update assets count
                if (document.getElementById('dash-assets')) {
                    const current = parseInt(document.getElementById('dash-assets').innerText) || 0;
                    if (current !== d.assets) {
                        this.animateNumber('dash-assets', current, d.assets || 0);
                    }
                }

                // Update items count
                if (document.getElementById('dash-items')) {
                    const current = parseInt(document.getElementById('dash-items').innerText) || 0;
                    if (current !== d.items) {
                        this.animateNumber('dash-items', current, d.items || 0);
                    }
                }

                // Update state
                window.AppState.set('dashStats', d);
            }

            // Load market stats
            const ms = await api('get_market_stats');
            if (ms) {
                if (document.getElementById('market-global-savings')) {
                    document.getElementById('market-global-savings').innerText =
                        (Number(ms.global_savings) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                }
                if (document.getElementById('market-avg-delivery')) {
                    document.getElementById('market-avg-delivery').innerText = ms.avg_delivery ?? '--';
                }

                window.AppState.set('marketStats', ms);
            }

            // Load and update tasks
            const taskData = await api('get_tasks');
            // API returns {ok: true, data: [...]} or just array for error fallback
            const taskArray = taskData?.data ?? taskData;
            if (taskArray && Array.isArray(taskArray)) {
                // Check if tasks changed
                if (window.ChangeDetector.hasChanged('tasks', taskArray)) {
                    window.AppState.set('tasks', taskArray);

                    // Legacy compatibility - update global tasks variable if exists
                    if (typeof window.tasks !== 'undefined') {
                        window.tasks = taskArray;
                    }

                    // Atualizar UI de ordens de servico
                    if (typeof renderOSTable === 'function') {
                        renderOSTable();
                    } else if (typeof loadDash === 'function') {
                        await loadDash();
                    } else if (typeof renderTasks === 'function') {
                        renderTasks();
                    }

                    window.AppEvents.emit('tasks:updated', taskArray);
                }
            }


        } catch (error) {
            // Error refreshing dashboard stats - silenced
        }
    }

    async refreshKPIs() {
        try {
            // IA Sync: Refresh Dashboard immediately if visible
            const kpis = await api('get_kpis');
            if (kpis && window.ChangeDetector.hasChanged('kpis', kpis)) {
                window.AppState.set('kpis', kpis);
                window.AppEvents.emit('kpis:updated', kpis);
            }
        } catch (error) {
            // Error refreshing KPIs - silenced
        }
    }

    animateNumber(elementId, from, to, duration = 500) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const start = Date.now();
        const range = to - from;

        const animate = () => {
            const now = Date.now();
            const progress = Math.min((now - start) / duration, 1);
            const current = Math.floor(from + (range * progress));

            element.innerText = current;

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };

        requestAnimationFrame(animate);
    }

    stop() {
        window.AutoRefresh.unregister('dashboard:stats');
        window.AutoRefresh.unregister('dashboard:kpis');
        this.isActive = false;
    }
}

/**
 * ASSET TREE SYNC
 * Auto-reloads asset tree when changes occur
 */
class AssetTreeSync {
    constructor() {
        this.isActive = false;
    }

    start() {
        if (this.isActive) return;
        this.isActive = true;

        // Auto-refresh desativado na arvore — usuario usa botao "Atualizar"
        // (evita piscar e ERR_INSUFFICIENT_RESOURCES no navegador)

        window.AppEvents.on('asset:created', () => this.refreshTree(true));
        window.AppEvents.on('asset:updated', () => this.refreshTree(true));
        window.AppEvents.on('asset:deleted', () => this.refreshTree(true));
    }

    async refreshTree(force = false) {
        if (!document.getElementById('view-assets')?.classList.contains('active')) return;

        try {
            const response = await api('get_tree');
            const tree = Array.isArray(response) ? response : (response?.data || null);
            if (!tree || !Array.isArray(tree)) return;

            if (!force && !window.ChangeDetector.hasChanged('assetTree', tree)) return;

            window.AppState.set('assets', tree);
            if (typeof window.globalTree !== 'undefined') window.globalTree = tree;

            if (typeof applyTreeData === 'function') {
                applyTreeData(tree);
                const sel = window.editingNode?.id;
                if (sel && typeof selectNode === 'function') selectNode(sel, { soft: true });
            } else if (typeof loadTree === 'function') {
                await loadTree(true);
            }

            window.AppEvents.emit('assets:updated', tree);
        } catch (error) {
            // Mantem arvore atual em caso de falha
        }
    }

    stop() {
        this.isActive = false;
    }
}

/**
 * MARKETPLACE SYNC
 * Updates market prices and offers in real-time
 */
class MarketplaceSync {
    constructor() {
        this.isActive = false;
        this.currentProduct = null;
    }

    start() {
        if (this.isActive) return;
        this.isActive = true;

        // Auto-refresh market data every 20s
        window.AutoRefresh.register('marketplace:offers', async () => {
            if (this.currentProduct) {
                await this.refreshOffers(this.currentProduct);
            }
        }, 20000, false);

        // Listen for product selection changes
        window.AppEvents.on('product:selected', (product) => {
            this.currentProduct = product;
            this.refreshOffers(product);
        });

        // console.log('✅ Marketplace Auto-Sync active');
    }

    async refreshOffers(product) {
        if (!product) return;

        try {
            const offers = await api('get_market_data', { cat_id: product });

            // Se o produto selecionado mudou enquanto a requisição estava em andamento,
            // descarta esta resposta desatualizada para não sobrescrever a tela do produto atual.
            if (this.currentProduct !== product) return;

            if (offers && window.ChangeDetector.hasChanged(`market:${product}`, offers)) {
                window.AppState.set('marketOffers', offers);

                // Update UI if function exists
                if (typeof renderMarketOffers === 'function') {
                    renderMarketOffers(offers);
                }

                window.AppEvents.emit('marketplace:updated', offers);
            }
        } catch (error) {
            // Error refreshing marketplace offers - silenced
        }
    }

    stop() {
        window.AutoRefresh.unregister('marketplace:offers');
        this.isActive = false;
    }
}

/**
 * CATALOG SYNC
 * Auto-updates catalog items
 */
class CatalogSync {
    constructor() {
        this.isActive = false;
    }

    start() {
        if (this.isActive) return;
        this.isActive = true;

        // Auto-refresh catalog every 45s
        window.AutoRefresh.register('catalog:items', async () => {
            if (document.getElementById('view-catalog')?.classList.contains('active')) {
                await this.refreshCatalog();
            }
        }, 45000, false);

        // Listen for catalog changes
        window.AppEvents.on('catalog:created', () => this.refreshCatalog());
        window.AppEvents.on('catalog:updated', () => this.refreshCatalog());
        window.AppEvents.on('catalog:deleted', () => this.refreshCatalog());

        // console.log('✅ Catalog Auto-Sync active');
    }

    async refreshCatalog() {
        try {
            const catalog = await api('get_catalog');
            if (catalog && window.ChangeDetector.hasChanged('catalog', catalog)) {
                window.AppState.set('catalog', catalog);

                if (typeof loadCatalog === 'function') {
                    loadCatalog();
                }

                window.AppEvents.emit('catalog:updated', catalog);
            }
        } catch (error) {
            // Error refreshing catalog - silenced
        }
    }

    stop() {
        window.AutoRefresh.unregister('catalog:items');
        this.isActive = false;
    }
}

/**
 * GLOBAL SYNC MANAGER
 * Coordinates all sync systems
 */
class GlobalSyncManager {
    constructor() {
        this.dashboard = new DashboardSync();
        this.assetTree = new AssetTreeSync();
        this.marketplace = new MarketplaceSync();
        this.catalog = new CatalogSync();
    }

    startAll() {
        this.dashboard.start();
        this.assetTree.start();
        this.marketplace.start();
        this.catalog.start();

        // console.log('🌐 All component auto-sync systems activated');
        window.AppEvents.emit('sync:started');
    }

    stopAll() {
        this.dashboard.stop();
        this.assetTree.stop();
        this.marketplace.stop();
        this.catalog.stop();

        // console.log('🛑 All component auto-sync systems stopped');
        window.AppEvents.emit('sync:stopped');
    }

    getStatus() {
        return {
            dashboard: this.dashboard.isActive,
            assetTree: this.assetTree.isActive,
            marketplace: this.marketplace.isActive,
            catalog: this.catalog.isActive,
            refreshers: window.AutoRefresh.getStatus()
        };
    }
}

// ==========================================
// INITIALIZE GLOBAL SYNC
// ==========================================

window.GlobalSync = new GlobalSyncManager();

// Sync leve: so dashboard/catalog — arvore de ativos e manual
window.AppEvents.once('app:ready', () => {
    setTimeout(() => {
        window.GlobalSync.dashboard.start();
        window.GlobalSync.catalog.start();
        window.GlobalSync.assetTree.start();
    }, 3000);
});

// Interceptors de save (registrar uma unica vez)
window.AppEvents.once('app:ready', () => {
    const originalSaveToCloudSingle = window.saveToCloudSingle;
    if (typeof originalSaveToCloudSingle === 'function') {
        window.saveToCloudSingle = async function () {
            const result = await originalSaveToCloudSingle.apply(this, arguments);
            if (window.GlobalSync?.assetTree?.isActive) {
                window.GlobalSync.assetTree.refreshTree(true);
            }
            return result;
        };
    }

    const originalSaveNewTask = window.saveNewTask;
    if (typeof originalSaveNewTask === 'function') {
        window.saveNewTask = async function () {
            const result = await originalSaveNewTask.apply(this, arguments);
            window.AppEvents.emit('task:created');
            return result;
        };
    }
});

// Legacy interceptors removed — now registered on app:ready above

// console.log('✅ Component Auto-Sync System loaded');
