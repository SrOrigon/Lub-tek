/**
 * Utilitários de acessibilidade (live region, alto contraste, painéis do dashboard).
 */
(function (global) {
    'use strict';

    function announce(message, priority) {
        if (!message) return;
        const el = document.getElementById('a11y-live-region');
        if (!el) return;
        el.setAttribute('aria-live', priority === 'assertive' ? 'assertive' : 'polite');
        el.textContent = '';
        global.setTimeout(function () {
            el.textContent = String(message);
        }, 30);
    }

    function toggleHighContrast() {
        const on = document.body.classList.toggle('theme-high-contrast');
        try {
            localStorage.setItem('lubtek_high_contrast', on ? '1' : '0');
        } catch (e) { /* ignore */ }
        const btn = document.getElementById('btn-high-contrast');
        if (btn) {
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.setAttribute('aria-label', on ? 'Desativar alto contraste' : 'Ativar alto contraste');
        }
        announce(on ? 'Alto contraste ativado' : 'Alto contraste desativado', 'polite');
    }

    function initHighContrastFromStorage() {
        try {
            if (localStorage.getItem('lubtek_high_contrast') === '1') {
                document.body.classList.add('theme-high-contrast');
                const btn = document.getElementById('btn-high-contrast');
                if (btn) {
                    btn.setAttribute('aria-pressed', 'true');
                    btn.setAttribute('aria-label', 'Desativar alto contraste');
                }
            }
        } catch (e) { /* ignore */ }
    }

    function dashViewportNarrow() {
        try {
            return global.matchMedia('(max-width: 1023px)').matches;
        } catch (e) {
            return false;
        }
    }

    function readCollapsedPref(key, fallback) {
        try {
            const v = localStorage.getItem(key);
            if (v === '1') return true;
            if (v === '0') return false;
        } catch (e) { /* ignore */ }
        return fallback;
    }

    function saveCollapsedPref(key, collapsed) {
        try {
            localStorage.setItem(key, collapsed ? '1' : '0');
        } catch (e) { /* ignore */ }
    }

    function setSectionExpanded(sectionId, toggleId, collapsed) {
        const section = document.getElementById(sectionId);
        const btn = document.getElementById(toggleId);
        if (!section) return;
        section.classList.toggle('is-collapsed', !!collapsed);
        section.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        if (btn) {
            btn.textContent = collapsed ? 'Expandir' : 'Recolher';
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    function initDashPanelDefaults() {
        const defaultCollapsed = dashViewportNarrow();
        setSectionExpanded(
            'neural-insights-section',
            'neural-dash-toggle',
            readCollapsedPref('lubtek_dash_neural_collapsed', defaultCollapsed)
        );
        setSectionExpanded(
            'ai-ops-panel-section',
            'ai-ops-toggle',
            readCollapsedPref('lubtek_dash_ai_ops_collapsed', defaultCollapsed)
        );
        setSectionExpanded(
            'os-agent-report-section',
            'os-agent-toggle',
            readCollapsedPref('lubtek_dash_os_agent_collapsed', defaultCollapsed)
        );
        updateDashTableScrollHint();
    }

    function updateDashTableScrollHint() {
        const wrap = document.querySelector('.dash-table-scroll');
        if (!wrap) return;
        const scrollable = wrap.scrollHeight > wrap.clientHeight + 8;
        wrap.classList.toggle('is-scrollable', scrollable);
        wrap.setAttribute('aria-busy', 'false');
    }

    function trapFocusInPanel(panel, enable) {
        if (!panel) return;
        if (enable) {
            panel.setAttribute('aria-modal', 'true');
            panel.setAttribute('role', 'dialog');
            const focusable = panel.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable && focusable.focus) focusable.focus();
        } else {
            panel.removeAttribute('aria-modal');
        }
    }

    global.LubtekA11y = {
        announce: announce,
        toggleHighContrast: toggleHighContrast,
        initHighContrastFromStorage: initHighContrastFromStorage,
        initDashPanelDefaults: initDashPanelDefaults,
        updateDashTableScrollHint: updateDashTableScrollHint,
        saveCollapsedPref: saveCollapsedPref,
        setSectionExpanded: setSectionExpanded,
        trapFocusInPanel: trapFocusInPanel
    };

    function bindGlobalA11yShortcuts() {
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            const form = document.getElementById('os-form-container');
            if (form && form.classList.contains('os-form-visible') && typeof global.closeOSForm === 'function') {
                global.closeOSForm();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initHighContrastFromStorage();
            initDashPanelDefaults();
            bindGlobalA11yShortcuts();
        });
    } else {
        initHighContrastFromStorage();
        initDashPanelDefaults();
        bindGlobalA11yShortcuts();
    }
})(typeof window !== 'undefined' ? window : globalThis);
