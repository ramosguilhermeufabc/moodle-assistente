<?php
// Segura a trava do relatorio do curso CL39UI por N segundos (outro processo), como uma execucao lenta do despachante.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
use local_assistente\infrastructure\persistence\report_repository;
use local_assistente\infrastructure\persistence\unit_of_work;
$course = $DB->get_field('course', 'id', ['shortname' => 'CL39UI'], MUST_EXIST);
$report = (new report_repository())->find_by_course((int) $course);
$seconds = (int) ($argv[1] ?? 8);
(new unit_of_work())->run_locked(unit_of_work::report_resource((int) $report->id), function () use ($seconds) {
    echo "hold: lock held by pid " . getmypid() . " for {$seconds}s\n";
    sleep($seconds);
});
echo "hold: lock released\n";
