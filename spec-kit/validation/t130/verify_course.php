<?php
// T130: confere os dados criados por prepare_course.php, pelas consultas do plugin e pela API de capabilities (sem senhas).
// Uso: php verify_course.php   (como www-data)
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

use local_assistente\application\query\{grades_query, frequency_query, quiz_metrics_query, synthesis_query};
use local_assistente\output\page_controller;

$a = $DB->get_record('course', ['shortname' => 'T130A'], '*', MUST_EXIST);
$b = $DB->get_record('course', ['shortname' => 'T130B'], '*', MUST_EXIST);
$uid = fn(string $l): int => (int) $DB->get_field('user', 'id', ['username' => $l], MUST_EXIST);
$ctx = context_course::instance($a->id);
$ctxb = context_course::instance($b->id);
$pc = new page_controller();

echo "== Capabilities e abas ==\n";
foreach (['t130_prof_edicao', 't130_prof_leitura', 't130_aluno_ana'] as $l) {
    $tabs = fn(int $cid) => has_capability('local/assistente:view', context_course::instance($cid), $uid($l)) ? implode(',', $pc->tabs($cid, $uid($l))) : '(sem view: negado)';
    echo sprintf("%-18s view=%d manage=%d\n   abas(A)=%s\n   abas(B)=%s\n", $l,
        has_capability('local/assistente:view', $ctx, $uid($l)), has_capability('local/assistente:manage', $ctx, $uid($l)), $tabs($a->id), $tabs($b->id));
}
echo 'groupmode=' . $a->groupmode . ' forçado=' . $a->groupmodeforce . ' lang=' . $a->lang . "\n";
echo 'config A enabled=' . $DB->get_field('local_assistente_config', 'enabled', ['course_id' => $a->id]) . '; config B: ' . ($DB->record_exists('local_assistente_config', ['course_id' => $b->id]) ? 'existe' : 'inexistente') . "\n";

function dump(string $title, array $rows, callable $f): void {
    echo "== $title ==\n";
    foreach ($rows as $r) { echo '  ' . $f($r) . "\n"; }
}
$e = $uid('t130_prof_edicao');
$g = (new grades_query())->query($a->id, $e);
dump('Notas (professor com edição)', $g['rows'], fn($r) => sprintf('%-14s total=%s atividades=%s', $r['fullname'] ?? ($r['name'] ?? '?'), $r['course_total']['text'], json_encode(array_map(fn($x) => [$x['activity'], $x['grade']['text'], $x['source']], $r['activities']), JSON_UNESCAPED_UNICODE)));
$f = (new frequency_query())->query($a->id, $e);
dump('Frequência', $f['rows'], fn($r) => sprintf('%-14s status=%s seções=%s', $r['fullname'] ?? ($r['name'] ?? '?'), $r['status']['text'] ?? 'ok', json_encode(array_map(fn($s) => $s['percentage']['text'], $r['sections']))));
$q = (new quiz_metrics_query())->query($a->id, $e);
dump('Questionários', $q['rows'], fn($r) => json_encode(['quiz' => $r['name'] ?? ($r['quiz'] ?? '?'), 'coverage' => $r['coverage']['text']], JSON_UNESCAPED_UNICODE));
$s = (new synthesis_query())->query($a->id, $e);
dump('Sínteses', $s['rows'], fn($r) => json_encode($r, JSON_UNESCAPED_UNICODE));

echo "== Grupos separados: professor sem edição (Grupo A) ==\n";
$p = $uid('t130_prof_leitura');
foreach ($pc->filter_data($a->id, 'grades', $p) as $k => $v) { echo "  filtro $k: " . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n"; }
$g2 = (new grades_query())->query($a->id, $p);
dump('Notas (professor sem edição)', $g2['rows'], fn($r) => $r['fullname'] ?? ($r['name'] ?? json_encode(array_keys($r))));

echo "== Relatório (versões e blocos) ==\n";
$rep = $DB->get_record('local_assistente_report', ['course_id' => $a->id], '*', MUST_EXIST);
foreach ($DB->get_records('local_assistente_report_version', ['report_id' => $rep->id], 'version') as $v) {
    $m = json_decode($v->snapshot_json, true);
    echo '  versão ' . $v->version . ($v->id == $rep->current_version ? ' (atual)' : '') . ': ' . implode(', ', array_map(fn($x) => $x['block'] . '=' . $x['state'], $m['blocks'])) . "\n";
}
echo "== Curso B (professor com edição) ==\n";
$r = (new grades_query())->query($b->id, $e);
echo '  grades: available=' . var_export($r['available'], true) . ' mensagem=' . $r['message'] . "\n";
