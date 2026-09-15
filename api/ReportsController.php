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
}
