<?php
// Prepara o curso de CL-39 (T117): CL39UI, assistente habilitado, tarefa (entrega), questionario (encerramento) e forum (entrega) com datas proprias
// diferentes e futuras. Nao cria usuarios nem registra a tarefa agendada do despachante (sem upgrade do plugin).
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$now = time();
$course = $gen->create_course(['fullname' => 'CL39 validacao do despachante', 'shortname' => 'CL39UI', 'numsections' => 1]);
(new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
$assign = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Tarefa CL39', 'duedate' => $now + 35 * DAYSECS], ['section' => 1]);
$quiz = $gen->create_module('quiz', ['course' => $course->id, 'name' => 'Questionario CL39', 'timeclose' => $now + 36 * DAYSECS], ['section' => 1]);
$forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Forum CL39', 'type' => 'general', 'duedate' => $now + 33 * DAYSECS], ['section' => 1]);
echo json_encode(['course' => $course->id, 'assign' => $assign->id, 'quiz' => $quiz->id, 'forum' => $forum->id]) . "\n";
