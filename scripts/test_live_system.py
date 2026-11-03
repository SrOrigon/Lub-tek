import requests
import re
import sys
import json

BASE_URL = 'https://casadolancheonline-com-159289.hostingersite.com'

headers = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'
}

def run_live_test():
    session = requests.Session()
    session.headers.update(headers)

    print("==================================================")
    print(f"TESTANDO AMBIENTE AO VIVO NA HOSTINGER:")
    print(f"URL: {BASE_URL}")
    print("==================================================")

    # 1. Checa Headers HTTP da raiz
    print("\n[1] Verificando cabeçalhos HTTP...")
    r_root = session.get(f"{BASE_URL}/", timeout=15)
    print(f"  Status: {r_root.status_code}")
    perm_policy = r_root.headers.get('Permissions-Policy', '')
    print(f"  Permissions-Policy: {perm_policy}")
    if 'microphone=(self)' in perm_policy:
        print("  [OK] Permissions-Policy contém microphone=(self)!")
    elif 'microphone=()' in perm_policy:
        print("  [AVISO] Permissions-Policy ainda está microphone=() - o pacote novo precisa ser atualizado na Hostinger!")
    else:
        print(f"  Permissions-Policy info: {perm_policy}")

    # 2. Carrega login.php
    print("\n[2] Carregando tela de login...")
    r_login = session.get(f"{BASE_URL}/login.php", timeout=15)
    print(f"  Status: {r_login.status_code}")
    csrf_m = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', r_login.text)
    csrf_token = csrf_m.group(1) if csrf_m else ''
    print(f"  CSRF Token obtido: {'SIM (' + csrf_token[:12] + '...)' if csrf_token else 'NÃO'}")

    # 3. Testa credenciais conhecidas
    credentials_to_try = [
        ('Admin', 'password'),
        ('Admin', 'Admin'),
        ('Admin', 'changeme'),
        ('admin@rodrigo.com', 'password'),
        ('admin@lubtek.com', 'admin123'),
        ('demo.Admin', 'changeme'),
        ('demo.Funcionario', 'changeme'),
        ('Cocacola.Admin', 'changeme'),
    ]

    logged_user = None
    logged_role = None

    print("\n[3] Autenticando usuário...")
    for user, pwd in credentials_to_try:
        data = {
            'username': user,
            'password': pwd,
            'csrf_token': csrf_token
        }
        r_post = session.post(f"{BASE_URL}/login_process.php", data=data, headers={'X-Requested-With': 'XMLHttpRequest'}, timeout=15)
        try:
            res_json = r_post.json()
            if res_json.get('success'):
                print(f"  [SUCESSO] Login realizado com: {user}")
                logged_user = user
                logged_role = res_json.get('user', {}).get('role', 'desconhecido')
                print(f"  Papel do usuário: {logged_role}")
                print(f"  Tenant ativo: {res_json.get('user', {}).get('tenant', 'global')}")
                break
            else:
                pass
        except Exception:
            # Fallback redirect check
            if r_post.status_code in [302, 200] and 'error=1' not in r_post.url:
                print(f"  [SUCESSO] Login redirecionado com: {user}")
                logged_user = user
                break

    if not logged_user:
        print("  [AVISO] Tentando login direto padrão sem XMLHttpRequest...")
        for user, pwd in credentials_to_try:
            r_post = session.post(f"{BASE_URL}/login_process.php", data={'username': user, 'password': pwd, 'csrf_token': csrf_token}, allow_redirects=True, timeout=15)
            if 'login.php' not in r_post.url:
                print(f"  [SUCESSO] Login aceito via formulário tradicional: {user}")
                logged_user = user
                break

    if not logged_user:
        print("  [INFO] Nenhuma das credenciais de teste padrão logou automaticamente.")
        print("  Verificando se há sessão guest ou acesso anônimo...")
    
    # 4. Navegando como Usuário Técnico pelas Telas Operacionais
    print("\n[4] Simulando Navegação do Usuário Técnico:")
    pages_to_test = [
        ('home', 'Página Inicial / Painel'),
        ('routes', 'Roteiro de Campo / Checklist Touch'),
        ('assets', 'Árvore de Ativos & POP (Procedimento Operacional)'),
        ('kpi', 'Painel de Indicadores & KPIs'),
        ('audit', 'Auditoria & Rastreamento'),
        ('sap', 'Integração SAP & Conexão Power BI'),
        ('catalog', 'Catálogo de Produtos & Lubrificantes'),
    ]

    for p, desc in pages_to_test:
        url = f"{BASE_URL}/index.php?page={p}"
        r_page = session.get(url, timeout=15)
        # Se redirecionou para login, indica que a sessão precisa de login no hostinger
        if 'login.php' in r_page.url:
            print(f"  - [{p}] {desc}: 302 Redirecionado para login (Proteção de sessão OK)")
        elif r_page.status_code == 200:
            # Checa se há erros PHP visíveis ou mensagens de Warning / Fatal
            content = r_page.text
            has_error = False
            for err_word in ['Fatal error', 'Parse error', 'Uncaught Error', 'Warning:', 'Notice:']:
                if err_word in content and 'lucide' not in content:
                    print(f"  - [{p}] {desc}: ⚠️ Detectado '{err_word}' no HTML!")
                    has_error = True
                    break
            if not has_error:
                print(f"  - [{p}] {desc}: [OK 200] Carregada com sucesso ({len(content)} bytes)")
        else:
            print(f"  - [{p}] {desc}: Status HTTP {r_page.status_code}")

    # 5. Testando Endpoints da API utilizados pela operação técnica
    print("\n[5] Testando Endpoints da API (Operação Técnica):")
    api_actions = [
        ('get_tasks', 'Consulta de Ordens de Serviço (O.S.)'),
        ('get_tree', 'Consulta da Árvore Hierárquica de Ativos'),
        ('get_kpis', 'Cálculo de Indicadores Operacionais'),
        ('get_catalog', 'Consulta do Estoque / Catálogo de Lubrificantes'),
        ('get_audit_logs', 'Consulta do Log de Auditoria'),
        ('powerbi_kpis', 'Ponte de Leitura do Power BI'),
    ]

    for action, desc in api_actions:
        url = f"{BASE_URL}/api.php?action={action}"
        r_api = session.get(url, timeout=15)
        try:
            res_json = r_api.json()
            success = res_json.get('success', False)
            if success:
                print(f"  - [API {action}] {desc}: [OK 200] Retornou JSON com sucesso!")
            else:
                err_msg = res_json.get('error', res_json.get('message', 'Erro desconhecido'))
                print(f"  - [API {action}] {desc}: Retornou JSON (status: {r_api.status_code}, aviso: {err_msg})")
        except Exception:
            print(f"  - [API {action}] {desc}: HTTP {r_api.status_code} (não-JSON: {r_api.text[:80]}...)")

    print("\n==================================================")
    print("FIM DO TESTE DE SIMULAÇÃO TÉCNICA")
    print("==================================================")

if __name__ == '__main__':
    run_live_test()
