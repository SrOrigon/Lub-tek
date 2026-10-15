<?php
/**
 * Contexto mínimo por ativo (RAG leve via SQL) para prompts de IA.
 */
class AssetRagContext
{
    public static function buildForAsset(PDO $db, int $assetId, int $maxOrders = 5): string
    {
        if ($assetId <= 0) {
            return '';
        }

        $stmt = $db->prepare('SELECT nome, tipo, fabricante, modelo, dados_tecnicos FROM ativos WHERE id = ?');
        $stmt->execute([$assetId]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$asset) {
            return '';
        }

        $parts = [];
        $parts[] = 'Cadastro: ' . ($asset['nome'] ?? '') . ' | ' . ($asset['tipo'] ?? '') . ' | ' . ($asset['fabricante'] ?? '') . ' ' . ($asset['modelo'] ?? '');
        if (!empty($asset['dados_tecnicos'])) {
            $tech = is_string($asset['dados_tecnicos']) ? $asset['dados_tecnicos'] : json_encode($asset['dados_tecnicos'], JSON_UNESCAPED_UNICODE);
            $parts[] = 'Dados técnicos: ' . mb_substr($tech, 0, 1200);
        }

        $os = $db->prepare(
            "SELECT descricao, situacao, prioridade, data_planejada, data_conclusao
             FROM ordens WHERE ativo_id = ? ORDER BY COALESCE(data_conclusao, data_planejada) DESC LIMIT " . (int) $maxOrders
        );
        $os->execute([$assetId]);
        $orders = $os->fetchAll(PDO::FETCH_ASSOC);
        if ($orders) {
            $parts[] = 'Últimas O.S.: ' . json_encode($orders, JSON_UNESCAPED_UNICODE);
        }

        try {
            $an = $db->prepare(
                'SELECT data_coleta, iso_4406, agua_ppm, fe_ppm, visc40, laudo_geral FROM analises WHERE ativo_id = ? ORDER BY data_coleta DESC LIMIT 3'
            );
            $an->execute([$assetId]);
            $analises = $an->fetchAll(PDO::FETCH_ASSOC);
            if ($analises) {
                $parts[] = 'Análises de óleo: ' . json_encode($analises, JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            // tabela analises pode não existir em tenants antigos
        }

        return implode("\n", $parts);
    }
}
