<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/gradelib.php');
\core\session\manager::set_user(get_admin());
foreach ([46 => 'cl34t editingteacher', 49 => 'cl34p teacher (nao avaliado)', 50 => 'cl34n sem matricula'] as $uid => $label) {
    $r = grade_update('mod/assign', 3, 'mod', 'assign', 1, 0, ['userid' => $uid, 'rawgrade' => 55.0]);
    echo "$label: grade_update=" . var_export($r, true) . "\n";
}
