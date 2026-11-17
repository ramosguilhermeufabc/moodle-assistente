<?php
// CL-16 (T099): resolucao de secao no Moodle real, pelas APIs reais do plugin e do Moodle. Requer os ids do prep.php em argv[1] (JSON).
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
\core\session\manager::set_user(get_admin());
$ids = json_decode($argv[1], true);
$sections = new \local_assistente\infrastructure\moodle\course_section_reader();
$course = $ids['course'];
$line = function (string $case, string $what, $got) {
    printf("%-42s %-34s %s\n", $case, $what, is_bool($got) ? var_export($got, true) : $got);
};
$resolve = function (string $case, int $courseid, int $cmid) use ($sections, $line) {
    $r = $sections->resolve_module_section($courseid, $cmid);
    $line($case, "resolve_module_section({$courseid},{$cmid})", $r->is_resolved() ? 'resolved section ' . $r->sectionid : $r->status->value);
    return $r;
};
global $DB;
echo "--- estado do modulo e da secao no banco\n";
foreach (['assign_cmid', 'inner_forum_cmid', 'subsection_cmid', 'page_cmid'] as $k) {
    $cm = $DB->get_record('course_modules', ['id' => $ids[$k]], 'id,section,deletioninprogress');
    $sec = $DB->get_record('course_sections', ['id' => $cm->section], 'id,section,component,itemid');
    printf("%-18s cm %d -> course_sections.id %d (number %d, component '%s', itemid %s), deletioninprogress %d\n", $k, $cm->id, $sec->id, $sec->section, $sec->component ?? '', $sec->itemid ?? 'null', $cm->deletioninprogress);
}
echo "--- CL-16\n";
$resolve('1 secao comum (tarefa)', $course, $ids['assign_cmid']);
$resolve('1 secao comum (forum)', $course, $ids['forum_cmid']);
$resolve('2 secao delegada (forum na subsecao)', $course, $ids['inner_forum_cmid']);
$resolve('2b o proprio modulo subsecao', $course, $ids['subsection_cmid']);
$before = $resolve('3 modulo ANTES da remocao (pagina)', $course, $ids['page_cmid']);
course_delete_module($ids['page_cmid'], true);
$cm = $DB->get_record('course_modules', ['id' => $ids['page_cmid']], 'id,deletioninprogress');
$line('3 apos course_delete_module(async)', 'deletioninprogress no banco', $cm ? (string) $cm->deletioninprogress : 'registro apagado');
$line('3 apos course_delete_module(async)', 'tarefa adhoc de remocao na fila', $DB->count_records_select('task_adhoc', "classname LIKE '%course_delete_modules%'"));
get_fast_modinfo($course, 0, true);
$resolve('3 modulo EM REMOCAO (pagina)', $course, $ids['page_cmid']);
$resolve('4 modulo de OUTRO curso', $course, $ids['other_course_cmid']);
$resolve('4b modulo inexistente', $course, 987654);
$v = fn(string $case, int $courseid, int $sid) => $line($case, "validate_section({$courseid},{$sid})", ($r = $sections->validate_section($courseid, $sid))->is_resolved() ? 'resolved ' . $r->sectionid : $r->status->value);
$v('5 secao comum do curso', $course, $ids['section1_id']);
$v('5 secao delegada do curso', $course, $ids['delegated_section_id']);
$v('6 secao de OUTRO curso', $course, $ids['other_course_section_id']);
$v('6b secao inexistente', $course, 987654);
echo "--- o gatilho (synthesis_trigger) sobre os mesmos casos\n";
$trigger = new \local_assistente\application\synthesis\synthesis_trigger();
foreach ([['comum', $ids['assign_cmid']], ['delegada', $ids['inner_forum_cmid']], ['em remocao', $ids['page_cmid']], ['outro curso', $ids['other_course_cmid']]] as [$name, $cmid]) {
    try {
        $r = $trigger->request_for_module($course, $cmid);
        $line("trigger {$name}", "request_for_module({$course},{$cmid})", $r->status->value . ' event ' . $r->event->id . ' payload ' . $r->event->payload);
    } catch (\RuntimeException $e) {
        $line("trigger {$name}", "request_for_module({$course},{$cmid})", 'RuntimeException: ' . $e->getMessage());
    }
}
