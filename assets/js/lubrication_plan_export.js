/**
 * Subsistema de exportação do Plano de Lubrificação (HTML/PDF — dados técnicos).
 */
(function () {
    'use strict';

    async function callApi(action, payload) {
        if (typeof api === 'function') {
            return api(action, payload);
        }
        const res = await fetch('api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload || {})
        });
        return res.json();
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type || 'info');
    }

    function buildExportUrl(assetId, frequency, title) {
        const params = new URLSearchParams({ id: String(assetId), use_ai: '0' });
        if (frequency) params.set('frequency', frequency);
        if (title) params.set('title', title);
        return 'export_lubrication_plan.php?' + params.toString();
    }

    function getEditingNode() {
        if (typeof editingNode !== 'undefined' && editingNode) return editingNode;
        return null;
    }

    async function exportLubricationPlan(options = {}) {
        const node = options.asset || getEditingNode();
        if (!node || !node.id) {
            toast('Selecione uma planta, linha ou equipamento na árvore de ativos.', 'warning');
            return;
        }

        const frequency = options.frequency || '';
        const title = options.title || 'PLANO DE LUBRIFICAÇÃO';

        toast('Preparando plano de lubrificação...', 'info');

        try {
            const meta = await callApi('get_lubrication_plan_meta', {
                id: node.id,
                frequency,
                use_ai: '0'
            });

            if (!meta || meta.ok === false) {
                toast(meta?.error || 'Não foi possível gerar o plano.', 'error');
                return;
            }

            const stats = meta.meta?.stats || {};
            const points = stats.points || 0;
            const equipments = stats.equipments || 0;

            if (points === 0 && equipments === 0) {
                toast('Nenhum ponto de lubrificação catalogado neste escopo.', 'warning');
                return;
            }

            toast(`Montando documento: ${equipments} equipamento(s), ${points} ponto(s)...`, 'info');

            const url = meta.export_url || buildExportUrl(node.id, frequency, title);
            window.open(url, '_blank', 'noopener,noreferrer');
            toast('Plano técnico aberto. Imprimir → PDF (paisagem).', 'success');
        } catch (e) {
            console.error(e);
            toast('Erro ao gerar plano de lubrificação.', 'error');
        }
    }

    function openLubricationPlanModal() {
        const node = getEditingNode();
        if (!node) {
            toast('Selecione uma planta ou linha na árvore de ativos.', 'warning');
            return;
        }

        const modal = document.getElementById('lub-plan-export-modal');
        if (modal) {
            const assetLabel = document.getElementById('lp-modal-asset-name');
            if (assetLabel) assetLabel.textContent = node.nome || 'Ativo Selecionado';
            modal.style.display = 'flex';
            if (window.lucide && typeof lucide.createIcons === 'function') lucide.createIcons();
            return;
        }

        exportLubricationPlan({
            asset: node,
            frequency: '',
            title: 'PLANO DE LUBRIFICAÇÃO'
        });
    }

    function executeLubPlanExport(frequency) {
        const modal = document.getElementById('lub-plan-export-modal');
        if (modal) modal.style.display = 'none';

        const node = getEditingNode();
        if (!node) {
            toast('Selecione um ativo.', 'warning');
            return;
        }

        exportLubricationPlan({
            asset: node,
            frequency: String(frequency || '').trim(),
            title: 'PLANO DE LUBRIFICAÇÃO'
        });
    }

    window.executeLubPlanExport = executeLubPlanExport;
    window.exportLubricationPlan = exportLubricationPlan;
    window.openLubricationPlanModal = openLubricationPlanModal;
})();
