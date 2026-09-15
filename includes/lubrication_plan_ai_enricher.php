<?php
/**
 * Enriquece o Plano de Lubrificação com textos gerados pela IA Lúbria (Gemini).
 * Textos coerentes para apresentações executivas e reuniões com o cliente.
 */
require_once __DIR__ . '/gemini_service.php';
require_once __DIR__ . '/ai_cache.php';

class LubricationPlanAiEnricher
{
    /** @var PDO */
    private $db;

    /** @var GeminiService */
    private $gemini;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->gemini = GeminiService::getInstance();
    }

    /**
     * @param array{frequency?:string,force_refresh?:bool} $options
     */
    public function enrich(array $plan, array $options = []): array
    {
        $frequency = (string) ($options['frequency'] ?? ($plan['meta']['frequency_filter'] ?? ''));
        $forceRefresh = !empty($options['force_refresh']);
        $revision = AiCache::plantRevision($this->db);
        $rootId = (int) ($plan['meta']['root_id'] ?? 0);

        $cacheKey = AiCache::makeKey(
            'lub_plan_ai',
            $revision,
            $rootId,
            $frequency,
            (string) ($plan['meta']['stats']['points'] ?? 0)
        );

        if (!$forceRefresh) {
            $cached = AiCache::get($cacheKey);
            if (is_array($cached) && !empty($cached['global'])) {
                return $this->mergeIntoPlan($plan, $cached);
            }
        }

        $compact = $this->compactPlan($plan);
        $enrichment = $this->gemini->isConfigured()
            ? $this->enrichWithGemini($compact, $plan)
            : $this->enrichLocal($compact, $plan);

        $enrichment['generated_at'] = date('c');
        AiCache::set($cacheKey, $enrichment, defined('AI_CACHE_TTL_JSON') ? AI_CACHE_TTL_JSON : AiCache::JSON_TTL);

        return $this->mergeIntoPlan($plan, $enrichment);
    }

    private function mergeIntoPlan(array $plan, array $enrichment): array
    {
        $plan['ai'] = [
            'source' => (string) ($enrichment['source'] ?? 'local'),
            'global' => $enrichment['global'] ?? [],
        ];

        $byId = $enrichment['equipments'] ?? [];
        foreach ($plan['areas'] as &$area) {
            foreach ($area['equipments'] as &$eq) {
                $key = (string) ($eq['id'] ?? '');
                $eqAi = $byId[$key] ?? null;
                if (!is_array($eqAi)) {
                    continue;
                }
                $eq['ai'] = $eqAi;
                if (!empty($eq['subsections']) && !empty($eqAi['subsections'])) {
                    foreach ($eq['subsections'] as &$sub) {
                        $subKey = (string) ($sub['title'] ?? '');
                        if ($subKey !== '' && !empty($eqAi['subsections'][$subKey])) {
                            $sub['ai_note'] = (string) $eqAi['subsections'][$subKey];
                        }
                    }
                    unset($sub);
                }
            }
            unset($eq);
        }
        unset($area);

        return $plan;
    }

    private function compactPlan(array $plan): array
    {
        $equipments = [];
        foreach ($plan['areas'] ?? [] as $area) {
            foreach ($area['equipments'] ?? [] as $eq) {
                $lubs = [];
                $freqs = [];
                $metodos = [];
                foreach ($eq['points'] ?? [] as $pt) {
                    if (!empty($pt['material'])) {
                        $lubs[$pt['material']] = true;
                    }
                    if (!empty($pt['periodo'])) {
                        $freqs[$pt['periodo']] = true;
                    }
                    if (!empty($pt['metodo'])) {
                        $metodos[$pt['metodo']] = true;
                    }
                }
                $equipments[] = [
                    'id' => (int) ($eq['id'] ?? 0),
                    'nome' => (string) ($eq['nome'] ?? ''),
                    'area' => (string) ($area['name'] ?? ''),
                    'tag' => (string) ($eq['tag'] ?? ''),
                    'fabricante' => (string) ($eq['fabricante'] ?? ''),
                    'modelo' => (string) ($eq['modelo'] ?? ''),
                    'obs' => mb_substr((string) ($eq['obs'] ?? ''), 0, 200),
                    'points_count' => count($eq['points'] ?? []),
                    'components_count' => count($eq['components'] ?? []),
                    'lubricants' => array_slice(array_keys($lubs), 0, 8),
                    'frequencies' => array_keys($freqs),
                    'methods' => array_keys($metodos),
                    'subsections' => array_values(array_map(
                        static fn($s) => (string) ($s['title'] ?? ''),
                        $eq['subsections'] ?? []
                    )),
                    'sample_points' => array_slice(array_map(static function ($p) {
                        return [
                            'ponto' => (string) ($p['ponto_lub'] ?? ''),
                            'material' => (string) ($p['material'] ?? ''),
                            'periodo' => (string) ($p['periodo'] ?? ''),
                            'metodo' => (string) ($p['metodo'] ?? ''),
                            'qtd' => trim((string) ($p['qtd_material'] ?? '') . ' ' . (string) ($p['unid_material'] ?? '')),
                        ];
                    }, $eq['points'] ?? []), 0, 4),
                ];
            }
        }

        return [
            'root_name' => (string) ($plan['meta']['root_name'] ?? ''),
            'root_obs' => mb_substr((string) ($plan['meta']['root_obs'] ?? ''), 0, 300),
            'stats' => $plan['meta']['stats'] ?? [],
            'stations' => array_slice($plan['meta']['line_stations'] ?? [], 0, 15),
            'equipments' => $equipments,
        ];
    }

    private function enrichWithGemini(array $compact, array $plan): array
    {
        $global = $this->fetchGlobalNarrative($compact);
        $equipments = [];

        $chunks = array_chunk($compact['equipments'], 8);
        foreach ($chunks as $chunk) {
            $batch = $this->fetchEquipmentBatch($compact, $chunk);
            if (is_array($batch)) {
                $equipments = array_merge($equipments, $batch);
            }
        }

        if (empty($global)) {
            return $this->enrichLocal($compact, $plan);
        }

        return [
            'source' => 'gemini',
            'global' => $global,
            'equipments' => $equipments,
        ];
    }

    private function fetchGlobalNarrative(array $compact): array
    {
        $system = <<<'SYS'
Você é a Lúbria, engenheira chefe de confiabilidade do LUB-TEK.
Gere textos em português técnico do Brasil para um PLANO DE LUBRIFICAÇÃO executivo (apresentação a gestores e clientes industriais).
Use APENAS dados fornecidos — não invente códigos SAP, quantidades ou equipamentos inexistentes.
Tom: profissional, claro, coerente para reunião presencial.
Textos CURTOS — serão embutidos inline em slides densos (nunca página só com 1 frase).
Retorne SOMENTE JSON válido:
{
  "executive_summary": "máx. 400 caracteres, 2 frases densas",
  "meeting_opening": "máx. 180 caracteres, 1 frase de abertura",
  "agenda": ["5-6 tópicos, máx. 70 caracteres cada"],
  "strategic_highlights": ["4 bullets, máx. 90 caracteres cada"],
  "closing_recommendations": "máx. 350 caracteres"
}
SYS;

        $prompt = "Dados do plano:\n" . json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $res = $this->gemini->ask($prompt, $system, true, 2048);
        if (!empty($res['error']) || empty($res['text'])) {
            return [];
        }

        $parsed = GeminiService::parseJsonResponse($res['text'] ?? null);
        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param array<int, array> $chunk
     * @return array<string, array>
     */
    private function fetchEquipmentBatch(array $compact, array $chunk): array
    {
        $system = <<<'SYS'
Você é a Lúbria (LUB-TEK). Gere textos de apresentação para equipamentos de um plano de lubrificação industrial.
Textos CURTOS para barras inline (máx. 180 chars por campo). JSON válido apenas:
{
  "equipments": {
    "<id_numerico>": {
      "overview": "máx. 120 chars — função + criticidade",
      "meeting_script": "máx. 140 chars — roteiro oral",
      "technical_context": "máx. 160 chars — lubrificantes/freq/métodos dos dados",
      "risk_notes": "máx. 100 chars",
      "subsections": { "<nome_subsecao>": "máx. 120 chars ou omitir se redundante" }
    }
  }
}
SYS;

        $payload = [
            'line' => $compact['root_name'],
            'equipments' => $chunk,
        ];
        $prompt = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $res = $this->gemini->ask($prompt, $system, true, 3072);
        if (!empty($res['error']) || empty($res['text'])) {
            return $this->localEquipmentBatch($chunk, $compact);
        }

        $parsed = GeminiService::parseJsonResponse($res['text'] ?? null);
        if (!is_array($parsed) || empty($parsed['equipments']) || !is_array($parsed['equipments'])) {
            return $this->localEquipmentBatch($chunk, $compact);
        }

        return $parsed['equipments'];
    }

    private function enrichLocal(array $compact, array $plan): array
    {
        $stats = $compact['stats'] ?? [];
        $eqCount = (int) ($stats['equipments'] ?? 0);
        $ptCount = (int) ($stats['points'] ?? 0);
        $root = (string) ($compact['root_name'] ?? 'Planta industrial');

        $stations = array_map(static fn($s) => ($s['num'] ?? '') . '. ' . ($s['nome'] ?? ''), $compact['stations'] ?? []);

        $global = [
            'executive_summary' => "Plano de lubrificação de {$root}: {$eqCount} equipamento(s), {$ptCount} ponto(s). "
                . "Integra fotos, SAP, frequências e métodos para execução e auditoria. Aderência reduz paradas e prolonga vida útil de mancais.",
            'meeting_opening' => "Apresentação do plano formal de lubrificação de {$root}, alinhado a SSMA e confiabilidade.",
            'agenda' => array_filter([
                'Contexto da linha e objetivos do plano',
                $stations ? 'Visão das estações: ' . implode(' · ', array_slice($stations, 0, 4)) : null,
                'Equipamentos críticos e pontos de lubrificação',
                'Lubrificantes, métodos e periodicidades',
                'SSMA e procedimentos de intervenção',
                'Próximos passos e governança do plano',
            ]),
            'strategic_highlights' => [
                'Plano único de referência para PCM, lubrificação e auditorias',
                'Rastreabilidade por TAG/SAP e evidência fotográfica',
                'Redução de risco de contaminação e falha por sub-lubrificação',
                'Base para treinamento de equipes de campo',
            ],
            'closing_recommendations' => "Validar com manutenção, distribuir checklists por frequência e revisar trimestralmente. "
                . "Atualizar o LUB-TEK a cada mudança de layout ou lubrificante.",
        ];

        return [
            'source' => 'local',
            'global' => $global,
            'equipments' => $this->localEquipmentBatch($compact['equipments'], $compact),
        ];
    }

    /**
     * @param array<int, array> $equipments
     * @return array<string, array>
     */
    private function localEquipmentBatch(array $equipments, array $compact): array
    {
        $out = [];
        foreach ($equipments as $eq) {
            $id = (string) ($eq['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $nome = (string) ($eq['nome'] ?? 'Equipamento');
            $n = (int) ($eq['points_count'] ?? 0);
            $lubs = implode(', ', array_slice($eq['lubricants'] ?? [], 0, 3));
            $freqs = implode(', ', $eq['frequencies'] ?? []);
            $fab = (string) ($eq['fabricante'] ?? '');

            $out[$id] = [
                'overview' => mb_substr("{$nome}" . ($fab ? " ({$fab})" : '') . " — {$n} ponto(s) na linha {$compact['root_name']}.", 0, 120),
                'meeting_script' => mb_substr("Apresentar {$nome}: {$n} pontos" . ($lubs ? ", lubrificantes {$lubs}" : '') . ($freqs ? ", freq. {$freqs}" : '') . '.', 0, 140),
                'technical_context' => mb_substr('Métodos e lubrificantes conforme cadastro LUB-TEK e tabelas deste bloco.', 0, 160),
                'risk_notes' => mb_substr('Não aderência eleva desgaste, aquecimento e paradas corretivas.', 0, 100),
                'subsections' => [],
            ];
        }
        return $out;
    }
}
