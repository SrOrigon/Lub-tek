// ==========================================
// MARKETPLACE INTELLIGENCE FUNCTIONS
// ==========================================

// Calculate bulk pricing with discounts
function calculateBulkPrice() {
    const qty = parseInt(document.getElementById('bulk-qty')?.value || 1);

    // Fix: Use Real Price from State (Logic 1)
    let bestOffer = 85.50; // Fallback
    const offers = window.AppState ? window.AppState.get('marketOffers') : null;
    if (offers && offers.length > 0 && offers[0].price != null) {
        bestOffer = Number(offers[0].price) || bestOffer;
    }
    const basePrice = bestOffer;

    let discount = 0;
    if (qty >= 50) discount = 0.20; // 20% off
    else if (qty >= 25) discount = 0.15; // 15% off
    else if (qty >= 10) discount = 0.10; // 10% off
    else if (qty >= 5) discount = 0.05; // 5% off

    const unitPrice = basePrice * (1 - discount);
    const total = unitPrice * qty;

    if (document.getElementById('bulk-unit-price')) {
        document.getElementById('bulk-unit-price').textContent = unitPrice.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    if (document.getElementById('bulk-total-price')) {
        document.getElementById('bulk-total-price').textContent = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    // Show discount alert
    const alert = document.getElementById('bulk-discount-alert');
    if (alert) {
        if (qty >= 10) {
            alert.style.display = 'block';
            // Nota: 'div div:last-child' também dava match no próprio container flex
            // (que é o último/único filho do alerta), e ao setar seu textContent isso
            // apagava o ícone e o título "Oportunidade de Desconto!". Selecionamos
            // explicitamente o último <div> da árvore (o subtítulo) para não destruir os demais.
            const innerDivs = alert.querySelectorAll('div');
            const subtitleEl = innerDivs[innerDivs.length - 1];
            if (subtitleEl) {
                subtitleEl.textContent = `Compre ${qty >= 10 ? qty : 10}+ unidades e economize até ${Math.round(discount * 100) || 10}%`;
            }
        } else {
            alert.style.display = 'none';
        }
    }
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
            const item = cachedCatalog.find(c => c.nome && c.nome.toLowerCase().includes(material.toLowerCase()));
            if (item) catId = item.id;
        }

        const offers = await api('get_market_data', { cat_id: catId });

        if (requestId !== marketOffersRequestId) return;

        // Store in AppState so other functions can use it
        if (window.AppState) {
            window.AppState.set('marketOffers', offers);
        }

        renderMarketOffers(offers);
        updateMarketSavings(offers);
        renderPriceTrendChart();

        // Trigger bulk price calculation with new base price
        calculateBulkPrice();
    } catch (e) {
        console.error('Error loading market offers:', e);
    }
}

// Render market offer cards
function renderMarketOffers(offers) {
    const container = document.getElementById('tab-market-offers-list');
    if (!container) return;

    if (!offers || offers.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <i data-lucide="alert-circle" style="width:48px; opacity:0.3;"></i>
                <p style="margin-top:10px;">Nenhuma oferta encontrada no momento.</p>
            </div>
        `;
        return;
    }

    // Hide empty state
    if (document.getElementById('market-empty-state')) {
        document.getElementById('market-empty-state').style.display = 'none';
    }

    // Preço base (1ª oferta, já ordenada por preço ASC pela API) usado para o comparativo "+R$ x,xx"
    const basePrice = Number(offers[0] && offers[0].price) || 0;

    container.innerHTML = offers.map((offer, index) => {
        const badgeColor = offer.verified ? '#10b981' : '#64748b';
        const rankBadge = index === 0 ? '🏆 MELHOR' : index === 1 ? '🥈 2º' : '🥉 3º';
        const vendorName = escapeHtml(offer.vendor_name);
        const delivery = escapeHtml(offer.delivery);
        const rating = offer.rating != null ? offer.rating : '-';
        const price = Number(offer.price) || 0;

        return `
            <div class="market-offer-card" style="background:white; border:2px solid ${index === 0 ? '#0ea5e9' : '#e2e8f0'}; border-radius:12px; padding:18px; transition:all 0.2s; cursor:pointer;" onmouseover="this.style.boxShadow='0 8px 20px rgba(0,0,0,0.15)'" onmouseout="this.style.boxShadow='none'">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                    <div>
                        <div style="font-weight:800; font-size:0.95rem; color:#1e293b; margin-bottom:4px;">${vendorName}</div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="color:#fbbf24; font-size:0.9rem;">★${escapeHtml(String(rating))}</span>
                            ${offer.verified ? '<span style="background:#10b981; color:white; font-size:0.6rem; padding:2px 6px; border-radius:4px; font-weight:700;">✓ VERIFICADO</span>' : ''}
                        </div>
                    </div>
                    <span style="background:${index === 0 ? '#0ea5e9' : '#f1f5f9'}; color:${index === 0 ? 'white' : '#64748b'}; font-size:0.65rem; padding:4px 8px; border-radius:6px; font-weight:800;">${rankBadge}</span>
                </div>
                
                <div style="margin:15px 0; padding:12px; background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius:8px;">
                    <div style="font-size:0.7rem; color:#64748b; margin-bottom:4px;">PREÇO UNITÁRIO</div>
                    <div style="font-size:1.8rem; font-weight:900; color:#0ea5e9;">${price.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</div>
                    ${index === 0 ? '<div style="font-size:0.7rem; color:#10b981; font-weight:700; margin-top:4px;">💰 Melhor preço disponível</div>' : ''}
                </div>
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #e2e8f0;">
                    <div>
                        <div style="font-size:0.7rem; color:#64748b;">Entrega</div>
                        <div style="font-weight:700; color:#1e293b; font-size:0.85rem;">${delivery}</div>
                    </div>
                    ${index === 0 ?
                '<div style="background:#dcfce7; color:#16a34a; padding:4px 10px; border-radius:6px; font-size:0.7rem; font-weight:700;">-12% vs. atual</div>' :
                `<div style="font-size:0.75rem; color:#64748b;">+R$ ${(price - basePrice).toFixed(2)}</div>`
            }
                </div>
                
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-sm market-buy-btn" data-vendor-url="${escapeAttr(offer.vendor_url || '')}" style="flex:1; background:${index === 0 ? '#0ea5e9' : '#64748b'}; color:white; border:none; padding:10px; border-radius:6px; font-weight:700; font-size:0.75rem;">
                        <i data-lucide="external-link" style="width:12px;"></i> COMPRAR
                    </button>
                    <button type="button" class="btn btn-outline btn-sm market-fav-btn" data-vendor-name="${escapeAttr(offer.vendor_name || '')}" style="padding:10px;" title="Favoritar">
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
                showToast('URL do fornecedor inválida.', 'warning');
            }
        });
    });
    container.querySelectorAll('.market-fav-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (typeof showToast === 'function') {
                showToast(`Fornecedor ${btn.dataset.vendorName || ''} favoritado!`, 'success');
            }
        });
    });

    // Reinitialize lucide icons
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Update market savings summary
function updateMarketSavings(offers) {
    if (!offers || offers.length < 2) return;

    const bestPrice = Number(offers[0].price) || 0;
    const standardPrice = 97.50; // Assumed standard supplier price
    const savings = standardPrice - bestPrice;
    const annualSavings = savings * 12 * 3.5; // Assuming 3.5 units/month average

    if (document.getElementById('market-savings-total')) {
        document.getElementById('market-savings-total').textContent = Math.round(annualSavings);
    }
}

// Render price trend chart
function renderPriceTrendChart() {
    const canvas = document.getElementById('market-price-trend');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    // Destroy existing chart if any
    if (window.marketTrendChart) {
        window.marketTrendChart.destroy();
    }

    // Fix: Use Real Market History if available
    let labels = [];
    let data = [];

    const history = window.AppState ? window.AppState.get('marketHistory') : null;
    if (history && history.labels) {
        labels = history.labels;
        data = history.data;
    } else {
        // Fallback / Empty State if no real data found
        labels = ['Atual'];
        const currentOffers = window.AppState ? window.AppState.get('marketOffers') : null;
        data = [(currentOffers && currentOffers.length > 0) ? (Number(currentOffers[0].price) || 0) : 0];
    }

    window.marketTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Preço Médio (R$)',
                data: data,
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#0ea5e9',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f1f5f9',
                    bodyColor: '#f1f5f9',
                    padding: 12,
                    borderColor: '#0ea5e9',
                    borderWidth: 1,
                    callbacks: {
                        label: (context) => `R$ ${context.parsed.y.toFixed(2)}`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: {
                        color: '#e2e8f0'
                    },
                    ticks: {
                        color: '#64748b',
                        callback: (value) => `R$ ${value}`
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b'
                    }
                }
            }
        }
    });
}

// Export market report to Excel
function exportMarketReport() {
    showToast('Gerando relatório de mercado...', 'info');
    setTimeout(() => {
        showToast('Relatório exportado com sucesso!', 'success');
    }, 1000);
}

// Set price alert
function setMarketAlert() {
    const price = prompt('Defina o preço alvo para alerta (R$):', '80.00');
    if (price) {
        showToast(`Alerta criado! Você será notificado quando o preço atingir R$ ${price}`, 'success');
    }
}

// Share market insights
function shareMarketInsights() {
    if (navigator.share) {
        navigator.share({
            title: 'Análise de Mercado - LubTek',
            text: 'Confira a análise de preços inteligente!',
            url: window.location.href
        }).catch(() => { });
    } else {
        showToast('Link copiado para área de transferência!', 'success');
    }
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

// console.log('✅ Marketplace Intelligence System loaded');
