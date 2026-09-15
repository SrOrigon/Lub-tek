<?php
require_once __DIR__ . '/lubrication_plan_image_helper.php';
require_once __DIR__ . '/lubrication_plan_ssma_content.php';
require_once __DIR__ . '/lubrication_plan_pattern_guard.php';

/**
 * Renderizador slide-a-slide — padrão executivo Pet/KHS (densidade cirúrgica).
 *
 * Contrato (independente do conteúdo):
 * - Sem imagem inválida no PDF
 * - Marcadores 1, 2, 3… (SAP só na tabela/legenda)
 * - Tabelas esparsas consolidadas; foto+tabela quando ≤4 pontos
 * - Assinaturas sempre no rodapé do último slide (doc-closing-footer)
 * - IA desligada salvo use_ai=true
 */
class LubricationPlanRenderer
{
    private const SAP_ROWS_PER_SLIDE = 12;
    private const SIMPLE_TABLE_MAX = 5;
    /** Máx. blocos/linhas antes de fechar slide consolidado */
    private const MERGE_MAX_ROWS = 14;
    private const MERGE_MAX_BLOCKS = 8;

    /** @var array<int, array{equipment:string,subtitle:string,points:array,context:string}> */
    private $sparseTableQueue = [];

    /** @var array */
    private $company;

    /** @var LubricationPlanImageHelper */
    private $images;

    /** @var int */
    private $pageNum = 0;

    /** @var array */
    private $aiGlobal = [];

    /** @var array */
    private $docOptions = [];

    public function __construct(array $company = [])
    {
        $this->company = $company;
        $this->images = new LubricationPlanImageHelper(dirname(__DIR__));
    }

    public function renderFull(array $plan, array $options = []): string
    {
        $this->pageNum = 0;
        $this->docOptions = $options;
        $meta = $plan['meta'] ?? [];
        $areas = $plan['areas'] ?? [];
        $title = (string) ($options['title'] ?? 'PLANO DE LUBRIFICAÇÃO');
        $subtitle = (string) ($options['subtitle'] ?? ($meta['root_name'] ?? ''));
        $this->aiGlobal = !empty($options['use_ai']) ? ($plan['ai']['global'] ?? []) : [];

        $body = '';
        $body .= $this->slideCover($title, $subtitle, $meta);
        $body .= $this->slideLineOverviewWithBriefing($meta);
        if ($this->images->isValidImage((string) ($meta['root_imagem'] ?? ''))) {
            $body .= $this->slideFullPhoto($subtitle, '', (string) $meta['root_imagem'], 'Mapa da linha', false);
        }
        $body .= $this->slideSsmaAndIndex($areas);

        $this->sparseTableQueue = [];
        foreach ($areas as $area) {
            foreach ($area['equipments'] ?? [] as $eq) {
                $body .= $this->renderEquipmentSlides((string) ($area['name'] ?? 'GERAL'), $eq);
            }
        }
        $body .= $this->flushSparseTableQueue();

        $body .= $this->renderSsmaTrainingSlides($meta);
        $body = $this->finalizeDocumentBody($body);

        return $this->wrapDocument($title, $body, $options);
    }

    /** Pipeline final — garante fila vazia, assinaturas anexadas e contrato visual. */
    private function finalizeDocumentBody(string $body): string
    {
        $body .= $this->flushSparseTableQueue();
        $body = $this->attachClosingFooter($body);
        return LubricationPlanPatternGuard::enforceBody($body);
    }

    public function renderEquipmentSlice(array $slice, int $offset = 0): string
    {
        $html = '';
        foreach ($slice as $item) {
            $html .= $this->renderEquipmentSlides(
                (string) ($item['area'] ?? 'GERAL'),
                $item['equipment'] ?? []
            );
        }
        return $html;
    }

    public function renderCoverOnly(array $plan, array $options = []): string
    {
        $this->pageNum = 0;
        $this->docOptions = $options;
        $meta = $plan['meta'] ?? [];
        $title = (string) ($options['title'] ?? 'PLANO DE LUBRIFICAÇÃO');
        $subtitle = (string) ($options['subtitle'] ?? ($meta['root_name'] ?? ''));
        $body = $this->slideCover($title, $subtitle, $meta);
        $body .= $this->slideLineOverviewWithBriefing($meta);
        return $this->wrapDocument($title, $body, $options);
    }

    private function renderEquipmentSlides(string $areaName, array $eq): string
    {
        $html = '';
        $eqName = (string) ($eq['nome'] ?? '');
        $eqAi = is_array($eq['ai'] ?? null) ? $eq['ai'] : [];
        $eqImg = (string) ($eq['imagem'] ?? '');
        $eqImgValid = $this->images->isValidImage($eqImg);

        $subsections = $eq['subsections'] ?? [];
        if (empty($subsections) && !empty($eq['points'])) {
            $subsections = [[
                'title' => $eqName,
                'subtitle' => $areaName,
                'imagem' => $eqImg,
                'obs' => (string) ($eq['obs'] ?? ''),
                'points' => $eq['points'],
            ]];
        }

        $firstSub = true;
        $allEqPoints = $eq['points'] ?? [];
        if (empty($allEqPoints)) {
            foreach ($subsections as $s) {
                foreach ($s['points'] ?? [] as $p) {
                    $allEqPoints[] = $p;
                }
            }
        }

        /** @var array<int, array{subtitle:string,points:array,context:string}> */
        $localSimpleBlocks = [];

        $totalEqRows = 0;
        foreach ($subsections as $s) {
            $totalEqRows += count($s['points'] ?? []);
        }

        if ($eqImgValid && $totalEqRows > 0 && $totalEqRows <= 4) {
            return $this->renderEquipmentCompactWithPhoto(
                $areaName,
                $eqName,
                $eq,
                $eqImg,
                $subsections,
                $eqAi
            );
        }

        foreach ($subsections as $sub) {
            $subTitle = (string) ($sub['title'] ?? $eqName);
            $subImg = (string) ($sub['imagem'] ?? '');
            $subImgValid = $this->images->isValidImage($subImg);
            $points = $sub['points'] ?? [];
            $contextBar = $this->buildContextBar($eqAi, $sub, $firstSub);
            $pointCount = count($points);
            $useSimple = $pointCount > 0 && $pointCount <= self::SIMPLE_TABLE_MAX;

            if ($firstSub) {
                $firstSub = false;
                if ($useSimple && $eqImgValid && $pointCount <= 3) {
                    $html .= $this->slidePhotoAndSimpleTable($eqName, $eqImg, $points, $contextBar);
                    $this->appendObsNote($html, $subTitle, $sub);
                    continue;
                }
                if ($eqImgValid) {
                    $html .= $this->slideFullPhoto(
                        $eqName,
                        $this->buildPhotoSubtitle($areaName, $eq),
                        $eqImg,
                        $eqName,
                        true,
                        $contextBar,
                        $allEqPoints
                    );
                }
            } elseif ($subImgValid && $subImg !== $eqImg) {
                $html .= $this->flushLocalSimpleBlocks($eqName, $localSimpleBlocks, $eqImgValid);
                $localSimpleBlocks = [];
                if ($useSimple && $pointCount <= 3) {
                    $html .= $this->slidePhotoAndSimpleTable(
                        $subTitle,
                        $subImg,
                        $points,
                        $this->inlineNote($sub['ai_note'] ?? '')
                    );
                    $this->appendObsNote($html, $subTitle, $sub);
                    continue;
                }
                $html .= $this->slideFullPhoto(
                    $subTitle,
                    (string) ($sub['subtitle'] ?? $eqName),
                    $subImg,
                    $subTitle,
                    true,
                    $this->inlineNote($sub['ai_note'] ?? ''),
                    $points
                );
            }

            if ($pointCount === 0) {
                continue;
            }

            $tableContext = $this->inlineNote($sub['ai_note'] ?? '');

            if ($useSimple) {
                $localSimpleBlocks[] = [
                    'subtitle' => $subTitle,
                    'points' => $points,
                    'context' => $tableContext,
                ];
                if ($this->localBlockRowCount($localSimpleBlocks) >= self::MERGE_MAX_ROWS) {
                    $html .= $this->flushLocalSimpleBlocks($eqName, $localSimpleBlocks, $eqImgValid);
                    $localSimpleBlocks = [];
                }
            } else {
                $html .= $this->flushLocalSimpleBlocks($eqName, $localSimpleBlocks, $eqImgValid);
                $localSimpleBlocks = [];
                $offset = 0;
                foreach (array_chunk($points, self::SAP_ROWS_PER_SLIDE) as $chunkIdx => $chunk) {
                    if (count($chunk) <= 3 && $chunkIdx === 0 && count($points) <= 3) {
                        $this->queueSparseTableBlock($eqName, $subTitle, $chunk, $tableContext);
                        $html .= $this->flushSparseTableQueueIfFull();
                    } else {
                        $html .= $this->flushSparseTableQueue();
                        $html .= $this->slideSapTable(
                            $eqName,
                            $subTitle,
                            $chunk,
                            $chunkIdx + 1,
                            $offset,
                            $chunkIdx === 0 ? $tableContext : ''
                        );
                    }
                    $offset += count($chunk);
                }
            }

            $this->appendObsNote($html, $subTitle, $sub);
        }

        $html .= $this->flushLocalSimpleBlocks($eqName, $localSimpleBlocks, $eqImgValid);

        return $html;
    }

    /** Equipamento com foto + até 4 pontos → uma única página densa (foto + tabela). */
    private function renderEquipmentCompactWithPhoto(
        string $areaName,
        string $eqName,
        array $eq,
        string $eqImg,
        array $subsections,
        array $eqAi
    ): string {
        $html = '';
        $blocks = [];
        $allPoints = [];
        foreach ($subsections as $sub) {
            $pts = $sub['points'] ?? [];
            if (empty($pts)) {
                continue;
            }
            $allPoints = array_merge($allPoints, $pts);
            $blocks[] = [
                'subtitle' => (string) ($sub['title'] ?? $eqName),
                'points' => $pts,
                'context' => '',
            ];
        }
        if (empty($allPoints)) {
            return '';
        }

        if (!$this->images->isValidImage($eqImg)) {
            $html = $this->slideMergedSimpleTables($eqName, $blocks);
            foreach ($subsections as $sub) {
                $this->appendObsNote($html, (string) ($sub['title'] ?? $eqName), $sub);
            }
            return $html;
        }

        $context = $this->buildContextBar($eqAi, $subsections[0] ?? null, true);
        $html .= $this->slidePhotoWithMergedTables(
            $eqName,
            $this->buildPhotoSubtitle($areaName, $eq),
            $eqImg,
            $blocks,
            $allPoints,
            $context
        );

        foreach ($subsections as $sub) {
            $this->appendObsNote($html, (string) ($sub['title'] ?? $eqName), $sub);
        }

        return $html;
    }

    /**
     * @param array<int, array{subtitle:string,points:array,context:string}> $blocks
     * @param array<int, array> $markerPoints
     */
    private function slidePhotoWithMergedTables(
        string $title,
        string $subtitle,
        string $imagePath,
        array $blocks,
        array $markerPoints,
        string $contextBar = ''
    ): string {
        if (!$this->images->isValidImage($imagePath)) {
            return $this->slideMergedSimpleTables($title, $blocks);
        }

        $photo = $this->images->photoWithMarkers($imagePath, $markerPoints, [
            'class' => 'hero-img compact-photo',
            'alt' => $title,
            'max_markers' => 10,
        ]);
        if ($photo === '') {
            return $this->slideMergedSimpleTables($title, $blocks);
        }

        $tables = '';
        foreach ($blocks as $b) {
            $rows = $this->renderSimpleRows($b['points']);
            $subHead = count($blocks) > 1
                ? '<div class="merged-subhead">' . $this->e($b['subtitle']) . '</div>'
                : '';
            $tables .= '<div class="merged-block">' . $subHead
                . '<table class="simple-table fill merged-inner"><thead><tr>'
                . '<th>#</th><th>Ponto / Descrição</th><th>Lubrificante</th><th>Método</th><th>Qt</th><th>Período</th><th>Recom.</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
        }

        $subHtml = $subtitle !== '' ? '<p class="photo-sub">' . $this->e($subtitle) . '</p>' : '';
        $headerSub = count($blocks) === 1 ? $this->e($blocks[0]['subtitle']) : 'Dados de lubrificação';

        return $this->slideWrap('photo-table-combo compact-slide', <<<HTML
<div class="slide-header-bar dark"><span>{$this->e($title)}</span><strong>{$headerSub}</strong></div>
{$contextBar}
<div class="photo-table-combo-body">
    <div class="combo-photo-top">{$photo}</div>
    <div class="combo-tables-bottom">{$tables}</div>
</div>
HTML);
    }

    /** @param array<int, array{subtitle:string,points:array,context:string}> $blocks */
    private function flushLocalSimpleBlocks(string $eqName, array $blocks, bool $eqHadPhoto = false): string
    {
        if (empty($blocks)) {
            return '';
        }

        $totalRows = $this->localBlockRowCount($blocks);

        if ($totalRows <= 2 && !$eqHadPhoto) {
            foreach ($blocks as $b) {
                $this->queueSparseTableBlock($eqName, $b['subtitle'], $b['points'], $b['context']);
            }
            return $this->flushSparseTableQueueIfFull();
        }

        return $this->slideMergedSimpleTables($eqName, $blocks);
    }

    /** @param array<int, array{subtitle:string,points:array,context:string}> $blocks */
    private function localBlockRowCount(array $blocks): int
    {
        $n = 0;
        foreach ($blocks as $b) {
            $n += count($b['points'] ?? []);
        }
        return $n;
    }

    private function queueSparseTableBlock(string $eqName, string $subtitle, array $points, string $context = ''): void
    {
        $this->sparseTableQueue[] = [
            'equipment' => $eqName,
            'subtitle' => $subtitle,
            'points' => $points,
            'context' => $context,
        ];
    }

    private function sparseQueueRowCount(): int
    {
        $n = 0;
        foreach ($this->sparseTableQueue as $b) {
            $n += count($b['points'] ?? []);
        }
        return $n;
    }

    private function flushSparseTableQueueIfFull(): string
    {
        if ($this->sparseQueueRowCount() >= self::MERGE_MAX_ROWS
            || count($this->sparseTableQueue) >= self::MERGE_MAX_BLOCKS) {
            return $this->flushSparseTableQueue();
        }
        return '';
    }

    private function flushSparseTableQueue(): string
    {
        if (empty($this->sparseTableQueue)) {
            return '';
        }
        $blocks = $this->sparseTableQueue;
        $this->sparseTableQueue = [];
        return $this->slideMergedSimpleTablesMultiEquip($blocks);
    }

    private function appendObsNote(string &$html, string $subTitle, array $sub): void
    {
        $obs = trim((string) ($sub['obs'] ?? ''));
        if (mb_strlen($obs) > 100) {
            $html .= $this->slideInlineNote($subTitle, $obs);
        }
    }

    /**
     * Várias subseções do MESMO equipamento num único slide.
     *
     * @param array<int, array{subtitle:string,points:array,context:string}> $blocks
     */
    private function slideMergedSimpleTables(string $eqName, array $blocks): string
    {
        if (empty($blocks)) {
            return '';
        }
        if (count($blocks) === 1) {
            $b = $blocks[0];
            return $this->slideSimpleTable($eqName, $b['subtitle'], $b['points'], $b['context'], true);
        }

        $stack = '';
        $firstContext = '';
        foreach ($blocks as $i => $b) {
            if ($i === 0 && ($b['context'] ?? '') !== '') {
                $firstContext = $b['context'];
            }
            $rows = $this->renderSimpleRows($b['points']);
            $stack .= '<div class="merged-block">'
                . '<div class="merged-subhead">' . $this->e($b['subtitle']) . '</div>'
                . '<table class="simple-table fill merged-inner"><thead><tr>'
                . '<th>#</th><th>Ponto / Descrição</th><th>Lubrificante</th><th>Método</th><th>Qt</th><th>Período</th><th>Recom.</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
        }

        return $this->slideWrap('merged-tables-slide compact-slide', <<<HTML
<div class="slide-header-bar"><span>{$this->e($eqName)}</span><strong>Dados de lubrificação</strong></div>
{$firstContext}
<div class="merged-stack">{$stack}</div>
HTML);
    }

    /**
     * Blocos de vários equipamentos num slide (pontos esparsos / filtro Mensal).
     *
     * @param array<int, array{equipment:string,subtitle:string,points:array,context:string}> $blocks
     */
    private function slideMergedSimpleTablesMultiEquip(array $blocks): string
    {
        if (empty($blocks)) {
            return '';
        }
        if (count($blocks) === 1) {
            $b = $blocks[0];
            return $this->slideSimpleTable(
                $b['equipment'],
                $b['subtitle'],
                $b['points'],
                $b['context'],
                true
            );
        }

        $stack = '';
        foreach ($blocks as $b) {
            $rows = $this->renderSimpleRows($b['points']);
            $head = $this->e($b['equipment']) . ' · ' . $this->e($b['subtitle']);
            $stack .= '<div class="merged-block">'
                . '<div class="merged-subhead">' . $head . '</div>'
                . '<table class="simple-table fill merged-inner"><thead><tr>'
                . '<th>#</th><th>Ponto / Descrição</th><th>Lubrificante</th><th>Método</th><th>Qt</th><th>Período</th><th>Recom.</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
        }

        return $this->slideWrap('merged-tables-slide compact-slide', <<<HTML
<div class="slide-header-bar dark"><span>Plano técnico</span><strong>Pontos de lubrificação</strong></div>
<div class="merged-stack">{$stack}</div>
HTML);
    }

    // ─── Slides principais ───────────────────────────────────────────────────

    private function slideCover(string $title, string $subtitle, array $meta): string
    {
        $company = $this->e($this->company['name'] ?? 'LUB-TEK');
        $logo = $this->e($this->company['logo'] ?? 'assets/img/system/img_69581d7fcdbbf.jpeg');
        $date = $this->e($this->formatDate($meta['generated_at'] ?? date('c')));
        $stats = $meta['stats'] ?? [];
        $points = (int) ($stats['points'] ?? 0);
        $equipments = (int) ($stats['equipments'] ?? 0);
        $aiBadge = $this->aiSourceBadge();
        $lineLabel = $this->e((string) ($meta['line_label'] ?? 'LINHA DE PRODUÇÃO'));
        $siteName = $this->e($this->extractSiteName((string) ($meta['root_name'] ?? '')));

        return $this->slideWrap('cover-slide', <<<HTML
<div class="cover-inner">
    <div class="cover-top-bar">
        <div class="cover-brand">{$company}</div>
        <img class="cover-logo" src="{$logo}" alt="Logo">
    </div>
    <div class="cover-main">
        <div class="cover-kicker">LUBRIFICAÇÃO</div>
        <h1 class="cover-line-label">{$lineLabel}</h1>
        <h2 class="cover-h1">{$siteName}</h2>
        <div class="cover-divider"></div>
        <p class="cover-doc-type">{$this->e($subtitle)} · {$this->e($title)}</p>
        {$aiBadge}
        <div class="cover-stats-row">
            <div class="cover-stat"><span>{$equipments}</span><small>Equipamentos</small></div>
            <div class="cover-stat"><span>{$points}</span><small>Pontos catalogados</small></div>
        </div>
    </div>
    <div class="cover-bottom">
        <div>Sistema LUB-TEK · Plano técnico de lubrificação</div>
        <div>Emissão: {$date} · Rev. 01</div>
    </div>
</div>
HTML);
    }

    /** Visão da linha + resumo IA + pauta — UMA página densa */
    private function slideLineOverviewWithBriefing(array $meta): string
    {
        $stations = $meta['line_stations'] ?? [];
        $subtitle = $this->e((string) ($meta['root_name'] ?? ''));
        $items = '';
        foreach ($stations as $st) {
            $num = (int) ($st['num'] ?? 0);
            $nome = $this->e((string) ($st['nome'] ?? ''));
            $items .= "<div class=\"station-card\"><div class=\"station-num\">{$num}</div><div class=\"station-name\">{$nome}</div></div>";
        }
        if ($items === '') {
            $items = '<p class="muted">Cadastre equipamentos filhos na árvore para numerar as estações.</p>';
        }

        $briefCol = '';
        $summary = $this->truncate((string) ($this->aiGlobal['executive_summary'] ?? ''), 520);
        if ($summary !== '') {
            $briefCol .= '<div class="brief-block"><h4>Resumo executivo</h4><p>' . $this->e($summary) . '</p></div>';
        }
        $opening = trim((string) ($this->aiGlobal['meeting_opening'] ?? ''));
        if ($opening !== '') {
            $briefCol .= '<div class="brief-block compact"><h4>Abertura da reunião</h4><p>' . $this->e($this->truncate($opening, 220)) . '</p></div>';
        }
        $agendaItems = '';
        foreach (array_slice($this->aiGlobal['agenda'] ?? [], 0, 6) as $i => $item) {
            $agendaItems .= '<li><span class="agenda-num">' . ($i + 1) . '</span>' . $this->e($this->truncate((string) $item, 80)) . '</li>';
        }
        if ($agendaItems !== '') {
            $briefCol .= '<div class="brief-block"><h4>Pauta</h4><ol class="agenda-inline">' . $agendaItems . '</ol></div>';
        }

        $briefHtml = $briefCol !== ''
            ? '<div class="overview-brief">' . $briefCol . '</div>'
            : '';

        $layoutClass = $briefHtml !== '' ? 'overview-split' : 'overview-full';

        return $this->slideWrap('overview-slide dense-slide', <<<HTML
<div class="slide-header-bar"><span>Visão da Linha</span><strong>{$subtitle}</strong></div>
<div class="{$layoutClass}">
    <div class="overview-stations"><div class="overview-grid">{$items}</div></div>
    {$briefHtml}
</div>
HTML);
    }

    /** SSMA + índice na mesma página */
    private function slideSsmaAndIndex(array $areas): string
    {
        $rows = '';
        $n = 0;
        foreach ($areas as $area) {
            foreach ($area['equipments'] ?? [] as $eq) {
                $n++;
                $rows .= '<tr>'
                    . '<td class="tc">' . $n . '</td>'
                    . '<td>' . $this->e((string) ($area['name'] ?? '')) . '</td>'
                    . '<td><strong>' . $this->e((string) ($eq['nome'] ?? '')) . '</strong></td>'
                    . '<td class="tc">' . count($eq['points'] ?? []) . '</td>'
                    . '</tr>';
            }
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="tc muted">Sem equipamentos.</td></tr>';
        }

        return $this->slideWrap('ssma-index-slide dense-slide', <<<HTML
<div class="slide-header-bar red"><span>SSMA & Índice</span><strong>Referência rápida</strong></div>
<div class="ssma-index-split">
    <div class="ssma-col">
        <h4>Segurança, Saúde e Meio Ambiente</h4>
        <ul class="ssma-compact">
            <li>EPIs: óculos, luvas químicas, calçado de segurança.</li>
            <li>LOTO antes de intervenção com equipamento parado.</li>
            <li>Conter respingos; usar panos absorventes e kit de contenção.</li>
            <li>Descarte em coletores sinalizados (classe I).</li>
            <li>Vazamento severo: interromper e acionar liderança.</li>
            <li>Bomba manual: respeitar vazão — não exceder volume nos mancais.</li>
        </ul>
    </div>
    <div class="index-col">
        <h4>Índice de equipamentos</h4>
        <table class="sap-table compact index-mini">
            <thead><tr><th>#</th><th>Área</th><th>Equipamento</th><th>Pts</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>
</div>
HTML);
    }

    private function slideFullPhoto(
        string $title,
        string $subtitle,
        string $imagePath,
        string $alt,
        bool $compactHeader = false,
        string $footerBar = '',
        array $points = []
    ): string {
        if (!$this->images->isValidImage($imagePath)) {
            return '';
        }

        if (!empty($points)) {
            $stage = $this->images->photoWithMarkers($imagePath, $points, [
                'class' => 'hero-img',
                'alt' => $alt,
                'max_markers' => 10,
            ]);
        } else {
            $img = $this->images->imgTag($imagePath, ['class' => 'hero-img', 'alt' => $alt]);
            $stage = $img !== '' ? '<div class="photo-frame plain">' . $img . '</div>' : '';
        }

        if ($stage === '') {
            return '';
        }

        $subHtml = $subtitle !== '' ? '<p class="photo-sub">' . $this->e($subtitle) . '</p>' : '';
        $headerClass = $compactHeader ? 'photo-header compact' : 'photo-header';

        return $this->slideWrap('photo-slide', <<<HTML
<div class="{$headerClass}">
    <h2>{$this->e($title)}</h2>
    {$subHtml}
</div>
<div class="photo-stage">{$stage}</div>
{$footerBar}
HTML);
    }

    /** @deprecated Mantido por compatibilidade — não gera slide (evita página vazia). */
    private function slideEquipmentTitle(string $area, string $equipment, array $eq, array $eqAi): string
    {
        return '';
    }

    /** Foto + tabela simples na MESMA página (padrão PDF com poucos pontos) */
    private function slidePhotoAndSimpleTable(string $title, string $imagePath, array $points, string $contextBar): string
    {
        $hasPhoto = $this->images->isValidImage($imagePath);
        $rows = $this->renderSimpleRows($points);

        if ($hasPhoto) {
            $img = $this->images->photoWithMarkers($imagePath, $points, [
                'class' => 'thumb-img',
                'alt' => $title,
                'max_markers' => 6,
            ]);
            if ($img === '') {
                $hasPhoto = false;
            }
        }

        if ($hasPhoto) {
            return $this->slideWrap('combo-slide dense-slide', <<<HTML
<div class="slide-header-bar dark"><span>{$this->e($title)}</span><strong>Dados de lubrificação</strong></div>
{$contextBar}
<div class="combo-split">
    <div class="combo-photo">{$img}</div>
    <div class="combo-table">
        <table class="simple-table fill">
            <thead><tr>
                <th>#</th><th>Ponto</th><th>Lubrificante</th><th>Método</th><th>Qtd</th><th>Período</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>
</div>
HTML);
        }

        return $this->slideSimpleTable($title, $title, $points, $contextBar);
    }

    private function slideSapTable(
        string $equipment,
        string $subsection,
        array $points,
        int $part = 1,
        int $rowOffset = 0,
        string $contextBar = ''
    ): string {
        $rows = '';
        $hasAnyThumb = false;
        foreach ($points as $i => $pt) {
            if ($this->images->isValidImage(trim((string) ($pt['imagem'] ?? $pt['lub_image'] ?? '')))) {
                $hasAnyThumb = true;
                break;
            }
        }

        foreach ($points as $i => $pt) {
            $itemNum = str_pad((string) ($rowOffset + $i + 1), 2, '0', STR_PAD_LEFT);
            $freqTroca = $pt['freq_troca_dias'] ?? null;
            $freqInsp = $pt['freq_inspecao_dias'] ?? null;
            $thumbCol = '';
            if ($hasAnyThumb) {
                $imgPath = trim((string) ($pt['imagem'] ?? $pt['lub_image'] ?? ''));
                $thumb = $this->images->isValidImage($imgPath)
                    ? $this->images->imgTag($imgPath, ['class' => 'cell-thumb', 'alt' => ''])
                    : '';
                $thumbCol = '<td class="tc thumb-cell">' . ($thumb ?: '—') . '</td>';
            }
            $sapRef = $this->images->pointSapRef($pt);
            $rows .= '<tr>'
                . '<td class="tc">' . $itemNum . '</td>'
                . $thumbCol
                . '<td class="small">' . $this->e((string) ($pt['local_instalacao'] ?? $equipment)) . '</td>'
                . '<td class="tc mono">' . $this->e((string) ($pt['tag'] ?: $pt['sap'] ?: '-')) . '</td>'
                . '<td class="tc">' . ($freqTroca !== null ? $this->e((string) $freqTroca) : '-') . '</td>'
                . '<td class="tc">' . ($freqInsp !== null ? $this->e((string) $freqInsp) : '-') . '</td>'
                . '<td>' . $this->e((string) ($pt['descricao_ponto'] ?? $pt['ponto_lub'] ?? '')) . '</td>'
                . '<td class="tc">' . $this->e($sapRef !== '' ? $sapRef : '-') . '</td>'
                . '<td class="tc mono">' . $this->e((string) ($pt['sap'] ?: $pt['lub_codigo'] ?: '-')) . '</td>'
                . '<td class="tc">' . $this->e((string) ($pt['qtd_material'] ?? '-')) . '</td>'
                . '<td class="tc">' . $this->e((string) ($pt['unid_material'] ?? '-')) . '</td>'
                . '<td class="small">' . $this->e((string) ($pt['metodo'] ?: $pt['servico'] ?: '-')) . '</td>'
                . '<td class="lub-cell">' . $this->e((string) ($pt['material'] ?? '-')) . '</td>'
                . '</tr>';
        }

        $partLabel = $part > 1 ? " · {$part}" : '';
        $thumbHead = $hasAnyThumb ? '<th>Foto</th>' : '';

        return $this->slideWrap('table-slide dense-slide', <<<HTML
<div class="slide-header-bar dark">
    <span>{$this->e($equipment)}</span>
    <strong>{$this->e($subsection)}{$partLabel}</strong>
</div>
{$contextBar}
<div class="table-scroll">
<table class="sap-table dense">
<thead>
<tr>
    <th>It.</th>{$thumbHead}<th>Local instalação</th><th>SAP/Tag</th>
    <th>Freq. troca (d)</th><th>Freq. insp. (d)</th><th>Descrição ponto</th><th>Pto SAP</th>
    <th>Cód. SAP</th><th>Qtd</th><th>Un.</th><th>Método</th><th>Lubrificante</th>
</tr>
</thead>
<tbody>{$rows}</tbody>
</table>
</div>
HTML);
    }

    private function slideSimpleTable(string $equipment, string $subsection, array $points, string $contextBar = '', bool $compact = false): string
    {
        $rows = $this->renderSimpleRows($points);
        $slideClass = $compact ? 'simple-table-slide compact-slide' : 'simple-table-slide dense-slide';

        return $this->slideWrap($slideClass, <<<HTML
<div class="slide-header-bar"><span>{$this->e($equipment)}</span><strong>{$this->e($subsection)}</strong></div>
{$contextBar}
<div class="table-scroll">
<table class="simple-table fill">
<thead><tr>
    <th>#</th><th>Ponto / Descrição</th><th>Lubrificante</th><th>Método intervenção</th><th>Qt</th><th>Periodicidade</th><th>Recomendação</th>
</tr></thead>
<tbody>{$rows}</tbody>
</table>
</div>
HTML);
    }

    private function renderSimpleRows(array $points): string
    {
        $rows = '';
        foreach ($points as $i => $pt) {
            $num = $this->images->pointMarkerNumber($i);
            $desc = trim((string) ($pt['descricao_ponto'] ?? $pt['ponto_lub'] ?? $pt['nome'] ?? ''));
            if ($desc === '') {
                $desc = 'Ponto ' . $num;
            }
            $sap = $this->images->pointSapRef($pt);
            if ($sap !== '' && stripos($desc, $sap) === false) {
                $desc .= ' · SAP ' . $sap;
            }
            $material = trim((string) ($pt['material'] ?? ''));
            $rows .= '<tr>'
                . '<td class="tc">' . $num . '</td>'
                . '<td>' . $this->e($desc) . '</td>'
                . '<td class="lub-cell">' . $this->e($material !== '' ? $material : '-') . '</td>'
                . '<td>' . $this->e((string) ($pt['metodo'] ?: $pt['servico'] ?: '-')) . '</td>'
                . '<td class="tc">' . $this->e(trim((string) ($pt['qtd_material'] ?? '') . ' ' . (string) ($pt['unid_material'] ?? ''))) . '</td>'
                . '<td>' . $this->e((string) ($pt['periodo'] ?? '-')) . '</td>'
                . '<td class="small">' . $this->e((string) ($pt['khs_recomendacao'] ?? '-')) . '</td>'
                . '</tr>';
        }
        return $rows;
    }

    private function renderSsmaTrainingSlides(array $meta): string
    {
        $lubs = $meta['unique_lubricants'] ?? [];
        $slides = LubricationPlanSsmaContent::trainingSlides(is_array($lubs) ? $lubs : []);
        $html = '';
        foreach ($slides as $slide) {
            $html .= $this->slideSsmaTraining($slide);
        }
        return $html;
    }

    /** @param array{title:string,subtitle:string,sections:array} $slide */
    private function slideSsmaTraining(array $slide): string
    {
        $cols = '';
        foreach ($slide['sections'] ?? [] as $sec) {
            $items = '';
            foreach ($sec['items'] ?? [] as $item) {
                $items .= '<li>' . $this->e((string) $item) . '</li>';
            }
            $cols .= '<div class="ssma-train-block"><h4>' . $this->e((string) ($sec['heading'] ?? '')) . '</h4><ul>' . $items . '</ul></div>';
        }

        return $this->slideWrap('ssma-train-slide dense-slide', <<<HTML
<div class="slide-header-bar red"><span>Treinamento SSMA</span><strong>{$this->e((string) ($slide['title'] ?? ''))}</strong></div>
<p class="ssma-train-sub">{$this->e((string) ($slide['subtitle'] ?? ''))}</p>
<div class="ssma-train-grid">{$cols}</div>
HTML);
    }

    private function extractSiteName(string $rootName): string
    {
        if (preg_match('/^(Unidade:\s*)?(.+?)(?:\s+LINH|\s+LINHA)/iu', $rootName, $m)) {
            return trim($m[2]);
        }
        if (preg_match('/SOLAR\s+COCA[- ]COLA[^\n]*/iu', $rootName, $m)) {
            return trim($m[0]);
        }
        return $rootName !== '' ? $rootName : 'Planta Industrial';
    }

    private function slideInlineNote(string $title, string $note): string
    {
        return $this->slideWrap('note-slide dense-slide', <<<HTML
<div class="slide-header-bar amber compact-bar"><span>Observação</span><strong>{$this->e($title)}</strong></div>
<div class="note-body inline-note">{$this->e($note)}</div>
HTML);
    }

    /** Anexa assinaturas (e encerramento IA, se houver) ao último slide — evita página vazia. */
    private function attachClosingFooter(string $body): string
    {
        $closingHtml = $this->buildClosingFooterHtml();
        if ($closingHtml === '') {
            return $body;
        }

        $needle = '<div class="slide-footer">';
        $lastPos = strrpos($body, $needle);
        if ($lastPos === false) {
            return $body . $this->buildClosingFooterHtml();
        }

        $before = substr($body, 0, $lastPos);
        $after = substr($body, $lastPos);
        $lastSectionPos = strrpos($before, '<section class="slide');
        if ($lastSectionPos !== false) {
            $head = substr($before, 0, $lastSectionPos);
            $sectionOpen = substr($before, $lastSectionPos);
            $sectionOpen = preg_replace(
                '/^<section class="slide([^"]*)"/',
                '<section class="slide$1 has-closing-footer"',
                $sectionOpen,
                1
            );
            $before = $head . $sectionOpen;
        }

        return $before . $closingHtml . $after;
    }

    private function buildClosingFooterHtml(): string
    {
        $closing = trim((string) ($this->aiGlobal['closing_recommendations'] ?? ''));
        $highlights = '';
        foreach (array_slice($this->aiGlobal['strategic_highlights'] ?? [], 0, 3) as $h) {
            $highlights .= '<li>' . $this->e($this->truncate((string) $h, 90)) . '</li>';
        }

        $closingBlock = $closing !== ''
            ? '<div class="closing-text-inline"><strong>Recomendações:</strong> ' . $this->e($this->truncate($closing, 320)) . '</div>'
            : '';
        $highlightsBlock = $highlights !== ''
            ? '<ul class="closing-highlights-inline">' . $highlights . '</ul>'
            : '';

        $metaBlock = ($closingBlock !== '' || $highlightsBlock !== '')
            ? '<div class="closing-meta">' . $closingBlock . $highlightsBlock . '</div>'
            : '';

        return <<<HTML
<div class="doc-closing-footer">
    {$metaBlock}
    <div class="sig-grid footer-sig">
        <div><div class="sig-line"></div><p>Elaborado — Eng. Lubrificação</p></div>
        <div><div class="sig-line"></div><p>Verificado — PCM / Confiabilidade</p></div>
        <div><div class="sig-line"></div><p>Aprovado — Cliente / Planta</p></div>
    </div>
</div>
HTML;
    }

    /** @deprecated Assinaturas agora são anexadas via attachClosingFooter() */
    private function slideClosingAndSignatures(): string
    {
        return '';
    }

    // ─── Helpers IA inline ───────────────────────────────────────────────────

    private function buildContextBar(array $eqAi, ?array $sub, bool $isFirst): string
    {
        if (empty($this->aiGlobal)) {
            return '';
        }
        if (!$isFirst || empty($eqAi)) {
            return $this->inlineNote((string) ($sub['ai_note'] ?? ''));
        }
        $parts = array_filter([
            $this->truncate((string) ($eqAi['overview'] ?? ''), 180),
            $this->truncate((string) ($eqAi['technical_context'] ?? ''), 160),
        ]);
        if (empty($parts)) {
            return $this->inlineNote((string) ($sub['ai_note'] ?? ''));
        }
        return '<div class="context-bar">' . $this->e(implode(' · ', $parts)) . '</div>';
    }

    private function buildPhotoSubtitle(string $area, array $eq): string
    {
        return implode(' · ', array_filter([
            $area,
            (string) ($eq['tag'] ?? ''),
            (string) ($eq['fabricante'] ?? ''),
        ]));
    }

    private function inlineNote(string $text): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) < 12) {
            return '';
        }
        return '<div class="context-bar note">' . $this->e($this->truncate($text, 200)) . '</div>';
    }

    private function aiSourceBadge(): string
    {
        $src = (string) ($this->docOptions['ai_source'] ?? '');
        if ($src === 'gemini') {
            return '<div class="cover-ai-badge">Textos IA Lúbria</div>';
        }
        if ($src === 'local') {
            return '<div class="cover-ai-badge local">Textos executivos</div>';
        }
        return '';
    }

    private function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    private function slideWrap(string $class, string $inner): string
    {
        $inner = trim($inner);
        if ($inner === '' || trim(strip_tags($inner)) === '') {
            return '';
        }
        $this->pageNum++;
        $footer = '<div class="slide-footer"><span>LUB-TEK · Plano de Lubrificação</span><span>Pág. ' . $this->pageNum . '</span></div>';
        return '<section class="slide ' . $class . '">' . $inner . $footer . '</section>';
    }

    private function wrapDocument(string $title, string $body, array $options = []): string
    {
        $autoPrint = !empty($options['auto_print']);
        $showToolbar = array_key_exists('show_toolbar', $options) ? (bool) $options['show_toolbar'] : true;
        $toolbar = $showToolbar ? '<div class="no-print toolbar"><button type="button" onclick="window.print()">Imprimir / Salvar PDF</button><span class="toolbar-hint">Ctrl+P · Paisagem · Margens mínimas</span></div>' : '';
        $autoPrintJs = $autoPrint ? 'window.addEventListener("load",()=>setTimeout(()=>window.print(),800));' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{$this->e($title)}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
<style>{$this->css()}</style>
</head>
<body>{$toolbar}<main id="plan-document" class="plan-document" data-plan-pattern="pet160-v2">{$body}</main><script>{$autoPrintJs}</script></body>
</html>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
:root { --red:#e10600; --red-dark:#9b0000; --ink:#1a1a1a; --muted:#5c5c5c; --line:#d9d9d9; --table-head:#2d2d2d; --soft:#f5f5f5; }
* { box-sizing:border-box; margin:0; padding:0; }
html,body { background:#888; color:var(--ink); font-family:'Inter',sans-serif; font-size:10px; }
.toolbar { position:sticky; top:0; z-index:99; display:flex; gap:12px; align-items:center; padding:10px 16px; background:#111; color:#fff; }
.toolbar button { background:var(--red); color:#fff; border:none; padding:10px 18px; font-weight:700; border-radius:6px; cursor:pointer; }
.toolbar-hint { font-size:11px; color:#bbb; }
.plan-document { width:297mm; margin:16px auto 40px; }

.slide {
    width:297mm; height:210mm; background:#fff; position:relative;
    page-break-after:always; break-after:page; overflow:hidden;
    display:flex; flex-direction:column; box-shadow:0 4px 24px rgba(0,0,0,.15); margin-bottom:12px;
}
.compact-slide { height:auto; min-height:auto; max-height:210mm; }
.compact-slide .table-scroll { flex:0 1 auto; }
.merged-stack { flex:1; padding:6px 10px 8px; overflow:hidden; display:flex; flex-direction:column; gap:8px; }
.merged-block { border:1px solid var(--line); border-radius:4px; overflow:hidden; }
.merged-subhead { background:var(--soft); padding:5px 10px; font-size:8px; font-weight:800; text-transform:uppercase; color:var(--red-dark); border-bottom:1px solid var(--line); }
.merged-inner { margin:0; font-size:7.5px; }
.merged-inner th { padding:4px 3px; font-size:6.5px; }
.merged-inner td { padding:3px 4px; }
.photo-table-combo-body { flex:1; display:flex; flex-direction:column; min-height:0; overflow:hidden; }
.combo-photo-top { flex:0 0 52%; min-height:0; background:#111; padding:4px; overflow:hidden; }
.combo-photo-top .photo-marked-wrap { height:100%; }
.combo-photo-top .point-legend { max-height:16mm; grid-template-columns:repeat(3,1fr); }
.combo-tables-bottom { flex:1; min-height:0; padding:6px 8px; overflow:hidden; background:#fff; }
.hero-img.compact-photo { max-height:100%; }
.slide-footer { margin-top:auto; display:flex; justify-content:space-between; padding:5px 12px; font-size:7px; color:var(--muted); border-top:1px solid var(--line); background:var(--soft); flex-shrink:0; }

.cover-slide { background:linear-gradient(145deg,#fff 55%,#fef2f2 100%); }
.cover-inner { flex:1; display:flex; flex-direction:column; padding:16mm 14mm 10mm; }
.cover-top-bar { display:flex; justify-content:space-between; align-items:flex-start; }
.cover-brand { font-weight:800; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); }
.cover-logo { width:64px; height:64px; object-fit:contain; }
.cover-main { flex:1; display:flex; flex-direction:column; justify-content:center; text-align:center; padding:8mm 0; }
.cover-kicker { font-family:'Oswald',sans-serif; font-size:20px; color:var(--red); letter-spacing:.18em; font-weight:700; }
.cover-line-label { font-family:'Oswald',sans-serif; font-size:26px; font-weight:700; color:var(--red-dark); margin:4px 0; line-height:1.1; text-transform:uppercase; letter-spacing:.06em; }
.cover-h1 { font-family:'Oswald',sans-serif; font-size:22px; font-weight:600; color:var(--ink); margin:4px 0; line-height:1.15; text-transform:uppercase; }
.cover-divider { width:70px; height:4px; background:var(--red); margin:10px auto; }
.cover-doc-type { font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase; }
.cover-ai-badge { display:inline-block; margin-top:10px; padding:4px 12px; background:#fef2f2; color:var(--red-dark); border:1px solid #fecaca; border-radius:99px; font-size:8px; font-weight:800; text-transform:uppercase; }
.cover-ai-badge.local { background:#f0f9ff; color:#0369a1; border-color:#bae6fd; }
.cover-stats-row { display:flex; justify-content:center; gap:36px; margin-top:18px; }
.cover-stat span { display:block; font-size:28px; font-weight:800; color:var(--red-dark); line-height:1; }
.cover-stat small { font-size:8px; text-transform:uppercase; color:var(--muted); font-weight:700; }
.cover-bottom { display:flex; justify-content:space-between; font-size:8px; color:var(--muted); border-top:1px dashed var(--line); padding-top:8px; }

.slide-header-bar { display:flex; justify-content:space-between; align-items:center; padding:6px 12px; background:var(--red); color:#fff; font-size:9px; flex-shrink:0; }
.slide-header-bar.compact-bar { padding:5px 12px; }
.slide-header-bar strong { font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
.slide-header-bar.dark { background:var(--table-head); }
.slide-header-bar.red { background:var(--red-dark); }
.slide-header-bar.amber { background:#b45309; }

.overview-split { flex:1; display:grid; grid-template-columns:1.1fr .9fr; min-height:0; }
.overview-full { flex:1; padding:10px; min-height:0; }
.overview-full .overview-grid { grid-template-columns:repeat(4,1fr); }
.overview-stations { padding:10px; border-right:1px solid var(--line); overflow:hidden; }
.overview-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
.station-card { border:2px solid var(--red); border-radius:6px; padding:8px; text-align:center; background:#fff; }
.station-num { font-family:'Oswald',sans-serif; font-size:22px; font-weight:700; color:var(--red); line-height:1; }
.station-name { font-size:8px; font-weight:700; text-transform:uppercase; margin-top:4px; line-height:1.25; }
.overview-brief { padding:10px 12px; overflow:hidden; font-size:9px; line-height:1.45; }
.overview-brief.empty { display:flex; align-items:center; justify-content:center; }
.brief-block { margin-bottom:10px; }
.brief-block h4 { font-size:8px; text-transform:uppercase; color:var(--red-dark); margin-bottom:4px; letter-spacing:.06em; }
.brief-block.compact p { font-size:8.5px; }
.agenda-inline { list-style:none; padding:0; margin:0; }
.agenda-inline li { position:relative; padding-left:22px; margin-bottom:5px; font-size:8.5px; font-weight:600; }
.agenda-num { position:absolute; left:0; width:16px; height:16px; background:var(--red); color:#fff; border-radius:50%; font-size:7px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }

.ssma-index-split { flex:1; display:grid; grid-template-columns:.95fr 1.05fr; min-height:0; }
.ssma-col, .index-col { padding:10px 12px; overflow:hidden; }
.ssma-col { border-right:1px solid var(--line); }
.ssma-col h4, .index-col h4 { font-size:9px; text-transform:uppercase; color:var(--red-dark); margin-bottom:8px; }
.ssma-compact { padding-left:16px; font-size:9px; line-height:1.45; }
.ssma-compact li { margin-bottom:5px; }
.index-mini { font-size:8px; }

.photo-slide { background:#111; }
.photo-header { padding:8px 12px; background:rgba(0,0,0,.88); color:#fff; flex-shrink:0; }
.photo-header.compact { padding:6px 12px; }
.photo-header h2 { font-family:'Oswald',sans-serif; font-size:14px; text-transform:uppercase; letter-spacing:.03em; line-height:1.2; }
.photo-sub { font-size:8.5px; color:#ccc; margin-top:2px; line-height:1.35; }
.photo-stage { flex:1; display:flex; flex-direction:column; align-items:stretch; justify-content:center; padding:4px 6px 6px; min-height:0; background:#1a1a1a; }
.photo-marked-wrap { flex:1; display:flex; flex-direction:column; min-height:0; width:100%; }
.photo-frame { position:relative; flex:1; display:flex; align-items:center; justify-content:center; min-height:0; overflow:hidden; }
.photo-frame.plain { flex:1; }
.hero-img { max-width:100%; max-height:100%; width:auto!important; height:auto!important; object-fit:contain; display:block; }
.lub-marker { position:absolute; transform:translate(-50%,-50%); z-index:2; display:flex; flex-direction:column; align-items:center; pointer-events:none; }
.lub-marker-num { display:flex; align-items:center; justify-content:center; min-width:22px; height:22px; padding:0 4px; background:var(--red); color:#fff; border:2px solid #fff; border-radius:50%; font-family:'Oswald',sans-serif; font-size:11px; font-weight:700; box-shadow:0 2px 6px rgba(0,0,0,.45); }
.legend-sap { display:block; color:#999; font-size:6px; margin-top:1px; font-family:ui-monospace,monospace; }
.photo-slide:not(:has(.photo-frame img)) { display:none; }
.lub-marker-tail { width:2px; height:10px; background:var(--red); margin-top:-1px; }
.point-legend { flex-shrink:0; display:grid; grid-template-columns:repeat(2,1fr); gap:3px 10px; padding:6px 8px; background:rgba(0,0,0,.75); border-top:1px solid #444; max-height:28mm; overflow:hidden; }
.legend-item { display:flex; align-items:flex-start; gap:5px; font-size:7px; color:#eee; line-height:1.25; }
.legend-num { flex-shrink:0; width:14px; height:14px; background:var(--red); color:#fff; border-radius:50%; font-size:7px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
.legend-text { flex:1; }
.legend-mat { display:block; color:#aaa; font-size:6.5px; margin-top:1px; }
.hero-placeholder { color:#888; font-size:10px; font-weight:600; padding:20px; text-align:center; }

.context-bar { flex-shrink:0; padding:5px 12px; background:#fef2f2; border-bottom:1px solid #fecaca; font-size:8px; line-height:1.35; color:#7f1d1d; }
.context-bar.note { background:#fffbeb; border-color:#fde68a; color:#78350f; }

.section-slide .section-hero.compact { flex:1; padding:14mm; justify-content:center; }
.section-area { font-size:10px; letter-spacing:.12em; text-transform:uppercase; opacity:.85; }
.section-eq { font-family:'Oswald',sans-serif; font-size:28px; font-weight:700; margin:6px 0 14px; line-height:1.1; text-transform:uppercase; }
.section-meta.inline { display:flex; gap:20px; font-size:9px; margin-bottom:10px; }
.section-meta label { display:block; font-size:7px; opacity:.7; text-transform:uppercase; }

.subsection-body { flex:1; padding:20mm; display:flex; flex-direction:column; justify-content:center; }
.subsection-lead { font-family:'Oswald',sans-serif; font-size:24px; text-transform:uppercase; color:var(--red-dark); }

.table-scroll { flex:1; min-height:0; overflow:hidden; padding:0 6px 4px; }
.sap-table, .simple-table { width:100%; border-collapse:collapse; table-layout:fixed; }
.sap-table.dense { font-size:6.5px; }
.sap-table.dense th { background:var(--table-head); color:#fff; padding:4px 2px; font-size:6px; text-transform:uppercase; vertical-align:middle; word-wrap:break-word; }
.sap-table.dense td { padding:3px 2px; border-bottom:1px solid var(--line); vertical-align:top; word-wrap:break-word; line-height:1.25; }
.sap-table.dense tr:nth-child(even) td { background:#fafafa; }
.simple-table.fill { font-size:8px; }
.simple-table.fill th { background:var(--table-head); color:#fff; padding:5px 4px; font-size:7px; text-transform:uppercase; }
.simple-table.fill td { padding:4px; border-bottom:1px solid var(--line); vertical-align:top; }
.sap-table.compact th, .sap-table.compact td { padding:4px 5px; font-size:8px; }
.lub-cell { font-size:6.5px; font-weight:600; }
.tc { text-align:center; }
.mono { font-family:ui-monospace,monospace; font-size:6px; }
.small { font-size:6.5px; }
.muted { color:var(--muted); }
.cell-thumb { width:28px; height:28px; object-fit:cover; border-radius:3px; border:1px solid var(--line); display:block; margin:0 auto; }
.thumb-cell { width:32px; }

.combo-split { flex:1; display:grid; grid-template-columns:38% 62%; min-height:0; }
.combo-photo { display:flex; align-items:stretch; justify-content:center; padding:6px; background:#111; border-right:1px solid var(--line); overflow:hidden; }
.combo-photo .photo-marked-wrap { height:100%; }
.thumb-img { max-width:100%; max-height:100%; object-fit:contain; }
.combo-table { padding:6px; overflow:hidden; display:flex; align-items:stretch; }

.note-body.inline-note { padding:10px 14px; font-size:9px; line-height:1.45; background:#fffbeb; margin:8px 12px; border-left:4px solid #f59e0b; flex:1; }

.closing-signatures { flex:1; display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:14mm; align-items:end; }
.closing-col { font-size:9px; line-height:1.45; }
.closing-col h4 { font-size:8px; text-transform:uppercase; color:var(--red-dark); margin-bottom:6px; }
.closing-highlights ul { padding-left:16px; margin-top:8px; }
.closing-highlights li { margin-bottom:4px; }

.doc-closing-footer {
    margin-top:auto; flex-shrink:0; border-top:2px solid var(--line);
    padding:8px 14mm 6px; background:linear-gradient(to bottom,#fafafa,#fff);
    page-break-inside:avoid; break-inside:avoid;
}
.has-closing-footer .ssma-train-grid,
.has-closing-footer .merged-stack,
.has-closing-footer .table-scroll { flex:1 1 auto; min-height:0; }
.closing-meta { font-size:7.5px; line-height:1.4; margin-bottom:8px; color:var(--muted); }
.closing-text-inline strong { color:var(--red-dark); text-transform:uppercase; font-size:7px; letter-spacing:.04em; }
.closing-highlights-inline { padding-left:14px; margin:4px 0 0; font-size:7.5px; }
.closing-highlights-inline li { margin-bottom:2px; }
.sig-grid.footer-sig { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; width:100%; }
.sig-grid.footer-sig .sig-line { border-top:1.5px solid var(--ink); margin-bottom:5px; }
.sig-grid.footer-sig p { font-size:7px; font-weight:700; text-transform:uppercase; color:var(--muted); text-align:center; margin:0; }

.sig-grid.wide { grid-template-columns:repeat(3,1fr); gap:24px; width:100%; }
.sig-only { flex:1; display:flex; align-items:flex-end; padding:20mm; }
.sig-line { border-top:2px solid var(--ink); margin-bottom:6px; }
.sig-grid p { font-size:8px; font-weight:700; text-transform:uppercase; color:var(--muted); text-align:center; }

.ssma-train-sub { padding:6px 12px; font-size:9px; font-weight:600; color:var(--muted); border-bottom:1px solid var(--line); flex-shrink:0; }
.ssma-train-grid { flex:1; display:grid; grid-template-columns:repeat(2,1fr); gap:10px; padding:10px 12px; overflow:hidden; min-height:0; }
.ssma-train-block { background:var(--soft); border-left:3px solid var(--red); padding:8px 10px; border-radius:4px; }
.ssma-train-block h4 { font-size:8px; text-transform:uppercase; color:var(--red-dark); margin-bottom:6px; letter-spacing:.04em; }
.ssma-train-block ul { padding-left:14px; font-size:8.5px; line-height:1.4; }
.ssma-train-block li { margin-bottom:4px; }

@media print { html,body { background:#fff; } .no-print { display:none!important; } .plan-document { width:auto; margin:0; } .slide { box-shadow:none; margin:0; } }
@page { size:A4 landscape; margin:0; }
CSS;
    }

    private function formatDate(string $iso): string
    {
        try { return (new DateTime($iso))->format('d/m/Y H:i'); } catch (Throwable $e) { return date('d/m/Y H:i'); }
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
