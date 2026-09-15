<?php
/**
 * Resolve caminhos de imagem do LUB-TEK para renderização confiável em PDF/impressão.
 */
class LubricationPlanImageHelper
{
    /** @var string */
    private $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?: dirname(__DIR__);
    }

    public function resolveSrc(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return ltrim($path, '/');
    }

    public function absolutePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return null;
        }
        $rel = ltrim($path, '/');
        $full = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        return is_file($full) ? $full : null;
    }

    /** Só incluir imagem no PDF se o arquivo existir (ou URL http válida). */
    public function isValidImage(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $path)) {
            return true;
        }
        return $this->absolutePath($path) !== null;
    }

    /**
     * Retorna tag <img> ou vazio se arquivo inexistente (evita ícone quebrado no PDF).
     */
    public function imgTag(string $path, array $options = []): string
    {
        $class = (string) ($options['class'] ?? '');
        $alt = htmlspecialchars((string) ($options['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
        $allowMissing = !empty($options['allow_missing']);

        if (!$this->isValidImage($path)) {
            return $allowMissing ? (string) ($options['placeholder'] ?? '') : '';
        }

        $src = $this->resolveSrc($path);
        if ($src === '') {
            return $allowMissing ? (string) ($options['placeholder'] ?? '') : '';
        }

        $dimAttr = '';
        $abs = $this->absolutePath($path);
        if ($abs && function_exists('getimagesize')) {
            $info = @getimagesize($abs);
            if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
                $dimAttr = ' width="' . (int) $info[0] . '" height="' . (int) $info[1] . '"';
            }
        }

        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '"' . $classAttr . $dimAttr . ' loading="lazy" decoding="async">';
    }

    /**
     * Foto com marcadores 1, 2, 3… + legenda. Retorna vazio se imagem inválida.
     *
     * @param array<int, array> $points
     */
    public function photoWithMarkers(string $path, array $points, array $options = []): string
    {
        if (!$this->isValidImage($path)) {
            return '';
        }

        $img = $this->imgTag($path, ['class' => (string) ($options['class'] ?? 'hero-img'), 'alt' => (string) ($options['alt'] ?? '')]);
        if ($img === '') {
            return '';
        }

        $markersHtml = '';
        $legendHtml = '';
        $maxMarkers = (int) ($options['max_markers'] ?? 10);
        $positions = $this->markerPositions($points, $maxMarkers);

        foreach ($points as $i => $pt) {
            $num = $this->pointMarkerNumber($i);
            $label = $this->pointLabel($pt, $num);
            $mat = htmlspecialchars((string) ($pt['material'] ?? ''), ENT_QUOTES, 'UTF-8');
            $sap = htmlspecialchars($this->pointSapRef($pt), ENT_QUOTES, 'UTF-8');
            $legendExtra = '';
            if ($mat !== '') {
                $legendExtra .= '<span class="legend-mat">' . $mat . '</span>';
            }
            if ($sap !== '') {
                $legendExtra .= '<span class="legend-sap">SAP ' . $sap . '</span>';
            }

            if ($i < $maxMarkers && isset($positions[$i])) {
                [$x, $y] = $positions[$i];
                $markersHtml .= '<div class="lub-marker" style="left:' . $x . '%;top:' . $y . '%" title="' . $label . '">'
                    . '<span class="lub-marker-num">' . $num . '</span>'
                    . '<span class="lub-marker-tail"></span></div>';
            }

            $legendHtml .= '<div class="legend-item"><span class="legend-num">' . $num
                . '</span><span class="legend-text">' . $label . $legendExtra . '</span></div>';
        }

        return '<div class="photo-marked-wrap">'
            . '<div class="photo-frame">' . $img . $markersHtml . '</div>'
            . ($legendHtml !== '' ? '<div class="point-legend">' . $legendHtml . '</div>' : '')
            . '</div>';
    }

    /** Marcador visual sempre 1, 2, 3… (códigos SAP vão na tabela/legenda). */
    public function pointMarkerNumber(int $index): int
    {
        return $index + 1;
    }

    public function pointLabel(array $pt, int $num): string
    {
        $label = trim((string) ($pt['descricao_ponto'] ?? $pt['ponto_lub'] ?? $pt['nome'] ?? ''));
        if ($label === '') {
            $label = 'Ponto ' . $num;
        }
        return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    }

    public function pointSapRef(array $pt): string
    {
        foreach (['tag', 'sap', 'ponto_num', 'ip'] as $k) {
            $v = trim((string) ($pt[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    /** @param array<int, array> $points @return array<int, array{0:float,1:float}> */
    private function markerPositions(array $points, int $max): array
    {
        $presets = [
            [48, 42], [32, 58], [68, 38], [38, 28], [62, 62], [25, 45],
            [75, 50], [42, 72], [58, 22], [52, 68], [28, 32], [72, 28],
        ];
        $out = [];
        $n = min(count($points), $max, count($presets));
        for ($i = 0; $i < $n; $i++) {
            $pt = $points[$i];
            $mx = $this->parsePercent($pt['marker_x'] ?? $pt['pos_x'] ?? $pt['pin_x'] ?? null);
            $my = $this->parsePercent($pt['marker_y'] ?? $pt['pos_y'] ?? $pt['pin_y'] ?? null);
            if ($mx !== null && $my !== null) {
                $out[$i] = [$mx, $my];
                continue;
            }
            $out[$i] = $presets[$i];
        }
        return $out;
    }

    private function parsePercent($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (float) str_replace(',', '.', (string) $value);
        if ($v < 0 || $v > 100) {
            return null;
        }
        return $v;
    }
}
