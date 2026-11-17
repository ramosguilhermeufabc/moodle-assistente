<?php
// Prepara o curso de CL-36: professor cl36t, estudantes cl36s1 e cl36s2, assistente habilitado, conclusao ligada,
// tarefa com entrega futura, pagina e arquivo com conclusao manual, forum e um recurso. Requer CL33_PASSWORD.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/completionlib.php');
\core\session\manager::set_user(get_admin());
$pw = getenv('CL33_PASSWORD') ?: throw new RuntimeException('defina CL33_PASSWORD');
$CFG->enablecompletion = 1;
$gen = new testing_data_generator();
$now = time();
$course = $gen->create_course(['fullname' => 'CL36 validacao UI', 'shortname' => 'CL36UI', 'numsections' => 2,
    'enablecompletion' => 1, 'enddate' => $now + 30 * DAYSECS]);
$ids = ['course' => $course->id];
foreach (['cl36t' => 'editingteacher', 'cl36s1' => 'student', 'cl36s2' => 'student'] as $name => $role) {
    $u = $gen->create_user(['username' => $name, 'password' => $pw, 'firstname' => $name, 'lastname' => 'Validacao', 'email' => "$name@example.com"]);
    $gen->enrol_user($u->id, $course->id, $role);
    $ids[$name] = $u->id;
}
(new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
$man = ['completion' => COMPLETION_TRACKING_MANUAL];
$a = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Tarefa CL36', 'duedate' => $now + 7 * DAYSECS,
    'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => 0, 'submissiondrafts' => 0, 'grade' => 100] + $man, ['section' => 1]);
$d = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Tarefa rascunho CL36', 'duedate' => $now + 7 * DAYSECS,
    'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => 0, 'submissiondrafts' => 1, 'grade' => 100], ['section' => 1]);
$p = $gen->create_module('page', ['course' => $course->id, 'name' => 'Pagina CL36'] + $man, ['section' => 1]);
$r = $gen->create_module('resource', ['course' => $course->id, 'name' => 'Arquivo CL36'] + $man, ['section' => 2]);
$f = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Forum CL36', 'type' => 'general'], ['section' => 2]);
$ids += ['assign_cmid' => $a->cmid, 'assign_inst' => $a->id, 'draft_cmid' => $d->cmid, 'draft_inst' => $d->id, 'page_cmid' => $p->cmid,
    'file_cmid' => $r->cmid, 'forum_cmid' => $f->cmid, 'forum_inst' => $f->id];
$ids['assign_item'] = $DB->get_field('grade_items', 'id', ['courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $a->id, 'itemnumber' => 0]);
echo json_encode($ids) . "\n";
