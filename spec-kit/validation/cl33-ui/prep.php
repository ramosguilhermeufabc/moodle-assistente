<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/phpunit/classes/util.php');
\core\session\manager::set_user(get_admin());
$pw = getenv('CL33_PASSWORD') ?: throw new RuntimeException('defina CL33_PASSWORD');
$gen = new testing_data_generator();
$course = $gen->create_course(['fullname' => 'CL33 validacao UI', 'shortname' => 'CL33UI', 'numsections' => 2]);
$users = [];
foreach (['cl33t' => 'editingteacher', 'cl33s1' => 'student', 'cl33s2' => 'student'] as $name => $role) {
    $u = $gen->create_user(['username' => $name, 'password' => $pw, 'firstname' => $name, 'lastname' => 'Validacao', 'email' => $name . '@example.com']);
    $gen->enrol_user($u->id, $course->id, $role);
    $users[$name] = $u->id;
}
(new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
$quiz = $gen->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id, 'name' => 'Prova CL33', 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => 3, 'preferredbehaviour' => 'deferredfeedback', 'attempts' => 0]);
$qg = $gen->get_plugin_generator('core_question');
$cat = $qg->create_question_category(['contextid' => context_module::instance($quiz->cmid)->id]);
foreach (['truefalse', 'numerical', 'essay'] as $t) {
    $q = $qg->create_question($t, null, ['category' => $cat->id]);
    quiz_add_quiz_question($q->id, $quiz);
}
echo json_encode(['course' => $course->id, 'cmid' => $quiz->cmid, 'quiz' => $quiz->id, 'users' => $users]) . "\n";
