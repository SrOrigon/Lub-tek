<?php
/**
 * Garante o contrato visual do Plano de Lubrificação independente do conteúdo.
 */
class LubricationPlanPatternGuard
{
    /** @return array{ok:bool,errors:array<int,string>,warnings:array<int,string>} */
    public static function validate(string $html): array
    {
        $body = self::extractBody($html);
        $errors = [];
        $warnings = [];

        if (substr_count($body, '<div class="doc-closing-footer">') !== 1) {
            $errors[] = 'Deve existir exatamente um bloco doc-closing-footer (assinaturas).';
        }

        if (preg_match('/<section class="slide[^"]*\bsig-slide\b/', $body)) {
            $errors[] = 'Slide dedicado só a assinaturas (sig-slide) não é permitido.';
        }

        if (preg_match('/class="[^"]*\bsig-only\b/', $body)) {
            $errors[] = 'Layout sig-only (página vazia de assinaturas) detectado.';
        }

        if (strpos($body, '<div class="hero-placeholder"') !== false) {
            $errors[] = 'Placeholder de imagem ausente detectado — imagem inválida não deve entrar no PDF.';
        }

        if (preg_match('/<section class="slide photo-slide[^"]*">(?:(?!<img).)*<\/section>/s', $body)) {
            $errors[] = 'Slide de foto sem <img> válida detectado.';
        }

        if (preg_match_all('/<section class="slide[^"]*">(.*?)<div class="slide-footer">/s', $body, $slides, PREG_SET_ORDER)) {
            foreach ($slides as $i => $slide) {
                $inner = trim(strip_tags($slide[1]));
                if ($inner === '') {
                    $errors[] = 'Slide ' . ($i + 1) . ' está vazio (sem conteúdo).';
                } elseif (strlen($inner) < 40 && strpos($slide[0], 'doc-closing-footer') === false) {
                    $warnings[] = 'Slide ' . ($i + 1) . ' com conteúdo muito curto (' . strlen($inner) . ' chars).';
                }
            }
        }

        if (!preg_match('/has-closing-footer[\s\S]*<div class="doc-closing-footer">[\s\S]*<div class="slide-footer">/s', $body)) {
            $errors[] = 'Assinaturas não estão anexadas ao último slide (has-closing-footer).';
        }

        if (preg_match('/lub-marker-num">\d{3,}/', $body)) {
            $errors[] = 'Marcador na foto usa código longo — deve ser 1, 2, 3…';
        }

        if (preg_match('/<img[^>]+src=""\s/', $body) || preg_match('/<img[^>]+src=""\/>/', $body)) {
            $errors[] = 'Tag <img> com src vazio detectada.';
        }

        if (strpos($html, 'data-plan-pattern="pet160-v2"') === false) {
            $errors[] = 'Atributo data-plan-pattern=pet160-v2 ausente no documento.';
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public static function countSlides(string $html): int
    {
        $body = self::extractBody($html);
        return substr_count($body, '<section class="slide');
    }

    private static function extractBody(string $html): string
    {
        if (preg_match('/<main id="plan-document"[^>]*>(.*)<\/main>/s', $html, $m)) {
            return $m[1];
        }
        return $html;
    }

    /** Corrige fragmento HTML do corpo (slides) antes do wrap final. */
    public static function enforceBody(string $body): string
    {
        $body = preg_replace('/<section class="slide[^"]*\bsig-slide\b[^"]*">.*?<\/section>/s', '', $body) ?? $body;
        $body = preg_replace('/<section class="slide photo-slide[^"]*">\s*<div class="photo-header[^"]*">.*?<\/div>\s*<div class="photo-stage">\s*<\/div>.*?<\/section>/s', '', $body) ?? $body;

        if (substr_count($body, '<div class="doc-closing-footer">') === 0) {
            $body = self::injectClosingFooter($body);
        }

        return $body;
    }

    /** @deprecated Use enforceBody() no pipeline interno; validate() no teste completo. */
    public static function enforce(string $html): string
    {
        $body = self::enforceBody(self::extractBody($html));
        return preg_replace(
            '/(<main id="plan-document"[^>]*>).*(<\/main>)/s',
            '$1' . $body . '$2',
            $html,
            1
        ) ?? $html;
    }

    private static function injectClosingFooter(string $html): string
    {
        $closing = <<<'HTML'
<div class="doc-closing-footer">
    <div class="sig-grid footer-sig">
        <div><div class="sig-line"></div><p>Elaborado — Eng. Lubrificação</p></div>
        <div><div class="sig-line"></div><p>Verificado — PCM / Confiabilidade</p></div>
        <div><div class="sig-line"></div><p>Aprovado — Cliente / Planta</p></div>
    </div>
</div>
HTML;

        $needle = '<div class="slide-footer">';
        $lastPos = strrpos($html, $needle);
        if ($lastPos === false) {
            return $html . $closing;
        }

        $before = substr($html, 0, $lastPos);
        $after = substr($html, $lastPos);
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

        return $before . $closing . $after;
    }
}
