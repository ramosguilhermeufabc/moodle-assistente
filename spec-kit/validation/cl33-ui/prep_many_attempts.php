<?php
// Escala: 40 estudantes com uma tentativa concluída cada (criadas pela API do quiz, não pela interface), para alongar o lote de reavaliação.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
\core\session\manager::set_user(get_admin());
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
$gen = new testing_data_generator();
for ($i = 1; $i <= 40; $i++) {
    $u = $gen->create_user(['username' => 'cl33b' . $i, 'password' => getenv('CL33_PASSWORD'), 'email' => "cl33b{$i}@example.com"]);
    $gen->enrol_user($u->id, 2, 'student');
    $quizobj = quiz_settings::create(1, $u->id);
    $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
    $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
    $t = time();
    $a = quiz_create_attempt($quizobj, 1, false, $t, false, $u->id);
    quiz_start_new_attempt($quizobj, $quba, $a, 1, $t);
    quiz_attempt_save_started($quizobj, $quba, $a);
    $ao = quiz_attempt::create($a->id);
    $ao->process_submitted_actions(time(), false, [1 => ['answer' => '1'], 2 => ['answer' => '3.14'], 3 => ['answer' => 'texto', 'answerformat' => 1]]);
    $ao = quiz_attempt::create($a->id);
    $ao->process_submit(time(), false);
    $ao->process_grade_submission(time());
}
echo "ok\n";
