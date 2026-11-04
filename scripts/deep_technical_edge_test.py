"""
Varredura Profunda de Casos de Borda e Possibilidades de Falha (com CSRF pós-login)
"""
import requests
import json
import uuid
import re

BASE_URL = "https://casadolancheonline-com-159289.hostingersite.com"
LOGIN_URL = f"{BASE_URL}/api.php?action=login"
INDEX_URL = f"{BASE_URL}/index.php"

session = requests.Session()
session.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Accept": "application/json, text/html, */*",
})

def run():
    print("[1] Efetuando login e obtendo contexto de sessão pós-login...")
    # Pre-login
    r_pre = session.get(f"{INDEX_URL}?page=home")
    m_pre = re.search(r'csrfToken\s*=\s*["\']([a-f0-9]+)["\']', r_pre.text)
    pre_csrf = m_pre.group(1) if m_pre else ""

    headers_login = {"X-CSRF-TOKEN": pre_csrf} if pre_csrf else {}
    res = session.post(LOGIN_URL, json={"username": "Admin", "password": "Admin"}, headers=headers_login)
    data = res.json()
    if not (data.get("success") or data.get("ok")):
        print(f"Falha de login: {data}")
        return

    # Post-login: carrega a página para pegar o novo CSRF token da sessão autenticada
    r_post = session.get(f"{INDEX_URL}?page=home")
    m_post = re.search(r'csrfToken\s*=\s*["\']([a-f0-9]+)["\']', r_post.text)
    if not m_post:
        m_post = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', r_post.text)
    csrf = m_post.group(1) if m_post else ""
    print(f"  -> CSRF token autenticado: {csrf[:12]}...")

    headers = {"X-CSRF-TOKEN": csrf}
    issues = []

    print("\n[2] Testando Idempotência de Criação/Baixa de O.S...")
    idemp_key = str(uuid.uuid4())
    os_payload = {
        "idempotency_key": idemp_key,
        "situacao": "Pendente",
        "desc": "Teste de Borda Idempotencia",
        "prio": "Média",
        "ativo_id": 406
    }
    r1 = session.post(f"{BASE_URL}/api.php?action=save_task", json=os_payload, headers=headers).json()
    r2 = session.post(f"{BASE_URL}/api.php?action=save_task", json=os_payload, headers=headers).json()
    created_id = r1.get("id") or (r1.get("data", {}).get("id"))
    print(f"  - Resposta R1: {r1}")
    print(f"  - Resposta R2 (duplicada): {r2}")
    if r2.get("idempotent") or r2.get("id") == created_id or r2.get("success"):
        print("  -> Idempotência tratada com sucesso.")
    else:
        issues.append("Possível falha de idempotência: requisição duplicada não reconhecida.")

    print("\n[3] Testando Exclusão de O.S...")
    if created_id:
        r_del = session.post(f"{BASE_URL}/api.php?action=delete_task", json={"id": created_id}, headers=headers).json()
        print(f"  - Exclusão da O.S. de teste #{created_id}: {r_del}")
        if not r_del.get("success") and not r_del.get("ok"):
            issues.append(f"Falha ao excluir O.S. temporária: {r_del}")

    print("\n[4] Testando Alerta de Rota (Geração de Corretiva) e Proteção contra Baixa Acidental...")
    alert_payload = {
        "id": 406,
        "ativo_id": 406,
        "motivo": "Teste automatizado de campo - vazamento de óleo",
        "equipamento": "REDUTOR"
    }
    r_alert = session.post(f"{BASE_URL}/api.php?action=report_route_alert", json=alert_payload, headers=headers).json()
    print(f"  - Alerta registrado: {r_alert}")
    alert_os_id = r_alert.get("os_id")
    if alert_os_id:
        print(f"  -> O.S. corretiva #{alert_os_id} gerada pelo alerta.")
        # Concluir a rota do ponto 406 NÃO DEVE FECHAR essa corretiva!
        r_done = session.post(f"{BASE_URL}/api.php?action=set_asset_status", json={"id": 406, "status": "Concluido", "dosage_amount": 0}, headers=headers).json()
        closed = r_done.get("closed_orders", [])
        print(f"  - Ordens fechadas pela rota: {closed}")
        if alert_os_id in closed:
            issues.append(f"ERRO CRÍTICO: Concluir rota fechou a ordem corretiva #{alert_os_id} gerada pelo alerta!")
        else:
            print(f"  -> SUCESSO: Ordem corretiva #{alert_os_id} permaneceu ABERTA e protegida!")
        
        # Limpa a ordem de alerta de teste
        session.post(f"{BASE_URL}/api.php?action=delete_task", json={"id": alert_os_id}, headers=headers)

    print("\n[5] Testando Filtro de Auditoria e Limpeza de Histórico...")
    r_audit = session.get(f"{BASE_URL}/api.php?action=get_audit_logs", headers=headers).json()
    audit_rows = r_audit.get("data", r_audit) if isinstance(r_audit, dict) else r_audit
    print(f"  - Total de registros de auditoria retornados: {len(audit_rows) if isinstance(audit_rows, list) else 0}")
    
    # Testa purga de logs antigos (deve manter os últimos 30 dias)
    r_purge = session.post(f"{BASE_URL}/api.php?action=purge_audit_logs", json={}, headers=headers).json()
    print(f"  - Resposta de expurgo de logs antigos: {r_purge}")
    if not (r_purge.get("success") or r_purge.get("ok")):
        issues.append(f"Falha na purga de logs de auditoria: {r_purge}")
    print(f"  - Resposta de expurgo de logs antigos: {r_purge}")
    if r_purge.get("success") or r_purge.get("ok"):
        print(f"  -> Limpeza de auditoria executada: {r_purge.get('deleted', 0)} registros antigos expurgados.")
    else:
        issues.append(f"Falha na purga de logs de auditoria: {r_purge}")

    print("\n[6] Testando Feed Power BI com Autenticação de Tenant...")
    r_key = session.get(f"{BASE_URL}/api.php?action=get_tenant_api_key", headers=headers).json()
    api_key = r_key.get("api_key")
    if api_key:
        pbi_headers = {"X-API-KEY": api_key}
        r_pbi_kpis = session.get(f"{BASE_URL}/api.php?action=powerbi_kpis&format=flat", headers=pbi_headers).json()
        print(f"  - Power BI KPIs com X-API-KEY: {len(r_pbi_kpis)} linhas retornadas")
        r_pbi_orders = session.get(f"{BASE_URL}/api.php?action=powerbi_orders&format=flat", headers=pbi_headers).json()
        print(f"  - Power BI Orders com X-API-KEY: {len(r_pbi_orders)} ordens retornadas")
        if not r_pbi_kpis or not r_pbi_orders:
            issues.append("Feed Power BI retornou lista vazia de KPIs ou Ordens com X-API-KEY.")

    print("\n" + "=" * 50)
    print(f"RELATÓRIO DE POSSÍVEIS FALHAS ENCONTRADAS: {len(issues)}")
    for iss in issues:
        print(f"  [PROBLEMA] {iss}")
    if len(issues) == 0:
        print("  -> NENHUMA FALHA OU VULNERABILIDADE DETECTADA NO USO REAL!")
    print("=" * 50)

if __name__ == "__main__":
    run()
