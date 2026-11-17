<?php
// Fronteira temporal da DEC-54 com o relogio e o banco reais: fixa a entrega da tarefa (cm 7) em um segundo futuro D, executa a tarefa
// agendada detect_reference_dates exatamente em D (nada deve entrar na fila) e em D+1 (reference_date_reached deve entrar).
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$inst = $DB->get_field('course_modules', 'instance', ['id' => 7]);
$d = time() + 3;
$DB->set_field('assign', 'duedate', $d, ['id' => $inst]);
$count = fn() => $DB->count_records('local_assistente_event', ['event_type' => 'reference_date_reached', 'course_id' => 5]);
$before = $count();
while (time() < $d) {
    usleep(20000);
}
$at = time();
(new \local_assistente\task\detect_reference_dates())->execute();
$atcount = $count();
while (time() <= $d) {
    usleep(20000);
}
$after = time();
(new \local_assistente\task\detect_reference_dates())->execute();
echo json_encode(['due' => $d, 'run_at' => $at, 'events_before' => $before, 'events_at_due' => $atcount, 'run_after' => $after, 'events_after' => $count()]) . "\n";
