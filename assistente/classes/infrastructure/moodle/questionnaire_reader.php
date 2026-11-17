<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_assistente\infrastructure\moodle;

use core_question\local\bank\random_question_loader;
use mod_quiz\question\bank\qbank_helper;
use mod_quiz\quiz_settings;

/**
 * Reads questionnaires (mod_quiz), their questions, attempts and answers (spec-004; DEC-35, DEC-36).
 *
 * A question is identified by `question_id` (question.id, the row of one version) plus `question_version`
 * (question_versions.version), never by `slot`. The stable entry of the question bank (`bank_entry_id`) is also
 * returned, because DEC-36 D5 links a new version to the analysed one through the same entry.
 *
 * Moodle API: mod_quiz\quiz_settings::create_for_cmid() and get_quiz(), get_quizid(), get_context();
 * mod_quiz\question\bank\qbank_helper::get_question_structure() for fixed and random slots and the version in
 * force; core_question\local\bank\random_question_loader::get_filtered_questions() for the candidates of a
 * random slot (the same call core makes to list the potential questions of a quiz); question_bank::load_question()
 * and qtype_exists(); question_engine::load_questions_usage_by_activity() with get_slots(),
 * get_question_attempt(), and question_attempt::get_fraction(), get_state(), get_max_mark(). Verified by reading
 * the MOODLE_502_STABLE sources; behaviour in a running Moodle 5.2 is validated in CL-33 (tasks T033 and T075).
 *
 * DML justification: (1) question and question_versions are joined to return, in one query, the
 * identification, type, text and status of a question version (no light API exists for it); (2) quiz_attempts is
 * listed by quiz because quiz_get_user_attempts() works per user only.
 *
 * Hypotheses pending CL-33: the candidates of the loader are the questions in force of the filter at that
 * moment; question types without `answers` or `get_correct_response()` are returned with empty values.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire_reader {
    /** @var string[] mod_quiz grade method code => name */
    private const GRADE_METHODS = ['1' => 'highest', '2' => 'average', '3' => 'first', '4' => 'last'];

    /**
     * Load the files that define the quiz functions and constants.
     */
    private function require_quiz_libraries(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');
    }

    /**
     * Read the questionnaire of a course module.
     *
     * @param int $cmid
     * @return \stdClass|null cmid, quiz_id, course_id, name, visible, time_open, time_close (null when unset),
     *                        grade_method (highest, average, first or last); null when the cmid is not a quiz
     */
    public function get_questionnaire(int $cmid): ?\stdClass {
        $this->require_quiz_libraries();
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        $quiz = quiz_settings::create_for_cmid($cmid)->get_quiz();
        return (object) [
            'cmid' => (int) $cmid,
            'quiz_id' => (int) $quiz->id,
            'course_id' => (int) $quiz->course,
            'name' => (string) $quiz->name,
            'visible' => (bool) $cm->visible,
            'time_open' => (int) $quiz->timeopen > 0 ? (int) $quiz->timeopen : null,
            'time_close' => (int) $quiz->timeclose > 0 ? (int) $quiz->timeclose : null,
            'grade_method' => self::GRADE_METHODS[(string) $quiz->grademethod] ?? 'highest',
        ];
    }

    /**
     * Questionnaires of some courses whose closing time has passed.
     *
     * DML justification: no API lists the quizzes of several courses by closing time; the query reads `quiz` and `course_modules`
     * only, and leaves out a module being deleted.
     *
     * @param int $now unix time
     * @param int[] $courseids courses to look at
     * @return \stdClass[] cmid, course_id, time_close; ordered by closing time, then cmid
     */
    public function get_closed_questionnaires(int $now, array $courseids): array {
        global $DB;
        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $records = $DB->get_records_sql(
            "SELECT cm.id AS cmid, q.course AS course_id, q.timeclose AS time_close
               FROM {quiz} q
               JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
               JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
              WHERE q.course {$insql}
                AND q.timeclose > 0
                AND q.timeclose <= :now
                AND cm.deletioninprogress = 0
           ORDER BY q.timeclose ASC, cm.id ASC",
            $params + ['now' => $now]
        );
        return array_values(array_map(static fn($r) => (object) [
            'cmid' => (int) $r->cmid,
            'course_id' => (int) $r->course_id,
            'time_close' => (int) $r->time_close,
        ], $records));
    }

    /**
     * Slots of the questionnaire in the order of the quiz, with the question version in force.
     *
     * Each item has: slot, slot_id, random (bool), question (stdClass|null, see get_question(); null for a random
     * slot or a missing question), filter (array|null, the filter condition of a random slot), status (version
     * status of a fixed question: ready or draft; null otherwise).
     *
     * @param int $cmid
     * @return \stdClass[] [] when the cmid is not a quiz
     */
    public function get_slots(int $cmid): array {
        $this->require_quiz_libraries();
        if ($this->get_questionnaire($cmid) === null) {
            return [];
        }
        $quizobj = quiz_settings::create_for_cmid($cmid);
        $rows = qbank_helper::get_question_structure($quizobj->get_quizid(), $quizobj->get_context());

        $slots = [];
        foreach ($rows as $row) {
            $slot = (object) [
                'slot' => (int) $row->slot,
                'slot_id' => (int) $row->slotid,
                'random' => (bool) $row->random,
                'question' => null,
                'filter' => $row->random ? $row->filtercondition : null,
                'status' => null,
            ];
            // A fixed slot whose question is missing keeps a placeholder id that starts with "s".
            if (!$row->random && is_numeric($row->questionid)) {
                $slot->question = $this->get_question((int) $row->questionid);
                $slot->status = $row->status ?? null;
            }
            $slots[] = $slot;
        }
        return $slots;
    }

    /**
     * Read one question version: identification, type, text, answers and the response the teacher marked correct.
     *
     * Result fields: question_id, question_version, bank_entry_id, status, type, name, text, text_format,
     * answers (list of text and fraction, empty when the type has none), correct_response (array|null).
     *
     * @param int $questionid question.id
     * @param bool $withoptions also load the answers and the correct response (needs the question type)
     * @return \stdClass|null null when the question does not exist
     */
    public function get_question(int $questionid, bool $withoptions = true): ?\stdClass {
        global $DB;
        $this->require_quiz_libraries();

        $row = $DB->get_record_sql(
            'SELECT q.id, q.name, q.questiontext, q.questiontextformat, q.qtype,
                    qv.version, qv.questionbankentryid, qv.status
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
              WHERE q.id = :id',
            ['id' => $questionid],
            IGNORE_MISSING
        );
        if (!$row) {
            return null;
        }

        $answers = [];
        $correct = null;
        if ($withoptions && \question_bank::qtype_exists($row->qtype)) {
            // Some types (calculated, for one) cannot build their answers or response outside an attempt (CL-33, T069): the
            // question is then returned with no answers and no response, as for the other types that cannot supply them.
            try {
                $question = \question_bank::load_question($questionid, false);
                if (isset($question->answers) && is_array($question->answers)) {
                    foreach ($question->answers as $answer) {
                        $answers[] = (object) ['text' => (string) $answer->answer, 'fraction' => (float) $answer->fraction];
                    }
                }
            } catch (\Throwable $e) {
                $answers = [];
            }
            // The response marked by the teacher comes from the question and, for multiple choice and matching, depends on the order
            // of an attempt that does not exist here: Moodle then warns and returns nothing. The warning is silenced and the empty result is kept.
            if (isset($question) && method_exists($question, 'get_correct_response')) {
                set_error_handler(static fn(int $no): bool => in_array($no, [E_WARNING, E_NOTICE], true));
                try {
                    $correct = $question->get_correct_response();
                } catch (\Throwable $e) {
                    $correct = null;
                } finally {
                    restore_error_handler();
                }
            }
        }

        return (object) [
            'question_id' => (int) $row->id,
            'question_version' => (int) $row->version,
            'bank_entry_id' => (int) $row->questionbankentryid,
            'status' => (string) $row->status,
            'type' => (string) $row->qtype,
            'name' => (string) $row->name,
            'text' => (string) $row->questiontext,
            'text_format' => (int) $row->questiontextformat,
            'answers' => $answers,
            'correct_response' => $correct,
        ];
    }

    /**
     * Candidate questions of a random slot at this moment (DEC-35 D1), one item per question version.
     *
     * @param array $filtercondition the `filter` condition of the slot, as returned in get_slots()
     * @return \stdClass[] question_id, question_version, bank_entry_id, type, name; no duplicates
     */
    public function get_random_candidates(array $filtercondition): array {
        global $DB;
        $this->require_quiz_libraries();

        $filter = $filtercondition['filter'] ?? $filtercondition;
        $loader = new random_question_loader(new \qubaid_list([]));
        $questions = $loader->get_filtered_questions($filter, 0);
        if (!$questions) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($questions), SQL_PARAMS_NAMED);
        $versions = $DB->get_records_sql(
            "SELECT qv.questionid, qv.version, qv.questionbankentryid
               FROM {question_versions} qv
              WHERE qv.questionid {$insql}",
            $params
        );

        $candidates = [];
        foreach ($questions as $question) {
            if (!isset($versions[$question->id])) {
                continue;
            }
            $candidates[(int) $question->id] = (object) [
                'question_id' => (int) $question->id,
                'question_version' => (int) $versions[$question->id]->version,
                'bank_entry_id' => (int) $versions[$question->id]->questionbankentryid,
                'type' => (string) $question->qtype,
                'name' => (string) $question->name,
            ];
        }
        ksort($candidates);
        return array_values($candidates);
    }

    /**
     * Attempts of a questionnaire, of all users, with their state.
     *
     * Each item has: attempt_id, user_id, attempt_number, state (inprogress, overdue, finished or abandoned),
     * preview (bool), is_finished (bool), time_start, time_finish (int|null), sum_grades (float|null).
     * Which of them count in the metrics is a rule of the metrics (spec-011), not of the reader.
     *
     * @param int $cmid
     * @return \stdClass[] [] when the cmid is not a quiz
     */
    public function get_attempts(int $cmid): array {
        global $DB;
        $this->require_quiz_libraries();
        $questionnaire = $this->get_questionnaire($cmid);
        if ($questionnaire === null) {
            return [];
        }

        $attempts = [];
        $records = $DB->get_records(
            'quiz_attempts',
            ['quiz' => $questionnaire->quiz_id],
            'userid ASC, attempt ASC',
            'id, userid, attempt, state, preview, timestart, timefinish, sumgrades'
        );
        foreach ($records as $record) {
            $attempts[] = (object) [
                'attempt_id' => (int) $record->id,
                'user_id' => (int) $record->userid,
                'attempt_number' => (int) $record->attempt,
                'state' => (string) $record->state,
                'preview' => (bool) $record->preview,
                'is_finished' => $record->state === 'finished',
                'time_start' => (int) $record->timestart,
                'time_finish' => (int) $record->timefinish > 0 ? (int) $record->timefinish : null,
                'sum_grades' => $record->sumgrades === null ? null : (float) $record->sumgrades,
            ];
        }
        return $attempts;
    }

    /**
     * Answers of one attempt, by slot: the question version answered, the fraction and the state.
     *
     * Each item has: slot, question_id, question_version, bank_entry_id, max_mark, fraction (float|null: null while
     * the answer is not graded, for example an essay waiting for manual grading), graded (bool), finished (bool)
     * state (name of the question state), choices (see choices(): null unless the question is multiple choice with several
     * answers) and parts (see parts(): null unless the question is matching, drag-and-drop words into sentences or Cloze and graded).
     *
     * @param int $attemptid
     * @return \stdClass[] [] when the attempt does not exist
     */
    public function get_attempt_questions(int $attemptid): array {
        return $this->get_attempt_detail($attemptid)?->questions ?? [];
    }

    /**
     * The answers of one attempt (as get_attempt_questions()) and the total mark of its questions, which is what the grade of the
     * attempt (`quiz_attempts.sumgrades`) must equal once Moodle has persisted it. After a regrade the questions are saved first and
     * `sumgrades` is updated later by the report, so the two differ in between (T075).
     *
     * @param int $attemptid
     * @return \stdClass|null questions (list) and total_mark (float|null: null while a question waits for manual grading); null when the
     *                       attempt does not exist
     */
    public function get_attempt_detail(int $attemptid): ?\stdClass {
        global $DB;
        $this->require_quiz_libraries();

        $uniqueid = $DB->get_field('quiz_attempts', 'uniqueid', ['id' => $attemptid], IGNORE_MISSING);
        if ($uniqueid === false) {
            return null;
        }
        $quba = \question_engine::load_questions_usage_by_activity((int) $uniqueid);

        $items = [];
        $questionids = [];
        foreach ($quba->get_slots() as $slot) {
            $attempt = $quba->get_question_attempt($slot);
            $state = $attempt->get_state();
            $questionids[] = (int) $attempt->get_question()->id;
            $items[] = (object) [
                'slot' => (int) $slot,
                'question_id' => (int) $attempt->get_question()->id,
                'question_version' => null,
                'bank_entry_id' => null,
                'max_mark' => (float) $attempt->get_max_mark(),
                'fraction' => $state->is_graded() && $attempt->get_fraction() !== null ? (float) $attempt->get_fraction() : null,
                'graded' => (bool) $state->is_graded(),
                'finished' => (bool) $state->is_finished(),
                'state' => (string) $state,
                'choices' => $this->choices($attempt),
                'parts' => $this->parts($attempt),
            ];
        }

        if ($questionids) {
            [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
            $versions = $DB->get_records_select('question_versions', "questionid {$insql}", $params, '', 'questionid, version, questionbankentryid');
            foreach ($items as $item) {
                if (isset($versions[$item->question_id])) {
                    $item->question_version = (int) $versions[$item->question_id]->version;
                    $item->bank_entry_id = (int) $versions[$item->question_id]->questionbankentryid;
                }
            }
        }
        $total = $quba->get_total_mark();
        return (object) ['questions' => $items, 'total_mark' => $total === null ? null : (float) $total];
    }

    /**
     * The parts of a matching, drag-and-drop words into sentences or Cloze question that the student got right, in an attempt (Q-011-07).
     *
     * Moodle 5.2.4: `get_num_parts_right()` of the question, the call Moodle itself makes to show "you got N of M", gives the stems (match) or
     * gaps (ddwtos) with the right choice and the number of stems or gaps; each one has exactly one right choice, so the total is the
     * total of correct items. For Cloze (`multianswer`) the parts are the embedded subquestions: those graded right (state `gradedright`)
     * are the numerator, and the subquestions that are partially right, wrong or unanswered stay out of the numerator and in the total;
     * the native count has no weight by subquestion. An empty stem or gap counts as wrong. The count is NOT the fraction of the question (the fraction carries the
     * penalty of the tries and the manual grade). `get_last_qt_data()` is not a sign of an answer, because it returns the data of the
     * first step (the order of the stems and choices) when nothing was answered; the sign is the grade of the state: an answer that
     * was never given is `gaveup`, has no fraction and no parts, and is left out, never taken as zero. The type is compared by name,
     * not by class: `randomsamatch` extends the class of `match` and is out of this indicator.
     *
     * One warning of Moodle is expected and silenced, and only that one: the count of a Cloze grades every embedded subquestion, answered
     * or not, and the numerical one reads `$response['answer']` without testing it (`numerical/question.php`), so a request that omitted
     * that field (an API or a script; the web form posts an empty one) gives `Undefined array key "answer"`. The result is still right:
     * the subquestion is graded wrong and is not counted. Any other warning or notice goes on to the handler that was in place.
     *
     * @param \question_attempt $attempt
     * @return array|null right (parts right) and total (parts of the question); null for any other type, or while the answer has no grade
     */
    private function parts(\question_attempt $attempt): ?array {
        $question = $attempt->get_question();
        if (!in_array($question->qtype->name(), ['match', 'ddwtos', 'multianswer'], true)) {
            return null;
        }
        if (!$attempt->get_state()->is_graded() || $attempt->get_fraction() === null) {
            return null;
        }
        $previous = null;
        $previous = set_error_handler(
            static function (int $no, string $message, string $file, int $line) use (&$previous): bool {
                if (
                    $no === E_WARNING
                    && $message === 'Undefined array key "answer"'
                    && str_ends_with(str_replace('\\', '/', $file), '/question/type/numerical/question.php')
                ) {
                    return true;
                }
                return is_callable($previous) ? (bool) $previous($no, $message, $file, $line) : false;
            }
        );
        try {
            [$right, $total] = $question->get_num_parts_right($attempt->get_last_qt_data());
        } finally {
            restore_error_handler();
        }
        if ($right === null || $total === null) {
            return null;
        }
        return ['right' => (int) $right, 'total' => (int) $total];
    }

    /**
     * The alternatives of a multiple choice question with several answers, in an attempt (Q-011-07).
     *
     * Moodle 5.2.4: the question is `qtype_multichoice_multi_question`; the order of the alternatives belongs to the attempt
     * (get_order()); the response keeps one `choiceN` per SELECTED alternative, N being the position in that order, and nothing for
     * the others, so a submission with nothing selected leaves no `choiceN` (observed). An alternative is correct when its fraction is
     * above zero and incorrect otherwise (a negative fraction is a penalty). get_num_parts_right() is not used: its total counts all
     * the alternatives, not the correct ones.
     *
     * @param \question_attempt $attempt
     * @return array|null correct_total (alternatives with a fraction above zero), selected_correct, selected_incorrect and recorded
     *                    (whether the response holds any `choiceN`); null for any other type of question
     */
    private function choices(\question_attempt $attempt): ?array {
        $question = $attempt->get_question();
        if (!$question instanceof \qtype_multichoice_multi_question) {
            return null;
        }
        $response = $attempt->get_last_qt_data();
        $total = 0;
        $correct = 0;
        $incorrect = 0;
        $recorded = false;
        foreach ($question->get_order($attempt) as $position => $answerid) {
            $iscorrect = (float) $question->answers[$answerid]->fraction > 0;
            $total += $iscorrect ? 1 : 0;
            if (array_key_exists("choice{$position}", $response)) {
                $recorded = true;
                if (!empty($response["choice{$position}"])) {
                    $correct += $iscorrect ? 1 : 0;
                    $incorrect += $iscorrect ? 0 : 1;
                }
            }
        }
        return [
            'correct_total' => $total,
            'selected_correct' => $correct,
            'selected_incorrect' => $incorrect,
            'recorded' => $recorded,
        ];
    }
}
