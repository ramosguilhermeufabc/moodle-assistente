<?php
// Executa uma scheduled task pela sequencia nativa do Moodle, idêntica a admin/cli/scheduled_task.php
// (lock 'core_cron', lock da task, run_inner_scheduled_task), SEM o guard de moodle_needs_upgrading()
// daquele script, que bloqueia qualquer execução no site hoje por um upgrade de plugin pendente
// (nada a ver com o mecanismo de lock que este teste mede). Nenhum mecanismo paralelo: chama só a API
// nativa que o proprio Moodle usa.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

$classname = $argv[1] ?? '\\local_assistente\\task\\process_queue';
fwrite(STDOUT, "pid=" . getmypid() . " starting=" . microtime(true) . "\n");

if (!$task = \core\task\manager::get_scheduled_task($classname)) {
    fwrite(STDOUT, "pid=" . getmypid() . " task not found\n");
    exit(1);
}

\core\task\manager::scheduled_task_starting($task);
raise_memory_limit(MEMORY_EXTRA);
\core\cron::setup_user();

$cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
$t0 = microtime(true);
if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
    fwrite(STDOUT, "pid=" . getmypid() . " t=" . microtime(true) . " Cannot obtain cron lock\n");
    exit(129);
}
if (!$lock = $cronlockfactory->get_lock('\\' . get_class($task), 10)) {
    $cronlock->release();
    fwrite(STDOUT, "pid=" . getmypid() . " t=" . microtime(true) . " Cannot obtain task lock\n");
    exit(130);
}
fwrite(STDOUT, "pid=" . getmypid() . " t=" . microtime(true) . " acquired both locks after " . (microtime(true) - $t0) . "s\n");

$task->set_lock($lock);
$cronlock->release();

\core\cron::run_inner_scheduled_task($task);
fwrite(STDOUT, "pid=" . getmypid() . " t=" . microtime(true) . " run_inner_scheduled_task finished\n");
