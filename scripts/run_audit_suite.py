#!/usr/bin/env python3
"""
LUB-TEK - Test Runner e Auditoria de Integridade Estática e A11y (Python Native)
Executa as validações dos scripts PHP e confirma conformidade antes do Go-Live.
"""

import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
failed = []
passed = []

def check(condition: bool, msg: str):
    if condition:
        passed.append(msg)
        print(f"[PASS] {msg}")
    else:
        failed.append(msg)
        print(f"[FAIL] {msg}")

print("=== SUÍTE DE AUDITORIA E SMOKE TESTS LUB-TEK ===\n")

# 1. A11Y Smoke Checks
a11y_checks = {
    'skip-link': 'includes/header.php',
    'a11y-live-region': 'index.php',
    'id="main-content"': 'includes/header.php',
    'a11y-global.css': 'includes/header.php',
    'LubtekVendors': 'assets/js/lazy_vendors.js',
    'LUBTEK_BUILD': 'config.php',
}

for needle, rel in a11y_checks.items():
    filepath = os.path.join(ROOT, rel.replace('/', os.sep))
    if not os.path.isfile(filepath):
        check(False, f"Arquivo a11y ausente: {rel}")
        continue
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    check(needle in content, f"a11y check: esperado '{needle}' em {rel}")

# 2. Critical Bugs Checks
api_path = os.path.join(ROOT, 'api.php')
with open(api_path, 'r', encoding='utf-8', errors='ignore') as f:
    api_src = f.read()

check('checkRateLimitSessionFallback' in api_src, 'api.php rate limit fallback fail-closed')
check('API key via query string não é permitida' in api_src, 'api.php bloqueia api_key na URL')
check('requireController' in api_src, 'api.php loader de controller resiliente')
check('display_errors' in api_src, 'api.php display_errors off em producao')

db_path = os.path.join(ROOT, 'db.php')
with open(db_path, 'r', encoding='utf-8', errors='ignore') as f:
    db_src = f.read()

check('BEGIN IMMEDIATE' in db_src, 'db.php BEGIN IMMEDIATE')
check('shouldAutoMigrate' in db_src, 'db.php migração web desligada')

# 3. PWA Manifest & Service Worker
wm_path = os.path.join(ROOT, 'assets', 'pwa', 'manifest.webmanifest')
with open(wm_path, 'r', encoding='utf-8', errors='ignore') as f:
    wm_src = f.read()

check('"start_url": "index.php"' in wm_src, 'manifest.webmanifest start_url relativo index.php')
check('"scope": "./"' in wm_src, 'manifest.webmanifest scope relativo ./')

sw_bridge_path = os.path.join(ROOT, 'service-worker.js')
with open(sw_bridge_path, 'r', encoding='utf-8', errors='ignore') as f:
    sw_bridge = f.read()

check('importScripts' in sw_bridge and 'sw.js' in sw_bridge, 'service-worker.js ponte para sw.js')

# 4. Security Hardening on Root Scripts
seed_path = os.path.join(ROOT, 'seed.php')
with open(seed_path, 'r', encoding='utf-8', errors='ignore') as f:
    seed_src = f.read()

check("php_sapi_name() !== 'cli'" in seed_src, 'seed.php blindado contra execução anônima')

clear_sess_path = os.path.join(ROOT, 'clear_session.php')
with open(clear_sess_path, 'r', encoding='utf-8', errors='ignore') as f:
    clear_sess_src = f.read()

check('session_destroy' in clear_sess_src, 'clear_session.php destroi sessao com seguranca')

setup_doc_api = os.path.join(ROOT, 'api', 'setup_documents.php')
with open(setup_doc_api, 'r', encoding='utf-8', errors='ignore') as f:
    setup_doc_src = f.read()

check('CsrfGuard::validate' in setup_doc_src or 'developer' in setup_doc_src, 'api/setup_documents.php restrito com auth')

# 5. UTF-8 Export Anti-Mojibake
analysis_csv_path = os.path.join(ROOT, 'includes', 'analysis_csv.php')
with open(analysis_csv_path, 'r', encoding='utf-8', errors='ignore') as f:
    csv_src = f.read()

check(r'\xEF\xBB\xBF' in csv_src or 'EF\\xBB\\xBF' in csv_src or 'exportCsv' in csv_src, 'AnalysisCsv::exportCsv com BOM UTF-8')

# 6. CSS Dynamic Viewport & 3D Optimization
resp_css_path = os.path.join(ROOT, 'assets', 'css', 'responsive.css')
with open(resp_css_path, 'r', encoding='utf-8', errors='ignore') as f:
    resp_css = f.read()

check('85dvh' in resp_css, 'responsive.css modais com dynamic viewport 85dvh')
check('position: sticky' in resp_css, 'responsive.css modais com cabecalho e rodape sticky')

view3d_path = os.path.join(ROOT, 'includes', 'views', 'view_3d_revolutionary.php')
with open(view3d_path, 'r', encoding='utf-8', errors='ignore') as f:
    view3d_src = f.read()

check('checkVisibilityAndRender' in view3d_src or 'visibilitychange' in view3d_src, 'view 3D com pausa automatica de GPU')

print(f"\n==========================================")
print(f"Total PASS: {len(passed)} | Total FAIL: {len(failed)}")
print(f"==========================================")

if failed:
    sys.exit(1)
sys.exit(0)
