/**
 * Relatório de consumo individual de lubrificantes por equipamento.
 * Isolado — não altera exportações existentes.
 */
(function () {
    let cache = null;

    function esc(s) {
        if (typeof escapeHtml === 'function') return escapeHtml(String(s ?? ''));
        const d = document.createElement('span');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function fmt(n, digits) {
        const v = Number(n || 0);
        return v.toLocaleString('pt-BR', { minimumFractionDigits: digits, maximumFractionDigits: digits });
    }

    function periodFactor(data) {
        return (data && data.period === 'year') ? 12 : 1;
    }

    function qtyLabel(data) {
        return (data && data.period === 'year') ? 'Consumo/ano' : 'Consumo/mês';
    }

    async function loadReport(force) {
        const period = document.getElementById('lub-cons-period')?.value || 'month';
        if (!force && cache && cache.period === period) return cache;
        if (typeof api !== 'function') throw new Error('API indisponível');
        const res = await api('get_lubricant_consumption', { period });
        if (!res || res.error) throw new Error((res && res.error) || 'Falha ao montar o relatório.');
        cache = res.data && res.data.lines ? res.data : res;
        return cache;
    }

    function render(data) {
        const panel = document.getElementById('lub-consumption-panel');
        const kpis = document.getElementById('lub-cons-kpis');
        const body = document.getElementById('lub-cons-body');
        const meta = document.getElementById('lub-cons-meta');
        if (!panel || !kpis || !body) return;

        panel.style.display = 'block';
        const t = data.totals || {};
        const f = periodFactor(data);
        if (meta) {
            meta.textContent = (data.company ? data.company + ' · ' : '') + (data.period_label || '') +
                ' · gerado em ' + (data.generated_at ? new Date(data.generated_at).toLocaleString('pt-BR') : '');
        }

        kpis.innerHTML = [
            ['Equipamentos', t.equipamentos || 0],
            ['Pontos', t.pontos || 0],
            ['Lubrificantes', t.lubrificantes || 0],
            ['Óleo (L)', fmt(t.consumo_l || 0, 2)],
            ['Graxa (kg)', fmt(t.consumo_kg || 0, 2)],
            ['O.S. no mês', t.os_concluidas_mes || 0]
        ].map(([l, v]) => `<div class="lub-cons-kpi"><span>${l}</span><strong>${v}</strong></div>`).join('');

        if (!data.has_data) {
            body.innerHTML = '<p style="color:#64748b;font-weight:600;">Nenhum ponto com lubrificante e quantidade cadastrados. Preencha o material nos pontos em Meus Ativos.</p>';
            return;
        }

        const qh = qtyLabel(data);
        const eqRows = (data.equipments || []).map(eq => {
            const lubs = (eq.lubrificantes || []).map(l =>
                `${esc(l.nome)}: ${fmt(l.consumo_mes * f, 2)} ${esc(l.unidade)} (${l.pontos} pt)`
            ).join('<br>');
            return `<tr>
                <td class="lub-cons-eq">${esc(eq.nome)}</td>
                <td>${esc(eq.tag || '—')}</td>
                <td>${esc(eq.area)}</td>
                <td>${eq.pontos}</td>
                <td>${fmt(eq.consumo_l_mes * f, 2)} L</td>
                <td>${fmt(eq.consumo_kg_mes * f, 2)} kg</td>
                <td>${lubs || '—'}</td>
                <td>${eq.os_concluidas_mes || 0}</td>
            </tr>`;
        }).join('');

        const lineRows = (data.lines || []).map(ln => `<tr>
            <td class="lub-cons-eq">${esc(ln.equipamento)}</td>
            <td>${esc(ln.area)}</td>
            <td>${esc(ln.ponto)}</td>
            <td>${esc(ln.lubrificante)}</td>
            <td>${fmt(ln.qtd_aplicacao, 2)} ${esc(ln.unidade)}</td>
            <td>${esc(ln.frequencia)}</td>
            <td>${fmt(ln.consumo_mes * f, 2)} ${esc(ln.unidade)}</td>
        </tr>`).join('');

        const lubRows = (data.lubricants || []).map(lb => `<tr>
            <td class="lub-cons-eq">${esc(lb.nome)}</td>
            <td>${fmt((data.period === 'year' ? lb.consumo_ano : lb.consumo_mes), 2)} ${esc(lb.unidade_base)}</td>
            <td>${lb.n_equipamentos}</td>
            <td>${lb.pontos}</td>
        </tr>`).join('');

        body.innerHTML = `
            <h3 class="lub-cons-h">Por equipamento</h3>
            <div class="lub-cons-table-wrap">
                <table class="lub-cons-table" id="lub-cons-eq-table">
                    <thead><tr>
                        <th>Equipamento</th><th>Tag</th><th>Área</th><th>Pontos</th>
                        <th>Óleo</th><th>Graxa</th><th>Lubrificantes</th><th>O.S. mês</th>
                    </tr></thead>
                    <tbody>${eqRows}</tbody>
                </table>
            </div>
            <h3 class="lub-cons-h">Consumo individual (ponto a ponto)</h3>
            <div class="lub-cons-table-wrap">
                <table class="lub-cons-table" id="lub-cons-line-table">
                    <thead><tr>
                        <th>Equipamento</th><th>Área</th><th>Ponto</th><th>Lubrificante</th>
                        <th>Dose</th><th>Frequência</th><th>${qh}</th>
                    </tr></thead>
                    <tbody>${lineRows}</tbody>
                </table>
            </div>
            <h3 class="lub-cons-h">Consolidado por lubrificante</h3>
            <div class="lub-cons-table-wrap">
                <table class="lub-cons-table">
                    <thead><tr><th>Lubrificante</th><th>${qh}</th><th>Equipamentos</th><th>Pontos</th></tr></thead>
                    <tbody>${lubRows}</tbody>
                </table>
            </div>`;
        filterLubricantConsumptionTable();
        if (window.lucide) lucide.createIcons();
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    window.filterLubricantConsumptionTable = function () {
        const q = (document.getElementById('lub-cons-filter')?.value || '').toLowerCase().trim();
        document.querySelectorAll('#lub-cons-eq-table tbody tr, #lub-cons-line-table tbody tr').forEach(tr => {
            tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    };

    window.openLubricantConsumptionReport = async function (force) {
        const body = document.getElementById('lub-cons-body');
        const panel = document.getElementById('lub-consumption-panel');
        if (panel) panel.style.display = 'block';
        if (body) body.innerHTML = '<p style="color:#64748b;font-weight:600;">Montando demonstrativo a partir da planta...</p>';
        try {
            const data = await loadReport(!!force);
            render(data);
        } catch (e) {
            if (body) body.innerHTML = '<p style="color:#dc2626;font-weight:700;">' + esc(e.message) + '</p>';
            if (typeof showToast === 'function') showToast(e.message, 'error');
        }
    };

    window.exportLubricantConsumption = async function (format) {
        try {
            if (typeof showToast === 'function') showToast('Gerando relatório...', 'info');
            const data = await loadReport(false);
            if (!data.has_data) {
                if (typeof showToast === 'function') showToast('Não há pontos com lubrificante cadastrado.', 'warning');
                return;
            }
            const f = periodFactor(data);
            const qh = qtyLabel(data);
            const ts = new Date().toISOString().slice(0, 10);
            const detalhe = (data.lines || []).map(ln => ({
                Area: ln.area,
                Equipamento: ln.equipamento,
                Tag: ln.equipamento_tag || '',
                Ponto: ln.ponto,
                Lubrificante: ln.lubrificante,
                Dose: ln.qtd_aplicacao,
                Unidade: ln.unidade,
                Frequencia: ln.frequencia,
                [qh]: Number((ln.consumo_mes * f).toFixed(4)),
                SAP: ln.sap || '',
                Rota: ln.rota || ''
            }));
            const porEq = (data.equipments || []).map(eq => ({
                Area: eq.area,
                Equipamento: eq.nome,
                Tag: eq.tag || '',
                Pontos: eq.pontos,
                Oleo_L: Number((eq.consumo_l_mes * f).toFixed(3)),
                Graxa_kg: Number((eq.consumo_kg_mes * f).toFixed(3)),
                Lubrificantes: (eq.lubrificantes || []).map(l => l.nome).join('; '),
                OS_mes: eq.os_concluidas_mes || 0
            }));

            if (format === 'excel') {
                if (typeof XLSX === 'undefined') return showToast('Biblioteca Excel não carregada.', 'error');
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(porEq), 'Por equipamento');
                XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(detalhe), 'Ponto a ponto');
                XLSX.writeFile(wb, `CONSUMO_LUBRIFICANTES_${ts}.xlsx`);
                if (typeof showToast === 'function') showToast('Excel gerado.', 'success');
                return;
            }

            if (format === 'pdf') {
                if (!window.jspdf) return showToast('Biblioteca PDF não carregada.', 'error');
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
                doc.setFontSize(14);
                doc.text('Consumo de lubrificantes por equipamento', 14, 14);
                doc.setFontSize(9);
                doc.setTextColor(80);
                doc.text((data.company || 'LUB-TEK') + ' · ' + (data.period_label || '') + ' · ' + ts, 14, 20);
                if (typeof doc.autoTable === 'function') {
                    doc.autoTable({
                        startY: 24,
                        head: [['Equipamento', 'Área', 'Pontos', 'Óleo (L)', 'Graxa (kg)', 'Lubrificantes']],
                        body: porEq.map(r => [r.Equipamento, r.Area, r.Pontos, r.Oleo_L, r.Graxa_kg, r.Lubrificantes]),
                        styles: { fontSize: 8 },
                        headStyles: { fillColor: [2, 132, 199] }
                    });
                    doc.autoTable({
                        startY: (doc.lastAutoTable && doc.lastAutoTable.finalY ? doc.lastAutoTable.finalY + 8 : 80),
                        head: [['Equipamento', 'Ponto', 'Lubrificante', 'Dose', 'Freq.', qh]],
                        body: detalhe.map(r => [r.Equipamento, r.Ponto, r.Lubrificante, r.Dose + ' ' + r.Unidade, r.Frequencia, r[qh]]),
                        styles: { fontSize: 7 },
                        headStyles: { fillColor: [15, 23, 42] }
                    });
                }
                doc.save(`CONSUMO_LUBRIFICANTES_${ts}.pdf`);
                if (typeof showToast === 'function') showToast('PDF gerado.', 'success');
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast(e.message || 'Erro ao exportar.', 'error');
        }
    };

    window.printLubricantConsumption = function () {
        const panel = document.getElementById('lub-consumption-panel');
        if (!panel || panel.style.display === 'none') {
            openLubricantConsumptionReport().then(() => window.print());
            return;
        }
        window.print();
    };
})();
