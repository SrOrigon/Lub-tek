"""
Script de Validação Automatizada do Punch-List e Refinamentos Finais de Lançamento
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

    # Refinamento 1: Ordem preventiva com prioridade crítica NÃO é pulada
    assert "strpos($prio, 'crit')" not in orders_code, "Prioridade crítica não deve impedir baixa de preventiva"
    assert "strpos($prio, 'emerg')" not in orders_code, "Prioridade de emergência genérica não deve pular rotina"

    # Refinamento 2: Ordem manual sem tipo (em branco) NÃO é baixada por engano
    assert "$tipo === ''" not in orders_code, "Ordem com tipo vazio não pode ser considerada rotina genericamente"
    
    # Item 2: Dosagem real e aviso de catálogo
    assert "checkLubricantInCatalog" in orders_code, "Falta checagem de lubrificante no catálogo"
    assert "appliedAmount" in orders_code, "Falta suporte a dose aplicada pelo técnico"
    assert "warning" in orders_code, "Falta retorno de aviso de divergência de catálogo"

    with open("api.php", "r", encoding="utf-8") as f:
        api_code = f.read()
    assert "catalogWarning" in api_code, "Falta captura de catalogWarning no api.php"
    assert "appliedDose" in api_code, "Falta passagem de appliedDose no api.php"
    print("  -> Item 1 & 2 (Refinados): PASS")

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

def test_refinements_route_feedback_and_dose():
    print("[TEST REFINEMENTS] Verificando feedback do servidor e dose sugerida...")
    with open("assets/js/scripts_extra.js", "r", encoding="utf-8") as f:
        extra_code = f.read()
    
    # Refinamento 3: Aguarda servidor antes do aviso e distingue se havia ordem ou apenas intervenção
    assert "saveRouteStatusDirect" in extra_code, "Deve aguardar saveRouteStatusDirect no modo online"
    assert "closedOrders.length > 0" in extra_code, "Deve diferenciar fechamento de ordens de apenas atualização de data"
    assert "Próxima lubrificação atualizada" in extra_code, "Deve informar atualização da próxima intervenção quando não há O.S."

    # Refinamento 4: Não sugerir 20g quando o ponto não tem dose
    assert "p.quantidade : 20" not in extra_code, "Não deve sugerir 20 quando o ponto não tem dose"
    assert "hasDoseInput" in extra_code, "Deve validar se técnico informou dose"

    with open("includes/views/routes.php", "r", encoding="utf-8") as f:
        routes_code = f.read()
    assert 'placeholder="Ex: 20"' not in routes_code, "Placeholder não deve sugerir 20g fixo"
    print("  -> Refinamentos de Feedback e Dose: PASS")

def main():
    print("=== EXECUTANDO TESTE DAS CORREÇÕES E REFINAMENTOS ===")
    try:
        test_item_1_and_2()
        test_item_3_and_8()
        test_item_4_and_5()
        test_item_6()
        test_item_7()
        test_refinements_route_feedback_and_dose()
        print("\n==================================================")
        print("TODOS OS REQUISITOS E REFINAMENTOS FORAM VALIDADOS COM SUCESSO!")
        print("==================================================")
    except AssertionError as e:
        print(f"\n[FALHA] {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
