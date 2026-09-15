<?php
/**
 * Carimbo de auditoria só em fotos novas (GD). Originais antigos não são alterados.
 */
class ImageWatermark
{
    public static function apply($img, array $meta): void
    {
        if (!$img || !function_exists('imagestring')) {
            return;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 80 || $h < 40) {
            return;
        }

        $user = self::clip((string) ($meta['user'] ?? ''), 32);
        $tag = self::clip((string) ($meta['tag'] ?? ''), 24);
        $line1 = date('d/m/Y H:i');
        $line2 = trim($tag . ($tag !== '' && $user !== '' ? ' · ' : '') . $user);
        if ($line2 === '') {
            $line2 = 'LUB-TEK';
        }

        $pad = 8;
        $boxH = 36;
        $boxY = $h - $boxH - 4;
        $overlay = imagecolorallocatealpha($img, 15, 23, 42, 70);
        imagefilledrectangle($img, 4, $boxY, $w - 4, $h - 4, $overlay);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 3, $pad, $boxY + 4, $line1, $white);
        imagestring($img, 2, $pad, $boxY + 18, $line2, $white);
    }

    private static function clip(string $s, int $max): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max, 'UTF-8');
        }
        return substr($s, 0, $max);
    }
}
