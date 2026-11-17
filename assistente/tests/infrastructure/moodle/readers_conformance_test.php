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

/**
 * Conformance of the readers of Moodle data (gate G-004; quickstart 004 Q3 and Q4; spec-004 §Regras).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class readers_conformance_test extends \advanced_testcase {
    /** @var string[] reader classes of this layer, one per source of Moodle data (plan 004), and the one of the source status of the history (T102) */
    private const READERS = [
        course_section_reader::class,
        student_reader::class,
        course_module_reader::class,
        questionnaire_reader::class,
        forum_reader::class,
        gradebook_reader::class,
        participation_reader::class,
        material_reader::class,
        source_record_reader::class,
        erasure_audit_reader::class,
    ];

    /**
     * Absolute path of the source of a class of the plugin.
     *
     * @param string $class
     * @return string
     */
    private static function source_path(string $class): string {
        global $CFG;
        $relative = str_replace('local_assistente\\', '', $class);
        return $CFG->dirroot . '/local/assistente/classes/' . str_replace('\\', '/', $relative) . '.php';
    }

    /**
     * Reader classes found in the directory, to make sure none is left out of the list above.
     *
     * @return string[]
     */
    private static function reader_classes_on_disk(): array {
        global $CFG;
        $classes = [];
        foreach (glob($CFG->dirroot . '/local/assistente/classes/infrastructure/moodle/*_reader.php') as $file) {
            $classes[] = __NAMESPACE__ . '\\' . basename($file, '.php');
        }
        sort($classes);
        return $classes;
    }

    /**
     * The list of readers is complete: the eight of plan 004, one per source of data, the reader of the records the history points to (T102) and the reader of the audit events of the erasures (T142).
     */
    public function test_the_readers_exist(): void {
        $expected = self::READERS;
        sort($expected);
        $this->assertCount(10, self::READERS);
        $this->assertSame($expected, self::reader_classes_on_disk());
    }

    /**
     * Q3: running every reader does not create a row in any table of the plugin.
     */
    public function test_readers_do_not_write_plugin_tables(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $quiz = $generator->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 1]);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $student->id,
        ]);

        $before = [];
        foreach (array_filter($DB->get_tables(false), static fn($name) => str_starts_with($name, 'local_assistente_')) as $table) {
            $before[$table] = $DB->count_records($table);
        }

        $sections = new course_section_reader();
        $sections->get_course($course->id);
        $sections->get_sections($course->id);
        $sections->resolve_module_section($course->id, $page->cmid);
        $sections->validate_section($course->id, 987654);

        $students = new student_reader();
        $students->get_students($course->id);
        $students->visible_students($course->id, (int) $teacher->id);
        $students->get_groups($course->id);

        $modules = new course_module_reader();
        $modules->get_modules($course->id, (int) $student->id);

        $questionnaires = new questionnaire_reader();
        $questionnaires->get_questionnaire($quiz->cmid);
        $questionnaires->get_slots($quiz->cmid);
        $questionnaires->get_attempts($quiz->cmid);

        $forums = new forum_reader();
        $forums->get_discussions($forum->cmid);
        $forums->get_messages((int) $discussion->id);
        $forums->get_post_counts($forum->cmid);

        $gradebook = new gradebook_reader();
        $gradebook->get_items($course->id);
        $gradebook->get_final_grade($course->id, $assign->cmid, (int) $student->id);
        $gradebook->get_course_total($course->id, (int) $student->id);

        $participation = new participation_reader();
        $participation->get_participation($course->id, (int) $student->id, $assign->cmid);
        $participation->get_last_course_access($course->id, (int) $student->id);

        $materials = new material_reader();
        $materials->get_materials($course->id);

        $after = [];
        foreach (array_keys($before) as $table) {
            $after[$table] = $DB->count_records($table);
        }
        $this->assertCount(21, $before);
        $this->assertSame($before, $after, 'The readers must not write to the plugin tables');
    }

    /**
     * Q4: each reader documents the Moodle API it uses and, when it uses $DB, the justification of the DML.
     */
    public function test_each_reader_documents_its_api_or_the_dml_justification(): void {
        $problems = [];
        foreach (self::READERS as $class) {
            $doc = (new \ReflectionClass($class))->getDocComment() ?: '';
            if (!str_contains($doc, 'Moodle API:')) {
                $problems[] = "{$class}: the class docblock must have a 'Moodle API:' paragraph";
            }
            if (str_contains(file_get_contents(self::source_path($class)), '$DB') && !str_contains($doc, 'DML justification:')) {
                $problems[] = "{$class}: it uses \$DB, so the class docblock must have a 'DML justification:' paragraph";
            }
        }
        $this->assertSame([], $problems);
    }

    /**
     * Readers never write: no insert, update or delete through DML (the gradebook recalculation is a documented
     * call of the Moodle API, not DML of the reader).
     */
    public function test_readers_contain_no_write_operations(): void {
        $problems = [];
        foreach (self::READERS as $class) {
            $code = '';
            foreach (token_get_all(file_get_contents(self::source_path($class))) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }
            if (preg_match('/->\s*(insert_record|update_record|delete_records|set_field|execute|import_record|bulk_insert_records|trigger)\s*\(/', $code, $match)) {
                $problems[] = "{$class}: {$match[1]}()";
            }
        }
        $this->assertSame([], $problems);
    }
}
