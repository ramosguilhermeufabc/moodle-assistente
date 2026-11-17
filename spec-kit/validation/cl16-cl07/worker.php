<?php
// CL-07 (T099): trabalhador de concorrência. Cada processo PHP é uma execução independente contra o banco real. Todos esperam o mesmo
// instante de partida e começam juntos.
//   php worker.php MODO SECTION_ID RODADAS PARTIDA_EPOCH_S [ROTULO]
//   MODO save    : synthesis_repository::save (transação + Lock API do par curso/seção), fingerprint novo em cada rodada
//   MODO nolock  : insere a versão max+1 SEM lock e SEM transação (simula um escritor que pulou o lock); o índice único é a barreira
//   MODO handler : synthesis_handler completo com uma porta LLM lenta (4 s) sobre um evento próprio da seção
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
[, $mode, $section, $rounds, $start] = $argv;
$label = $argv[5] ?? getmypid();
$section = (int) $section;
$course = 6;
$events = new \local_assistente\infrastructure\persistence\event_repository();
$repository = new \local_assistente\infrastructure\persistence\synthesis_repository();
$counts = ['ok' => 0];
$processing = function () use ($events, $section, $course) {
    $event = $events->create_event($course, \local_assistente\domain\event\event_type::SYNTHESIS_REQUESTED, ['section_id' => $section], time());
    $attempt = $events->begin_attempt((int) $event->id, time());
    return [$events->find_processing((int) $event->id), $attempt, $event];
};
while (microtime(true) < (float) $start) {
    usleep(1000);
}
$t0 = microtime(true);
if ($mode === 'handler') {
    $slow = new class implements \local_assistente\infrastructure\llm\llm_port {
        #[\Override]
        public function evaluate(\local_assistente\domain\llm\llm_context $context, string $instruction): \local_assistente\infrastructure\llm\structured_result {
            sleep(4);
            return (new \local_assistente\infrastructure\llm\fake_adapter())->evaluate($context, $instruction);
        }
    };
    [$p, $a, $e] = $processing();
    $result = (new \local_assistente\application\synthesis\synthesis_handler($slow))->handle($e, $p);
    $events->complete_attempt((int) $a->id, time(), (string) $result->message);
    echo "worker {$label}: success=" . var_export($result->success, true) . " message=" . $result->message . sprintf(" (%.1f s)\n", microtime(true) - $t0);
    exit(0);
}
for ($i = 0; $i < (int) $rounds; $i++) {
    [$p, $a] = $processing();
    $fingerprint = hash('sha256', $label . ':' . $i . ':' . microtime(true) . ':' . random_int(1, PHP_INT_MAX));
    try {
        if ($mode === 'save') {
            $repository->save($course, $section, (int) $p->id, "T099 {$label} {$i}", $fingerprint);
        } else {
            $next = 1 + (int) $DB->get_field_select('local_assistente_pedagogical_synthesis', 'MAX(version)', 'course_id = ? AND section_id = ?', [$course, $section]);
            $analysis = (new \local_assistente\infrastructure\persistence\table_repository('local_assistente_analysis'))->insert((object) [
                'course_id' => $course, 'processing_id' => (int) $p->id, 'analysis_type' => 'section_synthesis', 'cmid' => null, 'summary' => null, 'created_at' => time(),
            ]);
            (new \local_assistente\infrastructure\persistence\table_repository('local_assistente_pedagogical_synthesis'))->insert((object) [
                'analysis_id' => $analysis, 'course_id' => $course, 'section_id' => $section, 'version' => $next,
                'content' => "T099 nolock {$label} {$i}", 'input_fingerprint' => $fingerprint, 'created_at' => time(),
            ]);
        }
        $counts['ok']++;
        $events->complete_attempt((int) $a->id, time(), 'T099 concurrency');
    } catch (\Throwable $e) {
        $class = (new ReflectionClass($e))->getShortName();
        $counts[$class] = ($counts[$class] ?? 0) + 1;
        $events->complete_attempt((int) $a->id, time(), 'T099 concurrency: ' . $class);
    }
}
echo "worker {$label}: " . json_encode($counts) . sprintf(" (%.1f s)\n", microtime(true) - $t0);
