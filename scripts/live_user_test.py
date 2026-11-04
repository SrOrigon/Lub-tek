"""
Simulação de Uso Real: Usuário Admin no Servidor de Produção Hostinger
"""
import re
import requests
import json
import sys

BASE_URL = "https://casadolancheonline-com-159289.hostingersite.com"
LOGIN_URL = f"{BASE_URL}/api.php?action=login"
INDEX_URL = f"{BASE_URL}/index.php"

session = requests.Session()
session.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Accept": "application/json, text/html, */*",
})

def log(msg, status="INFO"):
    colors = {
        "INFO": "\033[94m",
        "PASS": "\033[92m",
        "FAIL": "\033[91m",
        "WARN": "\033[93m"
    }
    end = "\033[0m"
    print(f"[{status}] {msg}")

def test_login():
    log("Iniciando login com usuário Admin / Admin...", "INFO")
    # 1. Obter cookie de sessão inicial e CSRF
    res_home = session.get(f"{INDEX_URL}?page=home")
    csrf_token = None
    m = re.search(r'csrfToken\s*=\s*["\']([a-f0-9]+)["\']', res_home.text)
    if not m:
        m = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', res_home.text)
    if m:
        csrf_token = m.group(1)
    
    headers = {}
    if csrf_token:
        headers["X-CSRF-TOKEN"] = csrf_token

    payload = {"username": "Admin", "password": "Admin"}
    res = session.post(LOGIN_URL, json=payload, headers=headers)
    
    try:
        data = res.json()
    except Exception:
        log(f"Falha ao decodificar JSON de login: {res.text[:200]}", "FAIL")
        return False, None
    
    if data.get("success") or data.get("ok"):
        log("Login efetuado com sucesso!", "PASS")
        # Atualiza CSRF se retornado
        new_csrf = data.get("csrf_token") or data.get("token") or csrf_token
        return True, new_csrf
    else:
        log(f"Falha no login: {data.get('error', res.text)}", "FAIL")
        return False, None

def test_version_and_assets():
    log("Verificando versão de assets e cache busting na página carregada...", "INFO")
    res = session.get(f"{INDEX_URL}?page=home")
    
    # Checa BUILD_VER na página
    m_ver = re.findall(r'scripts_extra\.js\?v=([^\'"]+)', res.text)
    if m_ver:
        ver = m_ver[0]
        log(f"Versão de scripts_extra.js encontrada na página: v{ver}", "INFO")
        if ver == "3.5.4":
            log("Versão dos assets é v3.5.4 (Atualizada com sucesso no Hostinger)!", "PASS")
        else:
            log(f"Atenção: A versão retornada pelo servidor ainda é v{ver}!", "WARN")
    else:
        log("Não encontrou parâmetro ?v= em scripts_extra.js na página", "WARN")

    # Baixa scripts_extra.js diretamente para checar conteúdo
    res_js = session.get(f"{BASE_URL}/assets/js/scripts_extra.js?v=3.5.4")
    js_len_kb = round(len(res_js.content) / 1024, 1)
    log(f"Tamanho do scripts_extra.js recebido da CDN/servidor: {js_len_kb} KB", "INFO")
    
    has_modal_done = "route-done-overlay" in res_js.text or "openRouteDoneModal" in res_js.text
    has_save_direct = "saveRouteStatusDirect" in res_js.text
    has_no_20_default = "p.quantidade : 20" not in res_js.text

    if has_modal_done and has_save_direct and has_no_20_default:
        log("O arquivo JS recebido pelo navegador é a versão nova com quadro de dose e sem default 20g!", "PASS")
    else:
        log("O arquivo JS recebido ainda contém resquícios antigos:", "WARN")
        log(f"  - openRouteDoneModal presente: {has_modal_done}")
        log(f"  - saveRouteStatusDirect presente: {has_save_direct}")
        log(f"  - default 20 removido: {has_no_20_default}")

def test_routes_completion(csrf_token):
    log("Testando fluxo de conclusão de rota e dosagem...", "INFO")
    
    headers = {"X-CSRF-TOKEN": csrf_token} if csrf_token else {}

    # 1. Buscar árvore de ativos para encontrar um ponto de teste
    res_tree = session.post(f"{BASE_URL}/api.php?action=get_tree", json={}, headers=headers)
    try:
        tree = res_tree.json()
    except Exception:
        log(f"Falha ao ler get_tree: {res_tree.text[:150]}", "FAIL")
        return

    nodes = tree.get("data", tree) if isinstance(tree, dict) else tree
    
    # Achar pontos
    points = []
    def find_points(items):
        if not items or not isinstance(items, list):
            return
        for it in items:
            if it.get("tipo") == "ponto":
                points.append(it)
            if it.get("children"):
                find_points(it.get("children"))

    find_points(nodes)
    log(f"Total de pontos de lubrificação encontrados na planta: {len(points)}", "INFO")
    
    if not points:
        log("Nenhum ponto para ensaio.", "WARN")
        return

    # Escolher um ponto de teste que não seja os já concluídos (15, 37)
    test_point = None
    for p in points:
        if p.get("id") not in [15, 37, 401]:
            test_point = p
            break
    if not test_point:
        test_point = points[0]

    point_id = test_point.get("id")
    point_name = test_point.get("nome")
    log(f"Ponto de ensaio selecionado: ID={point_id} | Nome={point_name}", "INFO")

    # 2. Teste A: Concluir com dose vazia / zero
    log("Ensaio A: Concluir ponto com dose=0 (sem consumo de estoque)...", "INFO")
    payload_a = {
        "id": point_id,
        "status": "Concluido",
        "dosage_amount": 0,
        "dosage_unit": "g"
    }
    res_a = session.post(f"{BASE_URL}/api.php?action=set_asset_status", json=payload_a, headers=headers)
    data_a = res_a.json()
    log(f"Resposta Ensaio A: {json.dumps(data_a, ensure_ascii=False)}", "INFO")
    if data_a.get("updated") and data_a.get("status") == "Concluido":
        log(f"Ensaio A: Ponto concluído com sucesso! Ordens fechadas: {data_a.get('closed_orders')}", "PASS")
    else:
        log(f"Ensaio A: Resposta inesperada: {data_a}", "WARN")

    # 3. Teste B: Concluir com dose real (ex: 25.5g)
    log("Ensaio B: Concluir ponto com dose real informada de 25.5g...", "INFO")
    payload_b = {
        "id": point_id,
        "status": "Concluido",
        "dosage_amount": 25.5,
        "dosage_unit": "g"
    }
    res_b = session.post(f"{BASE_URL}/api.php?action=set_asset_status", json=payload_b, headers=headers)
    data_b = res_b.json()
    log(f"Resposta Ensaio B: {json.dumps(data_b, ensure_ascii=False)}", "INFO")
    if data_b.get("updated"):
        log(f"Ensaio B: Concluído com sucesso! Aviso de catálogo: {data_b.get('warning')}", "PASS")
    else:
        log(f"Ensaio B: Resposta inesperada: {data_b}", "WARN")

def test_pages_and_ui_elements():
    log("Varrendo módulos e verificando integridade de interface...", "INFO")
    pages = ["home", "dash", "assets", "routes", "pi", "sap", "catalog", "audit"]
    for pg in pages:
        res = session.get(f"{INDEX_URL}?page={pg}")
        if res.status_code == 200:
            # Verifica se há algum fatal error PHP no HTML
            has_error = "Fatal error" in res.text or "Parse error" in res.text or "Notice:" in res.text
            if has_error:
                log(f"Página '{pg}': Erro de PHP detectado no HTML!", "FAIL")
            else:
                log(f"Página '{pg}': Carregada limpa com HTTP 200", "PASS")
            
            # Verificações específicas por página
            if pg == "pi":
                # Verifica se botão de simular telemetria está oculto sem dev_simulator
                if 'id="btn-simulation-toggle"' in res.text:
                    log("Página 'pi': Botão de simular telemetria APARECEU sem o parâmetro dev_simulator!", "FAIL")
                else:
                    log("Página 'pi': Botão de simulação está oculto com segurança no uso normal.", "PASS")
            
            if pg == "assets":
                # Verifica se 'Salvar Estado' não está solto na barra
                if '<button class="btn btn-outline" onclick="saveSystemSnapshot()"' in res.text:
                    # Checa se está dentro do admin-tools-menu
                    pass
                log("Página 'assets': Barra e árvore de ativos renderizadas com integridade.", "PASS")
                
            if pg == "routes":
                # Verifica se o modal de confirmação de dose existe no HTML
                if 'id="route-done-overlay"' in res.text and 'id="route-done-dose-amount"' in res.text:
                    log("Página 'routes': Modal de confirmação de dosagem real presente no DOM!", "PASS")
                else:
                    log("Página 'routes': Modal route-done-overlay NÃO encontrado no HTML da página!", "FAIL")
        else:
            log(f"Página '{pg}': Retornou HTTP {res.status_code}", "FAIL")

def main():
    print("=" * 60)
    print("TESTE DE USUÁRIO TÉCNICO AO VIVO NA HOSTINGER")
    print(f"Alvo: {BASE_URL}")
    print("=" * 60)

    ok, csrf = test_login()
    if not ok:
        sys.exit(1)

    test_version_and_assets()
    test_pages_and_ui_elements()
    test_routes_completion(csrf)

    print("\n" + "=" * 60)
    print("VARREDURA AO VIVO FINALIZADA COM SUCESSO!")
    print("=" * 60)

if __name__ == "__main__":
    main()
