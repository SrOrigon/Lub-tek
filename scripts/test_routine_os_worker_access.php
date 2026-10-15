<?php
/**
 * Smoke: O.S. de rotina do agente deve ser executável por qualquer trabalhador (fila compartilhada).
 */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/includes/lubrication_schedule.php';

$routine = [
    'descricao' => LubricationSchedule::MARKER_PREFIX . "42] Tarefa fixa\n",
    'responsavel' => 'Rotina Lubrificação',
    'usuarios_id' => 1,
];
$personal = [
    'descricao' => 'Manutenção corretiva motor',
    'responsavel' => 'João Silva',
    'usuarios_id' => 99,
];

if (!LubricationSchedule::isRoutineOrderRow($routine)) {
    fwrite(STDERR, "FAIL: rotina deveria ser detectada\n");
    exit(1);
}
if (LubricationSchedule::isRoutineOrderRow($personal)) {
    fwrite(STDERR, "FAIL: OS pessoal não deveria ser rotina\n");
    exit(1);
}

echo "test_routine_os_worker_access: OK\n";
exit(0);
