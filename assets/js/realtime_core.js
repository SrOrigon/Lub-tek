// ==========================================
// --- 1.2 CORE UTILS & ICONS ---
if (typeof lucide === 'undefined') {
    window.lucide = { createIcons: () => { } }; // Mock until loaded
}
// REAL-TIME ECOSYSTEM CORE
// Central State Manager & Event Bus
// ==========================================

/**
 * GLOBAL STATE MANAGER
 * Centralized state management for the entire application
 */
class StateManager {
    constructor() {
        this.state = {
            user: null,
            assets: [],
            tasks: [],
            catalog: [],
            kpis: null,
            marketStats: null,
            currentAsset: null,
            lastSync: null
        };
        this.listeners = {};
        this.syncTimestamps = {};
    }

    // Get state value
    get(key) {
        return this.state[key];
    }

    // Set state value and notify listeners
    set(key, value) {
        const oldValue = this.state[key];
        this.state[key] = value;
        this.syncTimestamps[key] = Date.now();
        this.notify(key, value, oldValue);
        return value;
    }

    // Subscribe to state changes
    subscribe(key, callback) {
        if (!this.listeners[key]) {
            this.listeners[key] = [];
        }
        this.listeners[key].push(callback);

        // Return unsubscribe function
        return () => {
            this.listeners[key] = this.listeners[key].filter(cb => cb !== callback);
        };
    }

    // Notify all listeners of a state change
    notify(key, newValue, oldValue) {
        if (this.listeners[key]) {
            this.listeners[key].forEach(callback => {
                try {
                    callback(newValue, oldValue);
                } catch (error) {
                    // Error in state listener - silenced
                }
            });
        }
    }

    // Batch update multiple state keys
    batchUpdate(updates) {
        Object.keys(updates).forEach(key => {
            this.set(key, updates[key]);
        });
    }

    // Get last sync timestamp for a key
    getLastSync(key) {
        return this.syncTimestamps[key] || 0;
    }

    // Check if data is stale (older than maxAge in ms)
    isStale(key, maxAge = 30000) {
        const lastSync = this.getLastSync(key);
        return (Date.now() - lastSync) > maxAge;
    }
}

/**
 * EVENT BUS
 * Global pub/sub system for application events
 */
class EventBus {
    constructor() {
        this.events = {};
    }

    // Subscribe to an event
    on(event, callback) {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        this.events[event].push(callback);

        return () => {
            this.events[event] = this.events[event].filter(cb => cb !== callback);
        };
    }

    // Emit an event
    emit(event, data) {
        if (this.events[event]) {
            this.events[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    // Error in event handler - silenced
                }
            });
        }
    }

    // One-time event listener
    once(event, callback) {
        const wrapper = (data) => {
            callback(data);
            this.off(event, wrapper);
        };
        this.on(event, wrapper);
    }

    // Remove all listeners for an event
    off(event, callback) {
        if (this.events[event]) {
            if (callback) {
                this.events[event] = this.events[event].filter(cb => cb !== callback);
            } else {
                delete this.events[event];
            }
        }
    }
}

/**
 * AUTO-REFRESH MANAGER
 * Manages automatic data refreshing for all components
 */
class AutoRefreshManager {
    constructor(stateManager, eventBus) {
        this.state = stateManager;
        this.events = eventBus;
        this.intervals = {};
        this.activeRefreshers = new Map();
        this.runningTasks = new Set();
    }

    // Register a auto-refresh task
    register(name, callback, interval = 30000, immediate = true) {
        // Clear existing if any
        this.unregister(name);

        // Execute immediately if requested
        if (immediate) {
            this.executeRefresh(name, callback);
        }

        // Set up interval
        const intervalId = setInterval(() => {
            this.executeRefresh(name, callback);
        }, interval);

        this.intervals[name] = {
            intervalId,
            callback,
            interval,
            lastRun: immediate ? Date.now() : null
        };

        // console.log(`✅ Auto-refresh registered: ${name} (${interval}ms)`);
    }

    // Execute a refresh task
    async executeRefresh(name, callback) {
        // Evita chamadas sobrepostas: se um refresh para este "name" ainda estiver
        // em andamento (ex: rede lenta) quando o próximo intervalo disparar, pula
        // essa execução em vez de empilhar requisições concorrentes.
        if (this.runningTasks.has(name)) {
            return;
        }
        this.runningTasks.add(name);
        try {
            const startTime = Date.now();
            await callback();
            const duration = Date.now() - startTime;

            if (this.intervals[name]) {
                this.intervals[name].lastRun = Date.now();
            }

            this.events.emit('refresh:complete', { name, duration });

            if (duration > 1000) {
                // console.warn(`⚠️ Slow refresh: ${name} took ${duration}ms`);
            }
        } catch (error) {
            // console.warn(`❌ Error in auto-refresh ${name}:`, error);
            this.events.emit('refresh:error', { name, error });
        } finally {
            this.runningTasks.delete(name);
        }
    }

    // Unregister a auto-refresh task
    unregister(name) {
        if (this.intervals[name]) {
            clearInterval(this.intervals[name].intervalId);
            delete this.intervals[name];
            // console.log(`🛑 Auto-refresh unregistered: ${name}`);
        }
        // Libera o "lock" de execução para não bloquear um re-registro imediato
        // (ex: stop()/start() em sequência) caso uma chamada anterior ainda esteja em voo.
        this.runningTasks.delete(name);
    }

    // Pause auto-refresh (e.g., when tab is inactive)
    pauseAll() {
        Object.keys(this.intervals).forEach(name => {
            if (this.intervals[name].intervalId) {
                clearInterval(this.intervals[name].intervalId);
            }
        });
        // console.log('⏸️ All auto-refreshes paused');
    }

    // Resume auto-refresh
    resumeAll() {
        Object.keys(this.intervals).forEach(name => {
            const config = this.intervals[name];
            // Fix: Clear existing interval to avoid duplication
            if (config.intervalId) clearInterval(config.intervalId);

            config.intervalId = setInterval(() => {
                this.executeRefresh(name, config.callback);
            }, config.interval);
        });
        // console.log('▶️ All auto-refreshes resumed');
    }

    // Get status of all refreshers
    getStatus() {
        return Object.keys(this.intervals).map(name => ({
            name,
            interval: this.intervals[name].interval,
            lastRun: this.intervals[name].lastRun,
            nextRun: this.intervals[name].lastRun + this.intervals[name].interval
        }));
    }
}

/**
 * CHANGE DETECTOR
 * Detects changes in data and triggers updates
 */
class ChangeDetector {
    constructor(eventBus) {
        this.events = eventBus;
        this.checksums = {};
    }

    // Calculate simple checksum of data
    checksum(data) {
        return JSON.stringify(data).length + JSON.stringify(data).split('').reduce((a, b) => {
            a = ((a << 5) - a) + b.charCodeAt(0);
            return a & a;
        }, 0);
    }

    // Check if data has changed
    hasChanged(key, data) {
        const newChecksum = this.checksum(data);
        const oldChecksum = this.checksums[key];

        if (oldChecksum === undefined || newChecksum !== oldChecksum) {
            this.checksums[key] = newChecksum;
            return true;
        }

        return false;
    }

    // Mark data as changed
    markChanged(key) {
        delete this.checksums[key];
    }
}

// ==========================================
// INITIALIZE GLOBAL ECOSYSTEM
// ==========================================

// Create global instances
window.AppState = new StateManager();
window.AppEvents = new EventBus();
window.AutoRefresh = new AutoRefreshManager(window.AppState, window.AppEvents);
window.ChangeDetector = new ChangeDetector(window.AppEvents);

// Lifecycle events
// LIFECYCLE EVENTS
window.addEventListener('load', () => {
    window.AppEvents.emit('app:ready');
    // console.log('🌐 Real-Time Ecosystem initialized'); // SILENT MODE
});

// Handle page visibility (pause when hidden, resume when visible)
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        window.AutoRefresh.pauseAll();
        window.AppEvents.emit('app:paused');
    } else {
        window.AutoRefresh.resumeAll();
        window.AppEvents.emit('app:resumed');
    }
});

// Log all events in dev mode - DISABLED FOR CLEAN CONSOLE
/*
if (window.location.hostname === 'localhost' || window.location.hostname.includes('127.0.0.1')) {
    window.AppEvents.on('*', (data) => {
        console.log('📡 Event:', data);
    });
}
*/

// console.log('✅ Real-Time Ecosystem Core loaded'); // SILENT MODE

// GLOBAL ERROR HANDLER (Safety Net)
// GLOBAL ERROR HANDLER (Safety Net)
window.onerror = function (msg, url, lineNo, columnNo, error) {
    // Ignore common harmless errors
    if (msg && msg.includes('ResizeObserver')) return true;

    // Log debug info but do not alert the user
    // console.debug('Global Error:', msg);

    return false;
};

window.onunhandledrejection = function (event) {
    // console.error('Unhandled Promise Rejection:', event.reason);
    if (typeof showToast === 'function') {
        // Don't spam toasts for network errors, they are handled in api()
        if (event.reason && (event.reason.message || '').includes('Network')) return;
        showToast('Erro de processamento assíncrono.', 'error');
    }
};
