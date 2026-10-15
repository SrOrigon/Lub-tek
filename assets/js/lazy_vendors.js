/**
 * Carrega bibliotecas pesadas sob demanda (Chart, jsPDF, XLSX, ExcelJS, etc.).
 */
(function (global) {
    'use strict';

    const loaded = {};

    function loadScript(src) {
        if (loaded[src]) return loaded[src];
        loaded[src] = new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = function () { resolve(); };
            s.onerror = function () { reject(new Error('Failed ' + src)); };
            document.head.appendChild(s);
        });
        return loaded[src];
    }

    const LubtekVendors = {
        chart: function () {
            return loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js');
        },
        jsPdf: function () {
            return loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js')
                .then(function () {
                    return loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js');
                });
        },
        xlsx: function () {
            return loadScript('https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js');
        },
        exceljs: function () {
            return loadScript('https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js');
        },
        html2canvas: function () {
            return loadScript('https://html2canvas.hertzen.com/dist/html2canvas.min.js');
        },
        qrcode: function () {
            return loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js');
        },
        exportStack: function () {
            return Promise.all([
                LubtekVendors.xlsx(),
                LubtekVendors.jsPdf(),
                LubtekVendors.exceljs()
            ]);
        }
    };

    global.LubtekVendors = LubtekVendors;

    global.lubtekEnsureXlsx = function () {
        return typeof global.XLSX !== 'undefined' ? Promise.resolve() : LubtekVendors.xlsx();
    };

    global.lubtekEnsureJsPdf = function () {
        return (typeof global.jspdf !== 'undefined' && global.jspdf.jsPDF)
            ? Promise.resolve()
            : LubtekVendors.jsPdf();
    };

    global.lubtekEnsureExcelJs = function () {
        return typeof global.ExcelJS !== 'undefined' ? Promise.resolve() : LubtekVendors.exceljs();
    };

    global.lubtekEnsureChart = function () {
        return typeof global.Chart !== 'undefined' ? Promise.resolve() : LubtekVendors.chart();
    };

    global.lubtekEnsureHtml2Canvas = function () {
        return typeof global.html2canvas !== 'undefined' ? Promise.resolve() : LubtekVendors.html2canvas();
    };
})(typeof window !== 'undefined' ? window : globalThis);
