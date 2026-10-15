# Operação LUB-TEK

## Cron sugerido (CLI no servidor)

```cron
0 2 * * * php /path/to/Lub-tek/generate_orders.php
* * * * * php /path/to/Lub-tek/scripts/process_cbm_jobs.php
30 3 * * * php /path/to/Lub-tek/maintenance.php
15 4 * * * php /path/to/Lub-tek/sap_exporter.php
0 5 * * 0 php /path/to/Lub-tek/scripts/rebuild_tenant_login_index.php
```

## Migrações

```bash
php migrate_all_tenants.php
```

## Testes

```bash
php scripts/system_audit.php
php scripts/test_agent_eval.php
php scripts/calibrate_agent_weights.php TENANT_SLUG
```

## Deploy Hostinger

1. `php scripts/build_lubtek_deploy.php`
2. Enviar pasta `deploy/lubteksystem-production/` via FTP/painel
3. Garantir `config.local.php` fora do git no servidor

## Backups

- Local: `backup_db.php` (CLI)
- S3/R2: configurar em `config.local.php` (`backup_exporter`)

## IA / Agentes

- Feedback: endpoint `save_agent_feedback`
- Calibração: `scripts/calibrate_agent_weights.php`
- Orçamento diário: env `AI_DAILY_TOKEN_BUDGET`
