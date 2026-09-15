<?php
require_once __DIR__ . '/../includes/lubricant_consumption.php';

class ReportsController
{
    private $db;
    private $user;
    private $input;

    public function __construct($db, $user, $input)
    {
        $this->db = $db;
        $this->user = $user;
        $this->input = is_array($input) ? $input : [];
    }

    public function getLubricantConsumption()
    {
        $period = (string) ($this->input['period'] ?? $_GET['period'] ?? 'month');
        $data = LubricantConsumption::build($this->db, ['period' => $period]);
        $data['ok'] = true;
        $data['company'] = $this->user['tenant_label'] ?? 'LUB-TEK';
        return $data;
    }

    public function getRouteAuditAnomalies()
    {
        $stmt = $this->db->query("
            SELECT o.id, o.descricao, o.responsavel, o.data_conclusao, o.num_pontos, o.minutos_exec, a.nome as ativo_nome
            FROM ordens o
            LEFT JOIN ativos a ON o.ativo_id = a.id
            WHERE o.situacao = 'Concluído' AND o.minutos_exec > 0
            ORDER BY o.data_conclusao DESC LIMIT 100
        ");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $anomalies = [];
        foreach ($orders as $o) {
            $pontos = max(1, intval($o['num_pontos'] ?: 1));
            $minutos = floatval($o['minutos_exec']);
            $minPerPoint = $minutos / $pontos;

            // Anomalia: menos de 0.2 minutos (12 segundos) por ponto indica conclusão falsa de rota
            if ($minPerPoint < 0.2) {
                $anomalies[] = [
                    'order_id' => $o['id'],
                    'resp' => $o['responsavel'],
                    'ativo' => $o['ativo_nome'],
                    'pontos' => $pontos,
                    'minutos' => $minutos,
                    'avg_sec_per_point' => round($minPerPoint * 60, 1),
                    'risk' => 'SUSPEITA_MARCACAO_FALSA'
                ];
            }
        }

        return ['ok' => true, 'anomalies' => $anomalies, 'count' => count($anomalies)];
    }
}
