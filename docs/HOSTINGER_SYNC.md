# Sincronização lubteksystem.com (Hostinger)

## Política atual (sem Git na Hostinger)

Por enquanto **não** usar deploy automático via GitHub/Git na Hostinger. Manter só dois alvos:

| Alvo | O que é | Como atualizar |
|------|---------|----------------|
| **Hostinger local** | Espelho do código de produção em `deploy/hostinger-sync/` + `deploy/lubtek-hostinger-sync.zip` | Sempre que houver mudanças relevantes no repo: `powershell -ExecutionPolicy Bypass -File scripts\build_hostinger_full_sync.ps1` |
| **Hostinger (produção)** | `lubteksystem.com` → `public_html` | Enviar o ZIP no hPanel e extrair na raiz (não sobrescrever `config.local.php` nem `.sqlite`) |

Agentes: após implementar correções/features que entram em produção, **rodar o build** acima para o espelho local ficar alinhado. Subir para produção quando o usuário pedir sync na Hostinger (hPanel ou TUS se funcionar).

## O que roda em produção hoje

- **Domínio:** `lubteksystem.com` (conta `u808090199`)
- **Banco:** SQLite (não há MySQL atribuído ao domínio na API Hostinger)
  - `database.sqlite` (admin)
  - `banco de dados/ambev.sqlite`, `demo.sqlite`, `cocacola.sqlite`, etc.
- **Segredos:** `config.local.php` no servidor (não sobrescrever no deploy)
- **Migrações:** ao acessar o app, `db.php` aplica scripts novos em `migrations/` (002–007 após este deploy)

## 1. Baixar banco de produção para o PC (espelho local)

A API Hostinger **não permite** baixar arquivos `.sqlite` (binário / sensível).

No **hPanel → Arquivos → lubteksystem.com → public_html**:

1. Baixe `database.sqlite`
2. Baixe a pasta `banco de dados/` (tenants)
3. Coloque em `D:\Lub-tek\` e `D:\Lub-tek\banco de dados\` (ou paths do seu `config.local.php` local)

Alternativa: **SSH** (se ativo na Hostinger):

```bash
cd ~/domains/lubteksystem.com/public_html
php migrate_all_tenants.php
```

## 2. Enviar código atualizado (pacote local)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\build_hostinger_full_sync.ps1
```

Gera:

- `deploy/hostinger-sync/` — árvore pronta (~123 arquivos)
- `deploy/lubtek-hostinger-sync.zip` — upload único

**Não inclui:** `config.local.php`, `*.sqlite`, `uploads/`, sessões.

### Atenção: ZIP no Windows

Use **`scripts/build_hostinger_full_sync.ps1`** (empacota com `tar`, paths com `/`).  
ZIPs feitos com `Compress-Archive` antigo geravam entradas `includes\arquivo.php` e quebravam o site (HTTP 500 no login). Se isso ocorrer, rode no servidor o script `hostinger_fix_backslash_paths.php` (one-shot) ou reenvie o pacote com `tar`.

### Upload (File Manager ou TUS)

1. No hPanel, envie `lubtek-hostinger-sync.zip` para a **raiz** de `public_html`
2. **Extrair** o ZIP no Gerenciador de Arquivos (não marque sobrescrever `config.local.php` se o painel perguntar)
3. Apague o ZIP após extrair

Upload automatizado (TUS):

1. Gere credenciais via API Hostinger (`hosting_files_generate-upload-url`) e grave em `deploy/.tus_session.json` (campos `url`, `auth_key`, `rest_auth_key`).
2. Gere o script one-shot e o token (na mesma sessão do agente ou manualmente): `deploy/hostinger_apply_sync.php` + `deploy/.deploy_token`.
3. Execute:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\hostinger_deploy.ps1
```

O script usa `deploy/tus_upload_file.ps1`. Se o TUS retornar **401**, faça upload manual do ZIP no hPanel (alguns ambientes só aceitam TUS a partir do File Browser). Não commite tokens.

## 3. Após o deploy

1. **Migrações:** abra o site logado ou rode `php migrate_all_tenants.php` via SSH
2. **PWA:** DevTools → Application → Unregister service worker → Ctrl+Shift+R
3. **Gestor:** dashboard → bloco **Operação dos Agentes (IA)** → Atualizar
4. **Smoke:** login, uma O.S., recusa neural com motivo opcional, upload de imagem

## Segurança

- Nunca commite `config.local.php`, `.sqlite` ou tokens de API no repositório
- Se um token de API foi exposto no chat, **revogue e gere outro** no hPanel → API
