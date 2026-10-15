<?php
/**
 * Pesos heurísticos dos agentes (por tenant, em system_meta).
 */
class AgentWeights
{
    public const META_KEY = 'agent_weights';

    public static function defaults(): array
    {
        return [
            'os_agent' => [
                'overdue_day_score' => 4.0,
                'critical_bonus' => 25.0,
                'high_bonus' => 12.0,
                'medium_bonus' => 5.0,
            ],
            'neural_risk' => [
                'base' => 15.0,
                'corrective_ratio_multiplier' => 70.0,
                'mtbf_penalty_max' => 20.0,
                'oil_alert_bonus' => 10.0,
            ],
        ];
    }

    public static function load(PDO $db): array
    {
        $defaults = self::defaults();
        try {
            $db->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
            $stmt = $db->prepare('SELECT mval FROM system_meta WHERE mkey = ?');
            $stmt->execute([self::META_KEY]);
            $raw = $stmt->fetchColumn();
            if ($raw === false || $raw === '') {
                return $defaults;
            }
            $saved = json_decode((string) $raw, true);
            if (!is_array($saved)) {
                return $defaults;
            }
            return array_replace_recursive($defaults, $saved);
        } catch (Exception $e) {
            return $defaults;
        }
    }

    public static function save(PDO $db, array $weights): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
        $merged = array_replace_recursive(self::defaults(), $weights);
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $db->prepare(
                'INSERT INTO system_meta (mkey, mval) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE mval = VALUES(mval)'
            );
        } else {
            $stmt = $db->prepare('INSERT OR REPLACE INTO system_meta (mkey, mval) VALUES (?, ?)');
        }
        $stmt->execute([self::META_KEY, json_encode($merged, JSON_UNESCAPED_UNICODE)]);
    }
}
