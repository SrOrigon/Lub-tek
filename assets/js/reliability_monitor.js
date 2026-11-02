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
    window.currentAssetReliabilityData = data;
    window.currentReliabilityAssetId = reliabilityCurrentAssetId;

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
        const pct = data.reliability != null ? `${data.reliability}% ` : '';
        statusEl.textContent = data.reliability < 50 ? `⚠️ ${pct}${data.status}` : `${pct}${data.status}`;
        statusEl.style.color = data.color || '';
        statusEl.style.transition = 'color 0.5s ease';
    }

    const failureEl = document.getElementById('next-failure');
    if (failureEl) {
        if (data.next_failure_label) {
            failureEl.textContent = data.next_failure_label;
            failureEl.style.color = '#ef4444';
        } else if (data.next_failure_days == null) {
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
            co2El.textContent = '--,-- kg CO2 / Ano';
        } else {
            const formatted = Math.abs(Number(data.co2_impact)).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            co2El.textContent = `${formatted} kg CO2 / Ano`;
        }
    }

    const cardEl = document.getElementById('reliability-card');
    if (cardEl && data.timestamp) {
        cardEl.setAttribute('data-last-update', data.timestamp);
        cardEl.title = `Última atualização: ${new Date(data.timestamp).toLocaleTimeString('pt-BR')}`;
    }
}

function resetReliabilityDisplay() {
    window.currentAssetReliabilityData = null;
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

// ==========================================
// ASSET HEALTH & RELIABILITY DETAIL MODAL
// ==========================================

function openAssetHealthModal(focusSection) {
    const node = window.editingNode;
    const assetId = (node && node.id) || reliabilityCurrentAssetId;

    if (!assetId) {
        if (typeof showToast === 'function') {
            showToast('Selecione um ativo na árvore à esquerda para ver a saúde e confiabilidade.', 'info');
        }
        return;
    }

    const modal = document.getElementById('asset-health-modal');
    if (!modal) return;

    modal.style.display = 'flex';
    if (typeof lubtekLockBodyScroll === 'function') {
        lubtekLockBodyScroll();
    } else {
        document.body.style.overflow = 'hidden';
    }

    const nameEl = document.getElementById('ahm-asset-name');
    if (nameEl) nameEl.textContent = (node && node.nome) ? node.nome : `Ativo #${assetId}`;

    const tagEl = document.getElementById('ahm-asset-tag');
    if (tagEl) {
        if (node && node.tag) {
            tagEl.textContent = `TAG: ${node.tag}`;
            tagEl.style.display = 'inline-block';
        } else {
            tagEl.style.display = 'none';
        }
    }

    if (window.currentAssetReliabilityData && String(window.currentReliabilityAssetId) === String(assetId)) {
        renderAssetHealthModalContent(window.currentAssetReliabilityData, node);
    } else {
        const scoreEl = document.getElementById('ahm-score');
        if (scoreEl) scoreEl.textContent = '...';
        const statusEl = document.getElementById('ahm-status');
        if (statusEl) statusEl.textContent = 'Carregando...';
        const summaryEl = document.getElementById('ahm-summary');
        if (summaryEl) summaryEl.textContent = 'Consultando telemetria e motor de confiabilidade...';
        const actionEl = document.getElementById('ahm-next-action');
        if (actionEl) actionEl.textContent = 'Aguarde...';

        loadAssetReliability(assetId).then(() => {
            if (window.currentAssetReliabilityData) {
                renderAssetHealthModalContent(window.currentAssetReliabilityData, node);
            }
        });
    }

    if (window.lucide && typeof lucide.createIcons === 'function') {
        lucide.createIcons();
    }
}

function closeAssetHealthModal() {
    const modal = document.getElementById('asset-health-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    if (typeof lubtekUnlockBodyScroll === 'function') {
        lubtekUnlockBodyScroll();
    } else {
        document.body.style.overflow = '';
    }
}

function renderAssetHealthModalContent(data, node) {
    if (!data) return;

    const nameEl = document.getElementById('ahm-asset-name');
    if (nameEl && node && node.nome) nameEl.textContent = node.nome;

    const tagEl = document.getElementById('ahm-asset-tag');
    if (tagEl && node && node.tag) {
        tagEl.textContent = `TAG: ${node.tag}`;
        tagEl.style.display = 'inline-block';
    }

    const score = data.reliability !== undefined ? data.reliability : (data.health_score || 0);
    const scoreColor = data.color || (score >= 90 ? '#10b981' : (score >= 75 ? '#3b82f6' : (score >= 50 ? '#f59e0b' : '#ef4444')));

    const scoreEl = document.getElementById('ahm-score');
    if (scoreEl) {
        scoreEl.textContent = `${score}%`;
        scoreEl.style.color = scoreColor;
    }

    const statusEl = document.getElementById('ahm-status');
    if (statusEl) {
        statusEl.textContent = data.status || (score >= 90 ? 'Excelente' : (score >= 75 ? 'Bom' : (score >= 50 ? 'Atenção' : 'Crítico')));
        statusEl.style.color = scoreColor;
    }

    const iconBox = document.getElementById('ahm-icon-box');
    if (iconBox) {
        iconBox.style.color = scoreColor;
        iconBox.style.background = scoreColor + '18';
    }

    const riskBadge = document.getElementById('ahm-risk-badge');
    if (riskBadge) {
        const risk = data.risk_level || (score >= 75 ? 'Baixo' : (score >= 50 ? 'Médio' : 'Alto'));
        riskBadge.textContent = `Risco ${risk}`;
        if (risk.toLowerCase().includes('alto')) {
            riskBadge.style.background = '#fee2e2';
            riskBadge.style.color = '#b91c1c';
        } else if (risk.toLowerCase().includes('méd') || risk.toLowerCase().includes('med')) {
            riskBadge.style.background = '#fef3c7';
            riskBadge.style.color = '#b45309';
        } else {
            riskBadge.style.background = '#dcfce7';
            riskBadge.style.color = '#166534';
        }
    }

    const nextFailureEl = document.getElementById('ahm-next-failure');
    if (nextFailureEl) {
        if (data.next_failure_label) {
            nextFailureEl.textContent = data.next_failure_label;
            nextFailureEl.style.color = '#ef4444';
        } else if (data.next_failure_days != null) {
            nextFailureEl.textContent = `+${data.next_failure_days} Dias (Estimado)`;
            nextFailureEl.style.color = data.next_failure_days < 7 ? '#ef4444' : (data.next_failure_days < 30 ? '#f59e0b' : '#8b5cf6');
        } else {
            nextFailureEl.textContent = '+-- Dias (Estimado)';
            nextFailureEl.style.color = '#8b5cf6';
        }
    }

    const co2El = document.getElementById('ahm-co2');
    if (co2El) {
        if (data.co2_impact != null) {
            const formatted = Math.abs(Number(data.co2_impact)).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            co2El.textContent = `-${formatted} kg CO2/ano`;
        } else {
            co2El.textContent = '--.-- kg CO2/ano';
        }
    }

    const summaryEl = document.getElementById('ahm-summary');
    if (summaryEl) {
        summaryEl.textContent = data.summary || 'Indicadores disponíveis sem alertas críticos. Equipamento operando em conformidade com as rotinas industriais.';
    }

    const nextActionEl = document.getElementById('ahm-next-action');
    if (nextActionEl) {
        nextActionEl.textContent = data.next_action || 'Mantenha o cronograma do plano de lubrificação atual.';
    }

    // Breakdown bars
    const breakdown = data.breakdown || {};
    const statusScore = breakdown.status_score !== undefined ? breakdown.status_score : score;
    const maintScore = breakdown.maintenance_score !== undefined ? breakdown.maintenance_score : Math.round(score * 0.98);
    const oilScore = breakdown.oil_score !== undefined ? breakdown.oil_score : Math.min(100, Math.round(score * 1.02));

    const barStatus = document.getElementById('ahm-bar-status');
    const valStatus = document.getElementById('ahm-val-status');
    if (barStatus) barStatus.style.width = `${Math.max(0, Math.min(100, statusScore))}%`;
    if (valStatus) valStatus.textContent = `${statusScore}%`;

    const barMaint = document.getElementById('ahm-bar-maint');
    const valMaint = document.getElementById('ahm-val-maint');
    if (barMaint) barMaint.style.width = `${Math.max(0, Math.min(100, maintScore))}%`;
    if (valMaint) valMaint.textContent = `${maintScore}%`;

    const barOil = document.getElementById('ahm-bar-oil');
    const valOil = document.getElementById('ahm-val-oil');
    if (barOil) barOil.style.width = `${Math.max(0, Math.min(100, oilScore))}%`;
    if (valOil) valOil.textContent = `${oilScore}%`;

    if (window.lucide && typeof lucide.createIcons === 'function') {
        lucide.createIcons();
    }
}

// Close on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeAssetHealthModal();
    }
});

window.openAssetHealthModal = openAssetHealthModal;
window.closeAssetHealthModal = closeAssetHealthModal;
window.renderAssetHealthModalContent = renderAssetHealthModalContent;
