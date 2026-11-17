<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/gradelib.php');
\core\session\manager::set_user(get_admin());
$pw = getenv('CL33_PASSWORD') ?: throw new RuntimeException('defina CL33_PASSWORD');
$gen = new testing_data_generator();
$course = $gen->create_course(['fullname' => 'CL34 validacao UI', 'shortname' => 'CL34UI']);
$ids = ['course' => $course->id];
foreach (['cl34t' => 'editingteacher', 'cl34s1' => 'student', 'cl34s2' => 'student', 'cl34p' => 'teacher'] as $name => $role) {
    $u = $gen->create_user(['username' => $name, 'password' => $pw, 'firstname' => $name, 'lastname' => 'Validacao', 'email' => "$name@example.com"]);
    $gen->enrol_user($u->id, $course->id, $role);
    $ids[$name] = $u->id;
}
$out = $gen->create_user(['username' => 'cl34n', 'password' => $pw, 'firstname' => 'cl34n', 'lastname' => 'SemMatricula', 'email' => 'cl34n@example.com']);
$ids['cl34n'] = $out->id;
(new \local_assistente\infrastructure\persistence\config_repository())->create($course->id, true);
$mk = function (string $name, array $details) use ($gen, $course) {
    $a = $gen->create_module('assign', ['course' => $course->id, 'name' => $name]);
    grade_update('mod/assign', $course->id, 'mod', 'assign', $a->id, 0, null, ['itemname' => $name] + $details);
    return $a;
};
$a = $mk('A 0-100', ['gradetype' => GRADE_TYPE_VALUE, 'grademin' => 0, 'grademax' => 100]);
$b = $mk('B 5-10', ['gradetype' => GRADE_TYPE_VALUE, 'grademin' => 5, 'grademax' => 10]);
$scale = $gen->create_scale(['scale' => 'Ruim,Regular,Bom,Otimo', 'courseid' => $course->id]);
$s = $mk('S escala', ['gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $scale->id]);
$ids += ['a_cmid' => $a->cmid, 'a_inst' => $a->id, 'b_cmid' => $b->cmid, 'b_inst' => $b->id, 's_cmid' => $s->cmid, 's_inst' => $s->id];
$f = $gen->create_module('forum', ['course' => $course->id, 'name' => 'Forum duas notas', 'assessed' => 1, 'scale' => 100, 'grade_forum' => 100]);
$ids += ['f_cmid' => $f->cmid, 'f_inst' => $f->id];
echo json_encode($ids) . "\n";
