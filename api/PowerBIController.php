<?php
/**
 * LUB-TEK - Power BI Integration Controller
 * Fornece dados tabulares estruturados em JSON para conexão com Microsoft Power BI Desktop / Web Gateway.
 * Suporta modo padrão e modo plano (?format=flat) para carregamento instantâneo no Power Query.
 */

class PowerBIController
{
    private $db;
    private $user;
    private $input;

    public function __construct($db, $user, $input = [])
    {
        $this->db = $db;
        $this->user = $user;
        $this->input = $input;
    }

    private function safeCount(string $sql): int
    {
        try {
            $val = $this->db->query($sql)->fetchColumn();
            return (int) ($val !== false ? $val : 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    private function safeFetchAll(string $sql): array
    {
        try {
            $stmt = $this->db->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function isFlatFormat(): bool
    {
        return (isset($_GET['format']) && strtolower($_GET['format']) === 'flat') 
            || isset($_GET['flat']) || isset($_GET['raw']);
    }

    /**
     * Só é seguro mostrar dados de demonstração quando o tenant está genuinamente
     * "zerado" (instalação nova). Antes, cada endpoint checava apenas a própria
     * tabela: se um tenant já tivesse Ativos reais mas ainda nenhuma Ordem, o
     * gateway do Power BI devolvia Ordens FICTÍCIAS misturadas com Ativos reais,
     * o que é um risco de integridade de dados para o cliente (BI = fonte da verdade).
     */
    private function isSystemEmptyForDemo(): bool
    {
        $totalAtivos = $this->safeCount("SELECT COUNT(*) FROM ativos");
        $totalOrdens = $this->safeCount("SELECT COUNT(*) FROM ordens");
        return ($totalAtivos === 0 && $totalOrdens === 0);
    }

    /**
     * Endpoint Power BI: KPIs Consolidados
     */
    public function getKPIs()
    {
        $totalAtivos = $this->safeCount("SELECT COUNT(*) FROM ativos");
        $ativosAlerta = $this->safeCount("SELECT COUNT(*) FROM ativos WHERE status IN ('Alerta', 'Crítico', 'Danger')");
        $totalOrdens = $this->safeCount("SELECT COUNT(*) FROM ordens");
        $ordensPendentes = $this->safeCount("SELECT COUNT(*) FROM ordens WHERE situacao IN ('Pendente', 'Em Andamento', 'Aberta')");
        // Sistema usa 'Concluído' (com ó); aceita variantes legadas
        $ordensConcluidas = $this->safeCount(
            "SELECT COUNT(*) FROM ordens WHERE situacao IN ('Concluído', 'Concluída', 'Finalizada', 'Fechada')"
        );

        $itensCatalogo = $this->safeCount("SELECT COUNT(*) FROM catalogo");
        if ($itensCatalogo === 0) {
            $itensCatalogo = $this->safeCount("SELECT COUNT(*) FROM catalogo_lubrificantes");
        }

        // Demo apenas quando o banco está vazio (não inventar KPIs sobre dados reais)
        $isEmptyDemo = ($totalAtivos === 0 && $totalOrdens === 0);
        if ($isEmptyDemo) {
            $totalAtivos = 8;
            $ativosAlerta = 2;
            $totalOrdens = 8;
            $ordensPendentes = 3;
            $ordensConcluidas = 5;
            $itensCatalogo = max($itensCatalogo, 12);
        }

        $disponibilidade = $totalAtivos > 0 ? round((($totalAtivos - $ativosAlerta) / $totalAtivos) * 100, 2) : 100.0;
        $taxaConclusaoOS = $totalOrdens > 0 ? round(($ordensConcluidas / $totalOrdens) * 100, 2) : 100.0;

        require_once __DIR__ . '/../includes/reliability_metrics.php';
        $rel = ReliabilityMetrics::compute($this->db, null);

        $kpis = [
            'total_ativos' => $totalAtivos,
            'ativos_em_alerta' => $ativosAlerta,
            'disponibilidade_planta_pct' => $disponibilidade,
            'total_ordens_servico' => $totalOrdens,
            'ordens_pendentes' => $ordensPendentes,
            'ordens_concluidas' => $ordensConcluidas,
            'taxa_conclusao_os_pct' => $taxaConclusaoOS,
            'itens_catalogo_lubrificantes' => $itensCatalogo,
            'mtbf_estimado_horas' => $rel['mtbf_hours'],
            'mttr_estimado_horas' => $rel['mttr_hours'],
            'failures_12m' => $rel['failures_12m'],
            'reliability_hint' => $rel['hint'],
        ];

        if ($this->isFlatFormat()) {
            return [$kpis];
        }

        return [
            'ok' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'kpis' => $kpis
        ];
    }

    /**
     * Endpoint Power BI: Carteira e Histórico de Ordens de Serviço
     */
    public function getOrders()
    {
        $orders = $this->safeFetchAll("
            SELECT 
                o.id,
                o.descricao,
                o.responsavel,
                o.prioridade,
                o.situacao,
                o.tipo_manutencao,
                o.data_planejada,
                o.data_emissao,
                o.last_sync,
                o.reserva_almox,
                o.cod_serv,
                a.id as ativo_id,
                a.nome as ativo_nome,
                a.tag as ativo_tag,
                a.tipo as ativo_tipo
            FROM ordens o
            LEFT JOIN ativos a ON o.ativo_id = a.id
            ORDER BY o.id DESC
        ");

        if (empty($orders) && $this->isSystemEmptyForDemo()) {
            $orders = [
                ['id' => 8, 'descricao' => '[OS NEURAL] Status crítico detectado. Inspeção de lubrificação recomendada.', 'responsavel' => 'MOTOR NEURAL LUB-TEK', 'prioridade' => 'Alta', 'situacao' => 'Pendente', 'tipo_manutencao' => 'Preditiva', 'data_planejada' => date('Y-m-d'), 'data_emissao' => date('Y-m-d'), 'ativo_id' => 3, 'ativo_nome' => 'Sopradora KHS InnoPET Blomax', 'ativo_tag' => 'SOP-301', 'ativo_tipo' => 'Sopradora'],
                ['id' => 7, 'descricao' => '[CBM AUTOMÁTICO] Alerta Crítico do PI Sensor: Vibração RMS Rolamento', 'responsavel' => 'EQUIPE DE CONFIABILIDADE (PI)', 'prioridade' => 'Crítica', 'situacao' => 'Pendente', 'tipo_manutencao' => 'Corretiva', 'data_planejada' => date('Y-m-d'), 'data_emissao' => date('Y-m-d'), 'ativo_id' => 3, 'ativo_nome' => 'Sopradora KHS InnoPET Blomax', 'ativo_tag' => 'SOP-301', 'ativo_tipo' => 'Sopradora'],
                ['id' => 6, 'descricao' => 'Troca preventiva de cartucho de graxa na Bomba Lincoln', 'responsavel' => 'Marcos Souza', 'prioridade' => 'Média', 'situacao' => 'Concluída', 'tipo_manutencao' => 'Preventiva', 'data_planejada' => date('Y-m-d', strtotime('-2 days')), 'data_emissao' => date('Y-m-d', strtotime('-5 days')), 'ativo_id' => 4, 'ativo_nome' => 'Bomba Centrífuga D\'água', 'ativo_tag' => 'BMB-401', 'ativo_tipo' => 'Bomba'],
                ['id' => 5, 'descricao' => 'Verificação de vibração e nível de graxa na Bomba Automática Lincoln', 'responsavel' => 'Lucas Neves', 'prioridade' => 'Baixa', 'situacao' => 'Concluída', 'tipo_manutencao' => 'Inspeção', 'data_planejada' => date('Y-m-d', strtotime('-3 days')), 'data_emissao' => date('Y-m-d', strtotime('-6 days')), 'ativo_id' => 4, 'ativo_nome' => 'Bomba Centrífuga D\'água', 'ativo_tag' => 'BMB-401', 'ativo_tipo' => 'Bomba'],
                ['id' => 4, 'descricao' => 'Inspeção anual e checagem de folga do Rolamento Central', 'responsavel' => 'Carlos Silva', 'prioridade' => 'Alta', 'situacao' => 'Concluída', 'tipo_manutencao' => 'Preventiva', 'data_planejada' => date('Y-m-d', strtotime('-10 days')), 'data_emissao' => date('Y-m-d', strtotime('-12 days')), 'ativo_id' => 2, 'ativo_nome' => 'Redutor Planetário de Velocidade', 'ativo_tag' => 'RED-202', 'ativo_tipo' => 'Redutor'],
                ['id' => 3, 'descricao' => 'Correção de falta de lubrificação no anel de garras - Molde N°1', 'responsavel' => 'Marcos Souza', 'prioridade' => 'Média', 'situacao' => 'Concluída', 'tipo_manutencao' => 'Corretiva', 'data_planejada' => date('Y-m-d', strtotime('-15 days')), 'data_emissao' => date('Y-m-d', strtotime('-15 days')), 'ativo_id' => 7, 'ativo_nome' => 'Molde de Injeção 16 Cavidades', 'ativo_tag' => 'MLD-701', 'ativo_tipo' => 'Molde'],
                ['id' => 2, 'descricao' => 'Lubrificação preventiva e troca de óleo do Redutor principal', 'responsavel' => 'Carlos Silva', 'prioridade' => 'Média', 'situacao' => 'Concluída', 'tipo_manutencao' => 'Preventiva', 'data_planejada' => date('Y-m-d', strtotime('-20 days')), 'data_emissao' => date('Y-m-d', strtotime('-22 days')), 'ativo_id' => 1, 'ativo_nome' => 'Motor Elétrico Principal 150CV', 'ativo_tag' => 'MTR-101', 'ativo_tipo' => 'Motor']
            ];
        }

        if ($this->isFlatFormat()) {
            return $orders;
        }

        return [
            'ok' => true,
            'count' => count($orders),
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $orders
        ];
    }

    /**
     * Endpoint Power BI: Telemetria de Sensores em Tempo Real (PI System / CBM)
     */
    public function getTelemetry()
    {
        $tags = $this->safeFetchAll("
            SELECT 
                p.id as tag_id,
                p.tag_name,
                p.label,
                p.unit,
                p.current_value,
                p.current_status,
                p.warning_threshold,
                p.critical_threshold,
                p.last_update,
                a.id as ativo_id,
                a.nome as ativo_nome,
                a.tag as ativo_tag
            FROM pi_tags p
            LEFT JOIN ativos a ON p.ativo_id = a.id
            ORDER BY p.id ASC
        ");

        if (empty($tags) && $this->isSystemEmptyForDemo()) {
            $tags = [
                ['tag_id' => 1, 'tag_name' => 'LUBTEK.SLZ.KHS.VIB', 'label' => 'Sensor: Vibração RMS Rolamento Central', 'unit' => 'mm/s', 'current_value' => 10.0, 'current_status' => 'Critical', 'warning_threshold' => 4.5, 'critical_threshold' => 7.2, 'last_update' => date('Y-m-d H:i:s'), 'ativo_id' => 3, 'ativo_nome' => 'Sopradora KHS InnoPET Blomax', 'ativo_tag' => 'SOP-301'],
                ['tag_id' => 2, 'tag_name' => 'LUBTEK.SLZ.RED.TEMP', 'label' => 'Sensor: Temperatura Óleo Redutor', 'unit' => '°C', 'current_value' => 74.5, 'current_status' => 'Warning', 'warning_threshold' => 70.0, 'critical_threshold' => 85.0, 'last_update' => date('Y-m-d H:i:s'), 'ativo_id' => 2, 'ativo_nome' => 'Redutor Planetário de Velocidade', 'ativo_tag' => 'RED-202'],
                ['tag_id' => 3, 'tag_name' => 'LUBTEK.SLZ.MTR.AMP', 'label' => 'Sensor: Corrente Elétrica Motor 150CV', 'unit' => 'A', 'current_value' => 182.3, 'current_status' => 'Normal', 'warning_threshold' => 200.0, 'critical_threshold' => 220.0, 'last_update' => date('Y-m-d H:i:s'), 'ativo_id' => 1, 'ativo_nome' => 'Motor Elétrico Principal 150CV', 'ativo_tag' => 'MTR-101']
            ];
        }

        if ($this->isFlatFormat()) {
            return $tags;
        }

        return [
            'ok' => true,
            'count' => count($tags),
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $tags
        ];
    }

    /**
     * Endpoint Power BI: Inventário Completo de Ativos
     */
    public function getAssets()
    {
        // 'health_score' e 'critico' não existem como colunas físicas em 'ativos' (o schema
        // só possui 'status'); antes esta query lançava "no such column" e a exceção era
        // engolida por safeFetchAll(), fazendo com que dados reais NUNCA fossem retornados
        // (sempre caía no fallback demo). Agora deriva os dois campos a partir de 'status'.
        $assets = $this->safeFetchAll("
            SELECT 
                id,
                nome,
                tag,
                tipo,
                status,
                CASE 
                    WHEN status IN ('Crítico', 'Danger') THEN 35.0
                    WHEN status = 'Alerta' THEN 65.0
                    ELSE 98.0
                END as health_score,
                CASE WHEN status IN ('Alerta', 'Crítico', 'Danger') THEN 1 ELSE 0 END as critico,
                pai_id,
                fabricante,
                modelo,
                num_serie
            FROM ativos
            ORDER BY id ASC
        ");

        if (empty($assets) && $this->isSystemEmptyForDemo()) {
            $assets = [
                ['id' => 1, 'nome' => 'Motor Elétrico Principal 150CV', 'tag' => 'MTR-101', 'tipo' => 'Motor', 'status' => 'Operacional', 'health_score' => 98.5, 'critico' => 1, 'fabricante' => 'WEG', 'modelo' => 'W22 Magnet', 'num_serie' => 'SN-998231'],
                ['id' => 2, 'nome' => 'Redutor Planetário de Velocidade', 'tag' => 'RED-202', 'tipo' => 'Redutor', 'status' => 'Operacional', 'health_score' => 94.0, 'critico' => 1, 'fabricante' => 'SEW Eurodrive', 'modelo' => 'P042', 'num_serie' => 'SN-443120'],
                ['id' => 3, 'nome' => 'Sopradora KHS InnoPET Blomax', 'tag' => 'SOP-301', 'tipo' => 'Sopradora', 'status' => 'Alerta', 'health_score' => 76.2, 'critico' => 1, 'fabricante' => 'KHS', 'modelo' => 'Blomax 16', 'num_serie' => 'KHS-8871'],
                ['id' => 4, 'nome' => 'Bomba Centrífuga D\'água', 'tag' => 'BMB-401', 'tipo' => 'Bomba', 'status' => 'Operacional', 'health_score' => 99.0, 'critico' => 0, 'fabricante' => 'KSB', 'modelo' => 'Megachem', 'num_serie' => 'KSB-3321'],
                ['id' => 5, 'nome' => 'Compressor Parafuso Rotary', 'tag' => 'CMP-501', 'tipo' => 'Compressor', 'status' => 'Operacional', 'health_score' => 92.1, 'critico' => 1, 'fabricante' => 'Atlas Copco', 'modelo' => 'GA 110', 'num_serie' => 'AC-77610'],
                ['id' => 6, 'nome' => 'Esteira Transportadora de Caixas', 'tag' => 'EST-601', 'tipo' => 'Transportador', 'status' => 'Operacional', 'health_score' => 95.0, 'critico' => 0, 'fabricante' => 'FlexLink', 'modelo' => 'X85', 'num_serie' => 'FL-00912'],
                ['id' => 7, 'nome' => 'Molde de Injeção 16 Cavidades', 'tag' => 'MLD-701', 'tipo' => 'Molde', 'status' => 'Alerta', 'health_score' => 68.4, 'critico' => 1, 'fabricante' => 'Husky', 'modelo' => 'HyPET HPP5', 'num_serie' => 'HK-5519'],
                ['id' => 8, 'nome' => 'Ventilador Exaustor de Caldeira', 'tag' => 'VNT-801', 'tipo' => 'Ventilador', 'status' => 'Operacional', 'health_score' => 91.0, 'critico' => 0, 'fabricante' => 'Aerovent', 'modelo' => 'BI-36', 'num_serie' => 'AV-1120']
            ];
        }

        if ($this->isFlatFormat()) {
            return $assets;
        }

        return [
            'ok' => true,
            'count' => count($assets),
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $assets
        ];
    }

    /**
     * Endpoint Power BI: Trilha de Auditoria e Logs de Alterações
     */
    public function getAudit()
    {
        $logs = $this->safeFetchAll("
            SELECT 
                id,
                usuario,
                acao,
                alvo,
                dados_antigos,
                dados_novos,
                detalhes_erro,
                data
            FROM logs_auditoria
            ORDER BY id DESC
            LIMIT 1000
        ");

        if (empty($logs) && $this->isSystemEmptyForDemo()) {
            $logs = [
                ['id' => 1, 'usuario' => 'Admin', 'acao' => 'USER_LOGIN', 'alvo' => 'Painel Central', 'dados_antigos' => null, 'dados_novos' => 'Sessão Autenticada', 'detalhes_erro' => null, 'data' => date('Y-m-d H:i:s')],
                ['id' => 2, 'usuario' => 'MOTOR NEURAL LUB-TEK', 'acao' => 'OS_CREATE', 'alvo' => 'ordens:8', 'dados_antigos' => null, 'dados_novos' => 'OS Preditiva Gerada', 'detalhes_erro' => null, 'data' => date('Y-m-d H:i:s', strtotime('-1 hour'))]
            ];
        }

        if ($this->isFlatFormat()) {
            return $logs;
        }

        return [
            'ok' => true,
            'count' => count($logs),
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $logs
        ];
    }
}
