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

namespace local_assistente\application\query;

use local_assistente\domain\result_state;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\quiz_initial_repository;
use local_assistente\infrastructure\persistence\quiz_results_repository;

/**
 * Query of the metrics of the questionnaires (T110; spec-018 contract "métricas de questionários", DEC-35 D2, DEC-36, DEC-42 D3).
 *
 * For each questionnaire of the course it shows the CURRENT executions: the coverage and the complexity of each question (initial
 * analysis, spec-010, scale 0.0 to 1.0) and the results (spec-011): average correctness, correct and incorrect counts and partial result of
 * each question, and the mean, population standard deviation, minimum and maximum of the questionnaire. A questionnaire with no execution,
 * and a question nobody has answered, are "sem dados", never zero. "sem materiais" appears only where the snapshot of the initial analysis
 * listed no material (DEC-35 D2): when the snapshot is missing, the coverage 0.0 is shown as a real zero, since the absence of materials
 * cannot be asserted. A `partial_result` that does not apply (a question with one correct answer) is null, not zero.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_metrics_query extends aggregate_query {
    /**
     * Constructor; every collaborator can be replaced by tests.
     *
     * @param \local_assistente\application\access_service|null $access
     * @param \local_assistente\application\course_config_service|null $config
     * @param presentation|null $presentation
     * @param quiz_initial_repository|null $initial
     * @param quiz_results_repository|null $results
     * @param event_repository|null $events
     * @param course_module_reader|null $modules
     */
    public function __construct(
        ?\local_assistente\application\access_service $access = null,
        ?\local_assistente\application\course_config_service $config = null,
        ?presentation $presentation = null,
        private ?quiz_initial_repository $initial = null,
        private ?quiz_results_repository $results = null,
        private ?event_repository $events = null,
        private ?course_module_reader $modules = null
    ) {
        parent::__construct($access, $config, $presentation);
        $this->initial ??= new quiz_initial_repository();
        $this->results ??= new quiz_results_repository();
        $this->events ??= new event_repository();
        $this->modules ??= new course_module_reader();
    }

    /**
     * The metrics of the questionnaires of a course.
     *
     * @param int $courseid
     * @param int $userid the user that queries
     * @param int|null $groupid must be null (aggregate query)
     * @return array{available: bool, message: string|null, rows: array} each row has `cmid`, `quiz`, `coverage`, `metrics` and `questions`
     */
    public function query(int $courseid, int $userid, ?int $groupid = null): array {
        return $this->run($courseid, $userid, $groupid, function () use ($courseid): array {
            $rows = [];
            foreach ($this->modules->get_modules($courseid) as $module) {
                if ($module->modname === 'quiz') {
                    $rows[] = ['cmid' => $module->cmid, 'quiz' => $module->name] + $this->quiz($courseid, $module->cmid);
                }
            }
            return $rows;
        });
    }

    /**
     * The current coverage, metrics and questions of a questionnaire.
     *
     * @param int $courseid
     * @param int $cmid
     * @return array{coverage: array, metrics: array, questions: array}
     */
    private function quiz(int $courseid, int $cmid): array {
        $p = $this->presentation;
        $nodata = $this->shown(result_state::NO_DATA, $p->value(result_state::NO_DATA, null));
        $initials = $this->initial->find_for_quiz($courseid, $cmid);
        $coverage = $nodata;
        $analysed = [];
        foreach (array_values($initials) as $index => $analysis) {
            foreach ($this->initial->find_questions((int) $analysis->id) as $row) {
                $analysed[(int) $row->id] = $row;
            }
            $qa = $this->initial->find_questionnaire_analysis((int) $analysis->id);
            if ($index === 0 && $qa !== null) {
                $coverage = $this->shown(result_state::VALID, $p->coverage(result_state::VALID, $qa->coverage, $this->had_materials($analysis)));
            }
        }

        $current = $this->results->find_current($courseid, $cmid);
        $metric = $current === null ? null : $this->results->find_metric((int) $current->id);
        $metrics = ['mean' => $nodata, 'population_standard_deviation' => $nodata, 'minimum' => $nodata, 'maximum' => $nodata];
        if ($metric !== null) {
            foreach (array_keys($metrics) as $field) {
                $metrics[$field] = $this->shown(result_state::VALID, $p->value(result_state::VALID, $metric->{$field}));
            }
        }

        $byrow = [];
        foreach ($current === null ? [] : $this->results->find_question_results((int) $current->id) as $result) {
            $byrow[(int) $result->questionnaire_question_id][] = $result;
        }
        $questions = [];
        foreach ($analysed as $id => $row) {
            foreach ($byrow[$id] ?? [null] as $result) {
                $questions[] = $this->question($row, $result, $nodata);
            }
        }
        return ['coverage' => $coverage, 'metrics' => $metrics, 'questions' => $questions];
    }

    /**
     * One question: its complexity and, when someone answered, its results.
     *
     * @param \stdClass $row the analysed row (complexity)
     * @param \stdClass|null $result the result of the current execution, null when nobody has answered
     * @param array $nodata
     * @return array
     */
    private function question(\stdClass $row, ?\stdClass $result, array $nodata): array {
        $p = $this->presentation;
        $valid = static fn(string $text): array => ['state' => result_state::VALID->value, 'text' => $text];
        return [
            'question_id' => (int) ($result->question_id ?? $row->question_id),
            'question_version' => (int) ($result->question_version ?? $row->question_version),
            'complexity' => $valid($p->complexity(result_state::VALID, $row->complexity)),
            'average_correctness' => $result === null ? $nodata : $valid($p->value(result_state::VALID, $result->average_correctness)),
            'correct_count' => $result === null ? $nodata : $valid((string) (int) $result->correct_count),
            'incorrect_count' => $result === null ? $nodata : $valid((string) (int) $result->incorrect_count),
            'partial_result' => $result === null ? $nodata : ($result->partial_result === null ? null : $valid($p->value(result_state::VALID, $result->partial_result))),
        ];
    }

    /**
     * Whether the snapshot of the initial analysis listed at least one material; true when the snapshot cannot be read, since the absence
     * of materials cannot then be asserted.
     *
     * @param \stdClass $analysis
     * @return bool
     */
    private function had_materials(\stdClass $analysis): bool {
        $context = $this->events->find_context((int) $analysis->processing_id);
        $snapshot = $context === null ? null : json_decode((string) $context->context_json, true);
        return !is_array($snapshot) || !array_key_exists('materials', $snapshot) || !is_array($snapshot['materials']) || $snapshot['materials'] !== [];
    }
}
