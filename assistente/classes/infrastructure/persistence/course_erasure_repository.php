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


namespace local_assistente\infrastructure\persistence;

/**
 * Deletes every row of the plugin that belongs to a course (T103; spec-017, DEC-42 D2, plan 003 "Callbacks"). This is the only place that
 * deletes history, besides the erasure of a student (T104); the repositories of the aggregates stay immutable.
 *
 * A row belongs to a course by a key of the schema, never by its name or by proximity:
 * - direct: `config`, `event`, `analysis`, `material`, `report` and `pedagogical_synthesis` have `course_id`;
 * - by `config_id`: `criteria`; by `event_id`: `processing`, and through it `processing_attempt` and `processing_context`;
 * - by `analysis_id` of the analyses of the course: `questionnaire_analysis`, `questionnaire_metric`, `question_result`, `forum_difficulty`,
 *   `performance`, `frequency`, `criteria_result` and `pedagogical_synthesis`; through them `questionnaire_question` (by
 *   `questionnaire_analysis_id`) and `frequency_activity` (by `frequency_id`);
 * - by `material_id` of the materials: `material_version`; by `report_id` of the reports: `report_version`.
 * The foreign keys of the schema are not enforced by the database, and `material.current_version` and `report.current_version` point to their
 * versions, so the children are deleted before the parents. A row of another course is never reached: every condition starts at the course.
 * A row that points to another course through a key that contradicts its own (an analysis of this course whose processing is of an event of
 * another course) is not reached either, because that cannot be told from the keys of the other course.
 *
 * The retry tasks of Moodle that hold a delivery of the course (redelivery of an event) go too, in the same transaction and after the rows of
 * the plugin (the erasure of a student also removes its tasks last, so two erasures lock rows in the same order), also the ones that have no
 * attempts left; they are in a table of Moodle, so they are not in TABLES and not in the count that is returned.
 *
 * All in one transaction: either every row of the course goes or none does. It holds the lock of the erasure of the course
 * (unit_of_work::course_erasure_resource()) from before the transaction until after its commit, and the retry task of a delivery holds the
 * same lock around its delivery, so the two never overlap. That is the only lock it takes, and it can fail: if a task keeps it for the whole
 * wait (30 seconds), the erasure throws lock_unavailable_exception with nothing deleted, and nothing repeats it by itself (see
 * course_observer). The deliveries that do not come from a retry task (observers, queue, handlers) take no such lock, and the rows an attempt
 * already in flight might still write after the deletion are a limitation (the next erasure of the same course removes them; erasing twice is
 * harmless). It only deletes, and returns how many rows each table lost.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_erasure_repository {
    /**
     * The tables of the plugin, in the order they are emptied: children before parents. Every table of the schema is here; a test keeps it so.
     */
    public const TABLES = [
        'local_assistente_frequency_activity',
        'local_assistente_frequency',
        'local_assistente_criteria_result',
        'local_assistente_performance',
        'local_assistente_forum_difficulty',
        'local_assistente_question_result',
        'local_assistente_questionnaire_metric',
        'local_assistente_questionnaire_question',
        'local_assistente_questionnaire_analysis',
        'local_assistente_pedagogical_synthesis',
        'local_assistente_analysis',
        'local_assistente_processing_attempt',
        'local_assistente_processing_context',
        'local_assistente_processing',
        'local_assistente_event',
        'local_assistente_material_version',
        'local_assistente_material',
        'local_assistente_report_version',
        'local_assistente_report',
        'local_assistente_criteria',
        'local_assistente_config',
    ];

    /** @var unit_of_work */
    private unit_of_work $uow;

    /**
     * Constructor.
     *
     * @param unit_of_work|null $uow
     */
    public function __construct(?unit_of_work $uow = null) {
        $this->uow = $uow ?? new unit_of_work();
    }

    /**
     * The condition that selects, in each table, the rows of a course.
     *
     * @return array<string, string> table => SQL condition with the :course parameter
     */
    private static function conditions(): array {
        $analyses = 'SELECT id FROM {local_assistente_analysis} WHERE course_id = :course';
        $processings = 'SELECT p.id FROM {local_assistente_processing} p JOIN {local_assistente_event} e ON e.id = p.event_id WHERE e.course_id = :course';
        return [
            'local_assistente_frequency_activity' => "frequency_id IN (SELECT id FROM {local_assistente_frequency} WHERE analysis_id IN ({$analyses}))",
            'local_assistente_frequency' => "analysis_id IN ({$analyses})",
            'local_assistente_criteria_result' => "analysis_id IN ({$analyses})",
            'local_assistente_performance' => "analysis_id IN ({$analyses})",
            'local_assistente_forum_difficulty' => "analysis_id IN ({$analyses})",
            'local_assistente_question_result' => "analysis_id IN ({$analyses})",
            'local_assistente_questionnaire_metric' => "analysis_id IN ({$analyses})",
            'local_assistente_questionnaire_question' => "questionnaire_analysis_id IN (SELECT id FROM {local_assistente_questionnaire_analysis} WHERE analysis_id IN ({$analyses}))",
            'local_assistente_questionnaire_analysis' => "analysis_id IN ({$analyses})",
            'local_assistente_pedagogical_synthesis' => "course_id = :course OR analysis_id IN ({$analyses})",
            'local_assistente_analysis' => 'course_id = :course',
            'local_assistente_processing_attempt' => "processing_id IN ({$processings})",
            'local_assistente_processing_context' => "processing_id IN ({$processings})",
            'local_assistente_processing' => "id IN ({$processings})",
            'local_assistente_event' => 'course_id = :course',
            'local_assistente_material_version' => 'material_id IN (SELECT id FROM {local_assistente_material} WHERE course_id = :course)',
            'local_assistente_material' => 'course_id = :course',
            'local_assistente_report_version' => 'report_id IN (SELECT id FROM {local_assistente_report} WHERE course_id = :course)',
            'local_assistente_report' => 'course_id = :course',
            'local_assistente_criteria' => 'config_id IN (SELECT id FROM {local_assistente_config} WHERE course_id = :course)',
            'local_assistente_config' => 'course_id = :course',
        ];
    }

    /**
     * Give each use of the course in a condition its own named parameter (Moodle does not accept the same name twice in a query).
     *
     * @param string $condition SQL with :course where the course goes
     * @param int $courseid
     * @return array [sql, params]
     */
    private static function bind(string $condition, int $courseid): array {
        $params = [];
        $n = 0;
        $sql = preg_replace_callback('/:course\b/', static function () use (&$params, &$n, $courseid) {
            $name = 'course' . ++$n;
            $params[$name] = $courseid;
            return ':' . $name;
        }, $condition);
        return [$sql, $params];
    }

    /**
     * Delete the rows of a course, all or nothing.
     *
     * @param int $courseid
     * @return array<string, int> rows deleted from each table, in the order of TABLES; all zero for a course with no data
     * @throws \InvalidArgumentException when the course is not a positive identifier
     * @throws lock_unavailable_exception when the lock of the erasure of the course does not come; nothing is deleted
     * @throws \dml_exception when the database fails; nothing is deleted then
     */
    public function delete_course_rows(int $courseid): array {
        global $DB;
        if ($courseid <= 0) {
            throw new \InvalidArgumentException('The course to erase must be a positive identifier.');
        }
        $conditions = self::conditions();
        return $this->uow->run(function () use ($DB, $courseid, $conditions): array {
            $deleted = [];
            foreach (self::TABLES as $table) {
                [$sql, $params] = self::bind($conditions[$table], $courseid);
                $count = $DB->count_records_select($table, $sql, $params);
                if ($count > 0) {
                    $DB->delete_records_select($table, $sql, $params);
                }
                $deleted[$table] = $count;
            }
            // The retry tasks of Moodle that hold a delivery of this course (also the ones with no attempts left) go with it, in this transaction
            // and last, like in the erasure of a student. They are in a table of Moodle, not of the plugin, so they are not among TABLES and not in
            // the count that is returned.
            (new redelivery_task_repository())->delete_of_course($courseid);
            return $deleted;
        }, [unit_of_work::course_erasure_resource($courseid)]);
    }
}
