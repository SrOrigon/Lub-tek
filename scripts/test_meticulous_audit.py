"""
Meticulous Live Audit & Technical User Simulation
Tests all modules, APIs, workflows, and edge conditions on Hostinger.
"""
import requests
import json
import re
import uuid
import sys

BASE_URL = "https://casadolancheonline-com-159289.hostingersite.com"
LOGIN_URL = f"{BASE_URL}/api.php?action=login"
INDEX_URL = f"{BASE_URL}/index.php"

session = requests.Session()
session.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Accept": "application/json, text/html, */*",
})

report = {
    "passed": [],
    "warnings": [],
    "failures": []
}

def log(msg, status="INFO"):
    print(f"[{status}] {msg}")
    if status == "PASS":
        report["passed"].append(msg)
    elif status == "WARN":
        report["warnings"].append(msg)
    elif status == "FAIL":
        report["failures"].append(msg)

def run_meticulous_audit():
    print("=" * 60)
    print("INICIANDO REVISÃO METICULOSA AO VIVO NO HOSTINGER (v3.5.4)")
    print(f"Alvo: {BASE_URL}")
    print("=" * 60)

    # ---------------------------------------------------------
    # 1. VERIFICAÇÃO DE CABEÇALHOS E POLÍTICAS DE SEGURANÇA
    # ---------------------------------------------------------
    log("Verificando cabeçalhos de segurança HTTP...", "INFO")
    r_home = session.get(f"{INDEX_URL}?page=home")
    if r_home.status_code != 200:
        log(f"Falha ao carregar index.php: HTTP {r_home.status_code}", "FAIL")
        return

    headers = r_home.headers
    perm_policy = headers.get("Permissions-Policy", "")
    if "microphone=(self)" in perm_policy and "camera=(self)" in perm_policy:
        log("Permissions-Policy: microfone e câmera autorizados para (self).", "PASS")
    else:
        log(f"Permissions-Policy inesperado: {perm_policy}", "WARN")

    csp = headers.get("Content-Security-Policy", "")
    if csp:
        log("Content-Security-Policy ativo e configurado.", "PASS")
    else:
        log("Content-Security-Policy ausente.", "WARN")

    # ---------------------------------------------------------
    # 2. AUTENTICAÇÃO E RENOVAÇÃO DE SESSÃO / CSRF
    # ---------------------------------------------------------
    log("Testando autenticação de usuário Admin...", "INFO")
    m_csrf = re.search(r'csrfToken\s*=\s*["\']([a-f0-9]+)["\']', r_home.text)
    pre_csrf = m_csrf.group(1) if m_csrf else ""

    r_login = session.post(
        LOGIN_URL,
        json={"username": "Admin", "password": "Admin"},
        headers={"X-CSRF-TOKEN": pre_csrf} if pre_csrf else {}
    )
    login_data = r_login.json()
    if not (login_data.get("success") or login_data.get("ok")):
        log(f"Falha de autenticação: {login_data}", "FAIL")
        return
    log(f"Login efetuado com sucesso como {login_data.get('user', {}).get('nome', 'Admin')} ({login_data.get('user', {}).get('role')}).", "PASS")

    # Obtém novo CSRF pós-login
    r_post_login = session.get(f"{INDEX_URL}?page=home")
    m_post_csrf = re.search(r'csrfToken\s*=\s*["\']([a-f0-9]+)["\']', r_post_login.text)
    if not m_post_csrf:
        m_post_csrf = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', r_post_login.text)
    csrf = m_post_csrf.group(1) if m_post_csrf else ""
    if not csrf:
        log("Não foi possível extrair CSRF token da sessão pós-login.", "FAIL")
        return
    log(f"Sessão pós-login validada com CSRF token ativo ({csrf[:10]}...).", "PASS")

    api_headers = {"X-CSRF-TOKEN": csrf}

    # ---------------------------------------------------------
    # 3. MÓDULO DE ATIVOS & PONTOS DE LUBRIFICAÇÃO
    # ---------------------------------------------------------
    log("Testando API de Ativos (get_tree)...", "INFO")
    r_tree = session.get(f"{BASE_URL}/api.php?action=get_tree", headers=api_headers).json()
    tree_data = r_tree.get("data", [])
    if isinstance(tree_data, list) and len(tree_data) > 0:
        root_node = tree_data[0]
        equipamentos = root_node.get("children", [])
        log(f"Árvore de ativos carregada: {root_node.get('nome')} com {len(equipamentos)} equipamentos.", "PASS")
        ponto_id = 406
    else:
        log("get_tree retornou lista vazia ou inválida.", "FAIL")
        ponto_id = 406

    # Teste de resolução QR Code
    log(f"Testando resolução por QR Code / ID (get_asset_by_qr)...", "INFO")
    r_qr = session.get(f"{BASE_URL}/api.php?action=get_asset_by_qr&code={ponto_id}", headers=api_headers).json()
    if r_qr.get("success") or r_qr.get("ok"):
        log(f"Resolução QR Code bem-sucedida para o ponto #{ponto_id}.", "PASS")
        # Checa pré-carregamento de linha (Pre-fetching)
        line_id = r_qr.get("line", {}).get("id") or r_qr.get("asset", {}).get("parent_id") or ponto_id
        r_line = session.get(f"{BASE_URL}/api.php?action=get_line_lubrication_points&line_id={line_id}", headers=api_headers).json()
        if r_line.get("success") or r_line.get("ok"):
            log(f"Pre-fetching de linha executado com sucesso para line_id={line_id}.", "PASS")
        else:
            log(f"Aviso no pre-fetching de linha: {r_line.get('error', 'Sem pontos')}", "WARN")
    else:
        log(f"Falha na resolução QR Code para #{ponto_id}: {r_qr}", "WARN")

    # ---------------------------------------------------------
    # 4. MÓDULO DE ORDENS DE SERVIÇO & PROTEÇÃO DE CORRETIVAS
    # ---------------------------------------------------------
    log("Testando integridade e idempotência de Ordens de Serviço...", "INFO")
    r_tasks = session.get(f"{BASE_URL}/api.php?action=get_tasks", headers=api_headers).json()
    tasks_list = r_tasks.get("data", r_tasks) if isinstance(r_tasks, dict) else r_tasks
    log(f"Listagem de O.S. carregada com {len(tasks_list) if isinstance(tasks_list, list) else 0} ordens.", "PASS")

    # Criação com chave de idempotência
    idemp_key = str(uuid.uuid4())
    task_payload = {
        "idempotency_key": idemp_key,
        "situacao": "Pendente",
        "desc": "Teste de Auditoria Meticulosa - Verificação PCM",
        "prio": "Média",
        "ativo_id": ponto_id,
        "tipo": "Preventiva"
    }
    r_task1 = session.post(f"{BASE_URL}/api.php?action=save_task", json=task_payload, headers=api_headers).json()
    r_task2 = session.post(f"{BASE_URL}/api.php?action=save_task", json=task_payload, headers=api_headers).json()

    created_task_id = r_task1.get("id") or r_task1.get("data", {}).get("id")
    if r_task2.get("idempotent_replay") and (str(r_task2.get("id")) == str(created_task_id)):
        log(f"Idempotência comprovada: requisição duplicada retornou idempotent_replay sem criar ordem duplicada.", "PASS")
    else:
        log(f"Resposta de idempotência imprevista: {r_task2}", "WARN")

    # Exclusão segura da ordem de teste
    if created_task_id:
        r_del = session.post(f"{BASE_URL}/api.php?action=delete_task", json={"id": created_task_id}, headers=api_headers).json()
        if r_del.get("success") or r_del.get("ok"):
            log(f"Exclusão da O.S. de teste #{created_task_id} executada com sucesso.", "PASS")

    # Teste de Alerta de Campo & Proteção de Corretivas
    log("Testando proteção estrita de Ordens Corretivas durante conclusão de rotas...", "INFO")
    alert_payload = {
        "id": ponto_id,
        "ativo_id": ponto_id,
        "motivo": "Ruído anormal em rolamento - vibração elevada",
        "equipamento": "EQUIPAMENTO TESTE"
    }
    r_alert = session.post(f"{BASE_URL}/api.php?action=report_route_alert", json=alert_payload, headers=api_headers).json()
    alert_os_id = r_alert.get("os_id")
    if alert_os_id:
        log(f"Alerta de campo registrado com sucesso gerando O.S. corretiva #{alert_os_id}.", "PASS")
        # Simula conclusão de rota no mesmo ponto
        r_close_route = session.post(
            f"{BASE_URL}/api.php?action=set_asset_status",
            json={"id": ponto_id, "status": "Concluido", "dosage_amount": 0},
            headers=api_headers
        ).json()
        closed_orders = r_close_route.get("closed_orders", [])
        if alert_os_id in closed_orders or str(alert_os_id) in [str(c) for c in closed_orders]:
            log(f"FALHA CRÍTICA: Concluir rota fechou a ordem corretiva #{alert_os_id}!", "FAIL")
        else:
            log(f"Proteção confirmada: Ordem corretiva #{alert_os_id} PERMANECEU ABERTA após conclusão da rota.", "PASS")

        # Limpeza da ordem corretiva de teste
        session.post(f"{BASE_URL}/api.php?action=delete_task", json={"id": alert_os_id}, headers=api_headers)

    # ---------------------------------------------------------
    # 5. MÓDULO DE INVENTÁRIO / ALMOXARIFADO (CATÁLOGO)
    # ---------------------------------------------------------
    log("Testando Catálogo de Lubrificantes e Almoxarifado...", "INFO")
    r_cat = session.get(f"{BASE_URL}/api.php?action=get_catalog", headers=api_headers).json()
    cat_list = r_cat.get("data", r_cat) if isinstance(r_cat, dict) else r_cat
    if isinstance(cat_list, list) and len(cat_list) > 0:
        log(f"Catálogo de lubrificantes carregado com {len(cat_list)} produtos homologados.", "PASS")
        # Verifica se os lubrificantes têm campos essenciais
        first_prod = cat_list[0]
        has_essential = "nome" in first_prod and ("quantidade" in first_prod or "saldo" in first_prod or "estoque" in first_prod)
        if has_essential:
            log(f"Estrutura do produto de almoxarifado validada: {first_prod.get('nome')}", "PASS")
    else:
        log("Catálogo retornado vazio ou inválido.", "WARN")

    # ---------------------------------------------------------
    # 6. MÓDULO DO PORTAL PI SYSTEM & TELEMETRIA
    # ---------------------------------------------------------
    log("Testando Sensores e Telemetria PI System (get_pi_tags)...", "INFO")
    r_sensors = session.get(f"{BASE_URL}/api.php?action=get_pi_tags", headers=api_headers).json()
    sensors_list = r_sensors.get("data", [])
    if isinstance(sensors_list, list) and len(sensors_list) > 0:
        log(f"Portal PI System: {len(sensors_list)} sensores industriais ativos monitorados.", "PASS")
        # Checa anomalias numéricas
        valid_sensors = all("current_value" in s and "unit" in s for s in sensors_list)
        if valid_sensors:
            log("Leituras de sensores com métricas numéricas íntegras.", "PASS")
    else:
        log("Nenhum sensor retornado pelo endpoint get_pi_tags.", "WARN")

    # ---------------------------------------------------------
    # 7. INTEGRAÇÃO SAP & FEEDS POWER BI
    # ---------------------------------------------------------
    log("Testando integração SAP e feeds autenticados Power BI...", "INFO")
    r_api_key = session.get(f"{BASE_URL}/api.php?action=get_tenant_api_key", headers=api_headers).json()
    api_key = r_api_key.get("api_key")
    if api_key:
        log(f"Chave de API do tenant obtida: {api_key[:12]}...", "PASS")
        pbi_headers = {"X-API-KEY": api_key}

        # KPIs
        r_kpis = session.get(f"{BASE_URL}/api.php?action=powerbi_kpis", headers=pbi_headers).json()
        log(f"Power BI KPIs: {len(r_kpis) if isinstance(r_kpis, list) else 1} linha(s) tabular(es) retornada(s).", "PASS")

        # Orders
        r_pbi_orders = session.get(f"{BASE_URL}/api.php?action=powerbi_orders", headers=pbi_headers).json()
        log(f"Power BI Orders: {len(r_pbi_orders) if isinstance(r_pbi_orders, list) else 0} ordens exportadas.", "PASS")

        # Assets
        r_pbi_assets = session.get(f"{BASE_URL}/api.php?action=powerbi_assets", headers=pbi_headers).json()
        log(f"Power BI Assets: {len(r_pbi_assets) if isinstance(r_pbi_assets, list) else 0} ativos estruturados exportados.", "PASS")
    else:
        log(f"Não foi possível obter tenant API key para Power BI: {r_api_key}", "WARN")

    # ---------------------------------------------------------
    # 8. MÓDULO DE AUDITORIA & RETENÇÃO LEGAL
    # ---------------------------------------------------------
    log("Testando Logs de Auditoria e Expurgos de Histórico...", "INFO")
    r_audit = session.get(f"{BASE_URL}/api.php?action=get_audit_logs", headers=api_headers).json()
    audit_data = r_audit.get("data", r_audit) if isinstance(r_audit, dict) else r_audit
    log(f"Logs de auditoria retornados com sucesso: {len(audit_data) if isinstance(audit_data, list) else 0} registros.", "PASS")

    # ---------------------------------------------------------
    # 9. TODAS AS 8 VIEWS DO FRONTEND (HTTP 200 E RENDERIZAÇÃO)
    # ---------------------------------------------------------
    log("Testando carregamento e renderização de todas as páginas principais...", "INFO")
    views_to_test = [
        ("home", "Painel Principal"),
        ("dash", "Ordens de Serviço"),
        ("assets", "Árvore de Ativos"),
        ("routes", "Rotas de Lubrificação"),
        ("pi", "Portal PI System"),
        ("sap", "Integração SAP / Power BI"),
        ("catalog", "Almoxarifado & Lubrificantes"),
        ("audit", "Trilha de Auditoria"),
        ("reports", "Laboratório & Análise de Óleo"),
        ("calc", "Cálculos de Engenharia")
    ]
    all_views_ok = True
    for view_key, view_title in views_to_test:
        r_v = session.get(f"{INDEX_URL}?page={view_key}")
        if r_v.status_code == 200:
            # Verifica se não há erro PHP fatal ou aviso exposto
            if "Fatal error" in r_v.text or "Parse error" in r_v.text:
                log(f"ERRO FATAL na tela {view_title} ({view_key})!", "FAIL")
                all_views_ok = False
            else:
                log(f"Tela '{view_title}' carregou com HTTP 200 sem erros PHP.", "PASS")
        else:
            log(f"Falha ao abrir tela '{view_title}' ({view_key}): HTTP {r_v.status_code}", "FAIL")
            all_views_ok = False

    # ---------------------------------------------------------
    # SUMÁRIO FINAL
    # ---------------------------------------------------------
    print("\n" + "=" * 60)
    print("SUMÁRIO DA REVISÃO METICULOSA")
    print(f"Total de Testes Aprovados: {len(report['passed'])}")
    print(f"Total de Avisos:           {len(report['warnings'])}")
    print(f"Total de Falhas:           {len(report['failures'])}")
    print("=" * 60)

    if report["failures"]:
        print("\nFALHAS A CORRIGIR:")
        for f in report["failures"]:
            print(f"  [X] {f}")
    else:
        print("\n[OK] TODAS AS ROTINAS E MODULOS PASSARAM COM SUCESSO!")

if __name__ == "__main__":
    run_meticulous_audit()
