<?php
// T047 (CL-02): trabalhador de concorrência. Chama o serviço de publicação como o observer faria, em um processo PHP independente.
//   php worker.php COURSE_ID CMID PARTIDA_EPOCH_S ROTULO
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
[, $course, $cmid, $start, $label] = $argv;
while (microtime(true) < (float) $start) { usleep(500); }
try {
    $r = (new \local_assistente\application\quiz\quiz_publication_service())->handle_module_event((int) $course, (int) $cmid);
    echo "worker $label: " . ($r === null ? 'null' : json_encode(['status' => $r->status->value ?? (string) $r->status, 'event' => $r->event->id ?? null])) . "\n";
} catch (\Throwable $e) {
    echo "worker $label: EXCECAO " . get_class($e) . ': ' . $e->getMessage() . "\n";
}
