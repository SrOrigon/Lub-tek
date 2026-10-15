<?php
/**
 * Telemetria IA + feedback dos agentes (gestor/developer).
 */
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/agent_analytics.php';

class AgentAnalyticsController
{
    private PDO $db;
    private array $user;
    private array $input;

    public function __construct(PDO $db, array $user, array $input = [])
    {
        $this->db = $db;
        $this->user = $user;
        $this->input = $input;
    }

    private function assertGestor(): void
    {
        $role = $this->user['role'] ?? '';
        if (!Permissions::isGestor($role) && !Permissions::isDeveloper($role) && $role !== 'admin') {
            throw new Exception('Acesso negado.');
        }
    }

    public function getAiUsageSummary(): array
    {
        $this->assertGestor();
        $days = max(1, min(90, (int) ($this->input['days'] ?? $_GET['days'] ?? 7)));
        return [
            'success' => true,
            'summary' => AgentAnalytics::aiUsageSummary($this->db, $days),
        ];
    }

    public function getAgentFeedbackStats(): array
    {
        $this->assertGestor();
        $days = max(1, min(365, (int) ($this->input['days'] ?? $_GET['days'] ?? 30)));
        return [
            'success' => true,
            'stats' => AgentAnalytics::agentFeedbackStats($this->db, $days),
        ];
    }
}
