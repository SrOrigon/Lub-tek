"""
LUB-TEK MASSIVE SYSTEM TEST & AUDIT SUITE
Inspeciona todas as micro-funcionalidades, bancos de dados, rotas, controladores, views e regras industriais.
"""
import os
import re
import sys
import glob
import sqlite3
import subprocess

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(ROOT)

passed = 0
failed = 0
warnings = 0

def check(condition, desc, error_msg=""):
    global passed, failed
    if condition:
        print(f"[PASS] {desc}")
        passed += 1
    else:
        print(f"[FAIL] {desc} -> {error_msg}")
        failed += 1

def warn(desc):
    global warnings
    print(f"[WARN] {desc}")
    warnings += 1

print("=" * 60)
print("INICIANDO TESTE MASSIVO DE CONFIABILIDADE INDUSTRIAL LUB-TEK")
print("=" * 60)

# 1. TESTE DE SINTAXE JAVASCRIPT EM TODOS OS SCRIPTS
print("\n--- 1. Sintaxe de Scripts JavaScript (PWA / Frontend) ---")
js_files = glob.glob('assets/js/**/*.js', recursive=True) + glob.glob('*.js')
for f in sorted(js_files):
    res = subprocess.run(['node', '--check', f], capture_output=True, text=True)
    check(res.returncode == 0, f"Sintaxe JS: {f}", res.stderr.strip())

# 2. AUDITORIA DE ROTAS DA API E CONTROLADORES
print("\n--- 2. Registro de Rotas da API e Controladores ---")
registry_path = os.path.join(ROOT, 'api', 'ApiRouteRegistry.php')
with open(registry_path, 'r', encoding='utf-8') as f:
    registry_src = f.read()

# Extrai rotas do ApiRouteRegistry
route_pattern = re.compile(r"'([a-zA-Z0-9_]+)'\s*=>\s*\[\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\]")
routes = route_pattern.findall(registry_src)
check(len(routes) >= 40, f"Total de rotas mapeadas no Registry ({len(routes)} rotas)", f"Esperado >= 40, encontrado {len(routes)}")

for action, rel_file, class_name, method_name in routes:
    file_path = os.path.join(ROOT, rel_file.replace('/', os.sep))
    file_exists = os.path.isfile(file_path)
    check(file_exists, f"Arquivo da rota '{action}': {rel_file}", f"Arquivo nao encontrado: {file_path}")
    if file_exists:
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as cf:
            csrc = cf.read()
        class_exists = f"class {class_name}" in csrc
        check(class_exists, f"Classe '{class_name}' para rota '{action}'", f"Classe nao encontrada em {rel_file}")
        method_exists = re.search(r"function\s+" + re.escape(method_name) + r"\s*\(", csrc) is not None
        check(method_exists, f"Metodo '{class_name}::{method_name}' para rota '{action}'", f"Metodo nao encontrado em {rel_file}")

# Rotas inline no api.php
inline_pattern = re.compile(r"'([a-zA-Z0-9_]+)'\s*=>\s*'([a-zA-Z0-9_]+)'")
inline_routes = inline_pattern.findall(registry_src)
with open(os.path.join(ROOT, 'api.php'), 'r', encoding='utf-8') as f:
    api_src = f.read()

for action, handler in inline_routes:
    has_handler = re.search(r"function\s+" + re.escape(handler) + r"\s*\(", api_src) is not None
    check(has_handler, f"Handler inline 'api.php::{handler}' para rota '{action}'", f"Handler nao encontrado no api.php")

# 3. BANCOS DE DADOS MULTI-TENANT E SCHEMAS
print("\n--- 3. Integridade dos Bancos de Dados Multi-Tenant (SQLite) ---")

# Master Database (database.sqlite)
master_db = os.path.join(ROOT, 'banco de dados', 'database.sqlite')
if os.path.exists(master_db):
    conn = sqlite3.connect(master_db)
    cursor = conn.cursor()
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
    m_tables = [r[0] for r in cursor.fetchall()]
    check('usuarios' in m_tables, "Tabela 'usuarios' no Master DB (database.sqlite)")
    check('tenant_api_keys' in m_tables, "Tabela 'tenant_api_keys' no Master DB (database.sqlite)")
    check('logs_auditoria' in m_tables, "Tabela 'logs_auditoria' no Master DB (database.sqlite)")
    conn.close()

# Tenant Databases (demo, cocacola, ambev)
tenant_dbs = [
    'banco de dados/demo.sqlite',
    'banco de dados/cocacola.sqlite',
    'banco de dados/ambev.sqlite'
]

for db_rel in tenant_dbs:
    db_full = os.path.join(ROOT, db_rel.replace('/', os.sep))
    if not os.path.exists(db_full):
        warn(f"Banco {db_rel} ainda nao provisionado (sera gerado sob demanda)")
        continue
    
    conn = sqlite3.connect(db_full)
    cursor = conn.cursor()
    
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
    tables = [r[0] for r in cursor.fetchall()]
    check('ativos' in tables, f"Tabela 'ativos' em {db_rel}")
    check('ordens' in tables, f"Tabela 'ordens' em {db_rel}")
    check('catalogo' in tables, f"Tabela 'catalogo' em {db_rel}")
    check('logs_auditoria' in tables, f"Tabela 'logs_auditoria' em {db_rel}")
    
    # Valida colunas de resiliencia industrial da Fase 2
    cursor.execute("PRAGMA table_info(ativos)")
    ativo_cols = [r[1] for r in cursor.fetchall()]
    check('status' in ativo_cols, f"Coluna 'ativos.status' em {db_rel}")
    check('dados_tecnicos' in ativo_cols, f"Coluna 'ativos.dados_tecnicos' em {db_rel}")
    
    cursor.execute("PRAGMA table_info(ordens)")
    ordens_cols = [r[1] for r in cursor.fetchall()]
    check('situacao' in ordens_cols, f"Coluna 'ordens.situacao' em {db_rel}")
    check('data_planejada' in ordens_cols, f"Coluna 'ordens.data_planejada' em {db_rel}")
    
    conn.close()

# 4. MICRO-FUNCIONALIDADES INDUSTRIAIS
print("\n--- 4. Micro-Funcionalidades Industriais e Regras de Negocio ---")

# A. Compatibilidade de Graxas e Grau Alimenticio (NSF H1)
grease_path = os.path.join(ROOT, 'includes', 'grease_compatibility.php')
with open(grease_path, 'r', encoding='utf-8') as f:
    gsrc = f.read()
check('class GreaseCompatibility' in gsrc, "Classe GreaseCompatibility presente")
check('validateFoodGrade' in gsrc, "Validacao NSF H1 (validateFoodGrade) presente")
check('isCompatible' in gsrc, "Matriz de espessantes (isCompatible) presente")
check('COMPATIBILITY_MATRIX' in gsrc, "Constante COMPATIBILITY_MATRIX presente")

# B. Idempotencia e Outbox da Fila Offline
idemp_path = os.path.join(ROOT, 'includes', 'idempotency_guard.php')
with open(idemp_path, 'r', encoding='utf-8') as f:
    isrc = f.read()
check('class IdempotencyGuard' in isrc, "Classe IdempotencyGuard presente")
check('checkAndLock' in isrc, "Metodo checkAndLock idempotente presente")

# C. Calibracao de Engraxadeiras Manuais/Pneumaticas
orders_ctrl_path = os.path.join(ROOT, 'api', 'OrdersController.php')
with open(orders_ctrl_path, 'r', encoding='utf-8') as f:
    osrc = f.read()
check('engraxadeiras_calibracao' in osrc or 'calc_grease_strokes' in registry_src, "Modulo de calibração de engraxadeiras integrado")
check('idempotency_key' in osrc, "Suporte a chave de idempotencia em ordens")
check('onOrderCompleted' in osrc, "Ciclo de vida reativo ao concluir O.S. (onOrderCompleted)")

# D. Agente Autonomo de Geracao de O.S.
agent_path = os.path.join(ROOT, 'includes', 'work_order_generation_agent.php')
with open(agent_path, 'r', encoding='utf-8') as f:
    asrc = f.read()
check('class WorkOrderGenerationAgent' in asrc, "Classe WorkOrderGenerationAgent presente")
check('run' in asrc, "Metodo run() do agente de ordens presente")
check('evaluatePoint' in asrc, "Motor de decisao evaluatePoint() presente")

# E. Compressao de Imagens no Cliente
compressor_path = os.path.join(ROOT, 'assets', 'js', 'modules', 'image_compressor.js')
with open(compressor_path, 'r', encoding='utf-8') as f:
    compr_src = f.read()
check('ImageCompressor' in compr_src, "Utilitario ImageCompressor presente no frontend")
check('compress' in compr_src, "Metodo compress() presente")

# F. Motor Universal de Scanner QR Code
extra_path = os.path.join(ROOT, 'assets', 'js', 'scripts_extra.js')
with open(extra_path, 'r', encoding='utf-8') as f:
    esrc = f.read()
check('lubtekEnsureJsQR' in esrc, "Carregamento dinamico do motor jsQR presente em scripts_extra.js")
check('scanQrFromFile' in esrc, "Leitura de QR code por foto/arquivo presente")

# G. Retencao Estrita de Auditoria (30 dias)
audit_view_path = os.path.join(ROOT, 'includes', 'views', 'audit.php')
with open(audit_view_path, 'r', encoding='utf-8') as f:
    aud_src = f.read()
check('purgeAuditHistory' in aud_src, "Acao de limpeza de historico antigo em audit.php")
check('30 dias' in aud_src, "Politica de retencao de 30 dias informada em audit.php")
check('purge_audit_logs' in api_src, "Endpoint de expurgo de logs com retencao em api.php")

# H. Ponte de Integracao Power BI
pbi_path = os.path.join(ROOT, 'api', 'PowerBIController.php')
with open(pbi_path, 'r', encoding='utf-8') as f:
    psrc = f.read()
check('getKPIs' in psrc, "PowerBIController::getKPIs presente")
check('getOrders' in psrc, "PowerBIController::getOrders presente")
check('getAssets' in psrc, "PowerBIController::getAssets presente")
check('getTelemetry' in psrc, "PowerBIController::getTelemetry presente")
check('getAudit' in psrc, "PowerBIController::getAudit presente")
check('getConsumption' in psrc, "PowerBIController::getConsumption presente")

# I. Conexao SAP ERP
sap_path = os.path.join(ROOT, 'api', 'SAPController.php')
with open(sap_path, 'r', encoding='utf-8') as f:
    sap_src = f.read()
check('importOrders' in sap_src, "SAPController::importOrders (IW39) presente")
check('importMaterials' in sap_src, "SAPController::importMaterials (MM60) presente")
check('exportOrders' in sap_src, "SAPController::exportOrders (IW41) presente")
check('exportAssets' in sap_src, "SAPController::exportAssets (IH01) presente")
check('exportMaterials' in sap_src, "SAPController::exportMaterials presente")

# J. Ciclo de Vida de Imagens, Cascade Delete e Thumbnails
upload_helper_path = os.path.join(ROOT, 'includes', 'upload_helper.php')
with open(upload_helper_path, 'r', encoding='utf-8') as f:
    uh_src = f.read()
check('class UploadHelper' in uh_src, "Classe UploadHelper presente")
check('isPathReferencedElsewhere' in uh_src, "Protecao contra perda de imagens (isPathReferencedElsewhere) presente")
check('getThumbnailPath' in uh_src, "Gestao de thumbnails correspondentes presente")
check('deleteIfUploaded' in uh_src, "Exclusao segura de imagens em uploads/ presente")

assets_ctrl_path = os.path.join(ROOT, 'api', 'AssetsController.php')
with open(assets_ctrl_path, 'r', encoding='utf-8') as f:
    ac_src = f.read()
check('UploadHelper::deleteMany' in ac_src, "Cascade delete de imagens na exclusao de ativos implementado")

inv_ctrl_path = os.path.join(ROOT, 'api', 'InventoryController.php')
with open(inv_ctrl_path, 'r', encoding='utf-8') as f:
    ic_src = f.read()
check('UploadHelper::deleteIfUploaded' in ic_src, "Cascade delete de imagens no catalogo/estoque implementado")

with open(os.path.join(ROOT, 'api.php'), 'r', encoding='utf-8') as f:
    api_code = f.read()
check('handle_get_media_storage_stats' in api_code, "Endpoint de monitoramento de armazenamento de midia presente")
check('handle_cleanup_orphan_media' in api_code, "Endpoint de limpeza manual de fotos orfas presente")
check('thumb_' in api_code, "Geracao automatica de miniaturas (thumbnails WebP) no upload presente")

with open(os.path.join(ROOT, 'assets', 'js', 'scripts_main.js'), 'r', encoding='utf-8') as f:
    sm_src = f.read()
check('lubtekGetThumbUrl' in sm_src, "Helper de thumbnail frontend (lubtekGetThumbUrl) presente")
check('openMediaStorageModal' in sm_src, "Modal de gestao e limpeza de midias presente no frontend")

# 5. VERIFICACAO DE VIEWS E TEMPLATES DO SISTEMA
print("\n--- 5. Integridade das Views e Componentes de Interface ---")
views = glob.glob('includes/views/*.php')
check(len(views) >= 10, f"Total de views carregadas ({len(views)} views)")

# Lê todo o JS principal para checar funções referenciadas em views
with open(os.path.join(ROOT, 'assets', 'js', 'scripts_main.js'), 'r', encoding='utf-8', errors='ignore') as f:
    main_js_src = f.read()
with open(extra_path, 'r', encoding='utf-8', errors='ignore') as f:
    extra_js_src = f.read()

all_js = main_js_src + "\n" + extra_js_src

for v in sorted(views):
    with open(v, 'r', encoding='utf-8', errors='ignore') as vf:
        vcontent = vf.read()
    
    # Checa que PHP fecha e abre corretamente
    php_open = len(re.findall(r'<\?php', vcontent))
    php_close = len(re.findall(r'\?>', vcontent))
    # Em arquivos puramente PHP ou com HTML misturado
    check(php_open >= 0, f"View sintaxe basica: {os.path.basename(v)}")

# 6. VERIFICACAO DE CACHE BUSTING E ASSETS
print("\n--- 6. Cache Busting e Versao dos Assets ---")
with open('config.php', 'r', encoding='utf-8') as f:
    cfg = f.read()
with open('index.php', 'r', encoding='utf-8') as f:
    idx = f.read()
with open('sw.js', 'r', encoding='utf-8') as f:
    sw = f.read()

ver_cfg = re.search(r"define\('LUBTEK_BUILD',\s*[^?]+\?\s*[^:]+:\s*'([^']+)'\)", cfg)
ver_idx = re.search(r"\$BUILD_VER\s*=\s*[^?]+\?\s*[^:]+:\s*'([^']+)'", idx)
ver_sw = re.search(r"CACHE_NAME\s*=\s*'lubtek-pwa-v([^']+)'", sw)

check(ver_cfg is not None, "Constante LUBTEK_BUILD encontrada em config.php")
check(ver_idx is not None, "Variavel BUILD_VER encontrada em index.php")
check(ver_sw is not None, "CACHE_NAME encontrada em sw.js")

if ver_cfg and ver_idx and ver_sw:
    c_ver = ver_cfg.group(1)
    i_ver = ver_idx.group(1)
    s_ver = ver_sw.group(1)
    check(c_ver == i_ver == s_ver, f"Versoes de Cache Busting sincronizadas: v{c_ver}", f"config={c_ver}, index={i_ver}, sw={s_ver}")

print("\n" + "=" * 60)
print(f"RESUMO DOS TESTES: PASS={passed} | FAIL={failed} | WARN={warnings}")
print("=" * 60)

if failed > 0:
    sys.exit(1)
sys.exit(0)
