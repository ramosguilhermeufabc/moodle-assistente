<?php
// Worker externo que reproduz exatamente a sequencia de queue_processor::process_event/process_locked para
// UM evento dado (mesmo lock, mesma chamada a begin_attempt que a producao usa), mas para antes de chamar o
// handler e dorme no lugar dele, simulando "ainda dentro do handler". Nao altera nenhuma classe do plugin;
// so chama a API publica de event_repository e unit_of_work, como queue_processor ja faz.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

$eventid = (int) $argv[1];
$seconds = (int) ($argv[2] ?? 60);
$repo = new event_repository();
$uow = new unit_of_work();

$uow->run_locked(unit_of_work::event_resource($eventid), function () use ($repo, $uow, $eventid, $seconds) {
    $attempt = $uow->run(fn() => $repo->begin_attempt($eventid, time()));
    if ($attempt === null) {
        echo json_encode(['error' => 'exhausted']) . "\n";
        return;
    }
    echo json_encode([
        'pid' => getmypid(),
        'event' => $eventid,
        'attempt' => (int) $attempt->id,
        'attempt_number' => (int) $attempt->attempt_number,
        'state' => 'processing_simulated_handler',
        'at' => microtime(true),
    ]) . "\n";
    fflush(STDOUT);
    // "Ainda dentro do handler": nenhuma transacao aberta aqui, igual ao processamento real.
    sleep($seconds);
    echo json_encode(['pid' => getmypid(), 'event' => $eventid, 'state' => 'handler_would_finish_now']) . "\n";
});
