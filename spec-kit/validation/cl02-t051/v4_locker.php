<?php
// Segura um lock de linha nativo do InnoDB (SELECT ... FOR UPDATE, sem commit) sobre a linha de
// local_assistente_processing_attempt dada, por N segundos. Mecanismo de banco padrao, externo ao
// plugin: nao toca em codigo de producao nem em schema.
//
// ATENCAO: este script trava, de fato, a linha indicada por $attemptid pelo tempo informado. NAO
// use o id de uma tentativa real (de um evento de produção em andamento): qualquer processo da
// fila que precise dessa linha (inclusive um ciclo normal processando esse evento) vai esperar o
// tempo todo do hold, ou falhar por timeout, como nos testes V4. Use somente o id retornado pelo
// worker de teste (v4_worker.php) sobre um evento de um curso descartavel.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
global $DB;
$attemptid = (int) $argv[1];
$seconds = (int) ($argv[2] ?? 15);
$transaction = $DB->start_delegated_transaction();
$row = $DB->get_record_sql(
    "SELECT id FROM {local_assistente_processing_attempt} WHERE id = :id FOR UPDATE",
    ['id' => $attemptid]
);
if (!$row) {
    // Desfaz a transacao delegada explicitamente antes de encerrar; rollback() sempre relanca a
    // excecao recebida (comportamento do moodle_database), por isso o try/catch so para suprimir
    // essa relancada e terminar de forma limpa, sem deixar a transacao aberta.
    try {
        $transaction->rollback(new \RuntimeException('attempt not found: ' . $attemptid));
    } catch (\Throwable $ignored) {
        // Esperado: rollback() sempre relanca. O rollback em si ja foi feito.
    }
    echo json_encode(['error' => 'attempt not found']) . "\n";
    exit(1);
}
echo json_encode(['pid' => getmypid(), 'state' => 'row_locked', 'attempt' => $attemptid, 'at' => microtime(true)]) . "\n";
fflush(STDOUT);
sleep($seconds);
$transaction->allow_commit();
echo json_encode(['pid' => getmypid(), 'state' => 'row_released', 'at' => microtime(true)]) . "\n";
