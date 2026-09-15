<?php
/**
 * Processa fila CBM (OS + e-mail) fora da requisição do sensor.
 * Cron sugerido: * * * * * php scripts/process_cbm_jobs.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    die("Acesso negado. Execute via cron:\n  php scripts/process_cbm_jobs.php\n");
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/cbm_job_queue.php';

$total = 0;
$n = CbmJobQueue::processPending(DB::getInstance(null), 40);
$total += $n;
echo "[admin] $n\n";

foreach (TenantResolver::listTenantSlugs() as $slug) {
    $n = CbmJobQueue::processPending(DB::getInstance($slug), 40);
    $total += $n;
    echo "[$slug] $n\n";
}

echo "[OK] CBM jobs processados (total tentativas de lote): {$total}\n";
