<?php
/**
 * Roteamento local vs Gemini (testável / eval).
 */
require_once __DIR__ . '/neural_knowledge.php';

class AgentRouting
{
    /** Se true, resposta deve vir do guia local (sem Gemini), salvo indisponibilidade. */
    public static function shouldUseLocalOnly(string $prompt, bool $forceAi): bool
    {
        if ($forceAi) {
            return false;
        }
        return NeuralKnowledge::shouldUseLocalFirst($prompt);
    }
}
