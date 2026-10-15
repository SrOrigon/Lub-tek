<?php
/**
 * Maintenance Plans Controller
 * Manages Frequencies, Procedures and Routes
 */

class PlansController
{
    private $db;
    private $user;
    private $input;

    public function __construct($db, $user, $input)
    {
        $this->db = $db;
        $this->user = $user;
        $this->input = $input;
    }

    public function getPlans()
    {
        $assetId = $this->input['asset_id'] ?? null;
        if ($assetId) {
            $stmt = $this->db->prepare("SELECT * FROM planos WHERE ativo_id = ?");
            $stmt->execute([$assetId]);
            return $stmt->fetchAll();
        }
        return [];
    }

    public function savePlan()
    {
        // Simple CRUD for Plans
        $in = $this->input;

        // Validation
        if (empty($in['ativo_id']) || empty($in['frequencia_dias'])) {
            throw new Exception("Dados incompletos");
        }

        if (!empty($in['id'])) {
            $exists = $this->db->prepare("SELECT COUNT(*) FROM planos WHERE id = ?");
            $exists->execute([$in['id']]);
            if (!$exists->fetchColumn()) {
                throw new Exception("Plano não encontrado para atualização.");
            }

            $stmt = $this->db->prepare("UPDATE planos SET frequencia_dias=?, quantidade=?, procedimento=?, metodo=?, catalogo_id=? WHERE id=?");
            $stmt->execute([
                $in['frequencia_dias'],
                $in['quantidade'] ?? 0,
                $in['procedimento'] ?? '',
                $in['metodo'] ?? '',
                $in['catalogo_id'] ?? null,
                $in['id']
            ]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO planos (ativo_id, catalogo_id, frequencia_dias, quantidade, procedimento, metodo) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                $in['ativo_id'],
                $in['catalogo_id'] ?? null,
                $in['frequencia_dias'],
                $in['quantidade'] ?? 0,
                $in['procedimento'] ?? '',
                $in['metodo'] ?? ''
            ]);
        }
        return ['success' => true];
    }

    public function getReferencePlan()
    {
        $planKey = trim((string) ($this->input['plan_key'] ?? 'pet160'));
        $filePath = __DIR__ . '/../data/planos/pet160_linha_solar.json';
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }
        throw new Exception("Plano de referência não encontrado.");
    }

    public function deletePlan()
    {
        $id = $this->input['id'] ?? null;
        if (empty($id)) {
            throw new Exception("ID do plano não fornecido para exclusão.");
        }
        $this->db->prepare("DELETE FROM planos WHERE id=?")->execute([$id]);
        return ['success' => true];
    }
}
?>