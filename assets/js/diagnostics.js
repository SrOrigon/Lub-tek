/**
 * FULL STACK DIAGNOSTIC TOOL
 * Checks the entire communication flow
 */

async function runDiagnostics() {
    console.clear();
    console.log('🚀 STARTING FULL STACK DIAGNOSTICS...');

    const results = {
        api: {},
        functions: {},
        state: {}
    };

    // 1. CHECK API CONNECTIVITY
    console.group('1. API Connectivity');
    const endpoints = [
        'get_stats',
        'get_tasks',
        'get_tree',
        'get_market_data'
    ];

    for (const ep of endpoints) {
        try {
            const start = performance.now();
            const res = await api(ep);
            const time = (performance.now() - start).toFixed(2);

            if (res) {
                console.log(`✅ ${ep}: OK (${time}ms)`);
                results.api[ep] = true;
            } else {
                console.error(`❌ ${ep}: No Data`);
                results.api[ep] = false;
            }
        } catch (e) {
            console.error(`❌ ${ep}: Error`, e);
            results.api[ep] = false;
        }
    }
    console.groupEnd();

    // 2. CHECK KEY FUNCTIONS
    console.group('2. Key Functions');
    const fns = [
        'renderTasks',
        'loadTree',
        'loadDash',
        'updateCharts',
        'api'
    ];

    for (const fn of fns) {
        if (typeof window[fn] === 'function') {
            console.log(`✅ ${fn}: Exists`);
            results.functions[fn] = true;
        } else {
            console.error(`❌ ${fn}: MISSING`);
            results.functions[fn] = false;
        }
    }
    console.groupEnd();

    // 3. CHECK STATE
    console.group('3. App State');
    try {
        const state = window.AppState.get();
        console.log('State:', state);
        if (state) {
            console.log('✅ AppState: Active');
            results.state.active = true;
        } else {
            console.error('❌ AppState: Inactive');
            results.state.active = false;
        }
    } catch (e) {
        console.error('❌ AppState Error', e);
        results.state.active = false;
    }
    console.groupEnd();

    // 4. REPORT
    console.log('🏁 DIAGNOSTICS COMPLETE');
    return results;
}

// Expose
window.runDiagnostics = runDiagnostics;
console.log('🔧 Diagnostics loaded. Run window.runDiagnostics() to test.');
