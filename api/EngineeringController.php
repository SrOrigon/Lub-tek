<?php
/**
 * LUB-TEK - Engenharia tribologica
 * Dimensões: ISO 15 / ISO 355 / DIN 618
 * Relubrificação: SKF (tf, Gp) + fatores ambientais DIN 51825
 * Viscosidade: ASTM D341 (Walther)
 */

require_once __DIR__ . '/../includes/engineering_catalog.php';

class EngineeringController {
    private $db;
    private $userId;
    private $assetId;
    private $input;

    public function __construct($db, $userId, $assetId = null, $input = []) {
        $this->db = $db;
        $this->userId = $userId;
        $this->assetId = $assetId;
        $this->input = $input;
    }

    public function handleSearchCatalog() {
        $q = trim((string) ($this->input['q'] ?? $this->input['query'] ?? ''));
        $limit = (int) ($this->input['limit'] ?? 40);
        $limit = max(1, min(200, $limit));

        $items = EngineeringCatalog::search($q, $limit);
        $slim = array_map(static function ($row) {
            return [
                'code' => $row['code'],
                'd' => $row['d'],
                'D' => $row['D'],
                'B' => $row['B'],
                'type_name' => $row['type_name'],
                'standard' => $row['standard'],
                'label' => $row['code'] . ' — ' . $row['d'] . '×' . $row['D'] . '×' . $row['B'] . ' mm',
            ];
        }, $items);

        return [
            'items' => $slim,
            'total_catalog' => count(EngineeringCatalog::bearings()),
            'iso_vg' => EngineeringCatalog::isoVgGrades(),
            'nlgi' => EngineeringCatalog::nlgiGrades(),
        ];
    }

    /**
     * Fator DN = n·d e Ndm = n·dm (SKF)
     */
    public function handleCalcDN() {
        $d = floatval($this->input['d'] ?? 0);
        $D = floatval($this->input['D'] ?? 0);
        $B = floatval($this->input['B'] ?? 0);
        $rpm = floatval($this->input['rpm'] ?? 0);
        $code = trim((string) ($this->input['code'] ?? $this->input['name'] ?? ''));

        if ($code !== '' && ($d <= 0 || $D <= 0)) {
            $spec = EngineeringCatalog::findBearing($code);
            if ($spec) {
                $d = (float) $spec['d'];
                $D = (float) $spec['D'];
                $B = (float) $spec['B'];
            }
        }

        if ($d <= 0 || $rpm <= 0) {
            return ['found' => false, 'error' => 'Informe d (ou código ISO) e RPM.'];
        }
        if ($D <= 0) {
            $D = $d * 2;
        }

        $dm = ($d + $D) / 2.0;
        $dnBore = $rpm * $d;
        $ndm = $rpm * $dm;
        $volRelub = ($D > 0 && $B > 0) ? 0.005 * $D * $B : 0;
        $volInicial = ($D > 0 && $B > 0) ? 0.002 * $D * $B : 0;

        $meta = $this->dnPresentation($ndm);

        return array_merge([
            'found' => true,
            'd' => round($d, 3),
            'D' => round($D, 3),
            'B' => round($B, 3),
            'dm' => round($dm, 3),
            'dn_bore' => (int) round($dnBore),
            'ndm' => (int) round($ndm),
            'dn' => (int) round($ndm),
            'vol_inicial_g' => round($volInicial, 2),
            'vol_relub_g' => round($volRelub, 2),
            'status' => $meta['easy_status'],
            'max_scale' => 1000000,
        ], $meta);
    }

    public function handleSuggestLubrication() {
        $name = trim((string) ($this->input['name'] ?? $this->input['code'] ?? ''));
        $rpm = floatval($this->input['rpm'] ?? 0);
        $temp = floatval($this->input['temp'] ?? 70);
        $horasDia = floatval($this->input['horas_dia'] ?? $this->input['hours_day'] ?? 24);
        if ($horasDia <= 0 || $horasDia > 24) {
            $horasDia = 24;
        }

        $d = floatval($this->input['d'] ?? 0);
        $D = floatval($this->input['D'] ?? 0);
        $B = floatval($this->input['B'] ?? 0);
        $model = $name !== '' ? $name : 'Custom';
        $bearingType = 'Dimensões manuais';
        $kType = 1.0;
        $standard = 'Manual';

        if ($name !== '') {
            $specs = EngineeringCatalog::findBearing($name);
            if ($specs) {
                $d = (float) $specs['d'];
                $D = (float) $specs['D'];
                $B = (float) $specs['B'];
                $model = $specs['code'];
                $bearingType = $specs['type_name'];
                $kType = (float) $specs['k'];
                $standard = $specs['standard'];
            } elseif ($d <= 0 || $D <= 0) {
                return [
                    'found' => false,
                    'error' => 'Código não encontrado no catálogo ISO. Use 6205, 22210, NU2208, HK2016 ou informe d/D/B.',
                ];
            }
        }

        if ($d <= 0 || $D <= 0) {
            return ['found' => false, 'error' => 'Dimensões do rolamento (d, D) são obrigatórias.'];
        }
        if ($rpm <= 0) {
            $rpm = 1500;
        }
        if ($B <= 0) {
            $B = max(5, ($D - $d) * 0.35);
        }

        $this->input['d'] = $d;
        $this->input['D'] = $D;
        $this->input['B'] = $B;
        $this->input['rpm'] = $rpm;

        $base = $this->handleCalcDN();
        if (!empty($base['error']) && empty($base['dn'])) {
            return array_merge(['found' => false], $base);
        }

        $ndm = (float) $base['ndm'];
        $dm = ($d + $D) / 2.0;

        // SKF: Gp = 0,005 · D · B (g) relubrificação; carga inicial do rolamento ≈ 0,002 · D · B
        $gramsRelub = round(0.005 * $D * $B, 2);
        $gramsInitial = round(0.002 * $D * $B, 2);
        $gramsHousing = round(0.01 * $D * $B, 2);

        // Temperatura — regra de Arrhenius / DIN 51825 (referência 70 °C, Q10 ≈ 15 °C)
        if ($temp <= 70) {
            $ft = ($temp < 50) ? 1.15 : 1.0;
        } else {
            $ft = max(0.05, pow(2, -($temp - 70) / 15));
        }

        $fc = $this->factorFromSelect($this->input['cont'] ?? 'normal', [
            'clean' => 1.0, 'limpa' => 1.0, 'lab' => 1.0,
            'normal' => 0.8, 'moderate' => 0.8,
            'dirty' => 0.4, 'poeira' => 0.4, 'sujo' => 0.4, 'high' => 0.4,
            'extreme' => 0.15, 'extrema' => 0.15, 'lama' => 0.15
        ], 0.8);

        $fv = $this->factorFromSelect($this->input['vib'] ?? 'mid', [
            'low' => 1.0, 'baixa' => 1.0,
            'mid' => 0.8, 'media' => 0.8, 'moderate' => 0.8,
            'high' => 0.4, 'alta' => 0.4
        ], 0.8);

        $fp = $this->factorFromSelect($this->input['pos'] ?? 'horiz', [
            'horiz' => 1.0, 'horizontal' => 1.0,
            'vert' => 0.5, 'vertical' => 0.5, 'vertical_shaft' => 0.5,
            'outer_rot' => 0.5, 'anel_ext' => 0.5
        ], 1.0);

        $fm = $this->factorFromSelect($this->input['moist'] ?? $this->input['moisture'] ?? 'dry', [
            'dry' => 1.0, 'seco' => 1.0,
            'humid' => 0.7, 'umido' => 0.7, 'moderate' => 0.7,
            'wet' => 0.2, 'agua' => 0.2, 'agua_direta' => 0.2
        ], 1.0);

        $kgf = floatval($this->input['kgf'] ?? 1);
        $fl = ($kgf > 500) ? 0.5 : (($kgf > 100) ? 0.8 : 1.0);

        $fEnv = $ft * $fc * $fv * $fp * $fm * $fl;

        // SKF tf [h] = K · ((14·10^6)/(n·√d) − 4d)  — K já é o fator de tipo (não aplicar 2×)
        $sqrtD = sqrt(max(1.0, $d));
        $t_base = ($kType * 14000000.0) / max(1.0, $rpm * $sqrtD) - (4.0 * $d);
        $t_base = max(8.0, $t_base);
        $t_corrigido = max(8.0, $t_base * $fEnv);
        $t_corrigido = min($t_corrigido, 30000.0);

        $horas = round($t_corrigido, 1);
        $dias = round($t_corrigido / $horasDia, 2);
        $semanas = round($dias / 7, 2);
        $meses = round($dias / 30.4375, 2);

        if ($dias <= 3) {
            $freqLabel = 'Diária ou a cada 2-3 dias';
        } elseif ($dias <= 7) {
            $freqLabel = 'Semanal (' . (int) ceil($dias) . ' dias)';
        } elseif ($dias <= 15) {
            $freqLabel = 'Quinzenal (' . (int) ceil($dias) . ' dias)';
        } elseif ($dias <= 45) {
            $freqLabel = 'Mensal (' . round($meses, 1) . ' mês)';
        } elseif ($dias <= 100) {
            $freqLabel = 'Trimestral (' . round($meses, 1) . ' meses)';
        } elseif ($dias <= 200) {
            $freqLabel = 'Semestral (' . round($meses, 1) . ' meses)';
        } else {
            $freqLabel = 'Anual (' . round($meses / 12, 1) . ' ano)';
        }

        $viOil = floatval($this->input['vi'] ?? 95);
        if ($viOil < 20) {
            $viOil = 95;
        }
        $vgOil = floatval($this->input['vg'] ?? $this->input['iso_vg'] ?? 0);

        $kappaPack = $this->buildKappaAnalysis($d, $D, $B, $rpm, $temp, $vgOil > 0 ? $vgOil : null, $viOil);

        $warning = null;
        if ($temp > 90) {
            $warning = 'Temperatura crítica (>90°C): risco de oxidação e escorrimento. Preferir graxa de alta temperatura ou óleo.';
        } elseif ($ndm > 500000) {
            $warning = 'Ndm > 500.000: graxa convencional inadequada — névoa ou óleo circulante.';
        } elseif ($t_corrigido < 24) {
            $warning = 'Intervalo < 24 h: indicar lubrificação automática.';
        }

        return [
            'found' => true,
            'model' => $model,
            'bearing_type' => $bearingType,
            'standard' => $standard,
            'catalog_source' => $standard,
            'd' => round($d, 3),
            'D' => round($D, 3),
            'B' => round($B, 3),
            'dm' => $base['dm'],
            'dn' => $base['dn'],
            'dn_bore' => $base['dn_bore'] ?? (int) round($rpm * $d),
            'ndm' => $base['ndm'],
            'horas_dia' => $horasDia,
            'grams' => $gramsRelub,
            'grams_initial' => $gramsInitial,
            'grams_housing' => $gramsHousing,
            'hours' => $horas,
            'days' => $dias,
            'weeks' => $semanas,
            'months' => $meses,
            'freq_label' => $freqLabel,
            'frequencia_dias' => (int) round($dias),
            'frequencia_base_horas' => (int) round($t_base),
            'frequencia_corrigida_horas' => (int) round($t_corrigido),
            'nu1_rec_cst' => $kappaPack['nu1'],
            'iso_vg_rec' => $kappaPack['iso_vg_kappa_1'],
            'nlgi_rec' => $this->recommendNlgi($ndm),
            'kappa' => $kappaPack,
            'color' => $base['color'],
            'easy_status' => $base['easy_status'],
            'suggestion' => $base['suggestion'],
            'warning' => $warning,
            'alerta_temperatura' => ($temp > 90) ? 'Crítico: Risco de oxidação acelerada.' : 'Normal',
            'suggestion_only' => true,
            'plan_note' => 'Sugestão SKF/DIN — não substitui o plano já cadastrado no ponto. Confirme antes de aplicar quantidade ou intervalo.',
            'factors' => [
                'type' => round($kType, 2),
                'temp' => round($ft, 3),
                'cont' => round($fc, 2),
                'vib' => round($fv, 2),
                'pos' => round($fp, 2),
                'moisture' => round($fm, 2),
                'load' => round($fl, 2),
                'ftotal' => round($fEnv, 3),
                'temp_factor' => round($ft, 3),
                'cont_factor' => round($fc, 2),
                'vib_factor' => round($fv, 2),
                'pos_factor' => round($fp, 2),
                'moisture_factor' => round($fm, 2),
            ],
        ];
    }

    public function handleCalcViscosity() {
        $vg = floatval($this->input['vg'] ?? 46);
        $vi = floatval($this->input['vi'] ?? 95);
        $tempOp = floatval($this->input['temp'] ?? $this->input['temp_op'] ?? 40);

        if ($vg < 1.5 || $vg > 2000) {
            return ['error' => 'ISO VG fora da faixa (2 a 1500).'];
        }
        if ($tempOp < -20 || $tempOp > 250) {
            return ['error' => 'Temperatura de operação inválida.'];
        }

        $v40 = $vg;
        $v100 = EngineeringCatalog::v100ForVg($vg, $vi);
        $vOp = $this->waltherViscosity($v40, $v100, $tempOp);

        $alerta = 'Filme adequado para a maioria dos mancais (ν ≥ 13 cSt).';
        if ($vOp < 8) {
            $alerta = 'Filme insuficiente (ν < 8 cSt): risco de contato metal-metal.';
        } elseif ($vOp < 13) {
            $alerta = 'Filme no limite (ν < 13 cSt): elevar VG ou reduzir temperatura.';
        } elseif ($vOp > 400) {
            $alerta = 'Viscosidade muito alta: perdas por cisalhamento e partida a frio.';
        }

        $out = [
            'iso_vg' => $vg,
            'vi' => $vi,
            'temp_operacao' => $tempOp,
            'viscosidade_estimada_cst' => $vOp,
            'viscosity_op' => $vOp,
            'nu' => $vOp,
            'v40' => round($v40, 2),
            'v100' => $v100,
            'method' => 'ASTM D341 (Walther)',
            'alerta' => $alerta,
        ];

        $d = floatval($this->input['d'] ?? 0);
        $D = floatval($this->input['D'] ?? 0);
        $B = floatval($this->input['B'] ?? 0);
        $rpm = floatval($this->input['rpm'] ?? 0);
        $code = trim((string) ($this->input['code'] ?? $this->input['name'] ?? ''));
        if ($code !== '' && ($d <= 0 || $D <= 0)) {
            $spec = EngineeringCatalog::findBearing($code);
            if ($spec) {
                $d = (float) $spec['d'];
                $D = (float) $spec['D'];
                $B = (float) $spec['B'];
                $out['bearing_code'] = $spec['code'];
            }
        }

        if ($d > 0 && $rpm > 0) {
            $out['kappa'] = $this->buildKappaAnalysis($d, $D, $B, $rpm, $tempOp, $vg, $vi);
            $out = array_merge($out, $this->flattenKappa($out['kappa']));
        }

        return $out;
    }

    /**
     * Fator Kappa SKF: κ = ν / ν₁
     * ν  = viscosidade operacional (ASTM D341 na temperatura de trabalho)
     * ν₁ = viscosidade nominal do mancal (diâmetro médio dm e RPM)
     */
    public function handleCalcKappa() {
        $code = trim((string) ($this->input['code'] ?? $this->input['name'] ?? ''));
        $d = floatval($this->input['d'] ?? 0);
        $D = floatval($this->input['D'] ?? 0);
        $B = floatval($this->input['B'] ?? 0);
        $rpm = floatval($this->input['rpm'] ?? 0);
        $temp = floatval($this->input['temp'] ?? 70);
        $vg = floatval($this->input['vg'] ?? $this->input['iso_vg'] ?? 68);
        $vi = floatval($this->input['vi'] ?? 95);

        if ($code !== '') {
            $spec = EngineeringCatalog::findBearing($code);
            if ($spec) {
                $d = (float) $spec['d'];
                $D = (float) $spec['D'];
                $B = (float) $spec['B'];
                $this->input['code'] = $spec['code'];
            } elseif ($d <= 0 || $D <= 0) {
                return ['found' => false, 'error' => 'Código ISO não encontrado. Informe d e D do rolamento.'];
            }
        }

        if ($d <= 0 || $rpm <= 0) {
            return ['found' => false, 'error' => 'Informe d (ou código ISO) e a rotação (RPM).'];
        }
        if ($D <= 0) {
            $D = $d * 2;
        }
        if ($B <= 0) {
            $B = max(5.0, ($D - $d) * 0.35);
        }
        if ($vg < 1.5 || $vg > 2000) {
            return ['found' => false, 'error' => 'ISO VG do óleo (ou óleo-base da graxa) inválido.'];
        }

        $this->input['d'] = $d;
        $this->input['D'] = $D;
        $this->input['B'] = $B;
        $this->input['rpm'] = $rpm;

        $kappa = $this->buildKappaAnalysis($d, $D, $B, $rpm, $temp, $vg, $vi);
        $dn = $this->handleCalcDN();

        return array_merge([
            'found' => true,
            'bearing_code' => $code !== '' ? ($this->input['code'] ?? $code) : '',
            'formula' => 'κ = ν / ν₁',
            'formula_nu1' => 'ν₁ = 45 000 / (n^0,83 · dm^0,5)  [SKF, cSt na temperatura de trabalho]',
            'formula_nu' => 'ν pelo ASTM D341 (Walther) a partir do ISO VG e do VI',
        ], $kappa, [
            'ndm' => $dn['ndm'] ?? (int) round($rpm * (($d + $D) / 2)),
            'dn' => $dn['dn'] ?? null,
            'easy_status' => $dn['easy_status'] ?? null,
            'color' => $kappa['color'],
        ]);
    }

    public function handleCalcBushing() {
        $d = floatval($this->input['d'] ?? 0);
        $l = floatval($this->input['l'] ?? $this->input['L'] ?? 0);
        $k = floatval($this->input['k'] ?? 1);
        $rpm = floatval($this->input['rpm'] ?? $this->input['n'] ?? 0);
        if ($k <= 0) {
            $k = 1;
        }
        if ($l <= 0 && $d > 0) {
            $l = $d;
        }

        if ($d <= 0 || $l <= 0) {
            return ['found' => false, 'error' => 'Diâmetro (d) e comprimento (L) da bucha são obrigatórios.'];
        }

        // Consumo específico de graxa em mancal liso (g/h): (d·L·n·k) / 2·10^6
        $nEff = max(10.0, $rpm);
        $gPerHour = ($d * $l * $nEff * $k) / 2000000.0;
        $hoursMonth = 730.0;
        $gMonth = $gPerHour * $hoursMonth;
        $density = 0.90; // g/cm³ graxa típica
        $qty = round($gMonth / $density, 1);
        $g100h = round($gPerHour * 100, 2);

        return [
            'found' => true,
            'qty_cm3_monthly' => $qty,
            'grams_per_100h' => $g100h,
            'd' => $d,
            'l' => $l,
            'k' => $k,
            'rpm' => $rpm,
            'assumed_square' => ($l === $d && empty($this->input['l']) && empty($this->input['L'])),
            'message' => 'Consumo de graxa para bucha de deslizamento (ajuste k de serviço 0,5–3).',
        ];
    }

    public function handleCalcFiltering() {
        $iso = trim((string) ($this->input['iso'] ?? $this->input['target_iso'] ?? '18/16/13'));
        return [
            'target_iso' => $iso,
            'recommendation' => 'Filtração off-line recomendada até atingir ISO ' . $iso . '.',
            'message' => 'Use filtragem off-line com elemento adequado à viscosidade do fluido.',
        ];
    }

    private function flattenKappa(array $k): array
    {
        return [
            'kappa_value' => $k['kappa'] ?? null,
            'nu1' => $k['nu1'] ?? null,
            'nu' => $k['nu'] ?? null,
        ];
    }

    /**
     * SKF: viscosidade nominal ν₁ [cSt] na temperatura de operação.
     * ν₁ = 45 000 / (n^0,83 · dm^0,5), n em rpm, dm = (d+D)/2 em mm.
     */
    private function requiredViscosityNu1(float $rpm, float $dm): float
    {
        $n = max(10.0, $rpm);
        $dm = max(5.0, $dm);
        $nu1 = 45000.0 / (pow($n, 0.83) * pow($dm, 0.5));
        return round(max(2.0, min($nu1, 2500.0)), 2);
    }

    /**
     * @param float|null $vg ISO VG do óleo ou óleo-base da graxa (null = usa VG para κ≈1)
     */
    private function buildKappaAnalysis(float $d, float $D, float $B, float $rpm, float $temp, ?float $vg, float $vi): array
    {
        if ($D <= 0) {
            $D = $d * 2.0;
        }
        if ($B <= 0) {
            $B = max(5.0, ($D - $d) * 0.35);
        }
        if ($vi < 20) {
            $vi = 95.0;
        }

        $dm = ($d + $D) / 2.0;
        $nu1 = $this->requiredViscosityNu1($rpm, $dm);

        $vgForKappa1 = $this->nearestIsoVg($this->viscosityAt40FromOp($nu1, $temp, $vi));
        $vgForKappa4 = $this->nearestIsoVg($this->viscosityAt40FromOp(max($nu1 * 4.0, 8.0), $temp, $vi));

        $vgUsed = ($vg !== null && $vg > 0) ? $vg : (float) $vgForKappa1;
        $v100 = EngineeringCatalog::v100ForVg($vgUsed, $vi);
        $nu = $this->waltherViscosity($vgUsed, $v100, $temp);
        $kappa = $nu1 > 0.01 ? round($nu / $nu1, 2) : 0.0;

        $interp = $this->interpretKappa($kappa);

        return [
            'd' => round($d, 3),
            'D' => round($D, 3),
            'B' => round($B, 3),
            'dm' => round($dm, 3),
            'rpm' => $rpm,
            'temp' => $temp,
            'vi' => $vi,
            'iso_vg' => $vgUsed,
            'vg_informado' => ($vg !== null && $vg > 0),
            'nu' => $nu,
            'nu1' => $nu1,
            'kappa' => $kappa,
            'kappa_label' => 'κ = ν / ν₁',
            'band' => $interp['band'],
            'band_title' => $interp['title'],
            'regime' => $interp['regime'],
            'color' => $interp['color'],
            'advice' => $interp['advice'],
            'ep_required' => $interp['ep_required'],
            'life_factor' => $interp['life_factor'],
            'iso_vg_kappa_1' => $vgForKappa1,
            'iso_vg_kappa_4' => $vgForKappa4,
            'grams_relub' => round(0.005 * $D * $B, 2),
            'grams_initial' => round(0.002 * $D * $B, 2),
            'v100' => $v100,
        ];
    }

    private function interpretKappa(float $k): array
    {
        if ($k < 0.1) {
            return [
                'band' => 'critico',
                'title' => 'κ < 0,1 — Lubrificação limite',
                'regime' => 'Contato metal-metal. Vida do rolamento drasticamente reduzida.',
                'color' => '#b91c1c',
                'advice' => 'Troque para ISO VG maior, reduza a temperatura ou a rotação. Aditivos EP não substituem filme hidrodinâmico neste extremo.',
                'ep_required' => true,
                'life_factor' => 0.10,
            ];
        }
        if ($k < 1.0) {
            return [
                'band' => 'baixo',
                'title' => 'κ < 1 — Filme fino (regime misto)',
                'regime' => 'O filme é frágil: há risco de desgaste abrasivo e micropitting.',
                'color' => '#ea580c',
                'advice' => 'Use aditivos de extrema pressão (EP) e, se possível, suba o ISO VG até κ ≥ 1. Evite picos de carga.',
                'ep_required' => true,
                'life_factor' => round(max(0.15, pow(max($k, 0.1), 0.54)), 2),
            ];
        }
        if ($k <= 4.0) {
            $near4 = $k >= 3.0;
            return [
                'band' => 'ideal',
                'title' => $near4 ? 'κ próximo de 4 — Filme excelente' : 'κ entre 1 e 4 — Condição ideal',
                'regime' => $near4
                    ? 'Separação elasto-hidrodinâmica plena: vida útil máxima típica do rolamento.'
                    : 'Filme separa bem as superfícies. Valores rumo a 4 aumentam a vida calculada.',
                'color' => '#059669',
                'advice' => $near4
                    ? 'Mantenha este VG e controle a temperatura. Não é necessário EP por viscosidade.'
                    : 'Condição boa. Para vida máxima, o alvo SKF é κ ≈ 4 (sem ultrapassar).',
                'ep_required' => false,
                'life_factor' => round(1.0 + 0.5 * ($k - 1.0), 2),
            ];
        }
        return [
            'band' => 'alto',
            'title' => 'κ > 4 — Viscosidade excessiva',
            'regime' => 'Cisalhamento interno, aquecimento extra e perda de energia. A vida não aumenta além de κ ≈ 4.',
            'color' => '#d97706',
            'advice' => 'Reduza o ISO VG (alvo κ = 1 a 4), verifique partida a frio e considere óleo de VI mais alto se a faixa térmica for larga.',
            'ep_required' => false,
            'life_factor' => round(max(1.0, 2.5 * (4.0 / $k)), 2),
        ];
    }

    private function waltherViscosity(float $v40, float $v100, float $tempC): float
    {
        if (abs($tempC - 40.0) < 0.05) {
            return round($v40, 2);
        }
        if (abs($tempC - 100.0) < 0.05) {
            return round($v100, 2);
        }
        $z = static function (float $v): float {
            return log10(log10(max($v, 1.1) + 0.7));
        };
        $T40 = 40.0 + 273.15;
        $T100 = 100.0 + 273.15;
        $T = $tempC + 273.15;
        $u40 = $z($v40);
        $u100 = $z($v100);
        $den = log10($T40) - log10($T100);
        if (abs($den) < 1e-9) {
            return round($v40, 2);
        }
        $b = ($u40 - $u100) / $den;
        $a = $u40 - $b * log10($T40);
        $u = $a + $b * log10(max($T, 200));
        $visc = pow(10, pow(10, $u)) - 0.7;
        return round(max($visc, 1.0), 2);
    }

    private function viscosityAt40FromOp(float $vOp, float $tempOp, float $vi): float
    {
        if (abs($tempOp - 40.0) < 1) {
            return $vOp;
        }
        $probeVg = 46.0;
        for ($i = 0; $i < 8; $i++) {
            $v100 = EngineeringCatalog::v100ForVg($probeVg, $vi);
            $got = $this->waltherViscosity($probeVg, $v100, $tempOp);
            if ($got <= 0.1) {
                break;
            }
            $probeVg *= ($vOp / $got);
            $probeVg = max(2.0, min(1500.0, $probeVg));
        }
        return $probeVg;
    }

    private function nearestIsoVg(float $v40): int
    {
        $grades = EngineeringCatalog::isoVgGrades();
        $best = $grades[0];
        foreach ($grades as $g) {
            if ($g >= $v40) {
                return (int) $g;
            }
            $best = $g;
        }
        return (int) $best;
    }

    private function recommendNlgi(float $ndm): string
    {
        if ($ndm > 400000) {
            return 'NLGI 1 (ou óleo)';
        }
        if ($ndm > 200000) {
            return 'NLGI 2';
        }
        if ($ndm < 50000) {
            return 'NLGI 2–3';
        }
        return 'NLGI 2';
    }

    private function dnPresentation(float $ndm): array
    {
        if ($ndm > 500000) {
            return [
                'color' => '#ef4444',
                'easy_status' => 'CRÍTICO (ÓLEO)',
                'suggestion' => 'Ndm muito alto: preferir óleo circulante ou graxa especial de alta velocidade.',
            ];
        }
        if ($ndm > 300000) {
            return [
                'color' => '#f59e0b',
                'easy_status' => 'ATENÇÃO (LIMITE)',
                'suggestion' => 'Próximo ao limite de graxa convencional — revise viscosidade e intervalo.',
            ];
        }
        if ($ndm < 100000) {
            return [
                'color' => '#f59e0b',
                'easy_status' => 'BAIXA ROTAÇÃO',
                'suggestion' => 'Baixa rotação: atenção ao filme lubrificante e possível sangramento de graxa.',
            ];
        }
        return [
            'color' => '#10b981',
            'easy_status' => 'OPERAÇÃO SEGURA',
            'suggestion' => '',
        ];
    }

    private function factorFromSelect($value, array $map, float $default): float
    {
        $key = strtolower(trim((string) $value));
        return $map[$key] ?? $default;
    }
}
