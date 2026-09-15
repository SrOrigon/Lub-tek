<?php
/**
 * LUB-TEK - DB
 * Multi-tenant: database.sqlite (admin) + banco de dados/{empresa}.sqlite (clientes)
 */

require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/lubrication_tech_sync.php';
require_once __DIR__ . '/includes/cbm_job_queue.php';
require_once __DIR__ . '/includes/schema_migrations.php';

class DB
{
    public const SCHEMA_VERSION = '1.5';

    /** @var DB[] */
    private static $instances = [];
    /** @var array<string, bool> evita recursão durante o construtor */
    private static $constructing = [];
    private $pdo;
    private $tenantKey;
    private $tenantSlug;
    private $dbPath;

    private function __construct(?string $tenant = null)
    {
        $this->tenantSlug = $tenant;
        $this->tenantKey = $tenant ?? '__default__';
        $dbPath = TenantResolver::resolvePath($tenant);
        $this->dbPath = $dbPath;
        $isNew = !file_exists($dbPath);

        try {
            @set_time_limit(60);
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $this->pdo->exec('PRAGMA journal_mode=WAL;');
            $this->pdo->exec('PRAGMA synchronous=NORMAL;');
            $this->pdo->exec('PRAGMA foreign_keys=ON;');
            // 20s: 15 técnicos + IoT; WAL + BEGIN IMMEDIATE evitam "database is locked"
            $this->pdo->exec('PRAGMA busy_timeout = 20000;');
            $this->pdo->exec('PRAGMA temp_store = MEMORY;');
            $this->pdo->exec('PRAGMA wal_autocheckpoint = 1000;');
            // Valores moderados para hosting compartilhado (evita 503 por LVE/memória)
            $this->pdo->exec('PRAGMA mmap_size=8388608;');
            $this->pdo->exec('PRAGMA cache_size=-4000;');

            if ($tenant === null) {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS tenant_api_keys (
                    api_key TEXT PRIMARY KEY,
                    tenant_slug TEXT UNIQUE NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }

            // CRÍTICO: getMeta/setMeta usam $this->pdo (NUNCA getInstance) para evitar
            // recursão infinita no construtor — causa do 503 no Hostinger.
            $currentVer = $this->getMeta('db_version');
            if ($isNew) {
                $this->runMigrationsLocked(function () {
                    $this->initDatabase();
                    if ($this->ensureSchemaIntegrity()) {
                        SchemaMigrations::apply($this->pdo);
                        $this->ensureIndexes();
                        $this->setMeta('db_version', self::SCHEMA_VERSION);
                    }
                });
            } elseif ($currentVer !== self::SCHEMA_VERSION && self::shouldAutoMigrate()) {
                $this->runMigrationsLocked(function () {
                    if ($this->ensureSchemaIntegrity()) {
                        SchemaMigrations::apply($this->pdo);
                        $this->ensureIndexes();
                        $this->setMeta('db_version', self::SCHEMA_VERSION);
                    }
                });
            } elseif ($currentVer !== self::SCHEMA_VERSION) {
                error_log(
                    'DB schema desatualizado (versão ' . ($currentVer ?: 'null') .
                    ' → ' . self::SCHEMA_VERSION . '). Rode: php migrate_all_tenants.php'
                );
            }
        } catch (PDOException $e) {
            error_log('DB Critical Error: ' . $e->getMessage());
            if (defined('API_CONTEXT')) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Erro interno de banco de dados.']);
                exit;
            }
            die('Erro interno no servidor. Contate o suporte.');
        }
    }

    public static function setSystemMeta($key, $value, ?string $tenant = null)
    {
        try {
            $pdo = self::getInstance($tenant);
            $pdo->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO system_meta (mkey, mval) VALUES (?, ?)');
            $stmt->execute([$key, $value]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getSystemMeta($key, ?string $tenant = null)
    {
        try {
            $pdo = self::getInstance($tenant);
            $pdo->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
            $stmt = $pdo->prepare('SELECT mval FROM system_meta WHERE mkey = ?');
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val === false ? null : $val;
        } catch (Exception $e) {
            return null;
        }
    }

    /** Usa a conexão atual — seguro dentro do construtor */
    private function setMeta($key, $value)
    {
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
            $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO system_meta (mkey, mval) VALUES (?, ?)');
            $stmt->execute([$key, $value]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /** Usa a conexão atual — seguro dentro do construtor */
    private function getMeta($key)
    {
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS system_meta (mkey TEXT PRIMARY KEY, mval TEXT)');
            $stmt = $this->pdo->prepare('SELECT mval FROM system_meta WHERE mkey = ?');
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val === false ? null : $val;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return PDO
     */
    public static function getInstance(?string $tenant = null)
    {
        if ($tenant === null) {
            $tenant = TenantResolver::getCurrentTenant();
        }

        return self::getInstanceForTenant($tenant);
    }

    /**
     * Sempre o banco master (database.sqlite), ignorando sessão/tenant forçado.
     * Use para tenant_api_keys, rate_limits globais e login admin.
     */
    public static function getMaster(): PDO
    {
        return self::getInstanceForTenant(null);
    }

    private static function getInstanceForTenant(?string $tenant): PDO
    {
        $key = $tenant ?? '__default__';

        if (isset(self::$instances[$key])) {
            return self::$instances[$key]->pdo;
        }

        // Proteção contra reentrada (ex.: chamada estática durante o __construct)
        if (!empty(self::$constructing[$key])) {
            throw new RuntimeException('Inicialização recursiva do banco detectada para: ' . $key);
        }

        self::$constructing[$key] = true;
        try {
            self::$instances[$key] = new self($tenant);
        } finally {
            unset(self::$constructing[$key]);
        }

        return self::$instances[$key]->pdo;
    }

    public static function getCurrentTenantSlug(): ?string
    {
        $key = TenantResolver::getCurrentTenant();
        return $key;
    }

    public static function isLockedException(PDOException $e): bool
    {
        $msg = $e->getMessage();
        return stripos($msg, 'database is locked') !== false
            || stripos($msg, 'database is busy') !== false
            || (isset($e->errorInfo[1]) && ((int) $e->errorInfo[1] === 5 || (int) $e->errorInfo[1] === 6));
    }

    /**
     * Migração automática só em CLI/cron. Web não altera schema no construtor do PDO.
     */
    public static function shouldAutoMigrate(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        return defined('LUBTEK_ALLOW_WEB_MIGRATE') && LUBTEK_ALLOW_WEB_MIGRATE;
    }

    /**
     * Executes a callback within a transaction safely.
     * BEGIN IMMEDIATE reserva o lock de escrita no início (evita deadlock deferred).
     */
    public static function safeExecute(callable $work)
    {
        $pdo = self::getInstance();
        if ($pdo->inTransaction()) {
            return $work($pdo);
        }

        $attempts = 0;
        $maxAttempts = 8;
        $delay = 50000; // 50ms

        while (true) {
            try {
                $pdo->exec('BEGIN IMMEDIATE');
                $result = $work($pdo);
                $pdo->exec('COMMIT');
                return $result;
            }
            catch (PDOException $e) {
                try {
                    if ($pdo->inTransaction()) {
                        $pdo->exec('ROLLBACK');
                    }
                } catch (PDOException $ignored) {
                }

                if (self::isLockedException($e) && $attempts < $maxAttempts) {
                    $attempts++;
                    usleep($delay);
                    $delay = min(800000, $delay * 2);
                    continue;
                }
                throw $e;
            }
            catch (Exception $e) {
                try {
                    if ($pdo->inTransaction()) {
                        $pdo->exec('ROLLBACK');
                    }
                } catch (PDOException $ignored) {
                }
                throw $e;
            }
            catch (Throwable $e) {
                try {
                    if ($pdo->inTransaction()) {
                        $pdo->exec('ROLLBACK');
                    }
                } catch (PDOException $ignored) {
                }
                throw $e;
            }
        }
    }

    /**
     * Ensures Indices exist for critical query paths.
     * This prepares the DB for "more and more information".
     */
    private function ensureIndexes()
    {
        try {
            // List of critical indexes for scalability and fast bulk SAP/IoT imports
            $indexes = [
                'idx_ativos_pai' => 'CREATE INDEX IF NOT EXISTS idx_ativos_pai ON ativos(pai_id)',
                'idx_ativos_nome' => 'CREATE INDEX IF NOT EXISTS idx_ativos_nome ON ativos(nome)',
                'idx_ativos_tipo' => 'CREATE INDEX IF NOT EXISTS idx_ativos_tipo ON ativos(tipo)',
                'idx_ativos_tag' => 'CREATE INDEX IF NOT EXISTS idx_ativos_tag ON ativos(tag)',
                'idx_ativos_ip' => 'CREATE INDEX IF NOT EXISTS idx_ativos_ip ON ativos(ip)',
                'idx_catalogo_nome' => 'CREATE INDEX IF NOT EXISTS idx_catalogo_nome ON catalogo(nome)',
                'idx_catalogo_codigo' => 'CREATE INDEX IF NOT EXISTS idx_catalogo_codigo ON catalogo(codigo)',
                'idx_ordens_ativo' => 'CREATE INDEX IF NOT EXISTS idx_ordens_ativo ON ordens(ativo_id)',
                'idx_ordens_situacao' => 'CREATE INDEX IF NOT EXISTS idx_ordens_situacao ON ordens(situacao)',
                'idx_ordens_prio' => 'CREATE INDEX IF NOT EXISTS idx_ordens_prio ON ordens(prioridade)',
                'idx_ordens_data' => 'CREATE INDEX IF NOT EXISTS idx_ordens_data ON ordens(data_planejada)',
                'idx_ordens_sap' => 'CREATE INDEX IF NOT EXISTS idx_ordens_sap ON ordens(materiais_sap)',
                'idx_planos_ativo' => 'CREATE INDEX IF NOT EXISTS idx_planos_ativo ON planos(ativo_id)',
                'idx_pi_tags_ativo' => 'CREATE INDEX IF NOT EXISTS idx_pi_tags_ativo ON pi_tags(ativo_id)',
                'idx_pi_telemetry_tag' => 'CREATE INDEX IF NOT EXISTS idx_pi_telemetry_tag ON pi_telemetry(tag_id)',
                'idx_pi_telemetry_time' => 'CREATE INDEX IF NOT EXISTS idx_pi_telemetry_time ON pi_telemetry(timestamp)',
                'idx_cbm_jobs_status' => 'CREATE INDEX IF NOT EXISTS idx_cbm_jobs_status ON cbm_jobs(status, id)',
                'idx_lub_material' => 'CREATE INDEX IF NOT EXISTS idx_lub_material ON ativos_lubrificacao(lubrificante)',
                'idx_lub_setor' => 'CREATE INDEX IF NOT EXISTS idx_lub_setor ON ativos_lubrificacao(setor_id)',
            ];

            foreach ($indexes as $name => $sql) {
                try {
                    $this->pdo->exec($sql);
                } catch (Exception $idxEx) {
                    error_log("Index $name skipped: " . $idxEx->getMessage());
                }
            }
        }
        catch (Exception $e) {
            error_log("Index optimization failed: " . $e->getMessage());
        }
    }

    public static function forceMigration(?string $tenant = null)
    {
        if ($tenant === null && isset($_SESSION)) {
            $tenant = TenantResolver::getCurrentTenant();
        }

        $key = $tenant ?? '__default__';

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($tenant);
        }

        self::$instances[$key]->runMigrationsLocked(function () use ($key) {
            if (self::$instances[$key]->ensureSchemaIntegrity()) {
                SchemaMigrations::apply(self::$instances[$key]->pdo);
                self::$instances[$key]->ensureIndexes();
                self::$instances[$key]->setMeta('db_version', self::SCHEMA_VERSION);
            }
        });
    }

    private function runMigrationsLocked(callable $work): void
    {
        $lockPath = $this->dbPath . '.migrate.lock';
        $fh = @fopen($lockPath, 'c');
        if ($fh === false) {
            $work();
            return;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                $work();
                return;
            }
            $work();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Executa migrações no banco admin + todos os bancos de clientes.
     *
     * @return array<string, string>
     */
    public static function forceMigrationAll(): array
    {
        $results = [];

        try {
            self::forceMigration(null);
            $results['admin'] = 'ok';
        } catch (Exception $e) {
            $results['admin'] = 'error: ' . $e->getMessage();
        }

        foreach (TenantResolver::listTenantSlugs() as $slug) {
            try {
                self::forceMigration($slug);
                $results[$slug] = 'ok';
            } catch (Exception $e) {
                $results[$slug] = 'error: ' . $e->getMessage();
            }
        }

        return $results;
    }

    private function ensureSchemaIntegrity()
    {
        // ... (existing code)
        try {
            $addedColumns = [];

            // Check USUARIOS table
            $usuariosCols = $this->pdo->query("PRAGMA table_info(usuarios)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!empty($usuariosCols)) {
                if (!in_array('password_reset_required', $usuariosCols)) {
                    $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN password_reset_required INTEGER DEFAULT 0");
                    $addedColumns[] = "usuarios.password_reset_required";
                }
            }

            // Check ATIVOS table
            $ativosCols = $this->pdo->query("PRAGMA table_info(ativos)")->fetchAll(PDO::FETCH_COLUMN, 1);

            if (!empty($ativosCols)) {
                // Table exists, check for missing columns
                $requiredAtivosColumns = [
                    'dados_tecnicos' => 'ALTER TABLE ativos ADD COLUMN dados_tecnicos TEXT',
                    'imagem' => 'ALTER TABLE ativos ADD COLUMN imagem TEXT',
                    'imagem_3d' => 'ALTER TABLE ativos ADD COLUMN imagem_3d TEXT', // Added
                    'ip' => 'ALTER TABLE ativos ADD COLUMN ip INTEGER',
                    'json_specs' => 'ALTER TABLE ativos ADD COLUMN json_specs TEXT',
                    'status' => 'ALTER TABLE ativos ADD COLUMN status TEXT DEFAULT \'OK\'',
                    'user_id' => 'ALTER TABLE ativos ADD COLUMN user_id INTEGER DEFAULT 1',
                    'fabricante' => 'ALTER TABLE ativos ADD COLUMN fabricante TEXT',
                    'modelo' => 'ALTER TABLE ativos ADD COLUMN modelo TEXT',
                    'num_serie' => 'ALTER TABLE ativos ADD COLUMN num_serie TEXT',
                    'obs' => 'ALTER TABLE ativos ADD COLUMN obs TEXT',
                    'tag' => 'ALTER TABLE ativos ADD COLUMN tag TEXT'
                ];

                foreach ($requiredAtivosColumns as $col => $alterSql) {
                    if (!in_array($col, $ativosCols)) {
                        $this->pdo->exec($alterSql);
                        $addedColumns[] = "ativos.$col";
                    }
                }
            }

            // Check CATALOGO table
            $catalogoCols = $this->pdo->query("PRAGMA table_info(catalogo)")->fetchAll(PDO::FETCH_COLUMN, 1);

            if (!empty($catalogoCols)) {
                // Table exists, check for missing columns
                $requiredCatalogoColumns = [
                    'fabricante' => 'ALTER TABLE catalogo ADD COLUMN fabricante TEXT',
                    'codigo' => 'ALTER TABLE catalogo ADD COLUMN codigo TEXT',
                    'localizacao' => 'ALTER TABLE catalogo ADD COLUMN localizacao TEXT',
                    'descricao' => 'ALTER TABLE catalogo ADD COLUMN descricao TEXT',
                    'ip' => 'ALTER TABLE catalogo ADD COLUMN ip TEXT',
                    'imagem' => 'ALTER TABLE catalogo ADD COLUMN imagem TEXT',
                    'specs' => 'ALTER TABLE catalogo ADD COLUMN specs TEXT',
                    'estoque_atual' => 'ALTER TABLE catalogo ADD COLUMN estoque_atual REAL DEFAULT 0'
                ];

                foreach ($requiredCatalogoColumns as $col => $alterSql) {
                    if (!in_array($col, $catalogoCols)) {
                        $this->pdo->exec($alterSql);
                        $addedColumns[] = "catalogo.$col";
                    }
                }
            }

            // Check ORDENS table
            $ordensCols = $this->pdo->query("PRAGMA table_info(ordens)")->fetchAll(PDO::FETCH_COLUMN, 1);

            if (!empty($ordensCols)) {
                $requiredOrdensColumns = [
                    'data_conclusao' => 'ALTER TABLE ordens ADD COLUMN data_conclusao DATETIME',
                    'tipo_manutencao' => 'ALTER TABLE ordens ADD COLUMN tipo_manutencao TEXT',
                    'materiais' => 'ALTER TABLE ordens ADD COLUMN materiais TEXT',
                    'ip' => 'ALTER TABLE ordens ADD COLUMN ip TEXT',
                    'cod_serv' => 'ALTER TABLE ordens ADD COLUMN cod_serv TEXT',
                    'rota' => 'ALTER TABLE ordens ADD COLUMN rota TEXT',
                    'materiais_sap' => 'ALTER TABLE ordens ADD COLUMN materiais_sap TEXT',
                    'reserva_almox' => 'ALTER TABLE ordens ADD COLUMN reserva_almox TEXT',
                    'num_pontos' => 'ALTER TABLE ordens ADD COLUMN num_pontos INTEGER',
                    'complemento' => 'ALTER TABLE ordens ADD COLUMN complemento TEXT',
                    'data_emissao' => 'ALTER TABLE ordens ADD COLUMN data_emissao TEXT',
                    'data_execucao' => 'ALTER TABLE ordens ADD COLUMN data_execucao TEXT',
                    'horas_exec' => 'ALTER TABLE ordens ADD COLUMN horas_exec INTEGER',
                    'minutos_exec' => 'ALTER TABLE ordens ADD COLUMN minutos_exec INTEGER',
                    'cod_exec' => 'ALTER TABLE ordens ADD COLUMN cod_exec TEXT',
                    'conc_percent' => 'ALTER TABLE ordens ADD COLUMN conc_percent REAL',
                    'ph' => 'ALTER TABLE ordens ADD COLUMN ph REAL',
                    'agua_l' => 'ALTER TABLE ordens ADD COLUMN agua_l REAL',
                    'obs_exec' => 'ALTER TABLE ordens ADD COLUMN obs_exec TEXT',
                    'motivos' => 'ALTER TABLE ordens ADD COLUMN motivos TEXT',
                    'condicao_servico' => 'ALTER TABLE ordens ADD COLUMN condicao_servico TEXT',
                    'qtd_real' => 'ALTER TABLE ordens ADD COLUMN qtd_real TEXT'
                ];

                foreach ($requiredOrdensColumns as $col => $alterSql) {
                    if (!in_array($col, $ordensCols)) {
                        $this->pdo->exec($alterSql);
                        $addedColumns[] = "ordens.$col";
                    }
                }

                // Migrate cip to ip if cip exists but ip does not
                if (in_array('cip', $ordensCols) && !in_array('ip', $ordensCols)) {
                    try {
                        $this->pdo->exec('ALTER TABLE ordens RENAME COLUMN cip TO ip');
                    } catch (Exception $e) {
                        // ignore if fail
                    }
                    // Recarrega colunas: se o RENAME falhou (ex.: engine antigo do SQLite) e a coluna
                    // 'ip' já foi criada acima pelo loop de $requiredOrdensColumns, copia os dados
                    // de 'cip' para 'ip' (mesma estratégia usada em ativos/catalogo) para não perder dados.
                    $ordensColsAfter = $this->pdo->query("PRAGMA table_info(ordens)")->fetchAll(PDO::FETCH_COLUMN, 1);
                    if (in_array('cip', $ordensColsAfter, true) && in_array('ip', $ordensColsAfter, true)) {
                        $this->pdo->exec("UPDATE ordens SET ip = cip WHERE (ip IS NULL OR ip = '') AND cip IS NOT NULL AND cip != ''");
                    }
                }
            }

            // Ensure Analysis table
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS analises (
                id INTEGER PRIMARY KEY AUTOINCREMENT, 
                ativo_id INTEGER, 
                data_coleta TEXT, 
                laboratorio TEXT, 
                iso_4406 TEXT, 
                agua_ppm REAL, 
                fe_ppm REAL, 
                cu_ppm REAL, 
                si_ppm REAL, 
                laudo_geral TEXT, 
                arquivo TEXT, 
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // Ensure PI Tags & Telemetry tables (Condition-Based Maintenance)
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS pi_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tag_name TEXT UNIQUE NOT NULL,
                label TEXT NOT NULL,
                ativo_id INTEGER REFERENCES ativos(id) ON DELETE CASCADE,
                unit TEXT,
                warning_threshold REAL,
                critical_threshold REAL,
                current_value REAL,
                current_status TEXT DEFAULT 'OK',
                last_update DATETIME
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS pi_telemetry (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tag_id INTEGER REFERENCES pi_tags(id) ON DELETE CASCADE,
                value REAL NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            CbmJobQueue::ensureTable($this->pdo);
            try {
                LubricationTechSync::ensureTable($this->pdo);
                LubricationTechSync::backfill($this->pdo);
            } catch (Exception $lubEx) {
                error_log('Lubrication projection migration: ' . $lubEx->getMessage());
            }

            // Check ANALISES table for new fields
            $analisesCols = $this->pdo->query("PRAGMA table_info(analises)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!empty($analisesCols)) {
                $requiredAnalisescols = [
                    'visc40' => 'ALTER TABLE analises ADD COLUMN visc40 REAL',
                    'visc100' => 'ALTER TABLE analises ADD COLUMN visc100 REAL',
                    'acidez' => 'ALTER TABLE analises ADD COLUMN acidez REAL'
                ];
                foreach ($requiredAnalisescols as $col => $alter) {
                    if (!in_array($col, $analisesCols)) {
                        $this->pdo->exec($alter);
                        $addedColumns[] = "analises.$col";
                    }
                }
            }

            // DATA MIGRATION: cip -> ip (Legacy Support)
            if (!empty($ativosCols) && in_array('cip', $ativosCols) && in_array('ip', $ativosCols)) {
                $this->pdo->exec("UPDATE ativos SET ip = cip WHERE (ip IS NULL OR ip = '') AND cip IS NOT NULL AND cip != ''");
            }
            if (!empty($catalogoCols) && in_array('cip', $catalogoCols) && in_array('ip', $catalogoCols)) {
                $this->pdo->exec("UPDATE catalogo SET ip = cip WHERE (ip IS NULL OR ip = '') AND cip IS NOT NULL AND cip != ''");
            }

            // Marketplace offers table (padronizado como mercado_ofertas)
            $this->migrateLegacyMarketOffersTable();
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS mercado_ofertas (
                id INTEGER PRIMARY KEY,
                catalogo_id INTEGER,
                vendor_name TEXT,
                price REAL,
                url TEXT,
                delivery TEXT,
                verified INTEGER DEFAULT 0,
                rating REAL DEFAULT 4.5,
                obs TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // Rate limiting table (substitui arquivos temporários por IP)
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                ip_key TEXT PRIMARY KEY,
                count INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL
            )");
            $this->migrateRateLimitsSchema();

            // Log successful migrations
            if (!empty($addedColumns)) {
                error_log("DB Auto-Migration: Added columns: " . implode(', ', $addedColumns));
            }
            return true;
        }
        catch (Exception $e) {
            // If schema check fails, log but don't crash
            error_log("Schema integrity check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Migra tabela legada market_offers → mercado_ofertas (bancos de produção antigos).
     */
    private function migrateLegacyMarketOffersTable(): void
    {
        try {
            $tables = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'")
                ->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('market_offers', $tables, true)) {
                return;
            }

            $hadMercado = in_array('mercado_ofertas', $tables, true);

            // NÃO criar mercado_ofertas antes do RENAME — isso impedia a migração
            // e deixava código legado apontando para market_offers.
            if (!$hadMercado) {
                $this->pdo->exec('ALTER TABLE market_offers RENAME TO mercado_ofertas');
                error_log('DB Auto-Migration: market_offers renomeada para mercado_ofertas');
                return;
            }

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS mercado_ofertas (
                id INTEGER PRIMARY KEY,
                catalogo_id INTEGER,
                vendor_name TEXT,
                price REAL,
                url TEXT,
                delivery TEXT,
                verified INTEGER DEFAULT 0,
                rating REAL DEFAULT 4.5,
                obs TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $legacyCols = array_column(
                $this->pdo->query('PRAGMA table_info(market_offers)')->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );

            if (in_array('catalogo_id', $legacyCols, true)) {
                $this->pdo->exec(
                    'INSERT OR IGNORE INTO mercado_ofertas (catalogo_id, vendor_name, price, url, delivery, verified, rating)
                     SELECT catalogo_id, vendor_name, price, url, delivery, COALESCE(verified, 0), COALESCE(rating, 4.5)
                     FROM market_offers'
                );
            }

            $this->pdo->exec('DROP TABLE market_offers');
            error_log('DB Auto-Migration: dados de market_offers migrados para mercado_ofertas');
        } catch (Exception $e) {
            error_log('migrateLegacyMarketOffersTable: ' . $e->getMessage());
        }
    }

    private function initDatabase()
    {
        $queries = [
            "CREATE TABLE IF NOT EXISTS usuarios (
                 id INTEGER PRIMARY KEY AUTOINCREMENT,
                 nome TEXT NOT NULL,
                 email TEXT UNIQUE NOT NULL,
                 senha TEXT NOT NULL,
                 nivel INTEGER DEFAULT 1,
                 password_reset_required INTEGER DEFAULT 0
             )",
            "CREATE TABLE IF NOT EXISTS catalogo (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                tipo TEXT,
                fabricante TEXT,
                codigo TEXT,
                estoque_atual REAL DEFAULT 0,
                localizacao TEXT,
                descricao TEXT,
                specs TEXT,
                imagem TEXT
            )",
            "CREATE TABLE IF NOT EXISTS ativos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                tag TEXT,
                tipo TEXT,
                pai_id INTEGER REFERENCES ativos(id) ON DELETE CASCADE,
                imagem TEXT,
                obs TEXT,
                dados_tecnicos TEXT,
                ip INTEGER,
                json_specs TEXT,
                fabricante TEXT,
                modelo TEXT,
                num_serie TEXT,
                status TEXT DEFAULT 'OK',
                user_id INTEGER DEFAULT 1
            )",
            "CREATE TABLE IF NOT EXISTS planos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ativo_id INTEGER,
                catalogo_id INTEGER,
                frequencia_dias INTEGER,
                quantidade REAL,
                procedimento TEXT,
                metodo TEXT
            )",
            "CREATE TABLE IF NOT EXISTS ordens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                descricao TEXT,
                responsavel TEXT,
                data_planejada TEXT,
                prioridade TEXT,
                situacao TEXT DEFAULT 'Pendente',
                observacao TEXT,
                ativo_id INTEGER,
                usuarios_id INTEGER,
                last_sync DATETIME,
                qtd_real TEXT
            )",
            "CREATE TABLE IF NOT EXISTS logs_auditoria (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario TEXT,
                acao TEXT,
                alvo TEXT,
                dados_antigos TEXT,
                dados_novos TEXT,
                detalhes_erro TEXT,
                data TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($queries as $q) {
            $this->pdo->exec($q);
        }

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS mercado_ofertas (
            id INTEGER PRIMARY KEY,
            catalogo_id INTEGER,
            vendor_name TEXT,
            price REAL,
            url TEXT,
            delivery TEXT,
            verified INTEGER DEFAULT 0,
            rating REAL DEFAULT 4.5,
            obs TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            ip_key TEXT PRIMARY KEY,
            count INTEGER NOT NULL DEFAULT 0,
            window_start INTEGER NOT NULL
        )");
        $this->migrateRateLimitsSchema();
        CbmJobQueue::ensureTable($this->pdo);
        LubricationTechSync::ensureTable($this->pdo);

        // Fix 6: No Hardcoded Default Password
        // Bootstrap admin: senha aleatoria unica (nao usa hash publico conhecido).
        // password_reset_required=1 obriga troca no 1o acesso do gestor.
        $bootstrapSecret = bin2hex(random_bytes(24));
        $defaultHash = password_hash($bootstrapSecret, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (nome, email, senha, nivel, password_reset_required)
             SELECT 'Admin', 'admin@rodrigo.com', ?, 3, 1
             WHERE NOT EXISTS (SELECT 1 FROM usuarios)"
        );
        $stmt->execute([$defaultHash]);
    }

    /**
     * Migra rate_limits legado (coluna ip) → ip_key.
     */
    private function migrateRateLimitsSchema(): void
    {
        try {
            $cols = $this->pdo->query("PRAGMA table_info(rate_limits)")->fetchAll(PDO::FETCH_ASSOC);
            $names = array_column($cols, 'name');
            if (empty($names)) {
                return;
            }
            if (in_array('ip_key', $names, true)) {
                return;
            }
            if (!in_array('ip', $names, true)) {
                return;
            }

            $this->pdo->exec("ALTER TABLE rate_limits RENAME TO rate_limits_legacy");
            $this->pdo->exec("CREATE TABLE rate_limits (
                ip_key TEXT PRIMARY KEY,
                count INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL
            )");
            $this->pdo->exec("INSERT OR IGNORE INTO rate_limits (ip_key, count, window_start)
                SELECT ip, count, window_start FROM rate_limits_legacy");
            $this->pdo->exec("DROP TABLE rate_limits_legacy");
        } catch (Exception $e) {
            error_log('rate_limits migration failed: ' . $e->getMessage());
        }
    }

    // --- AUDIT LOGGING ---
    // --- AUDIT LOGGING ---
    public static function log($user, $action, $target, $old = null, $new = null)
    {
        try {
            // Use a separate quick connection or just exec if not in transaction conflict?
            // In SQLite, if we are in a transaction, this becomes part of it.
            // That's desired for successful audits.
            $pdo = self::getInstance();
            $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario, acao, alvo, dados_antigos, dados_novos) VALUES (?,?,?,?,?)");

            // Safe JSON Encode (Handle binary/invalid UTF8)
            $safeOld = json_encode($old, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($safeOld === false)
                $safeOld = "Error encoding data: " . json_last_error_msg();

            $safeNew = json_encode($new, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($safeNew === false)
                $safeNew = "Error encoding data: " . json_last_error_msg();

            $stmt->execute([
                $user,
                $action,
                $target,
                $safeOld,
                $safeNew
            ]);
        }
        catch (Exception $e) {
            error_log('DB::log failed: ' . $e->getMessage());
        }
    }

    public static function logError($e)
    {
        try {
            // Must force a new connection or ensure we are out of transaction for Error Logging if rollback happened?
            // SQLite supports only one writer. If transaction failed, we are likely rolled back.
            $pdo = self::getInstance();
            $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario, acao, detalhes_erro) VALUES (?,?,?)");
            $stmt->execute([
                'SYSTEM',
                'ERROR',
                $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine()
            ]);
        }
        catch (Exception $x) {
            error_log($x->getMessage());
        }
    }
    /**
     * Integrity Check for Tree Structure
     * Fixes orphaned nodes by moving them to a fallback root.
     */
    public static function checkTreeIntegrity()
    {
        $pdo = self::getInstance();

        // Find orphans: nodes with pai_id that doesn't exist in id
        $sql = "SELECT a.id FROM ativos a 
                LEFT JOIN ativos p ON a.pai_id = p.id 
                WHERE a.pai_id IS NOT NULL AND p.id IS NULL";

        $orphans = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

        if (count($orphans) > 0) {
            // Ensure Fallback Root
            $stmt = $pdo->prepare("SELECT id FROM ativos WHERE nome = ?");
            $stmt->execute(['Área Não Categorizada']);
            $rootId = $stmt->fetchColumn();

            if (!$rootId) {
                $pdo->prepare("INSERT INTO ativos (nome, tipo, obs) VALUES (?,?,?)")
                    ->execute(['Área Não Categorizada', 'setor', 'Criado automaticamente para abrigar itens órfãos.']);
                $rootId = $pdo->lastInsertId();
            }

            // Move Orphans
            $inQuery = implode(',', array_map('intval', $orphans));
            $pdo->exec("UPDATE ativos SET pai_id = $rootId WHERE id IN ($inQuery)");

            // Log this fix
            self::log('SYSTEM', 'AUTO_FIX', 'tree_integrity', null, ['moved_orphans' => count($orphans), 'target_root' => $rootId]);
        }
    }
}
