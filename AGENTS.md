# AGENTS.md — Diretrizes de Engenharia e Instruções para Agentes Autônomos (Jules & Antigravity)

Este documento contém convenções, regras arquiteturais e padrões de código do **LUB-TEK**.
Agentes autônomos devem ler e seguir rigorosamente estas diretrizes ao planejar, corrigir erros ou implementar melhorias no repositório.

---

## 1. Visão Geral da Arquitetura e Módulos Principais

- **Linguagem Principal**: PHP 8.1+ sem frameworks pesados (Vanilla PHP de alta performance).
- **Frontend**: HTML5 semântico, CSS3 estruturado e Vanilla JavaScript modular (arquitetura PWA para chão de fábrica).
- **Camada de Dados**: Abstração PDO em `db.php` com suporte transparente a SQLite por tenant e MySQL.
- **Roteamento da API**: O arquivo `api.php` atua como gateway/despachante único. As regras de negócio são divididas em Controllers em `api/` mapeadas via `api/ApiRouteRegistry.php`.

### 1.1. Módulos Funcionais do Sistema
1. **Módulo de Ativos & Hierarquia Industrial (`api/AssetsController.php`)**:
   - Árvore multinível: `Unidade/Área` ➔ `Equipamento` ➔ `Componente` ➔ `Ponto de Lubrificação`.
   - Propriedades técnicas: TAG industrial, IP hierárquico, fotos de identificação, fabricante e modelo.
2. **Módulo de Planos de Lubrificação (`export_lubrication_plan.php`, `includes/lubrication_plan_*`)**:
   - Geração dinâmica de planos baseados nos pontos cadastrados no banco de dados.
   - Padrão visual certificado `pet160-v2` para conformidade com auditorias industriais e ISO 55000.
   - Enriquecimento inteligente com IA (`LubricationPlanAiEnricher`) e validação de layout (`LubricationPlanPatternGuard`).
3. **Módulo de Pontos de Máquina & Especificações Técnicas**:
   - Armazenamento em JSON estruturado no campo `ativos.dados_tecnicos`: tipo de lubrificante (graxa/óleo, marcas homologadas LE, Kluber, Mobil, Shell), quantidade nominal, unidade (`g`, `l`, `kg`), frequência, condição da máquina (`Parada` ou `Em Marcha`) e histórico de intervenções.
4. **Módulo de Rotas QR Code e Operação em Campo**:
   - Rotas de inspeção geradas a partir da árvore de ativos com scanner de QR Code touch para tablets e smartphones industriais.
   - Apontamento rápido de execução em campo sem necessidade de download de manuais pesados.
5. **Módulo de Sincronização & Resiliência Offline (`api/OrdersController.php`)**:
   - Suporte a **Idempotência** via `idempotency_key` ou `request_id` para evitar registros duplicados em reconexões de rede no chão de fábrica.
   - Baixa de inventário não bloqueante e restauração automática de estoque em reabertura de O.S.
   - Agente de rotina: `includes/work_order_generation_agent.php` (`WorkOrderGenerationAgent`).
   - Ciclo de feedback: `agent_feedback` e telemetria Gemini com teto diário de tokens (`AI_DAILY_TOKEN_BUDGET`).

---

## 2. Governança de Contexto e Proteção contra Arquivos Binários

### 2.1. Proibição Estrita de Binários na Raiz
- **NUNCA** commite arquivos binários (`.pdf`, `.zip`, `.mp4`, `.tar`, `.exe`) na raiz do repositório.
- Documentos pesados de referência (ex: manuais técnicos de linhas completas) devem ser mantidos exclusivamente em `docs/planos-referencia/` e constar no `.gitignore`, `.cursorignore` e `.aiignore`.

### 2.2. Preservação de Headroom dos Agentes
- Para consultar dados técnicos de plantas industriais (ex: Linha Pet160), **NUNCA** tente ler PDFs brutos. Consulte os dados estruturados em `data/planos/`, `seed.php` ou via endpoints da API.
- Respeite as regras de exclusão de `.cursorignore` e `.aiignore` (pastas `data/sessions/`, `data/ai_cache/`, `deploy/`, `banco de dados/`).

---

## 3. Padrões de Código Backend (PHP)

### 3.1. Segurança e Banco de Dados
- **SEMPRE** use declarações preparadas (`prepare()` com placeholders nomeados ou posicionais) ao consultar ou gravar no banco de dados.
- **NUNCA** concatene variáveis diretamente em strings SQL (`SELECT ... WHERE id = " . $id` é expressamente proibido).
- Para operações multi-tabelas críticas, utilize transações (`$pdo->beginTransaction()`, `$pdo->commit()`, `$pdo->rollBack()`).

### 3.2. Padrão de Respostas da API
- Todos os endpoints devem responder em JSON padronizado com cabeçalho `Content-Type: application/json; charset=utf-8`.
- Utilize o formato padrão:
  ```php
  // Sucesso
  echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;

  // Erro
  http_response_code(400); // ou 403, 404, 500 conforme o caso
  echo json_encode(['success' => false, 'error' => 'Mensagem amigável'], JSON_UNESCAPED_UNICODE);
  exit;
  ```

### 3.3. Autenticação, Autorização e Idempotência
- Valide sessão ativa (`session_start()`, checagem de `$_SESSION['user_id']` e tenant) antes de executar qualquer ação restrita.
- Respeite os papéis de usuário (`developer`, `gestor`, `trabalhador`, `cliente`).
- Toda requisição mutatória com sessão ativa no frontend deve enviar e validar o token anti-CSRF (`CsrfGuard::validate()`).
- O sistema conta com proteção de força bruta ativa (`SecurityGuard::isLockedOut()`), bloqueando tentativas excessivas.
- Apontamentos de O.S. enviados em modo offline devem verificar a chave de idempotência para garantir execução única.

### 3.4. Calibração de Engraxadeiras e PCM 4.0
- As ordens de relubrificação suportam contagem de golpes de engraxadeira manual/pneumática (`engraxadeiras_calibracao`), convertendo automaticamente golpes em gramas reais de consumo (`qtd_real`).

---

## 4. Padrões de Frontend (JS e CSS)

- Mantenha o JavaScript modular e desacoplado de bibliotecas externas complexas, preservando o carregamento rápido do PWA.
- Toda chamada AJAX/fetch para a API deve tratar tanto sucesso (`res.ok`) quanto falha de rede e exibir feedback visual compreensível ao operador da fábrica.
- Ao adicionar elementos visuais, preserve a paleta de cores corporativa escura/industrial e garanta compatibilidade mobile (telas touch de tablets industriais e smartphones).

---

## 5. Banco de Dados e Migrações

- Ao adicionar novos campos ou tabelas:
  1. Crie um novo script de migração na pasta `migrations/` seguindo a numeração sequencial.
  2. Garanta compatibilidade tanto com SQLite quanto com MySQL.
  3. Evite `DROP TABLE` ou remoção destrutiva de colunas em produção.

---

## 6. Validação e Testes Obrigatórios Antes de Abrir Pull Request

Antes de submeter qualquer Pull Request ou finalizar uma tarefa, o agente **DEVE**:
1. Verificar a sintaxe de todos os arquivos PHP modificados (`php -l caminho/do/arquivo.php`).
2. Garantir que nenhuma credencial ou chave de API foi inserida diretamente no código-fonte.
3. Fornecer uma descrição clara no Pull Request contendo:
   - Qual problema foi corrigido ou qual melhoria foi adicionada.
   - Lista dos arquivos alterados.
   - Instruções para teste manual.

---

## 7. Fase 2 — Resiliência Industrial Avançada, Offline e Integrações

### 7.1. Roteamento de QR Code Desacoplado e Pre-fetching de Linha
- **Identificador Público Imutável (`qr_hash`)**: Os ativos suportam um identificador `qr_hash` indexado e imutável no banco (`ativos.qr_hash`), permitindo substituição física de etiquetas ou tags patrimoniais sem quebrar os apontamentos e histórico.
- **Resolução via API**: O endpoint `GET /api.php?action=get_asset_by_qr&code=...` resolve o ativo por `qr_hash`, com fallback para `tag`, `id` sequencial ou `ip`. O retorno inclui os dados do ponto, ordens pendentes e o `line.id` da linha hierárquica superior.
- **Pre-fetching de Linha (`get_line_lubrication_points`)**: Ao ler o primeiro QR Code de um equipamento/linha (ex: Linha Pet160), o frontend aciona o endpoint em lote `GET /api.php?action=get_line_lubrication_points&line_id=...`. O payload traz todos os pontos, lubrificantes requeridos e especificações da linha inteira, permitindo cache local preventivo (`localStorage` / IndexedDB) e navegação 100% offline no chão de fábrica.

### 7.2. Contrato de Fila Offline (Outbox Pattern) e Separação de Timestamps
- As mutações geradas sem conexão são gravadas na fila offline (`lub_api_queue`) com o seguinte contrato obrigatório:
  ```json
  {
    "action": "save_order",
    "params": {
      "idempotency_key": "c3a9f7d2-6411-49b8-b118-809ab7d2105e",
      "executed_at": "2026-10-15 08:30:00",
      "dosage_amount": 25.5,
      "dosage_unit": "g",
      "situacao": "Concluído",
      "ativo_id": 1042
    }
  }
  ```
- **Separação de Timestamps**:
  - `executed_at`: Timestamp real gerado pelo cliente no momento exato em que o técnico executou a lubrificação em campo. Esse é o timestamp gravado para fins de SLA e histórico de manutenção.
  - `synced_at`: Timestamp gerado pelo servidor no instante em que o pacote da fila offline foi recebido e processado.
- **Idempotência**: O backend valida `idempotency_key` via `IdempotencyGuard` e coluna única em `ordens.idempotency_key`, ignorando pacotes duplicados gerados por oscilações ou reconexões de rede no chão de fábrica.

### 7.3. Desacoplamento de Integrações Externas (SAP / ERP Queue)
- Chamadas de sincronização com sistemas legados ou ERP (SAP PM / SAP MM) **NUNCA** bloqueiam a requisição do técnico de campo.
- Ao concluir ou alterar uma ordem com impacto ERP, o sistema enfileira o evento em `integration_queue` via `IntegrationQueue::enqueue($pdo, 'SAP', 'WORK_ORDER_CONFIRMATION', $payload, $orderId)`.
- A fila possui:
  - Timeout de rede estrito (máximo 5s para evitar consumo de threads PHP).
  - Controle de tentativas (`attempts` vs `max_attempts`).
  - Tratamento de status (`PENDING`, `PROCESSING`, `SUCCESS`, `RETRY`, `FAILED`).
  - Reprocessamento manual e monitoramento via endpoints `get_integration_queue`, `retry_integration_item` e `process_integration_queue`.

### 7.4. Compressão de Mídia em Campo (Client-Side Image Compression)
- Antes do armazenamento em cache offline ou upload para a API, toda imagem capturada em campo passa pelo utilitário `ImageCompressor` (`assets/js/modules/image_compressor.js`).
- O utilitário utiliza HTML5 `<canvas>` para:
  - Redimensionar proporcionalmente para no máximo `1280px` de largura/altura.
  - Converter para `image/webp` com qualidade `0.75` (com fallback para `image/jpeg 0.75` em navegadores industriais legados).
  - Reduzir o payload de 5MB-10MB (câmera do smartphone/tablet) para cerca de 150KB-250KB, economizando largura de banda 4G e armazenamento do IndexedDB/servidor.

### 7.5. Governança e Rastreabilidade de Grau Alimentício (NSF H1)
- **Flag `is_food_grade_required`**: Presente na tabela `ativos` e na validação de engenharia. Identifica equipamentos e pontos críticos de contato alimentar (linhas de envase, bebidas e alimentos como Pet160).
- **Validação de Insumo**: O método `GreaseCompatibility::validateFoodGrade()` intercepta qualquer tentativa de aplicar lubrificantes sem homologação NSF H1 em pontos marcados com a flag, impedindo não conformidades de auditoria ISO 22000 / FSSC 22000.
- **Dosagem Estruturada**: Os campos `dosage_amount` (numérico) e `dosage_unit` (`g`, `ml`, `gotas`, `bombadas`) permitem cálculo e sumarização precisa do consumo de insumos por linha, setor e equipamento.

