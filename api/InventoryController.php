<?php
/**
 * Inventory Controller
 * Manages Catalog and Stock
 */

require_once __DIR__ . '/../includes/upload_helper.php';

class InventoryController
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

    public function getCatalog()
    {
        $res = $this->db->query("SELECT * FROM catalogo ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
        return $res;
    }

    public function getCrossEquivalents()
    {
        $id = intval($this->input['id'] ?? 0);
        if (!$id) return ['equivalents' => []];

        $stmt = $this->db->prepare("SELECT * FROM catalogo WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) return ['equivalents' => []];

        $tipo = $item['tipo'] ?? '';
        $nome = $item['nome'] ?? '';

        // Extract ISO VG or grade if available
        $vgMatch = '';
        if (preg_match('/(?:VG|ISO)?\s?(32|46|68|100|150|220|320|460|2)/i', $nome, $m)) {
            $vgMatch = $m[1];
        }

        if ($vgMatch) {
            $eqStmt = $this->db->prepare("SELECT id, nome, fabricante, estoque_atual, localizacao FROM catalogo WHERE id != ? AND (nome LIKE ? OR specs LIKE ?) AND estoque_atual > 0 LIMIT 5");
            $eqStmt->execute([$id, "%{$vgMatch}%", "%{$vgMatch}%"]);
            $eqs = $eqStmt->fetchAll(PDO::FETCH_ASSOC);
            return ['ok' => true, 'equivalents' => $eqs];
        }

        return ['ok' => true, 'equivalents' => []];
    }

    public function saveItem()
    {
        $in = $this->input;

        // 'specs' pode chegar como array/objeto (formulário de edição) OU como
        // string já JSON-encoded (quando o front reenvia um item tal como veio
        // do catálogo, ex: auto-save de IP em viewCatDetail). Evita re-encodar
        // uma string JSON dentro de outra string JSON (corrompendo os dados).
        $rawSpecs = $in['specs'] ?? null;
        if (is_string($rawSpecs)) {
            $decoded = json_decode($rawSpecs, true);
            $specs = (json_last_error() === JSON_ERROR_NONE) ? $rawSpecs : json_encode(new stdClass());
        } else {
            $specs = json_encode($rawSpecs ?? new stdClass());
        }

        // Aceita tanto 'estoque' (form de edição) quanto 'estoque_atual' (objeto
        // de catálogo vindo do backend), evitando zerar o estoque quando o item
        // é reenviado exatamente como veio da listagem (ex: auto-save de IP).
        $estoque = $in['estoque'] ?? $in['estoque_atual'] ?? 0;

        $oldImage = null;

        if (!empty($in['id'])) {
            $imgStmt = $this->db->prepare("SELECT imagem FROM catalogo WHERE id = ?");
            $imgStmt->execute([$in['id']]);
            $oldImage = $imgStmt->fetchColumn();
        }

        DB::safeExecute(function ($db) use ($in, $specs, $estoque) {
            if (!empty($in['id'])) {
                $db->prepare("UPDATE catalogo SET nome=?, tipo=?, codigo=?, fabricante=?, estoque_atual=?, localizacao=?, specs=?, ip=?, descricao=?, imagem=? WHERE id=?")
                    ->execute([
                        $in['nome'],
                        $in['tipo'],
                        $in['codigo'],
                        $in['fabricante'],
                        $estoque,
                        $in['localizacao'],
                        $specs,
                        $in['ip'] ?? '',
                        $in['descricao'] ?? '',
                        $in['imagem'] ?? '',
                        $in['id']
                    ]);
            } else {
                $db->prepare("INSERT INTO catalogo (nome, tipo, codigo, fabricante, estoque_atual, localizacao, specs, ip, descricao, imagem) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([
                        $in['nome'],
                        $in['tipo'],
                        $in['codigo'],
                        $in['fabricante'],
                        $estoque,
                        $in['localizacao'],
                        $specs,
                        $in['ip'] ?? '',
                        $in['descricao'] ?? '',
                        $in['imagem'] ?? ''
                    ]);
            }
        });

        if ($oldImage && $oldImage !== ($in['imagem'] ?? '')) {
            UploadHelper::deleteIfUploaded($oldImage);
        }

        return ['success' => true];
    }

    public function deleteItem()
    {
        $id = $this->input['id'] ?? null;
        if (empty($id)) {
            throw new Exception('ID do item não fornecido para exclusão.');
        }

        $imgStmt = $this->db->prepare("SELECT imagem FROM catalogo WHERE id = ?");
        $imgStmt->execute([$id]);
        $oldImage = $imgStmt->fetchColumn();

        DB::safeExecute(function ($db) use ($id) {
            // Cascade Delete: Mercado Ofertas
            try {
                $db->prepare("DELETE FROM mercado_ofertas WHERE catalogo_id = ?")->execute([$id]);
            } catch (Exception $e) { /* Ignore if table missing */
            }

            // Delete Main Item
            $db->prepare("DELETE FROM catalogo WHERE id = ?")->execute([$id]);
        });

        if ($oldImage) {
            UploadHelper::deleteIfUploaded($oldImage);
        }

        return ['success' => true];
    }
}
?>