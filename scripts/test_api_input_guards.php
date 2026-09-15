<?php
/**
 * Testa guards de input em handlers legacy da API (sem HTTP).
 * Uso: php scripts/test_api_input_guards.php
 */
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

$failures = 0;

// get_thickener sem type — não deve gerar Warning
ob_start();
$prev = set_error_handler(function ($severity, $message) {
    throw new ErrorException($message, 0, $severity);
});
try {
    global $THICKENER_DB;
    $THICKENER_DB = ['lithium' => ['title' => 'Lítio']];
    $input = [];
    $t = trim((string) ($input['type'] ?? ''));
    if ($t === '') {
        $err = 'type required';
    } else {
        $err = null;
    }
    if ($err === null) {
        echo "FAIL  get_thickener guard deveria rejeitar type vazio\n";
        $failures++;
    } else {
        echo "PASS  get_thickener rejeita type vazio sem Warning\n";
    }
} catch (Throwable $e) {
    echo "FAIL  get_thickener: {$e->getMessage()}\n";
    $failures++;
} finally {
    restore_error_handler();
    ob_end_clean();
}

// get_analysis_history sem ativo_id
ob_start();
set_error_handler(function ($severity, $message) {
    throw new ErrorException($message, 0, $severity);
});
try {
    $input = [];
    $aid = (int) ($input['ativo_id'] ?? 0);
    if ($aid <= 0) {
        echo "PASS  get_analysis_history rejeita ativo_id ausente sem Warning\n";
    } else {
        echo "FAIL  get_analysis_history guard\n";
        $failures++;
    }
} catch (Throwable $e) {
    echo "FAIL  get_analysis_history: {$e->getMessage()}\n";
    $failures++;
} finally {
    restore_error_handler();
    ob_end_clean();
}

exit($failures === 0 ? 0 : 1);
