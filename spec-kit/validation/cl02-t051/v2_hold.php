<?php
// Segura o lock do evento (unit_of_work::event_resource, mesmo recurso e mesma fabrica que a fila usa) por N
// segundos, como outro processo processando esse evento.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
use local_assistente\infrastructure\persistence\unit_of_work;
$eventid = (int) $argv[1];
$seconds = (int) ($argv[2] ?? 10);
(new unit_of_work())->run_locked(unit_of_work::event_resource($eventid), function () use ($seconds, $eventid) {
    echo "hold: pid=" . getmypid() . " locked event_{$eventid} at " . microtime(true) . ", holding {$seconds}s\n";
    sleep($seconds);
});
echo "hold: pid=" . getmypid() . " released at " . microtime(true) . "\n";
