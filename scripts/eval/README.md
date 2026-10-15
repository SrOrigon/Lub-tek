# Fixtures de avaliação dos agentes

Execute:

```bash
php scripts/test_agent_eval.php
```

| Arquivo | Casos | O que valida |
|---------|------:|--------------|
| `neural_local_router.json` | 37 | Router local vs perguntas técnicas (Gemini) |
| `os_urgency_scores.json` | 25 | Score e prioridade do agente de O.S. |
| `neural_risk_signals.json` | 25 | Risco % e saúde do Motor Neural |
| `prompt_registry_required.json` | 4 | Prompts versionados obrigatórios |
| `agent_weights_defaults.json` | 8 | Estrutura dos pesos default |
| `local_answer_smoke.json` | 5 | Respostas locais da Lúbria |

Fórmulas compartilhadas com produção: `includes/agent_eval_scoring.php`.

Ao alterar pesos ou heurísticas, ajuste expectativas aqui ou rode calibração e atualize ranges.
