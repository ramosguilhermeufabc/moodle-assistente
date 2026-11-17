<?php
// T130: monta o curso de teste da verificação do pesquisador (spec-kit/specs/023-*/checklists/usability-researcher.md, Q1–Q13).
//
// Uso (a partir da raiz do Moodle, como www-data, sem a variável da chave da API):
//   php /workspaces/moodle-assistente/spec-kit/validation/t130/prepare_course.php [--reset-passwords | --delete]
//
// Usa só APIs e geradores nativos do Moodle (testing_data_generator: cursos, usuários, matrículas, grupos, atividades) e o gerador de
// dados do plugin (assistente/tests/generator/lib.php). Os resultados do assistente são gravados direto nas tabelas do plugin, como as
// fixtures do Behat: nenhuma chamada à LLM é feita (nem real nem simulada). Senhas: geradas aqui, mostradas só no fim da execução e
// nunca gravadas em arquivo. --reset-passwords gera e mostra senhas novas para os usuários já criados; --delete remove os
// dois cursos e os usuários de teste (para recriar do zero).

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/accesslib.php');

use local_assistente\application\report\report_builder;

const SHORT_A = 'T130A';
const SHORT_B = 'T130B';
$users = [
    // login => [nome, sobrenome, idioma]
    't130_prof_edicao' => ['Paula', 'Professora Edição', 'en'],
    't130_prof_leitura' => ['Pedro', 'Professor Leitura', 'en'],
    't130_aluno_ana' => ['Ana', 'Teste', 'en'],
    't130_aluno_bia' => ['Bia', 'Teste', 'en'],
    't130_aluno_carlos' => ['Carlos', 'Teste', 'en'],
    't130_aluno_davi' => ['Davi', 'Teste', 'en'],
    't130_aluno_eva' => ['Eva', 'Teste', 'en'],
];

function new_password(): string {
    return 'Tt1-' . bin2hex(random_bytes(6)) . 'Z';
}

$passwords = [];

if (in_array('--delete', $argv, true)) {
    foreach ([SHORT_A, SHORT_B] as $short) {
        if ($course = $DB->get_record('course', ['shortname' => $short])) {
            delete_course($course, false);
        }
    }
    foreach (array_keys($users) as $login) {
        if ($user = $DB->get_record('user', ['username' => $login, 'deleted' => 0])) {
            delete_user($user);
        }
    }
    echo "Cursos e usuários de teste removidos.\n";
    exit(0);
}

if (in_array('--reset-passwords', $argv, true)) {
    foreach (array_keys($users) as $login) {
        $user = $DB->get_record('user', ['username' => $login], '*', MUST_EXIST);
        $passwords[$login] = new_password();
        update_internal_user_password($user, $passwords[$login]);
    }
    echo "Senhas novas (mostradas só aqui):\n";
    foreach ($passwords as $login => $p) {
        echo "  $login  $p\n";
    }
    exit(0);
}

if ($DB->record_exists('course', ['shortname' => SHORT_A]) || $DB->record_exists('course', ['shortname' => SHORT_B])) {
    cli_error('Os cursos ' . SHORT_A . ' ou ' . SHORT_B . ' já existem. Nada foi alterado.');
}
foreach (array_keys($users) as $login) {
    if ($DB->record_exists('user', ['username' => $login])) {
        cli_error("O usuário $login já existe. Nada foi alterado.");
    }
}

// O idioma pt_br só pode ser forçado no curso se o pacote de idioma estiver instalado (PARAM_LANG só aceita idiomas instalados).
// O script não instala pacotes: Administração > Idioma > Pacotes de idioma.
if (!get_string_manager()->translation_exists('pt_br')) {
    cli_error('O pacote de idioma pt_br do Moodle não está instalado. Instale-o em Administração > Idioma > Pacotes de idioma e rode de novo. Nada foi alterado.');
}

$gen = new testing_data_generator();
/** @var local_assistente_generator $pg */
$pg = $gen->get_plugin_generator('local_assistente');
$now = time();

// Curso A: grupos separados, forçados; idioma forçado pt_br (Q11, Q12); duas seções.
$courseA = $gen->create_course([
    'fullname' => 'Curso de teste do assistente (T130)', 'shortname' => SHORT_A, 'numsections' => 2,
    'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1, 'lang' => 'pt_br',
]);
// Curso B: sem configuração do assistente (Q6).
$courseB = $gen->create_course(['fullname' => 'Curso de teste sem configuração (T130)', 'shortname' => SHORT_B]);

$u = [];
foreach ($users as $login => [$first, $last, $lang]) {
    $passwords[$login] = new_password();
    $u[$login] = $gen->create_user([
        'username' => $login, 'firstname' => $first, 'lastname' => $last, 'lang' => $lang,
        'email' => $login . '@example.com', 'password' => $passwords[$login],
    ]);
}

// Papéis: professor com edição (view e manage por padrão); professor sem edição com view só no curso A (override no contexto do curso).
$gen->enrol_user($u['t130_prof_edicao']->id, $courseA->id, 'editingteacher');
$gen->enrol_user($u['t130_prof_edicao']->id, $courseB->id, 'editingteacher');
$gen->enrol_user($u['t130_prof_leitura']->id, $courseA->id, 'teacher');
$teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
assign_capability('local/assistente:view', CAP_ALLOW, $teacherrole->id, context_course::instance($courseA->id)->id, true);
foreach (['ana', 'bia', 'carlos', 'davi', 'eva'] as $s) {
    $gen->enrol_user($u["t130_aluno_$s"]->id, $courseA->id, 'student');
}

// Dois grupos: A (Ana, Carlos, professor de leitura) e B (Bia, Davi, Eva).
$groupa = $gen->create_group(['courseid' => $courseA->id, 'name' => 'Grupo A']);
$groupb = $gen->create_group(['courseid' => $courseA->id, 'name' => 'Grupo B']);
foreach (['t130_aluno_ana', 't130_aluno_carlos', 't130_prof_leitura'] as $l) {
    $gen->create_group_member(['groupid' => $groupa->id, 'userid' => $u[$l]->id]);
}
foreach (['t130_aluno_bia', 't130_aluno_davi', 't130_aluno_eva'] as $l) {
    $gen->create_group_member(['groupid' => $groupb->id, 'userid' => $u[$l]->id]);
}

// Atividades (seção 1) e questionários.
$act1 = $gen->create_module('assign', ['course' => $courseA->id, 'name' => 'Atividade 1', 'section' => 1]);
$act2 = $gen->create_module('assign', ['course' => $courseA->id, 'name' => 'Atividade 2', 'section' => 1]);
$gone = $gen->create_module('assign', ['course' => $courseA->id, 'name' => 'Atividade removida', 'section' => 1]);
$quiznomat = $gen->create_module('quiz', ['course' => $courseA->id, 'name' => 'Questionário sem materiais', 'section' => 2]);
$quizmat = $gen->create_module('quiz', ['course' => $courseA->id, 'name' => 'Questionário com materiais', 'section' => 2]);
$gen->create_module('quiz', ['course' => $courseA->id, 'name' => 'Questionário sem análise', 'section' => 2]);
$section1 = $DB->get_field('course_sections', 'id', ['course' => $courseA->id, 'section' => 1], MUST_EXIST);

// Assistente habilitado no curso A (o curso B fica sem configuração).
$config = $pg->create_config(['course_id' => $courseA->id, 'enabled' => 1]);
$pg->create_criteria(['config_id' => $config->id]);

/**
 * Cria evento + processamento + análise (+ snapshot do contexto) de um tipo.
 */
$analysis = function (string $type, ?int $studentid, ?int $cmid, array $context = []) use ($pg, $courseA, $DB, $now): stdClass {
    $event = $pg->create_event([
        'course_id' => $courseA->id, 'event_type' => $type,
        'payload' => json_encode($studentid === null ? new stdClass() : ['userid' => $studentid]),
        'status' => 'processed', 'processed_at' => $now,
    ]);
    $processing = $pg->create_processing(['event_id' => $event->id, 'status' => 'completed', 'finished_at' => $now]);
    $a = $pg->create_analysis([
        'course_id' => $courseA->id, 'processing_id' => $processing->id, 'analysis_type' => $type, 'cmid' => $cmid,
    ]);
    if ($context) {
        $DB->insert_record('local_assistente_processing_context', (object) [
            'processing_id' => $processing->id, 'context_json' => json_encode($context), 'created_at' => $now,
        ]);
    }
    return $a;
};

// Notas (aba grades): Ana e Bia empatam em 0,00%; Carlos tem uma atividade de fonte removida; Davi e Eva sem dados.
$gradeids = [];
foreach (['ana' => [0, [[$act1->cmid, 0]]], 'bia' => [0, [[$act1->cmid, 0]]],
          'carlos' => [75, [[$act1->cmid, 80], [$act2->cmid, 70], [$gone->cmid, 75]]]] as $s => [$total, $rows]) {
    $sid = (int) $u["t130_aluno_$s"]->id;
    $a = $analysis('grade_performance', $sid, null, ['course_total_percent' => $total]);
    $gradeids[] = (int) $a->id;
    foreach ($rows as [$cmid, $grade]) {
        $DB->insert_record('local_assistente_performance', (object) [
            'analysis_id' => $a->id, 'student_id' => $sid, 'activity_id' => $cmid, 'grade' => $grade,
        ]);
    }
}

// Fonte removida: a atividade que tem nota registrada é excluída depois (o resultado fica; a fonte não existe mais).
course_delete_module($gone->cmid);

// Frequência (aba frequency): Ana e Bia 0/4 = 0,00% (empate); Carlos 2/4; Eva com seção sem atividades (sem dados); Davi sem análise.
$freqids = [];
foreach (['ana' => [4, 0], 'bia' => [4, 0], 'carlos' => [4, 2], 'eva' => [0, 0]] as $s => [$total, $part]) {
    $sid = (int) $u["t130_aluno_$s"]->id;
    $a = $analysis('frequency', $sid, null, ['course_access' => [['student_id' => $sid, 'accessed' => true, 'last_access' => $now]]]);
    $freqids[] = (int) $a->id;
    $fid = $DB->insert_record('local_assistente_frequency', (object) [
        'analysis_id' => $a->id, 'student_id' => $sid, 'section_id' => $section1, 'total_activities' => $total,
        'participating_activities' => $part, 'percentage' => $total ? 100 * $part / $total : 0,
    ]);
    if ($total) {
        foreach ([$act1->cmid, $act2->cmid] as $i => $cmid) {
            $DB->insert_record('local_assistente_frequency_activity', (object) [
                'frequency_id' => $fid, 'activity_id' => $cmid, 'participated' => (int) ($i < $part), 'reference_date' => $now,
            ]);
        }
    }
}

// Questionários (aba quiz_metrics): cobertura 0.0 sem materiais (snapshot com materials vazio) e cobertura 0.5 com materiais.
$quizids = [];
foreach ([[$quiznomat, 0.0, []], [$quizmat, 0.5, [['name' => 'Material de exemplo']]]] as [$quiz, $coverage, $materials]) {
    $a = $analysis('quiz_initial', null, (int) $quiz->cmid, ['materials' => $materials]);
    $quizids[] = (int) $a->id;
    $DB->insert_record('local_assistente_questionnaire_analysis', (object) ['analysis_id' => $a->id, 'coverage' => $coverage]);
}

// Sínteses (aba synthesis): seção 1 com <script> e <b> (Q9); seção 2 em português (Q11, Q12).
$syn1 = $pg->create_pedagogical_synthesis([
    'analysis_id' => $analysis('section_synthesis', null, null)->id,
    'course_id' => $courseA->id, 'section_id' => $section1, 'content' => '<script>alert(1)</script> e <b>negrito</b>',
]);
$section2 = $DB->get_field('course_sections', 'id', ['course' => $courseA->id, 'section' => 2], MUST_EXIST);
$syn2 = $pg->create_pedagogical_synthesis([
    'analysis_id' => $analysis('section_synthesis', null, null)->id,
    'course_id' => $courseA->id, 'section_id' => $section2, 'content' => 'Síntese gerada em português do Brasil.',
]);

// Relatório (aba report): versão 1 sem dados; versão 2 (atual) com notas e frequência válidas, questionários em "erro técnico".
$builder = new report_builder();
$nodata = ['state' => 'no_data', 'analysis_ids' => [], 'material_versions' => [], 'synthesis_versions' => []];
$blocks1 = array_fill_keys(report_builder::BLOCKS, $nodata);
$blocks2 = $blocks1;
$blocks2[report_builder::BLOCK_GRADES] = ['state' => 'valid', 'analysis_ids' => $gradeids] + $nodata;
$blocks2[report_builder::BLOCK_FREQUENCY] = ['state' => 'valid', 'analysis_ids' => $freqids] + $nodata;
$blocks2[report_builder::BLOCK_QUIZ_METRICS] = ['state' => 'error'] + $nodata;
$report = $pg->create_report(['course_id' => $courseA->id]);
$pg->create_report_version(['report_id' => $report->id, 'snapshot_json' => $builder->build('periodic', $now - 3600, $blocks1), 'created_at' => $now - 3600]);
$pg->create_report_version(['report_id' => $report->id, 'snapshot_json' => $builder->build('periodic', $now, $blocks2), 'created_at' => $now]);

$url = $CFG->wwwroot . '/local/assistente/index.php?id=' . $courseA->id;
echo "Curso A (com dados): {$url}\n";
echo 'Curso B (sem configuração): ' . $CFG->wwwroot . '/local/assistente/index.php?id=' . $courseB->id . "\n";
echo "Usuários e senhas (mostradas só aqui, não gravadas em arquivo):\n";
foreach ($passwords as $login => $p) {
    echo "  $login  $p\n";
}
