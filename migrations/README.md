# Migrações LUB-TEK

Execute via CLI:

```bash
php migrate_all_tenants.php
```

Novos scripts numerados (`003_*` …) são idempotentes. DDL de runtime em controllers deve migrar para arquivos aqui.
