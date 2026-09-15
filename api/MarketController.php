<?php
/**
 * MARKETPLACE INTELLIGENCE CONTROLLER
 * Handles price tracking, vendor offers, and bulk discount logic.
 */

class MarketController
{
    private $db;
    private $user;
    private $input;

    public function __construct($db, $user, $input)
    {
        $this->db = $db;
        $this->user = $user;
        $this->input = $input;
        $this->ensureTable();
    }

    private function ensureTable()
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS mercado_ofertas (
            id INTEGER PRIMARY KEY, 
            catalogo_id INTEGER, 
            vendor_name TEXT, 
            price REAL, 
            url TEXT, 
            delivery TEXT, 
            verified INTEGER DEFAULT 0,
            rating REAL DEFAULT 4.5,
            obs TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function getMarketData()
    {
        $catId = $this->input['cat_id'] ?? null;
        if (!$catId) {
            return [];
        }

        // Se receber nome do material em vez de ID numérico, resolve no catálogo
        if (!is_numeric($catId)) {
            $stmt = $this->db->prepare("SELECT id FROM catalogo WHERE LOWER(nome) LIKE LOWER(?) LIMIT 1");
            $stmt->execute(['%' . $catId . '%']);
            $found = $stmt->fetchColumn();
            $catId = $found ?: $catId;
        }

        $stmt = $this->db->prepare("SELECT id, vendor_name, price, url as vendor_url, delivery, verified, rating FROM mercado_ofertas WHERE catalogo_id = ? ORDER BY price ASC");
        $stmt->execute([$catId]);
        $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $offers ?: [];
    }

    public function saveOffer()
    {
        $in = $this->input;
        $catalogoId = $in['catalogo_id'] ?? $in['cat_id'] ?? null;
        $vendorName = $in['vendor_name'] ?? $in['vendor'] ?? null;
        $price = $in['price'] ?? null;

        if (empty($catalogoId) || empty($vendorName) || $price === null || $price === '') {
            return ['ok' => false, 'error' => 'Dados incompletos'];
        }

        if (!is_numeric($catalogoId)) {
            return ['ok' => false, 'error' => 'ID de catálogo inválido.'];
        }

        if (!is_numeric($price) || (float) $price < 0) {
            return ['ok' => false, 'error' => 'Preço inválido.'];
        }

        // Sanitiza texto livre vindo do usuário para evitar XSS armazenado
        // (consistente com o padrão de strip_tags/trim usado no PIController).
        $vendorName = trim(strip_tags((string) $vendorName));
        $url = trim(strip_tags((string) ($in['url'] ?? '')));
        $delivery = trim(strip_tags((string) ($in['delivery'] ?? 'N/A')));

        if ($vendorName === '') {
            return ['ok' => false, 'error' => 'Dados incompletos'];
        }

        $this->db->prepare("INSERT INTO mercado_ofertas (catalogo_id, vendor_name, price, url, delivery, verified) VALUES (?,?,?,?,?,?)")
            ->execute([
                (int) $catalogoId,
                $vendorName,
                (float) $price,
                $url,
                $delivery,
                !empty($in['verified']) ? 1 : 0
            ]);

        return ['ok' => true];
    }

    public function deleteOffer()
    {
        $id = $this->input['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            return ['ok' => false, 'error' => 'ID da oferta não fornecido para exclusão.'];
        }
        $this->db->prepare("DELETE FROM mercado_ofertas WHERE id = ?")->execute([(int) $id]);
        return ['ok' => true];
    }

    public function getStats()
    {
        try {
            $count = $this->db->query("SELECT COUNT(*) FROM mercado_ofertas")->fetchColumn();
            // $avg = $this->db->query("SELECT AVG(price) FROM mercado_ofertas")->fetchColumn();
        } catch (Exception $e) {
            $count = 0;
        }

        return [
            'global_savings' => ($count > 0) ? ($count * 150) : 0,
            'avg_delivery' => ($count > 0) ? '2 Dias' : '--'
        ];
    }
}
