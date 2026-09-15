<?php
/**
 * Inicializa database.sqlite de forma segura (CLI ou web com ?key=).
 * Uso CLI: php scripts/ensure_db.php
 */
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/db.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    // Bloqueado via .htaccess em produção; permite diagnóstico local
    header('Content-Type: text/plain; charset=utf-8');
}

$t0 = microtime(true);
try {
    $pdo = DB::getMaster();
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
        ->fetchAll(PDO::FETCH_COLUMN);
    $elapsed = round(microtime(true) - $t0, 3);
    $msg = "OK database.sqlite em {$elapsed}s\nTabelas: " . implode(', ', $tables) . "\n";
    echo $msg;
    exit(0);
} catch (Throwable $e) {
    $elapsed = round(microtime(true) - $t0, 3);
    $msg = "ERRO em {$elapsed}s: " . $e->getMessage() . "\n";
    echo $msg;
    exit(1);
}
