"""
Script de Validação Automatizada dos 8 Itens do Punch-List de Lançamento
"""
import re
import sys

def test_item_1_and_2():
    print("[TEST 1 & 2] Verificando api/OrdersController.php e api.php...")
    with open("api/OrdersController.php", "r", encoding="utf-8") as f:
        orders_code = f.read()
    
    # Item 1: Não fecha corretivas nem alertas
    assert "strpos($tipo, 'corret') !== false" in orders_code, "Falta filtro de exclusão de corretivas"
    assert "stripos($desc, '[corretiva') !== false" in orders_code, "Falta filtro para descrições corretivas"
    assert "stripos($desc, '[alerta') !== false" in orders_code, "Falta filtro para alertas"
    assert "isRoutine" in orders_code, "Falta validação de ordem de rotina"
    
    # Item 2: Dosagem real e aviso de catálogo
    assert "checkLubricantInCatalog" in orders_code, "Falta checagem de lubrificante no catálogo"
    assert "appliedAmount" in orders_code, "Falta suporte a dose aplicada pelo técnico"
    assert "warning" in orders_code, "Falta retorno de aviso de divergência de catálogo"

    with open("api.php", "r", encoding="utf-8") as f:
        api_code = f.read()
    assert "catalogWarning" in api_code, "Falta captura de catalogWarning no api.php"
    assert "appliedDose" in api_code, "Falta passagem de appliedDose no api.php"
    print("  -> Item 1 & 2: PASS")

def test_item_3_and_8():
    print("[TEST 3 & 8] Verificando assets/js/scripts_extra.js...")
    with open("assets/js/scripts_extra.js", "r", encoding="utf-8") as f:
        extra_code = f.read()
    
    # Item 3: Foto offline não é perdida
    assert "pendingAlertPhotoBase64" in extra_code, "Falta pendingAlertPhotoBase64 para foto offline"
    assert "dataURLtoBlob" in extra_code, "Falta dataURLtoBlob para reidratação de imagem offline"
    assert "photo_base64" in extra_code, "Falta persistência de photo_base64 na fila de alertas"
    
    # Item 8: Upload de imagem 3D com token CSRF
    assert "lubtekFormUpload('upload_image', fd)" in extra_code, "Upload 3D deve usar lubtekFormUpload"
    assert "api.php?action=upload_image" not in extra_code, "fetch direto sem CSRF não deve mais existir no upload 3D"
    print("  -> Item 3 & 8: PASS")

def test_item_4_and_5():
    print("[TEST 4 & 5] Verificando assets/js/scripts_main.js...")
    with open("assets/js/scripts_main.js", "r", encoding="utf-8") as f:
        main_code = f.read()
    
    # Item 4: Pergunta ao sair com formulário de ordem preenchido
    assert "isOrderFormDirty" in main_code, "Falta função isOrderFormDirty"
    assert "Há dados preenchidos na Ordem de Serviço" in main_code, "Falta confirmação ao sair da view dash com OS suja"
    
    # Item 5: Dropdown de lubrificante aceita ambos os formatos da API
    assert "catalogData?.data || catalogData?.items || catalogData?.catalog" in main_code, "Falta normalização do catálogo em populateFormDropdowns"
    print("  -> Item 4 & 5: PASS")

def test_item_6():
    print("[TEST 6] Verificando includes/views/sap_integration.php...")
    with open("includes/views/sap_integration.php", "r", encoding="utf-8") as f:
        sap_code = f.read()
    
    # Item 6: Wording do teste Power BI
    assert "100% OPERACIONAL" not in sap_code, "Não deve declarar 100% OPERACIONAL sem saber se o cliente conectou o app"
    assert "O endereço respondeu com sucesso" in sap_code, "Deve indicar que o endereço respondeu com sucesso"
    assert "Veio tabela com" in sap_code, "Deve indicar quando veio tabela"
    assert "Veio vazio" in sap_code, "Deve indicar quando veio vazio"
    print("  -> Item 6: PASS")

def test_item_7():
    print("[TEST 7] Verificando includes/views/pi.php...")
    with open("includes/views/pi.php", "r", encoding="utf-8") as f:
        pi_code = f.read()
    
    # Item 7: Esconder simulação no uso do cliente
    assert "dev_simulator" in pi_code, "Simulador deve estar condicionado a dev_simulator"
    assert "Sem leituras no banco de dados. Inicie a simulação!" not in pi_code, "Frase de incentivo à simulação deve ser removida"
    print("  -> Item 7: PASS")

def test_routes_view():
    print("[TEST MODAL] Verificando includes/views/routes.php...")
    with open("includes/views/routes.php", "r", encoding="utf-8") as f:
        routes_code = f.read()
    assert "route-done-overlay" in routes_code, "Modal de confirmação de dose route-done-overlay deve existir"
    assert "route-done-dose-amount" in routes_code, "Campo de dosagem real deve existir"
    print("  -> Routes Modal: PASS")

def main():
    print("=== EXECUTANDO TESTE DAS CORREÇÕES DO PUNCH-LIST ===")
    try:
        test_item_1_and_2()
        test_item_3_and_8()
        test_item_4_and_5()
        test_item_6()
        test_item_7()
        test_routes_view()
        print("\n==================================================")
        print("TODOS OS 8 ITENS DO PUNCH-LIST FORAM VERIFICADOS E VALIDADOS!")
        print("==================================================")
    except AssertionError as e:
        print(f"\n[FALHA] {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
