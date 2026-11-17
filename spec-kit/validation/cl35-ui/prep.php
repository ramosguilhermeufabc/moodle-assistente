<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
\core\session\manager::set_user(get_admin());
$pw = getenv('CL33_PASSWORD') ?: throw new RuntimeException('defina CL33_PASSWORD');
$gen = new testing_data_generator();
$course = $gen->create_course(['fullname' => 'CL35 validacao UI', 'shortname' => 'CL35UI', 'numsections' => 2]);
$ids = ['course' => $course->id];
foreach (['cl35t' => 'editingteacher', 'cl35s1' => 'student', 'cl35s2' => 'student'] as $name => $role) {
    $u = $gen->create_user(['username' => $name, 'password' => $pw, 'firstname' => $name, 'lastname' => 'Validacao', 'email' => "$name@example.com"]);
    $gen->enrol_user($u->id, $course->id, $role);
    $ids[$name] = $u->id;
}
(new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
$f = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Forum CL35', 'type' => 'general'], ['section' => 1]);
$ids += ['f_cmid' => $f->cmid, 'f_inst' => $f->id];
echo json_encode($ids) . "\n";
