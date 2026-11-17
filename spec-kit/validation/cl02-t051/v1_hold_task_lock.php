<?php
// Segura o lock nativo da scheduled task process_queue (mesmo recurso, mesmo backend que
// admin/cli/scheduled_task.php usa: core\lock\lock_config::get_lock_factory('cron')) por N segundos,
// para forcar o caminho "Cannot obtain task lock" (timeout de 10s) do executor nativo.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$seconds = (int) ($argv[1] ?? 15);
$resource = '\\local_assistente\\task\\process_queue';
$factory = \core\lock\lock_config::get_lock_factory('cron');
$lock = $factory->get_lock($resource, 10);
if (!$lock) {
    echo "hold: could not acquire\n";
    exit(1);
}
echo "hold: pid=" . getmypid() . " acquired '$resource' at " . microtime(true) . ", holding {$seconds}s\n";
sleep($seconds);
$lock->release();
echo "hold: pid=" . getmypid() . " released at " . microtime(true) . "\n";
