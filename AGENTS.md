# AGENTS.md — Diretrizes de Engenharia e Instruções para Agentes Autônomos (Jules)

Este documento contém convenções, regras arquiteturais e padrões de código do **LUB-TEK**.
Agentes autônomos como o **Jules (Google)** devem ler e seguir rigorosamente estas diretrizes ao planejar, corrigir erros ou implementar melhorias no repositório.

---

## 1. Visão Geral da Arquitetura

- **Linguagem Principal**: PHP 8.1+ sem frameworks pesados (Vanilla PHP de alta performance).
- **Frontend**: HTML5 semântico, CSS3 estruturado e Vanilla JavaScript modular.
- **Camada de Dados**: Abstração PDO em `db.php` com suporte transparente a SQLite por tenant e MySQL.
- **Roteamento da API**: O arquivo `api.php` atua como gateway/despachante. As regras de negócio são divididas em Controllers na pasta `api/` (ex: `AssetsController.php`, `OrdersController.php`, `KPIController.php`, `SAPController.php`).

---

## 2. Padrões de Código Backend (PHP)

### 2.1. Segurança e Banco de Dados
- **SEMPRE** use declarações preparadas (`prepare()` com placeholders nomeados ou posicionais) ao consultar ou gravar no banco de dados.
- **NUNCA** concatene variáveis diretamente em strings SQL (`SELECT ... WHERE id = " . $id` é expressamente proibido).
- Para operações multi-tabelas críticas, utilize transações (`$pdo->beginTransaction()`, `$pdo->commit()`, `$pdo->rollBack()`).

### 2.2. Padrão de Respostas da API
- Todos os endpoints devem responder em JSON padronizado com cabeçalho `Content-Type: application/json; charset=utf-8`.
- Utilize a função helper ou formato padrão do sistema:
  ```php
  // Sucesso
  echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;

  // Erro
  http_response_code(400); // ou 404, 500 conforme o caso
  echo json_encode(['success' => false, 'error' => 'Mensagem amigável'], JSON_UNESCAPED_UNICODE);
  exit;
  ```

### 2.3. Autenticação e Autorização
- Valide sessão ativa (`session_start()`, checagem de `$_SESSION['user_id']` e tenant) antes de executar qualquer ação restrita.
- Respeite os papéis de usuário (`admin`, `manager`, `lubricator`, `viewer`).

---

## 3. Padrões de Frontend (JS e CSS)

- Mantenha o JavaScript modular e desacoplado de bibliotecas externas complexas, preservando o carregamento rápido do PWA.
- Toda chamada AJAX/fetch para a API deve tratar tanto sucesso (`res.ok`) quanto falha de rede e exibir feedback visual compreensível ao operador da fábrica.
- Ao adicionar elementos visuais, preserve a paleta de cores corporativa escura/industrial e garanta compatibilidade mobile (telas touch de tablets industriais e smartphones).

---

## 4. Banco de Dados e Migrações

- Ao adicionar novos campos ou tabelas:
  1. Crie um novo script de migração na pasta `migrations/` seguindo a numeração sequencial.
  2. Garanta compatibilidade tanto com SQLite quanto com MySQL.
  3. Evite `DROP TABLE` ou remoção destrutiva de colunas em produção.

---

## 5. Validação e Testes Obrigatórios Antes de Abrir Pull Request

Antes de submeter qualquer Pull Request ou finalizar uma tarefa, o agente **DEVE**:
1. Verificar a sintaxe de todos os arquivos PHP modificados:
   ```bash
   php -l caminho/do/arquivo.php
   ```
2. Garantir que nenhuma credencial ou chave de API foi inserida diretamente no código-fonte.
3. Fornecer uma descrição clara no Pull Request contendo:
   - Qual problema foi corrigido ou qual melhoria foi adicionada.
   - Lista dos arquivos alterados.
   - Instruções para teste manual.
