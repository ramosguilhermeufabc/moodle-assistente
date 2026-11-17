<?php
// Executa a fila real com um adaptador simulado que guarda o contexto enviado à LLM e o imprime.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
\core\session\manager::set_user(get_admin());
use local_assistente\domain\event\event_type;
$llm = new \local_assistente\infrastructure\llm\fake_adapter();
$handler = new \local_assistente\application\forum\forum_analysis_handler(new \local_assistente\application\llm\guarded_llm_port($llm));
$registry = new \local_assistente\application\queue\handler_registry();
foreach ([event_type::FORUM_POST_CREATED, event_type::FORUM_POST_UPDATED, event_type::FORUM_POST_DELETED, event_type::FORUM_DISCUSSION_DELETED] as $t) {
    $registry->register($t, $handler);
}
$report = (new \local_assistente\application\queue\queue_processor(null, $registry))->run_cycle();
echo $report->summary() . "\n";
foreach ($llm->requests as $i => $r) {
    echo "--- request $i ---\n" . json_encode($r['context']->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
