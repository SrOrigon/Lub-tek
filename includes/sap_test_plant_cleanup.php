<?php
/**
 * Remove plantas/equipamentos criados pelo teste SAP (não são dados de planta real).
 */
class SapTestPlantCleanup
{
    public static function isTestAsset(array $row): bool
    {
        $nome = (string) ($row['nome'] ?? '');
        $tag = (string) ($row['tag'] ?? '');
        if (stripos($nome, 'Planta SAP Test') === 0) {
            return true;
        }
        if (strcasecmp($nome, 'Equip SAP Test') === 0) {
            return true;
        }
        if (stripos($tag, 'SAP-ROOT-') === 0 || stripos($tag, 'SAP-EQ-') === 0) {
            return true;
        }
        return false;
    }

    /**
     * @return array{deleted:int, roots:int}
     */
    public static function purge(PDO $db): array
    {
        $rows = $db->query('SELECT id, nome, tag, pai_id FROM ativos')->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }

        $roots = [];
        foreach ($rows as $r) {
            if (self::isTestAsset($r)) {
                $roots[] = (int) $r['id'];
            }
        }
        $roots = array_values(array_unique($roots));

        $ids = [];
        foreach ($roots as $id) {
            self::collectDescendants($id, $byId, $ids);
        }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return ['deleted' => 0, 'roots' => 0];
        }

        $in = implode(',', array_map('intval', $ids));
        $db->exec('PRAGMA foreign_keys = OFF');
        try {
            foreach (['pi_telemetry', 'pi_tags', 'analises', 'planos', 'ativos_lubrificacao', 'grease_events'] as $table) {
                try {
                    if ($table === 'pi_telemetry') {
                        $db->exec("DELETE FROM pi_telemetry WHERE tag_id IN (SELECT id FROM pi_tags WHERE ativo_id IN ($in))");
                    } else {
                        $db->exec("DELETE FROM $table WHERE ativo_id IN ($in)");
                    }
                } catch (Exception $e) {
                    // tabela pode não existir neste tenant
                }
            }
            try {
                $db->exec("UPDATE ordens SET ativo_id = NULL WHERE ativo_id IN ($in)");
            } catch (Exception $e) {
            }
            $db->exec("DELETE FROM ativos WHERE id IN ($in)");
        } finally {
            $db->exec('PRAGMA foreign_keys = ON');
        }

        return ['deleted' => count($ids), 'roots' => count($roots)];
    }

    private static function collectDescendants(int $id, array $byId, array &$ids): void
    {
        if (isset($ids[$id])) {
            return;
        }
        $ids[$id] = $id;
        foreach ($byId as $row) {
            if ((int) ($row['pai_id'] ?? 0) === $id) {
                self::collectDescendants((int) $row['id'], $byId, $ids);
            }
        }
    }
}
