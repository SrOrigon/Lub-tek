// ==========================================
// MARKETPLACE INTELLIGENCE FUNCTIONS
// ==========================================
// Todos os indicadores desta aba são derivados das ofertas reais cadastradas
// em mercado_ofertas (get_market_data). Nada é estimado com valores fixos.

function marketCurrentOffers() {
    const offers = window.AppState ? window.AppState.get('marketOffers') : null;
    return Array.isArray(offers) ? offers : [];
}

function marketBRL(v) {
    return (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function marketEsc(v) {
    if (typeof escapeHtml === 'function') return escapeHtml(v == null ? '' : String(v));
    return String(v == null ? '' : v).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function marketMaterialKey() {
    const material = (document.getElementById('af-material')?.value || '').trim().toLowerCase();
    return material ? 'lubtek_market_alert_' + material : null;
}

function marketParseDeliveryDays(text) {
    if (!text) return null;
    const m = String(text).toLowerCase().match(/(\d+(?:[.,]\d+)?)\s*(h|hora|horas|d|dia|dias|sem|semana|semanas)?/);
    if (!m) return null;
    const n = parseFloat(m[1].replace(',', '.'));
    if (!isFinite(n)) return null;
    const unit = m[2] || 'd';
    if (unit.startsWith('h')) return n / 24;
    if (unit.startsWith('sem')) return n * 7;
    return n;
}

function marketPriceStats(offers) {
    const prices = offers.map(o => Number(o.price) || 0).filter(p => p > 0);
    if (!prices.length) return null;
    const best = Math.min(...prices);
    const worst = Math.max(...prices);
    const avg = prices.reduce((a, b) => a + b, 0) / prices.length;
    return { best, worst, avg, count: prices.length };
}

// Simulador de compra: preço unitário da melhor oferta real × quantidade.
function calculateBulkPrice() {
    const qty = Math.max(1, parseInt(document.getElementById('bulk-qty')?.value || 1, 10) || 1);
    const stats = marketPriceStats(marketCurrentOffers());
    const unitEl = document.getElementById('bulk-unit-price');
    const totalEl = document.getElementById('bulk-total-price');

    if (!stats) {
        if (unitEl) unitEl.textContent = '—';
        if (totalEl) totalEl.textContent = '—';
        return;
    }
    if (unitEl) unitEl.textContent = marketBRL(stats.best);
    if (totalEl) totalEl.textContent = marketBRL(stats.best * qty);
}

// Load market offers when material changes
let marketOffersRequestId = 0;
async function loadMarketOffersForProduct() {
    const material = document.getElementById('af-material')?.value;
    if (!material || material.length < 3) {
        if (document.getElementById('market-empty-state')) {
            document.getElementById('market-empty-state').style.display = 'block';
        }
        return;
    }

    // Evita que uma resposta antiga (ex: usuário digitou/trocou o material rapidamente)
    // sobrescreva a tela com dados de um material que não é mais o selecionado.
    const requestId = ++marketOffersRequestId;

    try {
        let catId = material;
        if (typeof cachedCatalog !== 'undefined' && cachedCatalog.length > 0) {
            const item = cachedCatalog.find(c => c.nome && c.nome.toLowerCase() === material.toLowerCase())
                || cachedCatalog.find(c => c.nome && c.nome.toLowerCase().includes(material.toLowerCase()));
            if (item) catId = item.id;
        } else if (typeof loadCatalog === 'function') {
            await loadCatalog();
            const item = (typeof cachedCatalog !== 'undefined' ? cachedCatalog : [])
                .find(c => c.nome && c.nome.toLowerCase().includes(material.toLowerCase()));
            if (item) catId = item.id;
        }

        const res = await api('get_market_data', { cat_id: catId });
        const offers = Array.isArray(res) ? res : (res && Array.isArray(res.data) ? res.data : []);

        if (requestId !== marketOffersRequestId) return;

        if (window.AppState) {
            window.AppState.set('marketOffers', offers);
        }

        renderMarketOffers(offers);
        updateMarketSavings(offers);
        renderPriceTrendChart();
        calculateBulkPrice();
    } catch (e) {
        console.error('Error loading market offers:', e);
        if (typeof showToast === 'function') showToast('Não foi possível carregar as ofertas de mercado.', 'warning');
    }
}

function marketGetFavorites() {
    try { return JSON.parse(localStorage.getItem('lubtek_market_favorites') || '[]'); } catch (e) { return []; }
}

function marketToggleFavorite(name) {
    const favs = marketGetFavorites();
    const idx = favs.indexOf(name);
    if (idx >= 0) favs.splice(idx, 1); else favs.push(name);
    try { localStorage.setItem('lubtek_market_favorites', JSON.stringify(favs)); } catch (e) { }
    return idx < 0;
}

// Render market offer cards
function renderMarketOffers(offers) {
    const container = document.getElementById('tab-market-offers-list');
    if (!container) return;
    offers = Array.isArray(offers) ? offers : [];

    if (offers.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <i data-lucide="alert-circle" style="width:48px; opacity:0.3;"></i>
                <p style="margin-top:10px;">Nenhuma oferta cadastrada para este lubrificante.</p>
                <p style="font-size:0.8rem;">Cadastre fornecedores no Almoxarifado para comparar preços.</p>
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }

    if (document.getElementById('market-empty-state')) {
        document.getElementById('market-empty-state').style.display = 'none';
    }

    // Preço base (1ª oferta, já ordenada por preço ASC pela API) usado para o comparativo "+R$ x,xx"
    const basePrice = Number(offers[0] && offers[0].price) || 0;
    const stats = marketPriceStats(offers);
    const favs = marketGetFavorites();

    container.innerHTML = offers.map((offer, index) => {
        const rankBadge = index === 0 ? '🏆 MELHOR' : (index + 1) + 'º';
        const vendorName = marketEsc(offer.vendor_name);
        const delivery = marketEsc(offer.delivery || 'N/A');
        const rating = offer.rating != null ? offer.rating : '-';
        const price = Number(offer.price) || 0;
        const isFav = favs.indexOf(offer.vendor_name || '') >= 0;
        let compare = '';
        if (index === 0 && stats && stats.count > 1 && stats.worst > price) {
            const pct = Math.round((1 - price / stats.worst) * 100);
            compare = `<div style="background:#dcfce7; color:#16a34a; padding:4px 10px; border-radius:6px; font-size:0.7rem; font-weight:700;">-${pct}% vs. mais cara</div>`;
        } else if (index > 0) {
            compare = `<div style="font-size:0.75rem; color:#64748b;">+${marketBRL(price - basePrice)}</div>`;
        }

        return `
            <div class="market-offer-card" style="background:white; border:2px solid ${index === 0 ? '#0ea5e9' : '#e2e8f0'}; border-radius:12px; padding:18px; transition:all 0.2s;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                    <div>
                        <div style="font-weight:800; font-size:0.95rem; color:#1e293b; margin-bottom:4px;">${vendorName}</div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="color:#fbbf24; font-size:0.9rem;">★${marketEsc(String(rating))}</span>
                            ${offer.verified ? '<span style="background:#10b981; color:white; font-size:0.6rem; padding:2px 6px; border-radius:4px; font-weight:700;">✓ VERIFICADO</span>' : ''}
                        </div>
                    </div>
                    <span style="background:${index === 0 ? '#0ea5e9' : '#f1f5f9'}; color:${index === 0 ? 'white' : '#64748b'}; font-size:0.65rem; padding:4px 8px; border-radius:6px; font-weight:800;">${rankBadge}</span>
                </div>

                <div style="margin:15px 0; padding:12px; background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius:8px;">
                    <div style="font-size:0.7rem; color:#64748b; margin-bottom:4px;">PREÇO UNITÁRIO</div>
                    <div style="font-size:1.8rem; font-weight:900; color:#0ea5e9;">${marketBRL(price)}</div>
                    ${index === 0 ? '<div style="font-size:0.7rem; color:#10b981; font-weight:700; margin-top:4px;">💰 Melhor preço cadastrado</div>' : ''}
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #e2e8f0;">
                    <div>
                        <div style="font-size:0.7rem; color:#64748b;">Entrega</div>
                        <div style="font-weight:700; color:#1e293b; font-size:0.85rem;">${delivery}</div>
                    </div>
                    ${compare}
                </div>

                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-sm market-buy-btn" data-vendor-url="${escapeAttr(offer.vendor_url || '')}" style="flex:1; background:${index === 0 ? '#0ea5e9' : '#64748b'}; color:white; border:none; padding:10px; border-radius:6px; font-weight:700; font-size:0.75rem;">
                        <i data-lucide="external-link" style="width:12px;"></i> COMPRAR
                    </button>
                    <button type="button" class="btn btn-outline btn-sm market-fav-btn" data-vendor-name="${escapeAttr(offer.vendor_name || '')}" style="padding:10px; ${isFav ? 'color:#ef4444; border-color:#ef4444;' : ''}" title="${isFav ? 'Remover dos favoritos' : 'Favoritar'}">
                        <i data-lucide="heart" style="width:14px;"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    // Wire up buttons via listeners (em vez de onclick inline) para evitar que
    // vendor_url/vendor_name vindos do banco quebrem o atributo HTML ou injetem JS.
    container.querySelectorAll('.market-buy-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const raw = btn.dataset.vendorUrl || '';
            const url = (typeof safeUrl === 'function') ? safeUrl(raw) : raw;
            if (url && !/^(javascript|data|vbscript):/i.test(url)) {
                window.open(url, '_blank', 'noopener,noreferrer');
            } else if (typeof showToast === 'function') {
                showToast('Este fornecedor não tem link de compra cadastrado.', 'warning');
            }
        });
    });
    container.querySelectorAll('.market-fav-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const name = btn.dataset.vendorName || '';
            const added = marketToggleFavorite(name);
            btn.style.color = added ? '#ef4444' : '';
            btn.style.borderColor = added ? '#ef4444' : '';
            btn.title = added ? 'Remover dos favoritos' : 'Favoritar';
            if (typeof showToast === 'function') {
                showToast(added ? `Fornecedor ${name} favoritado.` : `Fornecedor ${name} removido dos favoritos.`, 'success');
            }
        });
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Painéis de resumo: economia, cadeia de fornecimento, entrega, alertas e ranking.
function updateMarketSavings(offers) {
    offers = Array.isArray(offers) ? offers : [];
    const stats = marketPriceStats(offers);

    const totalEl = document.getElementById('market-savings-total');
    const yearEl = document.getElementById('market-savings-year');
    if (totalEl) totalEl.textContent = stats ? (stats.worst - stats.best).toFixed(2).replace('.', ',') : '0';
    if (yearEl) yearEl.textContent = stats ? (stats.avg - stats.best).toFixed(2).replace('.', ',') : '0';

    const trendBest = document.getElementById('market-trend-best');
    const trendWorst = document.getElementById('market-trend-worst');
    const trendAvg = document.getElementById('market-trend-avg');
    if (trendBest) trendBest.textContent = stats ? marketBRL(stats.best) : '—';
    if (trendWorst) trendWorst.textContent = stats ? marketBRL(stats.worst) : '—';
    if (trendAvg) trendAvg.textContent = stats ? marketBRL(stats.avg) : '—';

    const verified = offers.filter(o => Number(o.verified) === 1 || o.verified === true).length;
    const chainDot = document.getElementById('market-chain-dot');
    const chainStatus = document.getElementById('market-chain-status');
    const chainDetail = document.getElementById('market-chain-detail');
    let chainColor = '#94a3b8';
    let chainText = 'Sem ofertas cadastradas';
    if (offers.length === 1) { chainColor = '#f59e0b'; chainText = 'Fornecedor único'; }
    else if (offers.length > 1) { chainColor = '#10b981'; chainText = 'Concorrência ativa'; }
    if (chainDot) chainDot.style.background = chainColor;
    if (chainStatus) chainStatus.textContent = chainText;
    if (chainDetail) chainDetail.textContent = `${offers.length} oferta(s) · ${verified} verificada(s)`;

    const days = offers.map(o => marketParseDeliveryDays(o.delivery)).filter(d => d != null);
    const deliveryEl = document.getElementById('market-delivery-avg');
    const deliveryDetail = document.getElementById('market-delivery-detail');
    if (deliveryEl) {
        deliveryEl.textContent = days.length
            ? (days.reduce((a, b) => a + b, 0) / days.length).toFixed(1).replace('.', ',') + ' dias'
            : '—';
    }
    if (deliveryDetail) {
        deliveryDetail.textContent = days.length
            ? `Mais rápida: ${Math.min(...days).toFixed(1).replace('.', ',')} dia(s)`
            : 'Prazo não informado nas ofertas';
    }

    renderMarketAlerts(offers, stats);
    renderTopVendors(offers);
}

function renderMarketAlerts(offers, stats) {
    const box = document.getElementById('market-alerts-list');
    if (!box) return;
    const alerts = [];
    const key = marketMaterialKey();
    const target = key ? parseFloat(localStorage.getItem(key) || '') : NaN;

    if (stats && isFinite(target) && target > 0) {
        if (stats.best <= target) {
            alerts.push({ tone: 'success', title: '🎯 Preço-alvo atingido', text: `Melhor oferta ${marketBRL(stats.best)} ≤ alvo ${marketBRL(target)}.` });
        } else {
            alerts.push({ tone: 'neutral', title: '🔔 Alerta de preço ativo', text: `Alvo ${marketBRL(target)} · melhor atual ${marketBRL(stats.best)}.` });
        }
    }
    if (stats && stats.count > 1) {
        const spread = Math.round((1 - stats.best / stats.worst) * 100);
        if (spread >= 10) {
            alerts.push({ tone: 'warning', title: '📊 Variação entre fornecedores', text: `Diferença de ${spread}% entre a oferta mais barata e a mais cara.` });
        }
    }
    if (offers.length > 0 && !(Number(offers[0].verified) === 1 || offers[0].verified === true)) {
        alerts.push({ tone: 'neutral', title: '⚠️ Melhor oferta não verificada', text: 'Confirme o fornecedor antes de comprar.' });
    }

    if (!alerts.length) {
        box.innerHTML = '<div style="font-size:0.8rem; color:var(--text-muted); padding:8px 0;">Nenhum alerta no momento.</div>';
        return;
    }
    const styles = {
        success: 'background:#ecfdf5; border-left:4px solid #10b981; color:#065f46;',
        warning: 'background:linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left:4px solid #f59e0b; color:#78350f;',
        neutral: 'background:#f1f5f9; border-left:4px solid #64748b; color:#334155;'
    };
    box.innerHTML = alerts.map(a => `
        <div style="${styles[a.tone]} padding:12px; border-radius:8px;">
            <div style="font-weight:700; font-size:0.8rem; margin-bottom:3px;">${marketEsc(a.title)}</div>
            <div style="font-size:0.75rem;">${marketEsc(a.text)}</div>
        </div>
    `).join('');
}

function renderTopVendors(offers) {
    const box = document.getElementById('market-top-vendors');
    if (!box) return;
    const ranked = offers
        .filter(o => o.vendor_name)
        .slice()
        .sort((a, b) => (Number(b.rating) || 0) - (Number(a.rating) || 0) || (Number(a.price) || 0) - (Number(b.price) || 0))
        .slice(0, 3);
    if (!ranked.length) {
        box.innerHTML = '<div style="color:var(--text-muted);">Nenhum fornecedor cadastrado.</div>';
        return;
    }
    box.innerHTML = ranked.map(o => `
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span>${marketEsc(o.vendor_name)}</span>
            <div style="display:flex; align-items:center; gap:5px;">
                <span style="color:#fbbf24;">★</span>
                <span style="font-weight:700;">${marketEsc(o.rating != null ? String(o.rating) : '-')}</span>
            </div>
        </div>
    `).join('');
}

// Gráfico: preço de cada fornecedor cadastrado (sem histórico simulado).
async function renderPriceTrendChart() {
    const canvas = document.getElementById('market-price-trend');
    if (!canvas) return;

    try { if (typeof lubtekEnsureChart === 'function') await lubtekEnsureChart(); } catch (e) { }
    if (typeof Chart === 'undefined' || Chart.isPolyfill) return;

    if (window.marketTrendChart && typeof window.marketTrendChart.destroy === 'function') {
        window.marketTrendChart.destroy();
    }
    window.marketTrendChart = null;

    const offers = marketCurrentOffers();
    if (!offers.length) return;

    const labels = offers.map(o => o.vendor_name || 'Fornecedor');
    const data = offers.map(o => Number(o.price) || 0);

    try {
        window.marketTrendChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Preço (R$)',
                    data: data,
                    backgroundColor: data.map((_, i) => i === 0 ? 'rgba(16, 185, 129, 0.75)' : 'rgba(14, 165, 233, 0.45)'),
                    borderColor: data.map((_, i) => i === 0 ? '#10b981' : '#0ea5e9'),
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#f1f5f9',
                        bodyColor: '#f1f5f9',
                        padding: 12,
                        callbacks: { label: (context) => marketBRL(context.parsed.y) }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { color: '#64748b', callback: (value) => `R$ ${value}` } },
                    x: { grid: { display: false }, ticks: { color: '#64748b' } }
                }
            }
        });
    } catch (e) {
        console.warn('Gráfico de mercado indisponível', e);
    }
}

// Exporta as ofertas atuais do lubrificante selecionado para Excel.
async function exportMarketReport() {
    const offers = marketCurrentOffers();
    if (!offers.length) {
        return showToast('Nenhuma oferta carregada para exportar. Selecione um lubrificante com fornecedores cadastrados.', 'warning');
    }
    const material = document.getElementById('af-material')?.value || 'Lubrificante';
    const stats = marketPriceStats(offers);
    const rows = offers.map((o, i) => ({
        Posicao: i + 1,
        Lubrificante: material,
        Fornecedor: o.vendor_name || '',
        Preco_R$: Number(o.price) || 0,
        Diferenca_vs_Melhor_R$: stats ? Number(((Number(o.price) || 0) - stats.best).toFixed(2)) : 0,
        Entrega: o.delivery || '',
        Avaliacao: o.rating != null ? o.rating : '',
        Verificado: (Number(o.verified) === 1 || o.verified === true) ? 'Sim' : 'Não',
        Link: o.vendor_url || ''
    }));
    if (typeof exportToExcel === 'function') {
        const safeName = material.replace(/[^\w\-]+/g, '_').slice(0, 40);
        await exportToExcel(rows, 'Mercado_' + safeName + '_' + new Date().toISOString().slice(0, 10), 'Ofertas');
    } else {
        showToast('Exportação Excel indisponível nesta tela.', 'error');
    }
}

// Alerta de preço-alvo salvo neste dispositivo, por lubrificante.
function setMarketAlert() {
    const key = marketMaterialKey();
    if (!key) return showToast('Defina o lubrificante do ponto antes de criar um alerta.', 'warning');
    const stats = marketPriceStats(marketCurrentOffers());
    const current = localStorage.getItem(key) || (stats ? (stats.best * 0.95).toFixed(2) : '');
    const input = prompt('Preço-alvo para alerta (R$). Deixe vazio para remover o alerta:', current);
    if (input === null) return;
    const value = parseFloat(String(input).replace(',', '.'));
    if (String(input).trim() === '') {
        localStorage.removeItem(key);
        showToast('Alerta de preço removido.', 'info');
    } else if (!isFinite(value) || value <= 0) {
        return showToast('Valor inválido para o preço-alvo.', 'error');
    } else {
        localStorage.setItem(key, String(value));
        showToast(`Alerta salvo: preço-alvo ${marketBRL(value)}. Ele será avaliado sempre que as ofertas forem carregadas.`, 'success');
    }
    renderMarketAlerts(marketCurrentOffers(), marketPriceStats(marketCurrentOffers()));
}

// Compartilha o resumo real do comparativo.
async function shareMarketInsights() {
    const offers = marketCurrentOffers();
    const stats = marketPriceStats(offers);
    if (!stats) return showToast('Nenhuma oferta carregada para compartilhar.', 'warning');
    const material = document.getElementById('af-material')?.value || 'Lubrificante';
    const best = offers[0] || {};
    const text = `LUB-TEK · Mercado de ${material}: melhor oferta ${marketBRL(stats.best)} (${best.vendor_name || 'fornecedor'}), ` +
        `média ${marketBRL(stats.avg)}, ${stats.count} oferta(s) comparada(s).`;

    if (navigator.share) {
        try { await navigator.share({ title: 'Análise de Mercado - LUB-TEK', text: text }); return; } catch (e) {
            if (e && e.name === 'AbortError') return;
        }
    }
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return showToast('Resumo copiado para a área de transferência.', 'success');
        } catch (e) { }
    }
    prompt('Copie o resumo abaixo:', text);
}

// Update material preview in marketplace tab
function updateMaterialPreview() {
    const material = document.getElementById('af-material')?.value || 'Produto não selecionado';
    document.querySelectorAll('.af-mat-preview').forEach(el => {
        el.textContent = material;
    });

    // Auto-load offers when material changes
    loadMarketOffersForProduct();
}

// Hook into material input
if (document.getElementById('af-material')) {
    document.getElementById('af-material').addEventListener('change', updateMaterialPreview);
    document.getElementById('af-material').addEventListener('blur', updateMaterialPreview);
}

if (window.AppEvents && typeof window.AppEvents.on === 'function') {
    window.AppEvents.on('marketplace:updated', (offers) => {
        updateMarketSavings(Array.isArray(offers) ? offers : marketCurrentOffers());
        renderPriceTrendChart();
        calculateBulkPrice();
    });
}
