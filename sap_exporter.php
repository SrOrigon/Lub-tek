<?php
/**
 * LUB-TEK - SAP PM Export Script (Technical Completion - TECO)
 * Execução exclusiva via CLI (Cron Job).
 * Uso: php sap_exporter.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Acesso negado. Execute via SSH/Cron.\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/tenant.php';

echo "=== INICIANDO EXPORTAÇÃO NOTURNA PARA SAP PM ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

function exportTenantToSAP(?string $tenant): void {
    $label = $tenant ?? 'admin';
    echo "[$label] Mapeando Ordens Concluídas...\n";

    try {
        $db = DB::getInstance($tenant);
        
        // Coleta ordens concluídas nas últimas 24 horas
        $stmt = $db->prepare("
            SELECT o.id, o.descricao, o.responsavel, o.ativo_id, o.materiais, 
                   o.horas_exec, o.minutos_exec, o.data_conclusao, a.tag as equipamento_sap
            FROM ordens o
            LEFT JOIN ativos a ON o.ativo_id = a.id
            WHERE o.situacao = 'Concluído' 
              AND date(o.data_conclusao) = date('now')
        ");
        $stmt->execute();
        $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($ordens)) {
            echo "  -> Nenhuma OS para exportar hoje.\n";
            return;
        }

        $sapPayload = [];

        foreach ($ordens as $os) {
            // Conversão de tempo para decimal (ex: 1h30m = 1.5)
            $horasBase = (int)($os['horas_exec'] ?? 0);
            $minutosBase = (int)($os['minutos_exec'] ?? 0);
            $actualWork = round($horasBase + ($minutosBase / 60), 2);

            // Estrutura padrão SAP BAPI / OData
            $sapOrder = [
                'ExternalOrderID' => 'LUBTEK-' . $os['id'],
                'EquipmentTag'    => $os['equipamento_sap'] ?: 'N/A',
                'ShortText'       => $os['descricao'],
                'SystemStatus'    => 'TECO', 
                'ActualWork'      => $actualWork,
                'WorkCenter'      => $os['responsavel'],
                'CompletionDate'  => date('Ymd', strtotime($os['data_conclusao'])),
                'Components'      => []
            ];

            // Processamento do consumo de inventário
            $materiais = json_decode($os['materiais'] ?? '[]', true);
            if (is_array($materiais)) {
                foreach ($materiais as $mat) {
                    $sapOrder['Components'][] = [
                        'Material' => $mat['material'] ?? $mat['nome'] ?? '',
                        'Quantity' => (float)($mat['qtd'] ?? 0),
                        'Unit'     => $mat['unid'] ?? 'UN'
                    ];
                }
            }

            $sapPayload[] = $sapOrder;
        }

        // 1. Gera o arquivo JSON (Gêmeo do envio)
        $exportDir = __DIR__ . '/backups/sap_exports';
        if (!is_dir($exportDir)) mkdir($exportDir, 0755, true);
        
        $filename = $exportDir . "/sap_teco_{$label}_" . date('Ymd_His') . ".json";
        file_put_contents($filename, json_encode(['Orders' => $sapPayload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 2. AQUI ENTRA A INTEGRAÇÃO REAL (Descomente e configure conforme o cliente)
        /*
        $ch = curl_init('https://sap-gateway.cliente.com/api/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['Orders' => $sapPayload]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('SAP_API_TOKEN')
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        */

        echo "  -> [OK] " . count($ordens) . " Ordens empacotadas em $filename\n";

    } catch (Exception $e) {
        echo "  -> [ERRO] " . $e->getMessage() . "\n";
    }
}

// Executa para o banco principal e todos os clientes
exportTenantToSAP(null);
foreach (TenantResolver::listTenantSlugs() as $slug) {
    exportTenantToSAP($slug);
}

echo "\n=== INTEGRAÇÃO CONCLUÍDA ===\n";
