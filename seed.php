<?php
/**
 * LUB-TEK - Auto Seeder (MASTER PRODUCTION SYNC)
 * Replicates 100% of 'https://lubteksystem.com' assets and catalog.
 */

class Seeder
{
    public static function run($db)
    {
        // 0. CHECK FOR LOCAL SNAPSHOT (The "Saved within page" source of truth)
        $snapshotPath = __DIR__ . '/data/snapshot_latest.json';
        if (file_exists($snapshotPath)) {
            self::seedFromSnapshot($db, $snapshotPath);
        } else {
            // 1. FALLBACK: CATALOG SYNC (Ensures production SKUs exist)
            self::seedCatalog($db);

            // 2. FALLBACK: ASSETS SYNC (Digital Twin Hierarchy)
            self::seedAssets($db);
        }

        // 3. ALWAYS SEED ORDERS IF EMPTY
        self::seedOrders($db);

        // 4. SEED AUDIT LOGS IF EMPTY
        self::seedAuditLogs($db);
    }

    public static function seedFromSnapshot($db, $path)
    {
        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!$data || !isset($data['assets'])) {
            error_log("Snapshot corrupt or invalid. Falling back to hardcoded seed.");
            // Fallback
            self::seedCatalog($db);
            // self::seedAssets($db); // Disabled to prevent factory from coming back alone
            return;
        }

        // --- RESTORE CATALOG ---
        if (!empty($data['catalog'])) {
            // Only insert if empty? Or truncate?
            // "Saves everything" implies full restore.
            // But let's be safe: Insert OR IGNORE usually.
            // If the user wants a full restore, api('migrate') clears tables anyway?
            // DB::run calls seed.php AFTER ensureSchema. DB doesn't clear tables on run unless new db.
            // But handle_migrate() DOES NOT clear tables by default? 
            // Wait, previous handle_migrate was: seeder::run.

            // To be safe for "restore":
            // We should use INSERT OR REPLACE.

            $stmt = $db->prepare("INSERT OR REPLACE INTO catalogo (id, nome, tipo, fabricante, codigo, estoque_atual, localizacao, specs, imagem, ip) VALUES (?,?,?,?,?,?,?,?,?,?)");
            foreach ($data['catalog'] as $item) {
                $stmt->execute([
                    $item['id'],
                    $item['nome'],
                    $item['tipo'],
                    $item['fabricante'],
                    $item['codigo'],
                    $item['estoque_atual'],
                    $item['localizacao'],
                    $item['specs'],
                    $item['imagem'],
                    $item['ip']
                ]);
            }
        }

        // --- RESTORE ASSETS ---
        if (!empty($data['assets'])) {
            // If restoring snapshot, we probably want to mirror it exactly.
            // But deleting existing might be dangerous if we are just adding missing ones.
            // Since this is called by 'Migrate' (which implies a reset/sync), we assume we can overwrite.

            $cols = ['id', 'nome', 'tag', 'tipo', 'pai_id', 'imagem', 'obs', 'dados_tecnicos', 'ip', 'json_specs', 'status', 'user_id', 'fabricante', 'modelo', 'num_serie'];
            $pdoCols = implode(',', $cols);
            $pdobinds = implode(',', array_fill(0, count($cols), '?'));

            $stmt = $db->prepare("INSERT OR REPLACE INTO ativos ($pdoCols) VALUES ($pdobinds)");

            foreach ($data['assets'] as $a) {
                $vals = [];
                foreach ($cols as $c) {
                    $vals[] = $a[$c] ?? null;
                }
                $stmt->execute($vals);
            }
        }

        // error_log("Restored " . count($data['assets']) . " assets from Snapshot.");
    }

    public static function seedCatalog($db)
    {
        // Check if catalog is already populated (> 200 items means bearings are likely already there)
        $count = $db->query("SELECT COUNT(*) FROM catalogo")->fetchColumn();
        if ($count > 300)
            return;

        // Base Catalog Items (Production Essentials)
        $items = [
            // EQUIPAMENTOS
            ['Motor Elétrico WEG W22 Premium', 'Equipamento', 'Motor trifásico de alto rendimento.', 'WEG', 'SAP-112003', 5, 'Almox. Elétrica', ['Potência' => '10 CV', 'Tensão' => '380V', 'RPM' => '1750', 'Carcaça' => '132M']],
            ['Motor Siemens Simotics SD', 'Equipamento', 'Motor para aplicações severas.', 'Siemens', 'SAP-SIE-001', 2, 'Almox. Elétrica', ['Potência' => '7.5 CV', 'Eficiência' => 'IE3', 'Carcaça' => 'Ferro Fundido']],
            ['Redutor R 900 H&K', 'Equipamento', 'Redutor de velocidade coaxial.', 'H&K Drives', 'SAP-900821', 2, 'Almox. Central', ['Redução' => '1:45', 'Torque' => '2500 Nm', 'Óleo' => 'ISO VG 680']],
            ['Redutor SEW Eurodrive R77', 'Equipamento', 'Redutor helicoidal.', 'SEW', 'SAP-SEW-R77', 1, 'Almox. Mecânica', ['Ratio' => '23.44', 'Torque' => '820 Nm']],
            ['Bomba Centrífuga KSB Meganorm', 'Equipamento', 'Bomba de água padrão.', 'KSB', 'SAP-KSB-001', 1, 'Almox. Hidráulica', ['Vazão' => '50 m3/h', 'Altura' => '30m']],

            // COMPONENTES & PEÇAS
            ['Acoplamento Elástico A-80', 'Componente', 'Acoplamento de garras.', 'Vulcan', 'SAP-ACO-A80', 3, 'Prateleira D', ['Torque Nom' => '180 Nm']],
            ['Parafuso Sextavado M12x50 8.8', 'Componente', 'Zincado rosca parcial.', 'Ciser', 'SAP-PAR-M12', 200, 'Gaveta P1', ['Rosca' => 'MA']],
            ['Retentor 35x52x10 NBR', 'Componente', 'Retentor de óleo lábio duplo.', 'Sabó', 'SAP-RET-3552', 20, 'Gaveta C2', ['Material' => 'NBR']],

            // LUBRIFICANTES (PRODUCTION SET)
            ['MOBILGEAR 600 XP 68', 'Lubrificante', 'Óleo para engrenagens fechadas.', 'Mobil', 'LIS-GE-068', 200, 'Tambor', ['ISO VG' => '68', 'Tipo' => 'Mineral EP']],
            ['MOBILGEAR 600 XP 150', 'Lubrificante', 'Óleo para engrenagens fechadas.', 'Mobil', 'LIS-GE-150', 200, 'Tambor', ['ISO VG' => '150', 'Tipo' => 'Mineral EP']],
            ['MOBILGEAR 600 XP 220', 'Lubrificante', 'Óleo para engrenagens fechadas.', 'Mobil', 'LIS-GE-220', 100, 'Granel', ['ISO VG' => '220', 'Tipo' => 'Mineral EP']],
            ['MOBILGEAR 600 XP 320', 'Lubrificante', 'Óleo para engrenagens fechadas.', 'Mobil', 'LIS-GE-320', 400, 'Tanque 1', ['ISO VG' => '320', 'Base' => 'Mineral']],
            ['MOBILGEAR 600 XP 680', 'Lubrificante', 'Óleo para alta carga.', 'Mobil', 'LIS-GE-680', 2000, 'Tanque 3', ['ISO VG' => '680', 'Base' => 'Mineral']],
            ['MOBIL DTE 25 (ISO 46)', 'Lubrificante', 'Óleo hidráulico anti-desgaste.', 'Mobil', 'LIS-HY-046', 500, 'Tanque 2', ['ISO VG' => '46', 'Tipo' => 'Hydraulic AW']],
            ['MOBIL SHC 630', 'Lubrificante', 'Óleo sintético PAO.', 'Mobil', 'LIS-SY-220', 60, 'Almox. Lub.', ['ISO VG' => '220', 'Base' => 'PAO']],
            ['MOBIL SHC 634', 'Lubrificante', 'Óleo sintético de alto desempenho.', 'Mobil', 'LIS-SY-460', 120, 'Tambor', ['ISO VG' => '460', 'Base' => 'Sintético']],
            ['SHELL TELLUS S2 V46', 'Lubrificante', 'Óleo hidráulico multiviscoso.', 'Shell', 'LIS-HA-046', 400, 'Tambor', ['ISO VG' => '46', 'Visc_Index' => 'Alto']],
            ['SYNTHOIL GEAR SYNTH 220', 'Lubrificante', 'Óleo sintético para engrenagens.', 'Synthoil', 'LIS-SY-SYN220', 100, 'Galão', ['ISO VG' => '220', 'Base' => 'Sintético']],
            ['SYNTHOIL GEAR SYNTH 460', 'Lubrificante', 'Óleo sintético para engrenagens.', 'Synthoil', 'LIS-SY-SYN460', 100, 'Galão', ['ISO VG' => '460', 'Base' => 'Sintético']],
            ['Graxa Shell Gadus S2 V220', 'Lubrificante', 'Graxa extrema pressão.', 'Shell', 'LIS-GR-GAD', 18, 'Balde', ['NLGI' => '2', 'Viscosidade_Base' => '220 cSt']],
            ['Graxa MOBILGREASE XHP 222', 'Lubrificante', 'Graxa complexo de lítio azul.', 'Mobil', 'LIS-GR-XHP', 18, 'Balde', ['NLGI' => '2', 'Cor' => 'Azul']],

            // SPECIALTY LE LUBRICANTS (FOUND IN PRODUCTION)
            ['LE 4220 H1 Quinplex® Syn FG Gear Oil', 'Lubrificante', 'Óleo sintético de engrenagens grau alimentício.', 'LE', 'LE 4220', 40, 'Almox.', ['ISO VG' => '220', 'Cert' => 'H1']],
            ['LE 4024 H1 Quinplex® High Temp', 'Lubrificante', 'Lubrificante de alta temperatura grau alimentício.', 'LE', 'LE 4024', 100, 'Almox.', ['Tipo' => 'Grasa', 'Cert' => 'H1']],
            ['LE Almagard® 3751 Lubrificante', 'Lubrificante', 'Graxa vermelha de longa duração resistente à água.', 'LE', 'LE 3751', 18, 'Balde', ['NLGI' => '2', 'Cor' => 'Vermelha']]
        ];

        // ADD MASSIVE BEARING LIST (MTEK) - Approx 270 items
        self::seedMtekBearings($db);

        $stmt = $db->prepare("INSERT OR IGNORE INTO catalogo (nome, tipo, descricao, fabricante, codigo, estoque_atual, localizacao, specs) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($items as $i) {
            $stmt->execute([$i[0], $i[1], $i[2], $i[3], $i[4], $i[5], $i[6], json_encode($i[7])]);
        }
    }

    public static function seedAssets($db)
    {
        // 1. UNIT: SOLAR COCA-COLA (ID 18)
        self::insertAsset($db, [
            'id' => 18,
            'nome' => 'Unidade: SOLAR COCA-COLA SÃO LUIS-MA LINH LHS PET SLZ K-89409410',
            'tag' => '',
            'tipo' => 'unidade',
            'pai_id' => NULL,
            'imagem' => 'uploads/718f1375d888ad77a604c1878b5854d0.png',
            'obs' => 'Unidade Principal Production',
            'ip' => 1007
        ]);

        // 2. ENCHEDORA KHS (ID 32)
        self::insertAsset($db, [
            'id' => 32,
            'nome' => 'Enchedora KHS Innofill 50.000grf/h',
            'tag' => '',
            'tipo' => 'equipamento',
            'pai_id' => 18,
            'imagem' => 'uploads/f537ea1b94fc94de4731277e2d5f1e1e.png',
            'obs' => 'EQUIPAMENTO RESPONSAVEL PELO ENVASE',
            'ip' => 10070302
        ]);

        // 3. ACIONAMENTO STOBER (ID 33)
        self::insertAsset($db, [
            'id' => 33,
            'nome' => 'Acionamento Stober K813 principal redutor',
            'tag' => '',
            'tipo' => 'componente',
            'pai_id' => 32,
            'imagem' => 'uploads/69128895c31b4561d50eea74d796455c.png',
            'obs' => 'REDUTOR DE ACIONAMENTO PRINCIPAL',
            'ip' => 1007030201,
            'fabricante' => 'Stober',
            'modelo' => 'K813VG0190ME50'
        ]);

        // 4. PONTO REDUTOR (ID 34)
        self::insertAsset($db, [
            'id' => 34,
            'nome' => 'REDUTOR',
            'tag' => '',
            'tipo' => 'ponto',
            'pai_id' => 33,
            'imagem' => 'uploads/5ac1de33e776d1023457d8dec19d100d.png',
            'dados_tecnicos' => '{"ip":"18","ponto_lub":"REDUTOR","servico":"Inspecionar","duracao_h":"","duracao_m":"30","periodo":"Mensal","prioridade":"Rotina","rota":"","procedimento":"","condicao":"Parada","sistema_lub":"","material":"LE 4220 H1 Quinplex® Syn FG Gear Oil- ISO VG 220","qtd_material":"13","unid_material":"l"}',
            'ip' => 100703020101
        ]);

        // 5. SISTEMA LUB CENTRAL (ID 35)
        self::insertAsset($db, [
            'id' => 35,
            'nome' => 'SISTEMA DE LUBRIFICAÇÃO CENTRAL',
            'tag' => '',
            'tipo' => 'componente',
            'pai_id' => 32,
            'imagem' => 'uploads/149834f79856df61f6355cca3da69831.png',
            'obs' => 'Sistema LUBRIFICAÇÃO centralizado',
            'ip' => 1007030202,
            'fabricante' => 'LINCOLN'
        ]);

        // 6. BOMBA LINCOLN (ID 36)
        self::insertAsset($db, [
            'id' => 36,
            'nome' => 'BOMBA DE LUBRIFICAÇÃO LINCONL',
            'tag' => '',
            'tipo' => 'ponto',
            'pai_id' => 35,
            'imagem' => 'uploads/a899633deec93e74f44c057e02236f5c.png',
            'dados_tecnicos' => '{"ip":"20","ponto_lub":"Bomba Lubrificação automatica LINCOLN","servico":"Verificar Nivel","duracao_h":"","duracao_m":"15","periodo":"Semestral","prioridade":"Rotina","rota":"","procedimento":"","condicao":"Parada","sistema_lub":"","material":"LE 4024 H1 Quinplex® High Temperature Lubricant","qtd_material":"4","unid_material":"kg"}',
            'ip' => 100703020201
        ]);

        // 7. SOPRADORA (ID 24)
        self::insertAsset($db, [
            'id' => 24,
            'nome' => 'SOPRADORAN. E70 B20L 025',
            'tag' => '',
            'tipo' => 'equipamento',
            'pai_id' => 18,
            'imagem' => 'uploads/2c5c44bab8ed900d142e186d964d5f98.png',
            'ip' => 1007
        ]);

        // 8. ESTAÇÃO DE SOPRO (ID 27)
        self::insertAsset($db, [
            'id' => 27,
            'nome' => 'Estação de sopro/Molde',
            'tag' => '',
            'tipo' => 'componente',
            'pai_id' => 24,
            'imagem' => 'uploads/da920de2ac36f8ca56d76418c7dc159f.png',
            'ip' => 100702,
            'fabricante' => 'KHS'
        ]);

        // 9. MOLDE N1 (ID 28)
        self::insertAsset($db, [
            'id' => 28,
            'nome' => 'Molde N°1',
            'tag' => '',
            'tipo' => 'ponto',
            'pai_id' => 27,
            'imagem' => 'uploads/eb651335287580dd4c0a9da378f5bb44.png',
            'dados_tecnicos' => '{"ip":"12","ponto_lub":"Anel de garras","servico":"Lubrificar","duracao_h":"","duracao_m":"15","periodo":"Semanal","prioridade":"Rotina","rota":"","procedimento":"","condicao":"Parada","sistema_lub":"","material":"LE 4024 H1 Quinplex® High Temperature Lubricant","qtd_material":"50","unid_material":"g"}',
            'ip' => 10070201
        ]);

        // 10. ESTIRAMENTO (ID 29)
        self::insertAsset($db, [
            'id' => 29,
            'nome' => 'Molde N°1 / sistema de estiramento',
            'tag' => '',
            'tipo' => 'ponto',
            'pai_id' => 27,
            'dados_tecnicos' => '{"ip":"13","ponto_lub":"","servico":"","duracao_h":"","duracao_m":"","periodo":"Semanal","prioridade":"Rotina","rota":"","procedimento":"","condicao":"Parada","sistema_lub":"","material":"sistema de estiramento","qtd_material":"2","unid_material":"g"}',
            'ip' => 10070202
        ]);

        // 11. MODULO SOPRO (ID 25)
        self::insertAsset($db, [
            'id' => 25,
            'nome' => 'Modulo de sopro/lubrificaçâo central',
            'tag' => '',
            'tipo' => 'componente',
            'pai_id' => 24,
            'imagem' => 'uploads/18ab7ae498e5e4708e13b9fcb87bdf0e.png',
            'ip' => 100701,
            'fabricante' => 'KHS'
        ]);

        // 12. SISTEMA LUB CENTRAL PONTO (ID 26)
        self::insertAsset($db, [
            'id' => 26,
            'nome' => 'SISTEMA DE LUBRIFICAÇÃO CENTRAL',
            'tag' => '',
            'tipo' => 'ponto',
            'pai_id' => 25,
            'imagem' => 'uploads/60042165e1eb1376fa913db601df82fc.png',
            'dados_tecnicos' => '{"ip":"10","ponto_lub":"Bomba Lubrificação automatica LINCOLN","servico":"Lubrificar","duracao_h":"","duracao_m":"40","periodo":"Quinzenal","prioridade":"Rotina","rota":"","procedimento":"","condicao":"Parada","sistema_lub":"","material":"LE 4024 H1 Quinplex® High Temperature Lubricant","qtd_material":"4","unid_material":"kg"}',
            'ip' => 10070101,
            'fabricante' => 'LINCOLN'
        ]);

        // 13. ROLAMENTO CENTRAL COMP (ID 30)
        self::insertAsset($db, [
            'id' => 30,
            'nome' => 'ROLAMENTO CENTRAL',
            'tag' => '',
            'tipo' => 'componente',
            'pai_id' => 24,
            'imagem' => 'uploads/ac572e9a5f77fcccf620fe5197897392.png',
            'obs' => 'ROLAMENTO CENTRAL SOPRADORA',
            'ip' => 100703,
            'fabricante' => 'KHS'
        ]);

        // 14. ROLAMENTO PONTO (ID 31)
        self::insertAsset($db, [
            'id' => 31,
            'nome' => 'ROLAMENTO',
            'tag' => '',
            'tipo' => 'ponto',
            'pai_id' => 30,
            'dados_tecnicos' => '{"ip":"15","ponto_lub":"ROLAMENTO CENTRAL","servico":"Inspecionar","duracao_h":"","duracao_m":"","periodo":"","prioridade":"","rota":"","procedimento":"","condicao":"","sistema_lub":"","material":"LE 4024 H1 Quinplex® High Temperature Lubricant","qtd_material":"1","unid_material":"kg"}',
            'ip' => 10070301,
            'obs' => 'ROLAMENTO PRINCIPAL SOPRADORA'
        ]);

        // ---------------------------------------------------------
        // UNIT 2: MOCKUP REMOVED
        // (Clean slate request)
    }

    private static function insertAsset($db, $data)
    {
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = "INSERT OR REPLACE INTO ativos (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $db->prepare($sql)->execute(array_values($data));
    }

    /**
     * Massive Bearing Seeder - Based on technical relubrication tables
     */
    public static function seedMtekBearings($db)
    {
        $bore_codes = range(0, 30); // Up to 150mm bore
        $series_list = [
            ['prefix' => '60', 'type' => 'Esferas', 'D_f' => 1.5, 'D_a' => 10, 'B_f' => 0.25, 'B_a' => 6],
            ['prefix' => '62', 'type' => 'Esferas', 'D_f' => 1.7, 'D_a' => 12, 'B_f' => 0.3, 'B_a' => 9],
            ['prefix' => '63', 'type' => 'Esferas', 'D_f' => 2.3, 'D_a' => 15, 'B_f' => 0.5, 'B_a' => 10],
            ['prefix' => '222', 'type' => 'Autocompensador de Rolos', 'D_f' => 1.8, 'D_a' => 15, 'B_f' => 0.6, 'B_a' => 18],
            ['prefix' => '223', 'type' => 'Autocompensador de Rolos', 'D_f' => 2.5, 'D_a' => 20, 'B_f' => 0.8, 'B_a' => 25],
            ['prefix' => 'NU 2', 'type' => 'Rolos Cilíndricos', 'D_f' => 1.7, 'D_a' => 12, 'B_f' => 0.35, 'B_a' => 10],
            ['prefix' => 'NU 3', 'type' => 'Rolos Cilíndricos', 'D_f' => 2.3, 'D_a' => 15, 'B_f' => 0.55, 'B_a' => 12],
            ['prefix' => 'NJ 2', 'type' => 'Rolos Cilíndricos', 'D_f' => 1.7, 'D_a' => 12, 'B_f' => 0.35, 'B_a' => 10],
            ['prefix' => 'NJ 3', 'type' => 'Rolos Cilíndricos', 'D_f' => 2.3, 'D_a' => 15, 'B_f' => 0.55, 'B_a' => 12],
        ];

        $stmt = $db->prepare("INSERT OR IGNORE INTO catalogo (nome, tipo, descricao, fabricante, codigo, estoque_atual, localizacao, specs) 
                              VALUES (?, 'Rolamento', ?, 'Mtek/Diversos', ?, ?, ?, ?)");

        foreach ($series_list as $s) {
            foreach ($bore_codes as $bc) {
                // Bore Calculation
                if ($bc < 4) {
                    $d = [10, 12, 15, 17][$bc];
                } else {
                    $d = $bc * 5;
                }

                $suffix = str_pad($bc, 2, '0', STR_PAD_LEFT);
                $model = str_replace(' ', '', $s['prefix']) . $suffix;
                $fullName = $s['prefix'] . " " . $suffix;

                // Heuristic Dimensions (D and B)
                $D = round($d * $s['D_f'] + $s['D_a']);
                $B = round($d * $s['B_f'] + $s['B_a']);

                $specs = [
                    'd' => $d,
                    'D' => $D,
                    'B' => $B,
                    'tipo_serie' => $s['type'],
                    'dm' => ($d + $D) / 2
                ];

                $stmt->execute([
                    "ROLAMENTO " . $fullName,
                    "Rolamento de " . $s['type'] . " série " . $s['prefix'],
                    "MT-" . $model,
                    rand(0, 15), // Random stock
                    "ALMOX-ROL-Q" . rand(1, 4),
                    json_encode($specs)
                ]);
            }
        }
    }

    public static function seedOrders($db)
    {
        // Check if ordens is already populated
        $count = $db->query("SELECT COUNT(*) FROM ordens")->fetchColumn();
        if ($count > 0)
            return;

        // Seed 5 realistic work orders
        // active points in database are: 34 (REDUTOR), 36 (BOMBA LINCOLN), 28 (Molde N°1), 26 (SISTEMA DE LUBRIFICAÇÃO CENTRAL), 31 (ROLAMENTO)
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));
        $threeDaysAgo = date('Y-m-d', strtotime('-3 days'));
        $fiveDaysAgo = date('Y-m-d', strtotime('-5 days'));
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $nextWeek = date('Y-m-d', strtotime('+7 days'));

        $orders = [
            // Order 1: Concluída - Preventiva - Redutor (34)
            [
                'descricao' => 'Lubrificação preventiva e troca de óleo do Redutor principal.',
                'responsavel' => 'Carlos Silva',
                'data_planejada' => $threeDaysAgo,
                'data_conclusao' => $yesterday,
                'data_execucao' => $yesterday,
                'prioridade' => 'Alta',
                'situacao' => 'Concluído',
                'observacao' => 'Óleo trocado conforme procedimento técnico. Equipamento rodando macio e sem ruídos anômalos.',
                'ativo_id' => 34,
                'usuarios_id' => 1,
                'tipo_manutencao' => 'Preventiva',
                'materiais' => json_encode([
                    ['material' => 'LE 4220 H1 Quinplex® Syn FG Gear Oil- ISO VG 220', 'qtd' => 13, 'unid' => 'l', 'custo' => 585.00]
                ]),
                'horas_exec' => 2,
                'minutos_exec' => 30,
                'condicao_servico' => 'Parado'
            ],
            // Order 2: Concluída - Corretiva - Molde N1 (28)
            [
                'descricao' => 'Correção de falta de lubrificação no anel de garras - Molde N°1.',
                'responsavel' => 'Marcos Souza',
                'data_planejada' => $twoDaysAgo,
                'data_conclusao' => $yesterday,
                'data_execucao' => $yesterday,
                'prioridade' => 'Crítica',
                'situacao' => 'Concluído',
                'observacao' => 'Detectada alta temperatura e desgaste preliminar no anel de garras por falta de graxa. Aplicada graxa LE 4024 abundante.',
                'ativo_id' => 28,
                'usuarios_id' => 1,
                'tipo_manutencao' => 'Corretiva',
                'materiais' => json_encode([
                    ['material' => 'LE 4024 H1 Quinplex® High Temperature Lubricant', 'qtd' => 0.5, 'unid' => 'kg', 'custo' => 45.00]
                ]),
                'horas_exec' => 1,
                'minutos_exec' => 15,
                'condicao_servico' => 'Parado'
            ],
            // Order 3: Pendente - Preventiva - Rolamento (31)
            [
                'descricao' => 'Inspeção anual e checagem de folga do Rolamento Central da Sopradora.',
                'responsavel' => 'Carlos Silva',
                'data_planejada' => $tomorrow,
                'data_conclusao' => null,
                'data_execucao' => null,
                'prioridade' => 'Média',
                'situacao' => 'Pendente',
                'observacao' => 'Planejado para execução durante a parada técnica programada.',
                'ativo_id' => 31,
                'usuarios_id' => 1,
                'tipo_manutencao' => 'Preventiva',
                'materiais' => null,
                'horas_exec' => 0,
                'minutos_exec' => 0,
                'condicao_servico' => 'Parado'
            ],
            // Order 4: Pendente - Corretiva - Bomba Lincoln (36)
            [
                'descricao' => 'Verificação de vibração e nível de graxa na Bomba Automática Lincoln.',
                'responsavel' => 'Lucas Neves',
                'data_planejada' => $yesterday, // Overdue!
                'data_conclusao' => null,
                'data_execucao' => null,
                'prioridade' => 'Alta',
                'situacao' => 'Pendente',
                'observacao' => 'Atrasado devido à priorização da corretiva crítica do Molde N1.',
                'ativo_id' => 36,
                'usuarios_id' => 1,
                'tipo_manutencao' => 'Corretiva',
                'materiais' => null,
                'horas_exec' => 0,
                'minutos_exec' => 0,
                'condicao_servico' => 'Em Operação'
            ],
            // Order 5: Pendente - Preventiva - Bomba Central Sopro (26)
            [
                'descricao' => 'Troca preventiva de cartucho de graxa na Bomba Lincoln do Módulo Sopro.',
                'responsavel' => 'Marcos Souza',
                'data_planejada' => $nextWeek,
                'data_conclusao' => null,
                'data_execucao' => null,
                'prioridade' => 'Baixa',
                'situacao' => 'Pendente',
                'observacao' => 'Fazer checklist conforme plano semestral.',
                'ativo_id' => 26,
                'usuarios_id' => 1,
                'tipo_manutencao' => 'Preventiva',
                'materiais' => null,
                'horas_exec' => 0,
                'minutos_exec' => 0,
                'condicao_servico' => 'Parado'
            ]
        ];

        $stmt = $db->prepare("
            INSERT INTO ordens (
                descricao, responsavel, data_planejada, data_conclusao, data_execucao, 
                prioridade, situacao, observacao, ativo_id, usuarios_id, 
                tipo_manutencao, materiais, horas_exec, minutos_exec, condicao_servico, last_sync
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ");

        foreach ($orders as $o) {
            $stmt->execute([
                $o['descricao'],
                $o['responsavel'],
                $o['data_planejada'],
                $o['data_conclusao'],
                $o['data_execucao'],
                $o['prioridade'],
                $o['situacao'],
                $o['observacao'],
                $o['ativo_id'],
                $o['usuarios_id'],
                $o['tipo_manutencao'],
                $o['materiais'],
                $o['horas_exec'],
                $o['minutos_exec'],
                $o['condicao_servico']
            ]);
        }
    }

    public static function seedAuditLogs($db)
    {
        try {
            $count = $db->query("SELECT COUNT(*) FROM logs_auditoria")->fetchColumn();
            if ($count > 0) return;

            $entries = [
                [
                    'usuario' => 'admin@rodrigo.com',
                    'acao' => 'INICIALIZACAO_SISTEMA',
                    'alvo' => 'LUB-TEK 3.0 Core',
                    'dados_antigos' => null,
                    'dados_novos' => json_encode(['status' => 'ONLINE', 'tenant' => 'rodrigo', 'version' => '3.0.4']),
                    'data' => date('Y-m-d H:i:s', strtotime('-3 hours'))
                ],
                [
                    'usuario' => 'admin@rodrigo.com',
                    'acao' => 'CONFIGURACAO_COMPLIANCE',
                    'alvo' => 'Módulo de Auditoria',
                    'dados_antigos' => null,
                    'dados_novos' => json_encode(['politica' => 'ISO 55000', 'status' => 'ATIVO']),
                    'data' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                ],
                [
                    'usuario' => 'admin@rodrigo.com',
                    'acao' => 'ESTRUTURA_ATIVOS_VERIFICADA',
                    'alvo' => 'Planta Industrial (Digital Twin)',
                    'dados_antigos' => null,
                    'dados_novos' => json_encode(['ativos_totais' => 14, 'integridade' => 'OK']),
                    'data' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                ],
                [
                    'usuario' => 'admin@rodrigo.com',
                    'acao' => 'LOGIN_ADMINISTRADOR',
                    'alvo' => 'Painel Principal',
                    'dados_antigos' => null,
                    'dados_novos' => json_encode(['role' => 'admin', 'status' => 'Sessao Ativa']),
                    'data' => date('Y-m-d H:i:s')
                ]
            ];

            $stmt = $db->prepare("INSERT INTO logs_auditoria (usuario, acao, alvo, dados_antigos, dados_novos, data) VALUES (?,?,?,?,?,?)");
            foreach ($entries as $e) {
                $stmt->execute([$e['usuario'], $e['acao'], $e['alvo'], $e['dados_antigos'], $e['dados_novos'], $e['data']]);
            }
        } catch (Exception $e) {
            error_log("Error seeding audit logs: " . $e->getMessage());
        }
    }
}

// --- SELF-EXECUTION BLOCK FOR QUICK SEEDING (CLI only) ---
if (php_sapi_name() === 'cli') {
    try {
        require_once __DIR__ . '/db.php';
        $db = DB::getInstance();
        
        $fresh = isset($_GET['fresh']) || (isset($argv) && in_array('--fresh', $argv));
        if ($fresh) {
            $db->exec("DELETE FROM planos");
            $db->exec("DELETE FROM catalogo");
            $db->exec("DELETE FROM ativos");
            $db->exec("DELETE FROM ordens");
            echo "Banco de dados limpo com sucesso!\n";
        }

        Seeder::run($db);
        
        if (php_sapi_name() === 'cli') {
            echo "LUB-TEK database seeded successfully!\n";
        } else {
            echo "<!DOCTYPE html>
            <html lang='pt-BR'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>LUB-TEK Seeder</title>
                <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap' rel='stylesheet'>
                <style>
                    body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #0f172a; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                    .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; max-width: 480px; border: 1px solid #e2e8f0; }
                    h1 { color: #0284c7; font-weight: 800; font-size: 1.8rem; margin-bottom: 10px; }
                    p { color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 25px; }
                    .btn { background: #0284c7; color: white; text-decoration: none; padding: 12px 28px; border-radius: 10px; font-weight: 600; display: inline-block; transition: background 0.2s; }
                    .btn:hover { background: #0369a1; }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div style='font-size: 3rem; margin-bottom: 15px;'>⚡</div>
                    <h1>Banco de Dados LUB-TEK Ativado!</h1>
                    <p>Operação CLI concluída. O painel LUB-TEK está pronto para uso em produção.</p>
                    <a href='index.php' class='btn'>Acessar Painel LUB-TEK</a>
                </div>
            </body>
            </html>";
        }
    } catch (Exception $e) {
        if (php_sapi_name() === 'cli') {
            echo "ERROR: " . $e->getMessage() . "\n";
        } else {
            echo "<h1 style='color:red;'>Erro na População do Banco de Dados:</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}