<?php
/**
 * Importação CSV de laudos (ALS / Mobil Serv / genérico).
 * Não apaga análises existentes — só INSERT. Preview antes de gravar.
 */
class AnalysisCsv
{
    public static function parse(string $csv, PDO $db): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $csv);
        $lines = array_values(array_filter($lines, static function ($l) {
            return trim($l) !== '';
        }));
        if (count($lines) < 2) {
            return ['ok' => false, 'error' => 'CSV vazio ou sem cabeçalho.', 'rows' => []];
        }

        $header = self::splitRow(array_shift($lines));
        $map = self::mapHeader($header);
        $rows = [];
        $errors = [];

        foreach ($lines as $i => $line) {
            $cols = self::splitRow($line);
            $get = static function (string $key) use ($map, $cols) {
                $idx = $map[$key] ?? null;
                return $idx === null ? '' : trim((string) ($cols[$idx] ?? ''));
            };

            $tag = $get('tag');
            $ativoId = (int) $get('ativo_id');
            if ($ativoId <= 0 && $tag !== '') {
                $st = $db->prepare('SELECT id FROM ativos WHERE tag = ? OR nome = ? LIMIT 1');
                $st->execute([$tag, $tag]);
                $ativoId = (int) $st->fetchColumn();
            }
            $date = $get('date');
            if ($date === '') {
                $date = date('Y-m-d');
            }
            $date = self::normalizeDate($date);

            $row = [
                'line' => $i + 2,
                'ativo_id' => $ativoId,
                'tag' => $tag,
                'date' => $date,
                'lab' => $get('lab') !== '' ? $get('lab') : 'CSV',
                'iso' => $get('iso'),
                'water' => self::num($get('water')),
                'fe' => self::num($get('fe')),
                'cu' => self::num($get('cu')),
                'si' => self::num($get('si')),
                'visc40' => self::num($get('visc40')),
                'visc100' => self::num($get('visc100')),
                'acidez' => self::num($get('acidez')),
                'laudo' => $get('laudo') !== '' ? $get('laudo') : 'Normal',
            ];
            if ($ativoId <= 0) {
                $errors[] = 'Linha ' . $row['line'] . ': ativo/tag não encontrado (' . $tag . ').';
                $row['skip'] = true;
            }
            $rows[] = $row;
        }

        return ['ok' => true, 'rows' => $rows, 'errors' => $errors, 'importable' => count(array_filter($rows, static function ($r) {
            return empty($r['skip']);
        }))];
    }

    public static function insertRows(PDO $db, array $rows): int
    {
        $n = 0;
        $stmt = $db->prepare('INSERT INTO analises (ativo_id, data_coleta, laboratorio, iso_4406, agua_ppm, fe_ppm, cu_ppm, si_ppm, laudo_geral, visc40, visc100, acidez) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $r) {
            if (!empty($r['skip']) || (int) ($r['ativo_id'] ?? 0) <= 0) {
                continue;
            }
            $stmt->execute([
                (int) $r['ativo_id'],
                $r['date'],
                $r['lab'] ?? 'CSV',
                $r['iso'] ?? '',
                $r['water'] ?? 0,
                $r['fe'] ?? 0,
                $r['cu'] ?? 0,
                $r['si'] ?? 0,
                $r['laudo'] ?? 'Normal',
                $r['visc40'] ?? null,
                $r['visc100'] ?? null,
                $r['acidez'] ?? null,
            ]);
            $n++;
        }
        return $n;
    }

    private static function mapHeader(array $header): array
    {
        $aliases = [
            'ativo_id' => ['ativo_id', 'asset_id', 'id_ativo'],
            'tag' => ['tag', 'equipment', 'equipamento', 'ponto'],
            'date' => ['date', 'data', 'data_coleta', 'sampled'],
            'lab' => ['lab', 'laboratorio', 'laboratory'],
            'iso' => ['iso', 'iso_4406', 'iso4406'],
            'water' => ['water', 'agua', 'agua_ppm', 'h2o'],
            'fe' => ['fe', 'ferro', 'iron', 'fe_ppm'],
            'cu' => ['cu', 'cobre', 'copper'],
            'si' => ['si', 'silicio', 'silicon'],
            'visc40' => ['visc40', 'visc_40', 'kv40', 'viscosidade_40'],
            'visc100' => ['visc100', 'visc_100', 'kv100'],
            'acidez' => ['acidez', 'tan', 'tbn', 'acid'],
            'laudo' => ['laudo', 'status', 'parecer'],
        ];
        $map = [];
        foreach ($header as $i => $h) {
            $n = self::norm((string) $h);
            foreach ($aliases as $key => $list) {
                if (in_array($n, $list, true)) {
                    $map[$key] = $i;
                }
            }
        }
        return $map;
    }

    private static function splitRow(string $line): array
    {
        $sep = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
        return str_getcsv($line, $sep);
    }

    private static function norm(string $s): string
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower(trim($s), 'UTF-8') : strtolower(trim($s));
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ã' => 'a', 'ç' => 'c']);
    }

    private static function num($v): ?float
    {
        $s = trim(str_replace(',', '.', (string) $v));
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/[^0-9.\-]/', '', $s);
        return is_numeric($s) ? (float) $s : null;
    }

    private static function normalizeDate(string $d): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $d, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return $d;
    }
}
