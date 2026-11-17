<?php
// T047 (CL-02, publicação do questionário) no Moodle 5.2.4 em execução. Curso e usuários temporários (cl26pub..., removidos ao final pelo
// próprio script ou por ../cleanup_temp.php). Parte A: eventos reais do Moodle e observers do site (criado visível, oculto e depois visível,
// ícone de olho, ocultar/mostrar/editar, seção oculta). Parte B: concorrência real com processos PHP separados sobre o mesmo questionário.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$tag = 'cl26pub' . substr((string) time(), -5);
$out = [];
$ck = function (string $what, $exp, $act) use (&$out) { $out[] = sprintf('%-78s esperado=%-6s obtido=%-6s %s', $what, var_export($exp, true), var_export($act, true), $exp === $act ? 'OK' : 'FALHA'); };
$c = $gen->create_course(['shortname' => $tag, 'fullname' => 'T047 publicacao', 'numsections' => 2]);
(new \local_assistente\infrastructure\persistence\config_repository())->create($c->id, true);
$count = function (int $cmid) use ($DB, $c): int {
    $n = 0;
    foreach ($DB->get_records('local_assistente_event', ['course_id' => $c->id, 'event_type' => 'quiz_published']) as $e) {
        if ((int) (json_decode($e->payload, true)['cmid'] ?? 0) === $cmid) { $n++; }
    }
    return $n;
};
$edit = function ($quiz, array $set) use ($c) {
    $cm = get_coursemodule_from_id('quiz', $quiz->cmid, 0, false, MUST_EXIST);
    [$cm, , , $data] = get_moduleinfo_data($cm, $c);
    foreach ($set as $k => $v) { $data->$k = $v; }
    $data->quizpassword = '';
    update_moduleinfo($cm, $data, $c);
};
try {
    // A1: criado visível.
    $q1 = $gen->create_module('quiz', ['course' => $c->id], ['section' => 1]);
    $ck('A1 criado visível: 1 evento', 1, $count((int) $q1->cmid));
    $ck('A1 evento pendente', 'pending', (string) $DB->get_field('local_assistente_event', 'status', ['course_id' => $c->id, 'event_type' => 'quiz_published']));
    // A2: oculto e depois visível pelo formulário de edição.
    $q2 = $gen->create_module('quiz', ['course' => $c->id, 'visible' => 0], ['section' => 1]);
    $ck('A2 criado oculto: 0 evento', 0, $count((int) $q2->cmid));
    $edit($q2, ['visible' => 1]);
    $ck('A2 tornado visível no formulário: 1 evento', 1, $count((int) $q2->cmid));
    // A3: ocultar/mostrar pelo ícone de olho e editar depois da publicação: continua 1.
    set_coursemodule_visible($q2->cmid, 0); set_coursemodule_visible($q2->cmid, 1);
    $edit($q2, ['name' => 'Renomeado T047']);
    $ck('A3 ocultar, mostrar e editar: continua 1', 1, $count((int) $q2->cmid));
    // A4: ícone de olho em questionário criado oculto não gera evento (achado registrado em T046).
    $q3 = $gen->create_module('quiz', ['course' => $c->id, 'visible' => 0], ['section' => 1]);
    set_coursemodule_visible($q3->cmid, 1);
    $ck('A4 tornado visível só pelo ícone de olho: 0 evento (lacuna conhecida)', 0, $count((int) $q3->cmid));
    // A5: seção oculta com módulo visível (hipótese: só o flag do módulo conta; DEC-26 cobre por estudante).
    $q4 = $gen->create_module('quiz', ['course' => $c->id], ['section' => 2]);
    $q5 = $gen->create_module('quiz', ['course' => $c->id, 'visible' => 0], ['section' => 2]);
    set_section_visible($c->id, 2, 0);
    $edit($q5, ['visible' => 1]);
    $mod = (new \local_assistente\infrastructure\moodle\course_module_reader())->get_module($c->id, (int) $q5->cmid);
    $out[] = 'A5 seção 2 oculta, módulo marcado visível: leitor devolve visible=' . var_export($mod->visible, true) . ' available_to_user=' . var_export($mod->available_to_user, true) . ' eventos=' . $count((int) $q5->cmid);

    // B: concorrência real, 6 processos para o mesmo questionário recém-criado e visível, e para outro, em 2 rodadas.
    for ($round = 1; $round <= 2; $round++) {
        $qc = $gen->create_module('quiz', ['course' => $c->id, 'visible' => 0], ['section' => 1]);
        set_coursemodule_visible($qc->cmid, 1); // visível, sem evento de módulo: só os trabalhadores chamam o serviço.
        $before = $count((int) $qc->cmid);
        $start = microtime(true) + 3;
        $procs = [];
        for ($i = 1; $i <= 6; $i++) {
            $cmd = ['php', __DIR__ . '/worker.php', (string) $c->id, (string) $qc->cmid, (string) $start, "r{$round}w{$i}"];
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $procs[] = [$p, $pipes];
        }
        foreach ($procs as [$p, $pipes]) { $out[] = '  ' . trim(stream_get_contents($pipes[1])) . trim(stream_get_contents($pipes[2])); proc_close($p); }
        $ck("B rodada $round: antes 0, depois dos 6 processos simultâneos exatamente 1 evento", [0, 1], [$before, $count((int) $qc->cmid)]);
    }
} finally {
    echo implode("\n", $out) . "\n";
    $fail = count(array_filter($out, fn($l) => str_ends_with($l, 'FALHA')));
    delete_course($c->id, false);
    foreach ($DB->get_records_select('tool_recyclebin_category', "shortname LIKE ?", [$tag . '%']) as $r) { (new \tool_recyclebin\category_bin($r->categoryid))->delete_item($r); }
    echo 'limpeza: cursos=' . $DB->count_records_select('course', "shortname LIKE ?", [$tag . '%']) . ' eventos do curso=' . $DB->count_records('local_assistente_event', ['course_id' => $c->id]) . "\n";
    echo $fail ? "RESULTADO: $fail FALHA(S)\n" : "RESULTADO: TODOS OK\n";
}
