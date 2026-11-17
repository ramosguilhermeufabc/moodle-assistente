<?php
// Executa o despachante do plugin sobre o curso CL39UI, e so ele, com o Moodle, o banco, a Lock API (mysql_lock_factory) e o fuso do site reais.
// O instante "agora" e informado, para simular a passagem do tempo; o fuso e o do site (core_date::get_server_timezone()).
// Uso: php run.php <modo> [agora]. Modos: first (a, d), moved (d), resume (b), show.
// A tarefa agendada em si NAO e usada: o site real tem cursos habilitados (5 e 6) que nao podem ser tocados e o despachante varre todos.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
use local_assistente\application\report\report_generator;
use local_assistente\infrastructure\persistence\report_repository;

$mode = $argv[1] ?? 'show';
$course = $DB->get_field('course', 'id', ['shortname' => 'CL39UI'], MUST_EXIST);
$reports = new report_repository();
$generator = new report_generator();
$process = new ReflectionMethod($generator, 'process');
$run = fn(int $now): bool => $process->invoke($generator, (int) $course, $now);
$report = fn() => $reports->find_by_course((int) $course);
$fmt = fn($t) => $t === null ? null : userdate((int) $t, '%Y-%m-%d %H:%M:%S', \core_date::get_server_timezone()) . " ({$t})";
$versions = fn() => $report() ? count($reports->find_history((int) $report()->id)) : 0;
$dates = function () use ($DB, $course) {
    $a = (int) $DB->get_field('assign', 'duedate', ['course' => $course]);
    $q = (int) $DB->get_field('quiz', 'timeclose', ['course' => $course]);
    $f = (int) $DB->get_field('forum', 'duedate', ['course' => $course]);
    return ['assign.duedate' => $a, 'quiz.timeclose' => $q, 'forum.duedate' => $f];
};
$out = ['mode' => $mode, 'site_timezone' => \core_date::get_server_timezone(), 'php_default_tz' => date_default_timezone_get()];

if ($mode === 'first') {
    $now = (int) ($argv[2] ?? time());
    $before = $versions();
    $out['generated'] = $run($now);
    $r = $report();
    $max = max($dates());
    $d = usergetdate($max, \core_date::get_server_timezone());
    $expected = make_timestamp($d['year'], $d['mon'], $d['mday'] + 1, 0, 0, 0, \core_date::get_server_timezone());
    $out += [
        'now' => $fmt($now), 'dates' => array_map($fmt, $dates()), 'versions_before' => $before, 'versions_after' => $versions(),
        'final_activity_date' => $fmt($r->final_activity_date), 'farthest_own_date' => $fmt($max),
        'final_activity_matches_farthest' => (int) $r->final_activity_date === $max,
        'final_update_at' => $fmt($r->final_update_at), 'expected_midnight_next_day_site_tz' => $fmt($expected),
        'final_update_matches' => (int) $r->final_update_at === $expected, 'next_update_at' => $fmt($r->next_update_at),
        'next_is_now_plus_72h' => (int) $r->next_update_at === $now + 72 * 3600,
    ];
} else if ($mode === 'moved') {
    // (d) the assignment becomes the farthest date; nothing is due, so no version, but the stored final date follows.
    $now = (int) ($argv[2] ?? time());
    $new = max($dates()) + 2 * DAYSECS + 3600;
    $DB->set_field('assign', 'duedate', $new, ['course' => $course]);
    $before = $versions();
    $out['generated'] = $run($now);
    $r = $report();
    $d = usergetdate($new, \core_date::get_server_timezone());
    $out += [
        'dates' => array_map($fmt, $dates()), 'versions_before' => $before, 'versions_after' => $versions(),
        'final_activity_date' => $fmt($r->final_activity_date), 'final_activity_is_assign_duedate' => (int) $r->final_activity_date === $new,
        'final_update_matches' => (int) $r->final_update_at === make_timestamp($d['year'], $d['mon'], $d['mday'] + 1, 0, 0, 0, \core_date::get_server_timezone()),
    ];
} else if ($mode === 'resume') {
    // (b) the clock jumps 100 hours past the last success: one version, and the 72 hours count from it.
    $r = $report();
    $last = (int) $reports->find_current_version((int) $r->id)->created_at;
    $now = (int) ($argv[2] ?? $last + 100 * 3600);
    $before = $versions();
    $out['last_success'] = $fmt($last);
    $out['now'] = $fmt($now);
    $out['generated'] = $run($now);
    $r = $report();
    $out += [
        'versions_before' => $before, 'versions_after' => $versions(), 'next_update_at' => $fmt($r->next_update_at),
        'next_is_now_plus_72h' => (int) $r->next_update_at === $now + 72 * 3600,
        'second_run_same_instant_generated' => $run($now), 'versions_after_second_run' => $versions(),
    ];
} else if ($mode === 'concurrent') {
    // (c) one run: waits for the lock (held by hold.php) and then generates only if still due.
    $now = (int) $argv[2];
    $t = microtime(true);
    $out['generated'] = $run($now);
    $out['waited_seconds'] = round(microtime(true) - $t, 1);
    $out['pid'] = getmypid();
}
$r = $report();
$out['report'] = $r ? ['id' => (int) $r->id, 'current_version' => (int) $r->current_version, 'final_completed_at' => $r->final_completed_at] : null;
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
