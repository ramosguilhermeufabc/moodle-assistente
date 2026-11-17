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


namespace local_assistente\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_assistente\application\privacy\erasure_pending_exception;
use local_assistente\application\privacy\student_data_eraser;
use local_assistente\application\privacy\student_data_exporter;
use local_assistente\application\privacy\student_erasure_service;
use local_assistente\domain\privacy\erasure_key;
use local_assistente\infrastructure\persistence\student_data_repository;

/**
 * Privacy API provider of the teaching assistant (T104; spec-017, DEC-42 D1, DEC-34 D3, DEC-56, DEC-57).
 *
 * The data of a student are in the course context: individual results, entries in snapshots and the events that name the student.
 * - Export: the individual results of the student in each course context asked, and the entries of the student in the snapshots. The preserved
 *   texts are not exported.
 * - Deletion by user and by context: student_data_eraser, for the course contexts asked (contexts of another level hold nothing). It
 *   deletes the individual results, neutralizes the events, takes the student out of the snapshots, redacts the name in the five preserved texts
 *   (DEC-56) and neutralizes the identifier in failure messages. For a user it is one transaction under the locks of the student; for a context
 *   or a list of users it is one transaction for each student, and a failure stops at that student with the earlier ones done (repeat the request).
 *   The deletion of the whole course is the other mechanism (T103).
 * - The record of the operation is the one of tool_dataprivacy, which keeps the request. What the provider adds is a line of counts, without
 *   the student, through trace(), which goes to the output of the task that runs the request (mtrace) unless a test replaces it.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    /** @var string[][] the tables that hold personal data and their fields; the strings are privacy:metadata:<table> and :<field> */
    public const TABLES = [
        'local_assistente_performance' => ['student_id', 'activity_id', 'grade'],
        'local_assistente_frequency' => ['student_id', 'section_id', 'total_activities', 'participating_activities', 'percentage'],
        'local_assistente_frequency_activity' => ['activity_id', 'participated', 'reference_date'],
        'local_assistente_criteria_result' => ['student_id', 'activity_status', 'forum_status', 'frequency_status'],
        'local_assistente_processing_context' => ['context_json'],
        'local_assistente_event' => ['payload', 'response'],
        'local_assistente_processing_attempt' => ['response'],
        'local_assistente_analysis' => ['summary'],
        'local_assistente_forum_difficulty' => ['description'],
        'local_assistente_pedagogical_synthesis' => ['content'],
        'local_assistente_material_version' => ['summary'],
        'local_assistente_report_version' => ['snapshot_json'],
    ];

    /** @var callable|null replacement for the line of counts, used by tests */
    private static $tracer = null;

    /**
     * Replace the sink of the line of counts. Pass null to restore mtrace.
     *
     * @param callable|null $tracer receives the line
     */
    public static function use_tracer(?callable $tracer): void {
        self::$tracer = $tracer;
    }

    #[\Override]
    public static function get_metadata(collection $collection): collection {
        foreach (self::TABLES as $table => $fields) {
            $described = [];
            foreach ($fields as $field) {
                $described[$field] = "privacy:metadata:{$table}:{$field}";
            }
            $collection->add_database_table($table, $described, "privacy:metadata:{$table}");
        }
        // The language model service (DEC-60: OpenRouter). No identifier of a student is sent (DEC-34 D3).
        $collection->add_external_location_link('openrouter', [
            'model' => 'privacy:metadata:openrouter:model',
            'context' => 'privacy:metadata:openrouter:context',
            'instruction' => 'privacy:metadata:openrouter:instruction',
        ], 'privacy:metadata:openrouter');
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = (new student_data_repository())->course_context_sql((new student_data_repository())->course_ids_with_data($userid));
        if ($sql !== null) {
            $contextlist->add_from_sql($sql[0], $sql[1]);
        }
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_COURSE) {
            $userlist->add_users((new student_data_repository())->user_ids_in_course((int) $context->instanceid));
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        $courses = self::course_ids($contextlist->get_contextids());
        $data = (new student_data_exporter())->export((int) $contextlist->get_user()->id, array_values($courses));
        foreach ($courses as $contextid => $courseid) {
            if (!isset($data[$courseid])) {
                continue;
            }
            $context = \context::instance_by_id($contextid);
            $path = [get_string('privacy:path', 'local_assistente')];
            foreach (['performance', 'frequency', 'criteria', 'snapshots'] as $part) {
                if ($data[$courseid][$part]) {
                    writer::with_context($context)->export_data(
                        array_merge($path, [get_string("privacy:path:{$part}", 'local_assistente')]),
                        (object) ['records' => self::formatted($data[$courseid][$part])]
                    );
                }
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $courseid = (int) $context->instanceid;
        $targets = [];
        foreach ((new student_data_repository())->user_ids_in_course($courseid) as $userid) {
            $targets[] = [(int) $userid, [$courseid]];
        }
        self::erase_each($targets);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $courses = array_values(self::course_ids($contextlist->get_contextids()));
        if ($courses) {
            self::erase_each([[(int) $contextlist->get_user()->id, $courses]]);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $targets = [];
        foreach ($userlist->get_userids() as $userid) {
            $targets[] = [(int) $userid, [(int) $context->instanceid]];
        }
        self::erase_each($targets);
    }

    /**
     * Erase each student on its own (T142; DEC-58). A student whose erasure fails for lack of a lock gets a retry task and does not stop
     * the others; a student whose name is unavailable is marked as incomplete; any other failure is kept and thrown after the others were
     * processed. The Privacy API of Moodle ends the request as deleted whatever is thrown, so the exception exists to warn the data
     * protection officer: it has fixed text and counts only.
     *
     * @param array<int, array{0: int, 1: int[]}> $targets each a student and the courses of the erasure
     * @throws \Throwable the first failure that is not transient
     * @throws erasure_pending_exception when some erasure was deferred or is incomplete
     */
    private static function erase_each(array $targets): void {
        $deferred = 0;
        $incomplete = 0;
        $failure = null;
        $cause = null;
        foreach ($targets as [$userid, $courseids]) {
            try {
                $done = (new student_erasure_service())->request(new erasure_key($userid, $courseids));
            } catch (\Throwable $e) {
                $failure ??= $e;
                continue;
            }
            if ($done['outcome'] === student_erasure_service::DEFERRED) {
                $deferred++;
                $cause ??= $done['cause'];
                self::trace('local_assistente privacy: the erasure of a student was deferred and a retry was scheduled');
                continue;
            }
            $incomplete += $done['outcome'] === student_erasure_service::INCOMPLETE ? 1 : 0;
            self::trace(student_data_eraser::summary($done['result']));
        }
        if ($failure !== null) {
            throw $failure;
        }
        if ($deferred > 0 || $incomplete > 0) {
            throw new erasure_pending_exception($deferred, $incomplete, $cause);
        }
    }

    /**
     * Record a line of counts, through the task output of Moodle or the sink of the tests.
     *
     * @param string $line
     */
    private static function trace(string $line): void {
        self::$tracer !== null ? (self::$tracer)($line) : mtrace($line);
    }

    /**
     * Course ids of the course contexts of a list, by context id.
     *
     * @param int[] $contextids
     * @return array<int, int> context id => course id
     */
    private static function course_ids(array $contextids): array {
        $courses = [];
        foreach ($contextids as $contextid) {
            $context = \context::instance_by_id($contextid, IGNORE_MISSING);
            if ($context && $context->contextlevel === CONTEXT_COURSE) {
                $courses[(int) $contextid] = (int) $context->instanceid;
            }
        }
        return $courses;
    }

    /**
     * Rows for the export, with the dates in the format of the Privacy API.
     *
     * @param array $rows
     * @return array
     */
    private static function formatted(array $rows): array {
        $format = static function ($value, $key) use (&$format) {
            if (is_object($value) || is_array($value)) {
                $value = (array) $value;
                foreach ($value as $k => $v) {
                    $value[$k] = $format($v, $k);
                }
                return $value;
            }
            return in_array($key, ['created_at', 'last_access', 'reference_date'], true) && is_numeric($value) && (int) $value > 0
                ? transform::datetime((int) $value) : $value;
        };
        return array_map(static fn($row) => $format($row, ''), array_values($rows));
    }
}
