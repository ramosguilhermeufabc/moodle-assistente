<?php
// Roda um ciclo normal da fila (queue_processor::run_cycle), chamado diretamente (mesmo caminho que
// process_queue::execute delega), sem passar pelo executor de scheduled tasks.
//
// ATENCAO: run_cycle() nao filtra por curso. Ele processa QUALQUER evento pendente ou em
// processamento de QUALQUER curso habilitado no site Moodle em que este script for executado, nao
// so o evento de teste informado em $eventid (que serve apenas para o relatorio ao final). Rode
// isto apenas num site descartavel ou num momento em que nao haja eventos reais pendentes que
// nao devam ser processados agora.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
use local_assistente\application\queue\queue_processor;
use local_assistente\infrastructure\persistence\event_repository;
$eventid = (int) ($argv[1] ?? 0);
echo "cycle: pid=" . getmypid() . " starting at " . microtime(true) . "\n";
$report = (new queue_processor())->run_cycle();
echo $report->summary() . "\n";
if ($eventid) {
    $outcome = null;
    foreach ($report->events() as $id) {
        if ($id === $eventid) {
            foreach (\local_assistente\application\queue\cycle_outcome::cases() as $c) {
                if (in_array($eventid, $report->events($c), true)) { $outcome = $c->value; }
            }
        }
    }
    $event = (new event_repository())->find($eventid);
    echo "event {$eventid}: cycle_outcome=" . ($outcome ?? 'not_touched') . " final_status=" . $event->status . "\n";
}
echo "cycle: pid=" . getmypid() . " finished at " . microtime(true) . "\n";
