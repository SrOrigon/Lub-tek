# LUB-TEK — Sistema Avançado de Gestão de Lubrificação Industrial & PCM 4.0

![Versão](https://img.shields.io/badge/vers%C3%A3o-3.2.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?logo=php&logoColor=white)
![Status](https://img.shields.io/badge/status-produ%C3%A7%C3%A3o-green.svg)
![IA](https://img.shields.io/badge/AI-Google%20Gemini-orange.svg)

O **LUB-TEK** é uma plataforma corporativa completa desenvolvida para gestão de lubrificação de ativos industriais, planejamento e controle de manutenção (PCM), execução em campo e inteligência preditiva.

---

## 🚀 Principais Recursos

- **Engenharia de Lubrificação & Ativos**: Cadastro hierárquico de plantas, linhas, conjuntos mecânicos, pontos de lubrificação, métodos de aplicação e especificações técnicas de lubrificantes.
- **Planos & Rotas Periódicas**: Criação e acompanhamento de rotas de lubrificação (diárias, semanais, mensais, anuais) com cronograma automatizado.
- **Ordens de Serviço (OS)**: Abertura, delegação, execução e encerramento de ordens preventivas, preditivas e corretivas com registro fotográfico e checklist.
- **Inteligência Artificial (Google Gemini)**: Assistente técnico integrado para análise de causas raízes, sugestão de intervalos de relubrificação e recomendações de compatibilidade química de óleos e graxas.
- **Inventário & Controle de Insumos**: Monitoramento de saldo em tambores/galões, consumo médio, ponto de pedido e controle de lote/validade.
- **Painel de Indicadores & KPIs**: Cálculo em tempo real de MTBF, MTTR, taxa de aderência de rota, índice de conformidade e custo de lubrificação.
- **Integrações Corporativas**: Módulo de exportação de dados para SAP e endpoints compatíveis com Microsoft Power BI.
- **Arquitetura Multi-Tenant & Offline-Ready**: Suporte a múltiplos clientes/unidades com bancos isolados e Progressive Web App (PWA) com Service Worker.

---

## 🛠️ Stack Tecnológica

- **Backend**: PHP 8.1+ com arquitetura modular baseada em Controllers (`api/`)
- **Banco de Dados**: Suporte a SQLite (local/tenant) e MySQL via PDO com Migrations automatizadas
- **Frontend**: HTML5, CSS3 responsivo e Vanilla JavaScript com componentes modulares
- **PWA**: Service Worker (`sw.js` / `service-worker.js`) e Web App Manifest
- **IA**: Google Gemini API via cURL com cache inteligente de respostas

---

## 📁 Estrutura de Diretórios

```
Lub-tek/
├── api/                   # Controllers modulares da API (Assets, Orders, KPI, SAP, etc.)
├── assets/                # Arquivos CSS, ícones e recursos visuais
├── config/                # Configurações de layout e parâmetros da aplicação
├── data/                  # Diretório de sessões, templates e cache de IA
├── includes/              # Bibliotecas de suporte, helpers e validações
├── js/                    # Scripts client-side e gerenciadores de telas
├── migrations/            # Scripts de evolução e esquema do banco de dados
├── scripts/               # Utilitários de manutenção, auditoria e sincronização
├── tools/                 # Ferramentas auxiliares de desenvolvimento e teste
├── api.php                # Roteador central e despachante da API
├── config.php             # Configuração global do sistema
├── db.php                 # Camada de abstração de dados (PDO) e multi-tenant
└── index.php              # Ponto de entrada da aplicação web
```

---

## ⚙️ Instalação e Execução Local

### Pré-requisitos
- PHP 8.1 ou superior (com extensões `pdo`, `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`)
- Servidor Web (Apache com `mod_rewrite` habilitado ou PHP Built-in Server)

### Passos
1. **Configurar variáveis locais**:
   Crie o arquivo `config.local.php` na raiz baseado no exemplo:
   ```bash
   cp config.local.php.example config.local.php
   ```
   Defina suas chaves de API (ex: `GEMINI_API_KEY`) e configurações de ambiente.

2. **Inicializar o banco de dados**:
   Execute as migrações ou execute a rotina de provisionamento:
   ```bash
   php migrate_all_tenants.php
   ```

3. **Executar o servidor de desenvolvimento**:
   ```bash
   php -S localhost:8080
   ```
   Acesse no navegador: `http://localhost:8080`

---

## 🔒 Segurança

- Credenciais locais e chaves privadas nunca são versionadas (`.gitignore`).
- Arquivos de sessão, locks e caches de IA são mantidos isolados em ambiente local.
- Proteção CSRF, sanitização de inputs em PDO e restrições de permissão por perfil de usuário.

---

## 📄 Licença

Propriedade privada e confidencial — Todos os direitos reservados.
