<?php
/**
 * Identifica e remove mídia órfã/duplicada com segurança.
 * Uso: php scripts/scan_orphan_media.php [--apply]
 * Retorna ['removed'=>int,'bytes'=>int] quando incluído por cleanup_workspace.php.
 */
if (!function_exists('lubtek_collect_db_media_paths')) {

function lubtek_collect_db_media_paths(PDO $pdo): array
{
    $paths = [];
    $queries = [
        "SELECT imagem FROM ativos WHERE imagem IS NOT NULL AND imagem != ''",
        "SELECT dados_tecnicos FROM ativos WHERE dados_tecnicos IS NOT NULL",
        "SELECT imagem FROM catalogo WHERE imagem IS NOT NULL AND imagem != ''",
    ];
    foreach ($queries as $sql) {
        try {
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $val) {
                if (!is_string($val) || $val === '') {
                    continue;
                }
                $paths[] = str_replace('\\', '/', $val);
                if (preg_match_all('#(?:uploads/|assets/img/)[^\s"\'<>]+#i', $val, $m)) {
                    foreach ($m[0] as $p) {
                        $paths[] = $p;
                    }
                }
            }
        } catch (Exception $e) {
        }
    }
    return array_unique($paths);
}

function lubtek_file_hash(string $path): ?string
{
    return is_file($path) ? md5_file($path) : null;
}

/** @return array{removed:int,bytes:int} */
function lubtek_scan_orphan_media(string $root, PDO $pdo, bool $apply = false, bool $verbose = true): array
{
    $removed = 0;
    $bytes = 0;

    $dbPaths = lubtek_collect_db_media_paths($pdo);
    $dbNorm = [];
    foreach ($dbPaths as $p) {
        $dbNorm[strtolower(str_replace('\\', '/', $p))] = true;
        $dbNorm[strtolower(basename($p))] = true;
    }

    if ($verbose) {
        echo 'Referências no banco: ' . count($dbPaths) . "\n\n";
    }

    $legacyDir = $root . '/assets/img/uploads';
    if (is_dir($legacyDir)) {
        foreach (glob($legacyDir . '/*') ?: [] as $f) {
            if (!is_file($f)) {
                continue;
            }
            $rel = 'assets/img/uploads/' . basename($f);
            $base = basename($f);
            if (isset($dbNorm[strtolower($rel)]) || isset($dbNorm[strtolower($base)])) {
                if ($verbose) {
                    echo "KEEP (DB): $rel\n";
                }
                continue;
            }
            $dupInUploads = $root . '/uploads/' . $base;
            $hashLegacy = lubtek_file_hash($f);
            $hashUploads = lubtek_file_hash($dupInUploads);
            if ($hashUploads && $hashLegacy === $hashUploads) {
                $size = filesize($f) ?: 0;
                if ($verbose) {
                    echo ($apply ? 'DEL' : 'DRY') . " duplicate: $rel (= uploads/$base) {$size}B\n";
                }
                if ($apply) {
                    @unlink($f);
                }
                $removed++;
                $bytes += $size;
                continue;
            }
            $size = filesize($f) ?: 0;
            if ($verbose) {
                echo ($apply ? 'DEL' : 'DRY') . " orphan legacy: $rel {$size}B\n";
            }
            if ($apply) {
                @unlink($f);
            }
            $removed++;
            $bytes += $size;
        }
    }

    foreach (glob($root . '/data/test_*.html') ?: [] as $f) {
        $size = filesize($f) ?: 0;
        if ($verbose) {
            echo ($apply ? 'DEL' : 'DRY') . ' test preview: ' . basename($f) . " {$size}B\n";
        }
        if ($apply) {
            @unlink($f);
        }
        $removed++;
        $bytes += $size;
    }

    $hashes = [];
    foreach (glob($root . '/assets/img/system/*') ?: [] as $f) {
        if (!is_file($f)) {
            continue;
        }
        $h = lubtek_file_hash($f);
        if (!$h) {
            continue;
        }
        if (isset($hashes[$h])) {
            $size = filesize($f) ?: 0;
            $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $f);
            if ($verbose) {
                echo ($apply ? 'DEL' : 'DRY') . " duplicate system img: $rel (= {$hashes[$h]}) {$size}B\n";
            }
            if ($apply) {
                @unlink($f);
            }
            $removed++;
            $bytes += $size;
        } else {
            $hashes[$h] = str_replace($root . DIRECTORY_SEPARATOR, '', $f);
        }
    }

    foreach (glob($root . '/uploads/*') ?: [] as $f) {
        if (!is_file($f)) {
            continue;
        }
        $rel = 'uploads/' . basename($f);
        if (isset($dbNorm[strtolower($rel)])) {
            continue;
        }
        if (preg_match('/^import_/i', basename($f))) {
            $size = filesize($f) ?: 0;
            if ($verbose) {
                echo ($apply ? 'DEL' : 'DRY') . " orphan import temp: $rel {$size}B\n";
            }
            if ($apply) {
                @unlink($f);
            }
            $removed++;
            $bytes += $size;
        }
    }

    return ['removed' => $removed, 'bytes' => $bytes];
}

}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    require __DIR__ . '/../config.php';
    require __DIR__ . '/../db.php';

    $root = dirname(__DIR__);
    $apply = in_array('--apply', $argv ?? [], true);

    echo '=== scan_orphan_media ===' . ($apply ? ' (APPLY)' : ' (dry-run)') . "\n";
    $result = lubtek_scan_orphan_media($root, DB::getInstance(), $apply, true);
    echo "\n=== RESULTADO ===\n";
    echo 'Itens: ' . $result['removed'] . "\n";
    echo 'Espaço: ' . round($result['bytes'] / 1024 / 1024, 2) . " MB\n";
    exit(0);
}
