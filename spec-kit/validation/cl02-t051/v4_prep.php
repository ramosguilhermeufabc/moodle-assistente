<?php
// Prepara curso descartavel CL44V4 com o assistente habilitado, um estudante matriculado e uma tarefa, e cria um
// evento pending (reference_date_reached, frequency_handler), para a validacao da CL-44.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\event_repository;
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$now = time();
$course = $gen->create_course(['fullname' => 'CL44 V4 validacao de persistencia', 'shortname' => 'CL44V4', 'numsections' => 1]);
(new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
$assign = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Tarefa CL44V4', 'duedate' => $now - 3600], ['section' => 1]);
$student = $gen->create_user(['username' => 'cl44v4student']);
$gen->enrol_user($student->id, $course->id, 'student');
$event = (new event_repository())->create_event(
    (int) $course->id,
    event_type::REFERENCE_DATE_REACHED,
    ['cmid' => (int) $assign->cmid, 'reference_date' => $now - 3600],
    $now
);
echo json_encode([
    'course' => (int) $course->id,
    'cmid' => (int) $assign->cmid,
    'student' => (int) $student->id,
    'event' => (int) $event->id,
]) . "\n";
