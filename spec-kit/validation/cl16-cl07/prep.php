<?php
// Prepara o curso controlado de CL-16/CL-07 (T099): curso CL16CL07 com o assistente habilitado, dois estudantes, seção comum (1), subseção
// delegada com um fórum dentro, uma atividade com nota, uma página que fica em remoção e a seção/módulo de OUTRO curso lidos do curso 5.
// Não cria nada no curso 5. Imprime os ids em JSON. Rode uma vez.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$now = time();
$course = $gen->create_course(['fullname' => 'CL16 CL07 validacao sintese', 'shortname' => 'CL16CL07', 'numsections' => 3, 'enddate' => $now - 500]);
$ids = ['course' => (int) $course->id];
foreach (['cl16s1', 'cl16s2'] as $name) {
    $u = $gen->create_user(['username' => $name, 'firstname' => $name, 'lastname' => 'Validacao', 'email' => "$name@example.com"]);
    $gen->enrol_user($u->id, $course->id, 'student');
    $ids[$name] = (int) $u->id;
}
(new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
$assign = $gen->create_module('assign', ['course' => $course->id, 'name' => 'Tarefa CL16', 'duedate' => $now - 1000, 'grade' => 100], ['section' => 1]);
$forum = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Forum comum CL16'], ['section' => 1]);
$sub = $gen->create_module('subsection', ['course' => $course->id, 'name' => 'Subsecao CL16'], ['section' => 2]);
$delegated = $DB->get_record('course_sections', ['course' => $course->id, 'component' => 'mod_subsection', 'itemid' => $sub->id], '*', MUST_EXIST);
$inner = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Forum na subsecao CL16'], ['section' => $delegated->section]);
$page = $gen->create_module('page', ['course' => $course->id, 'name' => 'Pagina em remocao CL16'], ['section' => 3]);
$ids += [
    'assign_cmid' => (int) $assign->cmid, 'assign_inst' => (int) $assign->id, 'forum_cmid' => (int) $forum->cmid, 'forum_inst' => (int) $forum->id,
    'subsection_cmid' => (int) $sub->cmid, 'delegated_section_id' => (int) $delegated->id, 'inner_forum_cmid' => (int) $inner->cmid,
    'page_cmid' => (int) $page->cmid,
    'section1_id' => (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]),
    'section2_id' => (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]),
    'section3_id' => (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 3]),
    'assign_item' => (int) $DB->get_field('grade_items', 'id', ['courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $assign->id, 'itemnumber' => 0]),
    'other_course_cmid' => 7,
    'other_course_section_id' => (int) $DB->get_field('course_sections', 'id', ['course' => 5, 'section' => 1]),
];
echo json_encode($ids) . "\n";
