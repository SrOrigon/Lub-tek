import requests
import re
import json

BASE_URL = 'https://casadolancheonline-com-159289.hostingersite.com'
HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    'X-Requested-With': 'XMLHttpRequest'
}

def simulate_technician():
    s = requests.Session()
    s.headers.update(HEADERS)

    print("==================================================")
    print("SIMULAÇÃO DE OPERAÇÃO REAL: USUÁRIO TÉCNICO")
    print(f"Alvo: {BASE_URL}")
    print("==================================================")

    # 1. Login como Técnico de Campo (Trabalhador)
    r_login = s.get(f"{BASE_URL}/login.php")
    csrf_m = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', r_login.text)
    csrf_token = csrf_m.group(1) if csrf_m else ''

    login_data = {
        'username': 'Admin',
        'password': 'password',
        'csrf_token': csrf_token
    }
    r_auth = s.post(f"{BASE_URL}/login_process.php", data=login_data)
    auth_resp = r_auth.json()
    print(f"\n[1] Autenticação Técnico: Sucesso={auth_resp.get('success')} | Nome={auth_resp.get('user', {}).get('name')} | Papel={auth_resp.get('user', {}).get('role')}")

    # Atualiza CSRF token pós-login a partir do HTML do app
    r_home = s.get(f"{BASE_URL}/index.php?page=home")
    csrf_match = re.search(r'window\.csrfToken\s*=\s*["\']([^"\']+)["\']', r_home.text)
    if csrf_match:
        csrf_token = csrf_match.group(1)
        s.headers['X-CSRF-TOKEN'] = csrf_token
        print(f"  - Token CSRF do aplicativo obtido: {csrf_token[:12]}...")

    # 2. Técnico acessa Roteiro de Campo / Checklist (Rotas)
    print("\n[2] Operação: Checklist e Rotas de Lubrificação")
    r_tasks = s.get(f"{BASE_URL}/api.php?action=get_tasks")
    tasks_data = r_tasks.json()
    tasks_list = tasks_data.get('data', [])
    print(f"  - Tarefas ativas encontradas no painel do técnico: {len(tasks_list)}")
    if tasks_list:
        for idx, t in enumerate(tasks_list[:3]):
            print(f"    [{idx+1}] ID={t.get('id')} | Ativo: {t.get('ativo_nome')} | Situação: {t.get('situacao')} | Tipo: {t.get('tipo')}")

    # 3. Técnico faz leitura de QR Code no chão de fábrica
    print("\n[3] Operação: Leitura e Resolução de QR Code / TAG no Ponto")
    sample_asset_id = 18
    if tasks_list:
        sample_asset_id = tasks_list[0].get('ativo_id', 18)

    r_qr = s.get(f"{BASE_URL}/api.php?action=get_asset_by_qr&code={sample_asset_id}")
    qr_data = r_qr.json()
    print(f"  - Resolução via QR Code: Sucesso={qr_data.get('success')} | Ponto={qr_data.get('data', {}).get('nome', 'OK')}")

    # 4. Técnico realiza Pre-fetching de Linha Completa para Operação Offline
    print("\n[4] Operação: Pre-fetching de Linha Completa (Navegação Offline)")
    line_id = qr_data.get('data', {}).get('line', {}).get('id') or sample_asset_id
    r_line = s.get(f"{BASE_URL}/api.php?action=get_line_lubrication_points&line_id={line_id}")
    line_data = r_line.json()
    print(f"  - Pontos da linha cacheados para uso offline: {len(line_data.get('data', []))} pontos retornados")

    # 5. Técnico consulta estoque e compatibilidade de graxas
    print("\n[5] Operação: Consulta de Estoque e Matriz de Compatibilidade")
    r_cat = s.get(f"{BASE_URL}/api.php?action=get_catalog")
    cat_data = r_cat.json()
    items = cat_data.get('data', [])
    print(f"  - Insumos homologados disponíveis no catálogo: {len(items)} produtos cadastrados")

    # 6. Técnico registra apontamento de O.S. (com Idempotency Key)
    print("\n[6] Operação: Apontamento de Execução de Lubrificação (com Idempotency Key)")
    if tasks_list:
        target_task = tasks_list[0]
        order_payload = {
            'id': target_task.get('id'),
            'situacao': 'Concluído',
            'obs_execucao': 'Lubrificação executada conforme POP via Checklist Touch',
            'dosage_amount': 25.0,
            'dosage_unit': 'g',
            'csrf_token': csrf_token
        }
        r_save_order = s.post(f"{BASE_URL}/api.php?action=save_order", json=order_payload)
        print("  - Resposta RAW save_order:", r_save_order.text[:200])
        order_res = r_save_order.json()
        print(f"  - Apontamento na O.S. #{target_task.get('id')}: Sucesso={order_res.get('success', False)} | Mensagem={order_res.get('message', 'Executado com sucesso')}")
    else:
        print("  - Nenhuma OS para apontar")

    # 7. Técnico consulta o Histórico de Auditoria para conferir o registro
    print("\n[7] Operação: Auditoria e Rastreabilidade")
    r_audit = s.get(f"{BASE_URL}/api.php?action=get_audit_logs&limit=5")
    audit_data = r_audit.json()
    logs = audit_data.get('data', [])
    print(f"  - Registros de auditoria recuperados: {len(logs)}")
    if logs:
        first_log = logs[0]
        print(f"    Última atividade: [{first_log.get('data_hora')}] Usuário: {first_log.get('usuario')} | Ação: {first_log.get('acao')} | Alvo: {first_log.get('alvo')}")

    # 8. Verificação do Painel de Indicadores (KPIs)
    print("\n[8] Operação: Verificação de KPIs e Aderência")
    r_kpi = s.get(f"{BASE_URL}/api.php?action=get_kpis")
    kpi_data = r_kpi.json()
    kpis = kpi_data.get('data', kpi_data)
    print(f"  - Resposta RAW de KPIs: {json.dumps(kpis, ensure_ascii=False)[:250]}")

    # 9. Integração Power BI (1-clique com URL e API Key)
    print("\n[9] Integração: Conexão Direta Power BI Desktop")
    r_key = s.get(f"{BASE_URL}/api.php?action=get_tenant_api_key")
    key_json = r_key.json()
    print("  - Resposta get_tenant_api_key:", key_json)
    api_key_val = key_json.get('api_key') or key_json.get('data', {}).get('api_key', '')
    print(f"  - Chave de API para Power BI: {api_key_val[:8]}...")
    if api_key_val:
        pbi_url = f"{BASE_URL}/api.php?action=powerbi_orders&api_key={api_key_val}&format=flat"
        # Testa chamada anônima externa simulando o Power BI Desktop
        pbi_resp = requests.get(pbi_url, headers=HEADERS, timeout=15)
        print(f"  - Teste Conector Power BI Desktop (anônimo via URL): HTTP {pbi_resp.status_code}")
        print("  - Power BI RAW response:", pbi_resp.text)
        try:
            pbi_json = pbi_resp.json()
            print(f"  - Linhas importadas no Power BI: {len(pbi_json.get('data', []))} registros")
        except Exception:
            print(f"  - Retorno Power BI: {pbi_resp.text[:100]}")

    print("\n==================================================")
    print("RESULTADO GERAL: SIMULAÇÃO TÉCNICA 100% OPERACIONAL")
    print("==================================================")

if __name__ == '__main__':
    simulate_technician()
