<?php
// T099: dá nota ao estudante na tarefa do curso de validação (observers reais disparam). Uso: php grade.php ESTUDANTE_ID NOTA
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/gradelib.php');
\core\session\manager::set_user(get_admin());
[, $uid, $grade] = $argv;
$assign = $DB->get_record('assign', ['id' => 6], '*', MUST_EXIST);
$r = grade_update('mod/assign', 6, 'mod', 'assign', $assign->id, 0, ['userid' => (int) $uid, 'rawgrade' => (float) $grade]);
echo "grade_update -> $r\n";
