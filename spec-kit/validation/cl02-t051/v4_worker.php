<?php
// Worker que reproduz process_locked() de queue_processor para UM evento: begin_attempt real, chama o
// handler REAL (frequency_handler, sem LLM) e so entao tenta complete_attempt. Nao altera nenhuma classe
// do plugin; usa so a API publica (event_repository, unit_of_work, frequency_handler), exatamente como a
// producao usa. Antes de chamar complete_attempt, dorme alguns segundos (so no script de teste) para dar
// tempo ao locker externo de prender a linha da tentativa; encurta o innodb_lock_wait_timeout so nesta
// sessao (variavel de sessao, nao de schema) para o teste nao esperar os 50s padrao.
//
// ATENCAO: este script executa o handler REAL (frequency_handler) sobre o evento informado, nao
// um substituto. Se $eventid apontar para um evento de produção, o handler roda de verdade e grava
// um registro em local_assistente_analysis — um registro que o repositório trata como IMUTAVEL
// (nenhum metodo do plugin altera ou apaga uma analise). Esse efeito NAO E desfeito pela limpeza
// deste diretório e não é reversível por este script. Use somente sobre um evento de um curso
// descartavel, criado para o teste (ver v4_prep.php).
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
use local_assistente\application\frequency\frequency_handler;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\unit_of_work;

global $DB;
$eventid = (int) $argv[1];
$waitforlocker = (int) ($argv[2] ?? 4);
$repo = new event_repository();
$uow = new unit_of_work();

function out(array $data): void {
    echo json_encode($data + ['pid' => getmypid(), 'at' => microtime(true)]) . "\n";
    fflush(STDOUT);
}

try {
    $uow->run_locked(unit_of_work::event_resource($eventid), function () use ($repo, $uow, $eventid, $waitforlocker) {
        $attempt = $uow->run(fn() => $repo->begin_attempt($eventid, time()));
        out(['state' => 'attempt_started', 'event' => $eventid, 'attempt' => (int) $attempt->id]);

        $event = $repo->find($eventid);
        $processing = $repo->find_processing($eventid);
        $handler = new frequency_handler();
        $result = $handler->handle($event, $processing);
        out(['state' => 'handler_done', 'success' => $result->success, 'response' => $result->response()]);

        out(['state' => 'waiting_for_locker', 'seconds' => $waitforlocker]);
        sleep($waitforlocker);

        global $DB;
        $DB->execute('SET SESSION innodb_lock_wait_timeout = 2');
        out(['state' => 'calling_complete_attempt']);
        try {
            $uow->run(fn() => $repo->complete_attempt((int) $attempt->id, time(), $result->response()));
            out(['state' => 'complete_attempt_succeeded_unexpectedly']);
        } catch (\Throwable $e) {
            out(['state' => 'complete_attempt_failed', 'exception' => get_class($e), 'message' => substr($e->getMessage(), 0, 200)]);
            throw $e;
        }
    });
    out(['state' => 'run_locked_returned_normally']);
} catch (\Throwable $e) {
    out(['state' => 'run_locked_threw', 'exception' => get_class($e)]);
}
out(['state' => 'worker_exiting_normally']);
