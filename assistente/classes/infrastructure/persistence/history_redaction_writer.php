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

use local_assistente\domain\privacy\name_redactor;

/**
 * The only code that changes preserved history, and only for the erasure of a student (T104; DEC-42 D1, DEC-56, DEC-57). The repositories of
 * the aggregates stay immutable: none of them gets a way to change a row. architecture_test keeps this class reachable only from
 * application/privacy.
 *
 * It does three things and no other, each one on columns fixed in the code:
 * - redact_course_texts(): replace the name of the student by the marker in the five texts that DEC-56 lists (`analysis.summary`,
 *   `forum_difficulty.description`, `pedagogical_synthesis.content`, `material_version.summary`, `report_version.snapshot_json`) of the rows of
 *   a course, writing a row only when it changed;
 * - remove_snapshot_entries(): take the entries of the student out of `processing_context.context_json` (DEC-42 D1): the identifier and the
 *   individual values of an individual snapshot, and the element of the student in the lists `course_access` and `students`; the rest of the
 *   JSON, its keys and its order stay;
 * - neutralize_messages(): in `processing_attempt.response` and `event.response`, replace the number that follows "student" or "user" by 0
 *   when it is the identifier of the student, as the messages of the repositories write it ("The student 58 does not exist."); the rest of the
 *   message is kept.
 * An id, a version, a status, a date or a relation is never written. It takes no lock and opens no transaction: the caller holds both
 * (student_event_eraser).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_redaction_writer {
    /** @var string[] the keys of an individual snapshot that are the student's own and go with the student */
    private const INDIVIDUAL_KEYS = [
        'grade_performance' => ['student_id', 'course_total_percent', 'activities_with_grade'],
        'criteria_check' => [
            'student_id', 'activity_value', 'frequency_value', 'forums_due', 'forums_missing', 'performance_analysis_id', 'frequency_analysis_id',
        ],
    ];

    /**
     * The texts of a course and the SQL that reads them: [column, SQL that returns `id` and `text` for one course].
     *
     * @return array<string, array{0: string, 1: string}> table => [column, sql]
     */
    private static function text_sources(): array {
        return [
            'local_assistente_analysis' => ['summary', 'SELECT id, summary AS text FROM {local_assistente_analysis} WHERE course_id = :course AND summary IS NOT NULL'],
            'local_assistente_forum_difficulty' => ['description', 'SELECT d.id, d.description AS text FROM {local_assistente_forum_difficulty} d JOIN {local_assistente_analysis} a ON a.id = d.analysis_id WHERE a.course_id = :course'],
            'local_assistente_pedagogical_synthesis' => ['content', 'SELECT id, content AS text FROM {local_assistente_pedagogical_synthesis} WHERE course_id = :course'],
            'local_assistente_material_version' => ['summary', 'SELECT v.id, v.summary AS text FROM {local_assistente_material_version} v JOIN {local_assistente_material} m ON m.id = v.material_id WHERE m.course_id = :course AND v.summary IS NOT NULL'],
            'local_assistente_report_version' => ['snapshot_json', 'SELECT v.id, v.snapshot_json AS text FROM {local_assistente_report_version} v JOIN {local_assistente_report} r ON r.id = v.report_id WHERE r.course_id = :course'],
        ];
    }

    /**
     * How many times the names appear in the preserved texts of a course. It writes nothing: it reads the same five sources that
     * redact_course_texts() redacts, with the same rule, to tell whether any text still cites the student.
     *
     * @param int $courseid
     * @param name_redactor $redactor
     * @return int occurrences found
     */
    public function count_name_occurrences(int $courseid, name_redactor $redactor): int {
        global $DB;
        if (!$redactor->has_names()) {
            return 0;
        }
        $found = 0;
        foreach (self::text_sources() as [$column, $sql]) {
            $rows = $DB->get_recordset_sql($sql, ['course' => $courseid]);
            foreach ($rows as $row) {
                $found += ($column === 'snapshot_json' ? $redactor->redact_json((string) $row->text) : $redactor->redact((string) $row->text))[1];
            }
            $rows->close();
        }
        return $found;
    }

    /**
     * Redact the name of the students in the preserved texts of a course.
     *
     * @param int $courseid
     * @param name_redactor $redactor
     * @return array<string, array{texts: int, occurrences: int}> by `table.column`: rows changed and occurrences replaced
     */
    public function redact_course_texts(int $courseid, name_redactor $redactor): array {
        global $DB;
        $result = [];
        foreach (self::text_sources() as $table => [$column, $sql]) {
            $key = $table . '.' . $column;
            $result[$key] = ['texts' => 0, 'occurrences' => 0];
            if (!$redactor->has_names()) {
                continue;
            }
            $rows = $DB->get_recordset_sql($sql, ['course' => $courseid]);
            $changes = [];
            foreach ($rows as $row) {
                [$text, $count] = $column === 'snapshot_json' ? $redactor->redact_json((string) $row->text) : $redactor->redact((string) $row->text);
                if ($count > 0) {
                    $changes[(int) $row->id] = [$text, $count];
                }
            }
            $rows->close();
            foreach ($changes as $id => [$text, $count]) {
                $DB->set_field($table, $column, $text, ['id' => $id]);
                $result[$key]['texts']++;
                $result[$key]['occurrences'] += $count;
            }
        }
        return $result;
    }

    /**
     * Take the entries of a student out of the snapshots of some courses.
     *
     * @param int $userid
     * @param int[] $courseids
     * @param student_data_repository $data
     * @return int snapshots changed
     */
    public function remove_snapshot_entries(int $userid, array $courseids, student_data_repository $data): int {
        global $DB;
        $changed = 0;
        foreach ($data->context_rows($userid, $courseids) as $row) {
            $json = json_decode((string) $row->context_json, true);
            if (!is_array($json)) {
                continue;
            }
            if (($json['student_id'] ?? null) === $userid) {
                $drop = self::INDIVIDUAL_KEYS[$json['kind'] ?? ''] ?? ['student_id'];
                $json = array_diff_key($json, array_flip($drop));
            }
            foreach (['course_access', 'students'] as $list) {
                if (is_array($json[$list] ?? null)) {
                    $json[$list] = array_values(array_filter(
                        $json[$list],
                        static fn($entry) => !(is_array($entry) && ($entry['student_id'] ?? null) === $userid)
                    ));
                }
            }
            $DB->set_field(
                'local_assistente_processing_context',
                'context_json',
                json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
                ['id' => $row->id]
            );
            $changed++;
        }
        return $changed;
    }

    /**
     * Neutralize the identifier of a student in the failure messages of the events and attempts of some courses.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return int messages changed
     */
    public function neutralize_messages(int $userid, array $courseids): int {
        global $DB;
        if (!$courseids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED, 'course');
        $pattern = '/\b(student|user)(\s+#?)' . $userid . '\b/i';
        $changed = 0;
        $sources = [
            'local_assistente_event' => "SELECT e.id, e.response AS text FROM {local_assistente_event} e WHERE e.course_id {$insql} AND e.response IS NOT NULL",
            'local_assistente_processing_attempt' => "SELECT t.id, t.response AS text FROM {local_assistente_processing_attempt} t
                JOIN {local_assistente_processing} p ON p.id = t.processing_id JOIN {local_assistente_event} e ON e.id = p.event_id
               WHERE e.course_id {$insql} AND t.response IS NOT NULL",
        ];
        foreach ($sources as $table => $sql) {
            $rows = $DB->get_recordset_sql($sql, $params);
            $edits = [];
            foreach ($rows as $row) {
                $new = preg_replace($pattern, '${1}${2}0', (string) $row->text, -1, $count);
                if ($count > 0) {
                    $edits[(int) $row->id] = $new;
                }
            }
            $rows->close();
            foreach ($edits as $id => $text) {
                $DB->set_field($table, 'response', $text, ['id' => $id]);
                $changed++;
            }
        }
        return $changed;
    }
}
