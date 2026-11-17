<?php
// Prepara um curso descartavel (CL02T051) com o assistente habilitado e cria um evento pending do tipo
// reference_date_reached (frequency_handler, sem LLM), para o teste de contencao de lock por evento (V2).
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\event_repository;
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$now = time();
$course = $gen->create_course(['fullname' => 'CL02 T051 validacao de lock', 'shortname' => 'CL02T051', 'numsections' => 1]);
(new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
$assign = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Tarefa CL02T051', 'duedate' => $now - 3600], ['section' => 1]);
$event = (new event_repository())->create_event(
    (int) $course->id,
    event_type::REFERENCE_DATE_REACHED,
    ['cmid' => (int) $assign->cmid, 'reference_date' => $now - 3600],
    $now
);
echo json_encode(['course' => (int) $course->id, 'cmid' => (int) $assign->cmid, 'event' => (int) $event->id]) . "\n";
