<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
foreach ((new \local_assistente\infrastructure\moodle\gradebook_reader())->get_items(3) as $i) { echo json_encode([$i->cmid, $i->modname, $i->item_number, $i->grade_type, $i->grade_min, $i->grade_max]) . "\n"; }
