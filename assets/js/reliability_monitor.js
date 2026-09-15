// ==========================================
// REAL-TIME ASSET RELIABILITY SYSTEM
// ==========================================

let reliabilityUpdateInterval = null;
let reliabilityCurrentAssetId = null;

async function loadAssetReliability(assetId) {
    if (!assetId || assetId === 'null') {
        resetReliabilityDisplay();
        return;
    }

    try {
        const data = await api('get_asset_reliability', { asset_id: assetId });

        // Se o usuário já selecionou outro ativo enquanto esta requisição estava em
        // andamento, ignora a resposta desatualizada para não sobrescrever a tela
        // do ativo atualmente selecionado com dados de um ativo antigo.
        if (String(assetId) !== String(reliabilityCurrentAssetId)) return;

        if (data && data.reliability !== undefined) {
            updateReliabilityDisplay(data);
        } else {
            resetReliabilityDisplay();
        }
    } catch (error) {
        if (String(assetId) !== String(reliabilityCurrentAssetId)) return;
        resetReliabilityDisplay();
    }
}

function updateReliabilityDisplay(data) {
    const percentEl = document.getElementById('reliability-percent');
    if (percentEl) {
        percentEl.textContent = `${data.reliability}%`;
        percentEl.style.transition = 'all 0.5s ease';
    }

    const circleEl = document.getElementById('reliability-circle');
    if (circleEl) {
        circleEl.style.transition = 'border-color 0.5s ease, color 0.5s ease';
        circleEl.style.borderColor = data.color;
        const percentSpan = circleEl.querySelector('#reliability-percent');
        if (percentSpan) {
            percentSpan.style.color = data.color;
        }
    }

    const statusEl = document.getElementById('reliability-status');
    if (statusEl) {
        // Usa sempre textContent (nunca innerHTML) pois 'status'/'risk_level' pode vir
        // de uma resposta da IA (Gemini) e não deve ser interpretado como HTML.
        statusEl.textContent = data.reliability < 50 ? `⚠️ ${data.status}` : data.status;
        statusEl.style.transition = 'color 0.5s ease';
    }

    const failureEl = document.getElementById('next-failure');
    if (failureEl) {
        if (data.next_failure_days == null) {
            failureEl.textContent = '+-- Dias (Estimado)';
            failureEl.style.color = 'var(--text-main)';
        } else {
            failureEl.textContent = `+${data.next_failure_days} Dias (Estimado)`;
            if (data.next_failure_days < 7) {
                failureEl.style.color = '#ef4444';
            } else if (data.next_failure_days < 30) {
                failureEl.style.color = '#f59e0b';
            } else {
                failureEl.style.color = 'var(--text-main)';
            }
        }
        failureEl.style.transition = 'color 0.5s ease';
    }

    const co2El = document.getElementById('co2-impact');
    if (co2El) {
        if (data.co2_impact == null) {
            co2El.textContent = '--.-- kg CO2 / Ano';
        } else {
            const sign = data.co2_impact >= 0 ? '-' : '+';
            co2El.textContent = `${sign}${Math.abs(data.co2_impact).toFixed(1)}kg CO2 / Ano`;
        }
    }

    const cardEl = document.getElementById('reliability-card');
    if (cardEl && data.timestamp) {
        cardEl.setAttribute('data-last-update', data.timestamp);
        cardEl.title = `Última atualização: ${new Date(data.timestamp).toLocaleTimeString('pt-BR')}`;
    }
}

function resetReliabilityDisplay() {
    const percentEl = document.getElementById('reliability-percent');
    if (percentEl) percentEl.textContent = '--';

    const statusEl = document.getElementById('reliability-status');
    if (statusEl) statusEl.textContent = 'Sem dados';

    const failureEl = document.getElementById('next-failure');
    if (failureEl) failureEl.textContent = '+-- Dias (Estimado)';

    const co2El = document.getElementById('co2-impact');
    if (co2El) co2El.textContent = '--.-- kg CO2 / Ano';

    const circleEl = document.getElementById('reliability-circle');
    if (circleEl) {
        circleEl.style.borderColor = '#64748b';
        const percentSpan = circleEl.querySelector('#reliability-percent');
        if (percentSpan) percentSpan.style.color = '#64748b';
    }
}

function startReliabilityAutoUpdate(assetId, intervalMs = 120000) {
    if (!assetId) {
        stopReliabilityAutoUpdate();
        resetReliabilityDisplay();
        return;
    }

    if (String(assetId) === String(reliabilityCurrentAssetId) && reliabilityUpdateInterval) {
        return;
    }

    stopReliabilityAutoUpdate();
    reliabilityCurrentAssetId = assetId;
    loadAssetReliability(assetId);

    reliabilityUpdateInterval = setInterval(() => {
        if (document.hidden) return;
        if (document.getElementById('view-assets')?.classList.contains('active')) {
            loadAssetReliability(assetId);
        }
    }, intervalMs);
}

function stopReliabilityAutoUpdate() {
    if (reliabilityUpdateInterval) {
        clearInterval(reliabilityUpdateInterval);
        reliabilityUpdateInterval = null;
    }
    reliabilityCurrentAssetId = null;
}

function onNodeSelected(node) {
    if (node && node.id) {
        startReliabilityAutoUpdate(node.id);
    } else {
        stopReliabilityAutoUpdate();
        resetReliabilityDisplay();
    }
}

if (window.AppEvents) {
    window.AppEvents.on('node:selected', onNodeSelected);
} else {
    document.addEventListener('DOMContentLoaded', () => {
        if (window.AppEvents) window.AppEvents.on('node:selected', onNodeSelected);
    });
}

window.addEventListener('beforeunload', () => stopReliabilityAutoUpdate());

window.loadAssetReliability = loadAssetReliability;
window.startReliabilityAutoUpdate = startReliabilityAutoUpdate;
window.stopReliabilityAutoUpdate = stopReliabilityAutoUpdate;
