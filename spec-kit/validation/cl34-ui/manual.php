<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/gradelib.php');
\core\session\manager::set_user(get_admin());
$i = new grade_item(['courseid' => 3, 'itemtype' => 'manual', 'itemname' => 'Manual descartavel', 'grademax' => 10, 'gradetype' => GRADE_TYPE_VALUE], false);
$i->insert();
echo $i->id . "\n";
