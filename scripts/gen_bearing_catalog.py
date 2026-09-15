# -*- coding: utf-8 -*-
"""Gera includes/engineering_catalog.php com dimensões ISO 15 / ISO 355."""
from pathlib import Path

def bore(code: int) -> float:
    return {0: 10, 1: 12, 2: 15, 3: 17}.get(code, code * 5)

# series -> {bore_code: (D, B)}  ISO 15 (esferas rígidas / envelope)
S60 = {
    0: (26, 8), 1: (28, 8), 2: (32, 9), 3: (35, 10), 4: (42, 12), 5: (47, 12),
    6: (55, 13), 7: (62, 14), 8: (68, 15), 9: (75, 16), 10: (80, 16), 11: (90, 18),
    12: (95, 18), 13: (100, 18), 14: (110, 20), 15: (115, 20), 16: (125, 22),
    17: (130, 22), 18: (140, 24), 19: (145, 24), 20: (150, 24), 21: (160, 26),
    22: (170, 28), 24: (180, 28), 26: (200, 33), 28: (210, 33), 30: (225, 35), 32: (240, 38),
}
S62 = {
    0: (30, 9), 1: (32, 10), 2: (35, 11), 3: (40, 12), 4: (47, 14), 5: (52, 15),
    6: (62, 16), 7: (72, 17), 8: (80, 18), 9: (85, 19), 10: (90, 20), 11: (100, 21),
    12: (110, 22), 13: (120, 23), 14: (125, 24), 15: (130, 25), 16: (140, 26),
    17: (150, 28), 18: (160, 30), 19: (170, 32), 20: (180, 34), 21: (190, 36),
    22: (200, 38), 24: (215, 40), 26: (230, 40), 28: (250, 42), 30: (270, 45), 32: (290, 48),
}
S63 = {
    0: (35, 11), 1: (37, 12), 2: (42, 13), 3: (47, 14), 4: (52, 15), 5: (62, 17),
    6: (72, 19), 7: (80, 21), 8: (90, 23), 9: (100, 25), 10: (110, 27), 11: (120, 29),
    12: (130, 31), 13: (140, 33), 14: (150, 35), 15: (160, 37), 16: (170, 39),
    17: (180, 41), 18: (190, 43), 19: (200, 45), 20: (215, 47), 22: (240, 50),
    24: (260, 55), 26: (280, 58), 28: (300, 62), 30: (320, 65), 32: (340, 68),
}
S64 = {
    3: (62, 17), 4: (72, 19), 5: (80, 21), 6: (90, 23), 7: (100, 25), 8: (110, 27),
    9: (120, 29), 10: (130, 31), 11: (140, 33), 12: (150, 35), 13: (160, 37),
    14: (180, 42), 15: (190, 45), 16: (200, 48), 17: (210, 52), 18: (225, 54),
    20: (250, 58), 22: (270, 67), 24: (310, 72),
}
S68 = {
    0: (19, 5), 1: (21, 5), 2: (24, 5), 3: (26, 5), 4: (32, 7), 5: (37, 7),
    6: (42, 7), 7: (47, 7), 8: (52, 7), 9: (58, 7), 10: (65, 7), 11: (72, 9),
    12: (78, 10), 13: (85, 10), 14: (90, 10), 15: (95, 10), 16: (100, 10),
    17: (110, 13), 18: (115, 13), 20: (125, 13), 22: (140, 16), 24: (150, 16),
    26: (165, 18), 28: (175, 18), 30: (190, 20), 32: (200, 20),
}
S69 = {
    0: (22, 6), 1: (24, 6), 2: (28, 7), 3: (30, 7), 4: (37, 9), 5: (42, 9),
    6: (47, 9), 7: (55, 10), 8: (62, 12), 9: (68, 12), 10: (72, 12), 11: (80, 13),
    12: (85, 13), 13: (90, 13), 14: (100, 16), 15: (105, 16), 16: (110, 16),
    17: (120, 18), 18: (125, 18), 20: (140, 20), 22: (150, 20), 24: (165, 22),
    26: (180, 24), 28: (190, 24), 30: (210, 28), 32: (220, 28),
}
S160 = {
    2: (32, 8), 3: (35, 8), 4: (42, 8), 5: (47, 8), 6: (55, 9), 7: (62, 9),
    8: (68, 9), 9: (75, 10), 10: (80, 10), 11: (90, 11), 12: (95, 11),
    13: (100, 11), 14: (110, 13), 15: (115, 13), 16: (125, 14), 18: (140, 16),
    20: (150, 16), 22: (170, 18), 24: (180, 18), 26: (200, 20), 28: (210, 22), 30: (225, 24), 32: (240, 24),
}

# Rolos autocompensadores ISO 15 (largura série 2 / 3)
S222 = {
    5: (52, 18), 6: (62, 20), 7: (72, 23), 8: (80, 23), 9: (85, 23), 10: (90, 23),
    11: (100, 25), 12: (110, 28), 13: (120, 31), 14: (125, 31), 15: (130, 31),
    16: (140, 33), 17: (150, 36), 18: (160, 40), 19: (170, 43), 20: (180, 46),
    22: (200, 53), 24: (215, 58), 26: (230, 64), 28: (250, 68), 30: (270, 73),
    32: (290, 80), 34: (310, 86), 36: (320, 86), 38: (340, 92), 40: (360, 98),
}
S223 = {
    8: (90, 33), 9: (100, 36), 10: (110, 40), 11: (120, 43), 12: (130, 46),
    13: (140, 48), 14: (150, 51), 15: (160, 55), 16: (170, 58), 17: (180, 60),
    18: (190, 64), 19: (200, 67), 20: (215, 73), 22: (240, 80), 24: (260, 86),
    26: (280, 93), 28: (300, 102), 30: (320, 108), 32: (340, 114), 34: (360, 120),
    36: (380, 126), 38: (400, 132), 40: (420, 138),
}
S213 = {
    4: (52, 15), 5: (52, 15), 6: (72, 19), 7: (80, 21), 8: (90, 23), 9: (100, 25),
    10: (110, 27), 11: (120, 29), 12: (130, 31), 13: (140, 33), 14: (150, 35),
    15: (160, 37), 16: (170, 39), 17: (180, 41), 18: (190, 43), 20: (215, 47),
    22: (240, 50),
}
S230 = {
    22: (170, 45), 24: (180, 46), 26: (200, 52), 28: (210, 53), 30: (225, 56),
    32: (240, 60), 34: (260, 67), 36: (280, 74), 38: (290, 75), 40: (310, 82),
    44: (340, 90), 48: (360, 92), 52: (400, 108),
}
S231 = {
    20: (165, 52), 22: (180, 56), 24: (200, 62), 26: (210, 64), 28: (225, 68),
    30: (250, 80), 32: (270, 86), 34: (280, 88), 36: (300, 96), 38: (320, 104),
    40: (340, 112), 44: (370, 120), 48: (400, 128),
}
S232 = {
    18: (160, 52.4), 20: (180, 60.3), 22: (200, 69.8), 24: (215, 76), 26: (230, 80),
    28: (250, 88), 30: (270, 96), 32: (290, 104), 34: (310, 110), 36: (320, 112),
    38: (340, 120), 40: (360, 128), 44: (400, 144),
}
S240 = {
    24: (180, 60), 26: (200, 69), 28: (210, 69), 30: (225, 75), 32: (240, 80),
    34: (260, 90), 36: (280, 100), 38: (290, 100), 40: (310, 109), 44: (340, 118),
    48: (360, 122),
}
S241 = {
    22: (180, 60), 24: (200, 69), 26: (210, 73), 28: (225, 78), 30: (250, 88),
    32: (270, 96), 34: (280, 96), 36: (300, 106), 38: (320, 114), 40: (340, 122),
}

# Cônicos ISO 355 (T = largura total)
S302 = {
    4: (47, 15.25), 5: (52, 16.25), 6: (62, 17.25), 7: (72, 18.25), 8: (80, 19.75),
    9: (85, 20.75), 10: (90, 21.75), 11: (100, 22.75), 12: (110, 23.75), 13: (120, 24.75),
    14: (125, 26.25), 15: (130, 27.25), 16: (140, 28.25), 17: (150, 30.5), 18: (160, 32.5),
    19: (170, 34.5), 20: (180, 37), 21: (190, 39), 22: (200, 41), 24: (215, 43.5),
    26: (230, 46.5), 28: (250, 51.5), 30: (270, 55), 32: (290, 58),
}
S303 = {
    4: (52, 16.25), 5: (62, 18.25), 6: (72, 20.75), 7: (80, 22.75), 8: (90, 25.25),
    9: (100, 27.25), 10: (110, 29.25), 11: (120, 31.5), 12: (130, 33.5), 13: (140, 36),
    14: (150, 38), 15: (160, 40), 16: (170, 42.5), 17: (180, 44.5), 18: (190, 46.5),
    20: (215, 51.5), 22: (240, 54.5), 24: (260, 59.5), 26: (280, 63.5), 28: (300, 67.5),
}
S322 = {
    5: (52, 19.25), 6: (62, 21.25), 7: (72, 24.25), 8: (80, 24.75), 9: (85, 24.75),
    10: (90, 24.75), 11: (100, 26.75), 12: (110, 29.75), 13: (120, 32.75), 14: (125, 33.25),
    15: (130, 33.25), 16: (140, 35.25), 17: (150, 38.5), 18: (160, 42.5), 19: (170, 45.5),
    20: (180, 49), 22: (200, 56), 24: (215, 61.5), 26: (230, 67.75), 28: (250, 73.25),
}
S323 = {
    5: (62, 25.25), 6: (72, 28.75), 7: (80, 32.75), 8: (90, 35.25), 9: (100, 38.25),
    10: (110, 42.25), 11: (120, 45.5), 12: (130, 48.5), 13: (140, 51), 14: (150, 54),
    15: (160, 58), 16: (170, 61.5), 17: (180, 63.5), 18: (190, 67.5), 20: (215, 77.5),
    22: (240, 84.5), 24: (260, 90.5),
}

# Agulhas comuns (HK/BK envelope métrico DIN 618)
NEEDLE = {
    "HK0810": (8, 12, 10), "HK1010": (10, 14, 10), "HK1012": (10, 14, 12),
    "HK1212": (12, 16, 12), "HK1512": (15, 21, 12), "HK1516": (15, 21, 16),
    "HK1612": (16, 22, 12), "HK1616": (16, 22, 16), "HK2016": (20, 26, 16),
    "HK2020": (20, 26, 20), "HK2212": (22, 28, 12), "HK2516": (25, 32, 16),
    "HK2520": (25, 32, 20), "HK3016": (30, 37, 16), "HK3020": (30, 37, 20),
    "HK3516": (35, 42, 16), "HK3520": (35, 42, 20), "HK4016": (40, 47, 16),
    "HK4020": (40, 47, 20), "NA4900": (10, 22, 13), "NA4901": (12, 24, 13),
    "NA4902": (15, 28, 13), "NA4903": (17, 30, 13), "NA4904": (20, 37, 17),
    "NA4905": (25, 42, 17), "NA4906": (30, 47, 17), "NA4907": (35, 55, 20),
    "NA4908": (40, 62, 22), "NA4910": (50, 72, 22), "NA4912": (60, 85, 25),
}

ISO_VG = [2, 3, 5, 7, 10, 15, 22, 32, 46, 68, 100, 150, 220, 320, 460, 680, 1000, 1500]
NLGI = ["000", "00", "0", "1", "2", "3"]

# v100 típico mineral VI≈95 (ASTM D2270 / cartas ISO 3448)
VG_V100_VI95 = {
    2: 1.0, 3: 1.2, 5: 1.6, 7: 2.1, 10: 2.6, 15: 3.3, 22: 4.3, 32: 5.4, 46: 6.8,
    68: 8.8, 100: 11.4, 150: 14.8, 220: 19.0, 320: 24.2, 460: 30.5, 680: 38.0,
    1000: 48.0, 1500: 60.0,
}

TYPE_META = {
    "ball_deep": ("Esferas rígido de uma carreira (ISO 15)", 1.00, "ISO 15"),
    "ball_thin": ("Esferas rígido seção fina (ISO 15)", 1.00, "ISO 15"),
    "ball_angular": ("Esferas de contato angular (ISO 15)", 1.00, "ISO 15"),
    "cyl": ("Rolos cilíndricos (ISO 15)", 0.50, "ISO 15"),
    "spherical": ("Rolos autocompensadores (ISO 15)", 0.20, "ISO 15"),
    "tapered": ("Rolos cônicos (ISO 355)", 0.30, "ISO 355"),
    "needle": ("Rolos de agulha (DIN 618 / ISO 1206)", 0.30, "DIN 618"),
}


def add(rows, code, d, D, B, kind, aliases=None):
    name, k, std = TYPE_META[kind]
    rec = {
        "code": code,
        "d": d,
        "D": D,
        "B": B,
        "type": kind,
        "type_name": name,
        "k": k,
        "standard": std,
        "aliases": aliases or [],
    }
    rows[code] = rec


def expand_series(rows, prefix, table, kind, extra_aliases=None):
    for bc, (D, B) in table.items():
        d = bore(bc)
        code = f"{prefix}{bc:02d}"
        aliases = []
        if extra_aliases:
            for a in extra_aliases:
                aliases.append(f"{a}{bc:02d}")
        add(rows, code, d, D, B, kind, aliases)


def main():
    rows = {}
    expand_series(rows, "60", S60, "ball_deep")
    expand_series(rows, "62", S62, "ball_deep")
    expand_series(rows, "63", S63, "ball_deep")
    expand_series(rows, "64", S64, "ball_deep")
    expand_series(rows, "68", S68, "ball_thin")
    expand_series(rows, "69", S69, "ball_thin")
    expand_series(rows, "160", S160, "ball_thin")
    expand_series(rows, "70", S60, "ball_angular")
    expand_series(rows, "72", S62, "ball_angular")
    expand_series(rows, "73", S63, "ball_angular")
    expand_series(rows, "NU2", S62, "cyl", ["NJ2", "NUP2", "N2"])
    expand_series(rows, "NU3", S63, "cyl", ["NJ3", "NUP3", "N3"])
    expand_series(rows, "NU4", S64, "cyl", ["NJ4", "NUP4"])
    expand_series(rows, "NU10", S60, "cyl", ["NJ10"])
    expand_series(rows, "NU22", S222, "cyl", ["NJ22", "NUP22"])
    expand_series(rows, "NU23", S223, "cyl", ["NJ23", "NUP23"])
    expand_series(rows, "222", S222, "spherical")
    expand_series(rows, "223", S223, "spherical")
    expand_series(rows, "213", S213, "spherical")
    expand_series(rows, "230", S230, "spherical")
    expand_series(rows, "231", S231, "spherical")
    expand_series(rows, "232", S232, "spherical")
    expand_series(rows, "240", S240, "spherical")
    expand_series(rows, "241", S241, "spherical")
    expand_series(rows, "302", S302, "tapered")
    expand_series(rows, "303", S303, "tapered")
    expand_series(rows, "322", S322, "tapered")
    expand_series(rows, "323", S323, "tapered")
    for code, (d, D, B) in NEEDLE.items():
        add(rows, code, d, D, B, "needle")

    # PHP dump
    def php_num(x):
        if isinstance(x, float) and x == int(x):
            return str(int(x))
        return str(x)

    lines = []
    lines.append("<?php")
    lines.append("/**")
    lines.append(" * Catálogo dimensional de engenharia (ISO 15, ISO 355, DIN 618, ISO 3448).")
    lines.append(" * Gerado por scripts/gen_bearing_catalog.py — não editar à mão.")
    lines.append(" */")
    lines.append("class EngineeringCatalog")
    lines.append("{")
    lines.append("    public static function findBearing(string $code): ?array")
    lines.append("    {")
    lines.append("        $norm = self::normalizeCode($code);")
    lines.append("        $all = self::bearings();")
    lines.append("        if (isset($all[$norm])) {")
    lines.append("            return $all[$norm];")
    lines.append("        }")
    lines.append("        foreach ($all as $row) {")
    lines.append("            foreach ($row['aliases'] as $alias) {")
    lines.append("                if (self::normalizeCode($alias) === $norm) {")
    lines.append("                    return $row;")
    lines.append("                }")
    lines.append("            }")
    lines.append("        }")
    lines.append("        return null;")
    lines.append("    }")
    lines.append("")
    lines.append("    public static function search(string $q, int $limit = 50): array")
    lines.append("    {")
    lines.append("        $q = self::normalizeCode($q);")
    lines.append("        if ($q === '') {")
    lines.append("            return array_slice(array_values(self::bearings()), 0, $limit);")
    lines.append("        }")
    lines.append("        $out = [];")
    lines.append("        foreach (self::bearings() as $row) {")
    lines.append("            $hay = $row['code'] . implode('', $row['aliases']);")
    lines.append("            if (strpos(self::normalizeCode($hay), $q) !== false) {")
    lines.append("                $out[] = $row;")
    lines.append("                if (count($out) >= $limit) {")
    lines.append("                    break;")
    lines.append("                }")
    lines.append("            }")
    lines.append("        }")
    lines.append("        return $out;")
    lines.append("    }")
    lines.append("")
    lines.append("    public static function codes(): array")
    lines.append("    {")
    lines.append("        return array_keys(self::bearings());")
    lines.append("    }")
    lines.append("")
    lines.append("    public static function isoVgGrades(): array")
    lines.append("    {")
    lines.append("        return " + repr(ISO_VG).replace("[", "[").replace("]", "]") + ";")
    lines.append("    }")
    lines.append("")
    lines.append("    public static function nlgiGrades(): array")
    lines.append("    {")
    lines.append("        return ['000', '00', '0', '1', '2', '3'];")
    lines.append("    }")
    lines.append("")
    lines.append("    public static function v100ForVg(float $vg, float $vi = 95.0): float")
    lines.append("    {")
    lines.append("        static $map = [")
    for vg, v100 in VG_V100_VI95.items():
        lines.append(f"            {vg} => {v100},")
    lines.append("        ];")
    lines.append("        $keys = array_keys($map);")
    lines.append("        if (isset($map[(int) $vg])) {")
    lines.append("            $base = $map[(int) $vg];")
    lines.append("        } else {")
    lines.append("            $lo = $keys[0];")
    lines.append("            $hi = $keys[count($keys) - 1];")
    lines.append("            foreach ($keys as $k) {")
    lines.append("                if ($k <= $vg) { $lo = $k; }")
    lines.append("                if ($k >= $vg) { $hi = $k; break; }")
    lines.append("            }")
    lines.append("            if ($hi === $lo) {")
    lines.append("                $base = $map[$lo];")
    lines.append("            } else {")
    lines.append("                $t = ($vg - $lo) / max(0.001, ($hi - $lo));")
    lines.append("                $base = $map[$lo] + $t * ($map[$hi] - $map[$lo]);")
    lines.append("            }")
    lines.append("        }")
    lines.append("        $scale = 1.0 + (($vi - 95.0) * 0.0035);")
    lines.append("        return round(max(0.8, $base * $scale), 2);")
    lines.append("    }")
    lines.append("")
    lines.append("    public static function normalizeCode(string $code): string")
    lines.append("    {")
    lines.append("        $code = strtoupper(trim($code));")
    lines.append("        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? $code;")
    lines.append("        $code = preg_replace('/^(SKF|FAG|NSK|NTN|TIMKEN|KOYO|NACHI|INA)/', '', $code) ?? $code;")
    lines.append("        $suffixes = ['2RSR', '2RSH', '2RS', 'ZZ', '2Z', 'W33', 'C3', 'C4', 'E1', 'CC', 'CK', 'TVP', 'E'];")
    lines.append("        $changed = true;")
    lines.append("        while ($changed) {")
    lines.append("            $changed = false;")
    lines.append("            foreach ($suffixes as $sfx) {")
    lines.append("                $len = strlen($sfx);")
    lines.append("                if (strlen($code) > $len + 3 && substr($code, -$len) === $sfx) {")
    lines.append("                    $code = substr($code, 0, -$len);")
    lines.append("                    $changed = true;")
    lines.append("                    break;")
    lines.append("                }")
    lines.append("            }")
    lines.append("        }")
    lines.append("        return $code;")
    lines.append("    }")
    lines.append("")
    lines.append("    public static function bearings(): array")
    lines.append("    {")
    lines.append("        static $cache = null;")
    lines.append("        if ($cache !== null) {")
    lines.append("            return $cache;")
    lines.append("        }")
    lines.append("        $cache = [")

    for code, rec in rows.items():
        aliases = "[" + ", ".join("'" + a + "'" for a in rec["aliases"]) + "]"
        B = rec["B"]
        Bphp = str(B) if B != int(B) else str(int(B))
        Dphp = str(rec["D"]) if rec["D"] != int(rec["D"]) else str(int(rec["D"]))
        dphp = str(rec["d"]) if rec["d"] != int(rec["d"]) else str(int(rec["d"]))
        lines.append(
            f"            '{code}' => ['code'=>'{code}','d'=>{dphp},'D'=>{Dphp},'B'=>{Bphp},"
            f"'type'=>'{rec['type']}','type_name'=>'{rec['type_name']}','k'=>{rec['k']:.2f},"
            f"'standard'=>'{rec['standard']}','aliases'=>{aliases}],"
        )

    lines.append("        ];")
    lines.append("        return $cache;")
    lines.append("    }")
    lines.append("}")
    lines.append("")

    out = Path(__file__).resolve().parents[1] / "includes" / "engineering_catalog.php"
    out.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"Wrote {len(rows)} bearings to {out}")


if __name__ == "__main__":
    main()
