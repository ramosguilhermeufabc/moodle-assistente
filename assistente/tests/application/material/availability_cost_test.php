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

namespace local_assistente\application\material;

use local_assistente\application\course_config_service;
use local_assistente\application\sync\initial_sync_service;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\student_reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Cost of the evaluation of the availability of the materials, counted in database reads (CL-48; T058; DEC-51; R-020-01).
 *
 * Decision of the responsible (03/11/2026): no real load test. The cost is measured here, in the test database, with the data
 * generator of Moodle, counting the reads with `$DB->perf_get_reads()`. Time is neither measured nor asserted, and the numbers are
 * reported (stderr) and registered in CL-48; they are not limits of the test. The assertions are deterministic:
 * - (a) the common case, a material visible to all, reads less than (b) the worst case, a material unavailable to all;
 * - (c) the initial synchronization creates exactly one `material_changed` event per material.
 *
 * Sizes: 40 students x 30 modules and 80 students x 60 modules (the sizes measured on 31/10/2026).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_module_reader::class)]
#[CoversClass(initial_sync_service::class)]
final class availability_cost_test extends \advanced_testcase {
    /**
     * Real transactions are used by the synchronization, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The sizes. The small ones are the permanent test; the sizes measured on 31/10/2026 (40 x 30 and 80 x 60) are added only when the
     * environment variable LOCAL_ASSISTENTE_CL48_FULL is set (one-off measurement of 03/11/2026, several minutes before DEC-72).
     *
     * @return array<string, int[]> students, modules
     */
    public static function sizes(): array {
        $sizes = ['10 students x 5 modules' => [10, 5], '20 students x 10 modules' => [20, 10]];
        if (getenv('LOCAL_ASSISTENTE_CL48_FULL')) {
            $sizes['40 students x 30 modules'] = [40, 30];
            $sizes['80 students x 60 modules'] = [80, 60];
        }
        return $sizes;
    }

    /**
     * A course with pages and enrolled students.
     *
     * @param int $students
     * @param int $modules
     * @param bool $visible whether the pages are visible to the students
     * @return \stdClass course and cmids
     */
    private function course(int $students, int $modules, bool $visible): \stdClass {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 1]);
        for ($i = 0; $i < $students; $i++) {
            $generator->create_and_enrol($course, 'student');
        }
        $cmids = [];
        for ($i = 0; $i < $modules; $i++) {
            $page = $generator->create_module('page', [
                'course' => $course->id, 'content' => '<p>Texto ' . $i . '</p>', 'visible' => $visible ? 1 : 0,
            ]);
            $cmids[] = (int) $page->cmid;
        }
        return (object) ['course' => $course, 'cmids' => $cmids];
    }

    /**
     * Database reads of evaluating the availability of every module of the course, one evaluation per module, as the queue does for each
     * `material_changed` event. The caches of the course are rebuilt before, so every size starts from the same state.
     *
     * @param \stdClass $data
     * @param bool $single evaluate only the first module (one `material_changed` event) instead of all of them
     * @return int[] reads, modules found available
     */
    private function evaluate(\stdClass $data, bool $single = false): array {
        global $DB;
        $studentids = (new student_reader())->get_students($data->course->id);
        $reader = new course_module_reader();
        rebuild_course_cache($data->course->id, true);
        \course_modinfo::clear_instance_cache();
        $before = $DB->perf_get_reads();
        $available = 0;
        foreach ($single ? [$data->cmids[0]] : $data->cmids as $cmid) {
            if ($reader->is_available_to_any_user($data->course->id, $cmid, $studentids)) {
                $available++;
            }
        }
        return [$DB->perf_get_reads() - $before, $available];
    }

    /**
     * (a) common case and (b) worst case: reads of the evaluation, for both sizes.
     *
     * @param int $students
     * @param int $modules
     */
    #[DataProvider('sizes')]
    public function test_reads_of_the_evaluation_of_the_availability(int $students, int $modules): void {
        $common = $this->course($students, $modules, true);
        $worst = $this->course($students, $modules, false);

        [$commonreads, $commonavailable] = $this->evaluate($common);
        [$worstreads, $worstavailable] = $this->evaluate($worst);
        [$commonone] = $this->evaluate($common, true);
        [$worstone, $worstoneavailable] = $this->evaluate($worst, true);

        fwrite(STDERR, sprintf(
            "\n[CL-48] %d students x %d modules: (a) common case %d reads, (b) worst case %d reads; one module only: (a) %d, (b) %d\n",
            $students,
            $modules,
            $commonreads,
            $worstreads,
            $commonone,
            $worstone
        ));
        $this->assertSame(0, $worstoneavailable);
        $this->assertLessThan($worstone, $commonone, 'The same holds for the evaluation of one module');
        $this->assertSame($modules, $commonavailable, 'Every visible module is available');
        $this->assertSame(0, $worstavailable, 'No hidden module is available to a student');
        $this->assertLessThan($worstreads, $commonreads, 'Stopping at the first student with access reads less than reading all students');
    }

    /**
     * The evaluation of one material in the worst case (b) grows in proportion to students x modules (DEC-72): between 10 x 5 and 20 x 10 the
     * product grows 4 times, and the reads must stay well below the 8 times of a cost proportional to students x modules squared.
     *
     * The sum over all the materials of a course (the initial synchronization) is N x M squared and is an accepted limitation (CL-48): it
     * happens once, when the course is enabled, in the background. The common case, releasing one material at a time, is one evaluation.
     */
    public function test_the_evaluation_of_one_material_grows_in_proportion_to_students_times_modules(): void {
        $small = $this->evaluate($this->course(10, 5, false), true)[0];
        $large = $this->evaluate($this->course(20, 10, false), true)[0];

        fwrite(STDERR, sprintf("\n[CL-48] worst case, one material, 10 x 5: %d reads, 20 x 10: %d reads, factor %.2f\n", $small, $large, $large / $small));
        $this->assertLessThan(6 * $small, $large, 'Four times the students x modules must not cost close to eight times the reads');
    }

    /**
     * (c) initial synchronization: one `material_changed` event per material, and the reads of the synchronization.
     *
     * @param int $students
     * @param int $modules
     */
    #[DataProvider('sizes')]
    public function test_initial_synchronization_creates_one_event_per_material(int $students, int $modules): void {
        global $DB;
        $data = $this->course($students, $modules, true);
        $sync = new initial_sync_service();
        $config = new course_config_service(null, null, null, null, $sync);

        $before = $DB->perf_get_reads();
        $this->assertTrue($config->enable($data->course->id));
        $reads = $DB->perf_get_reads() - $before;

        $events = $DB->count_records('local_assistente_event', ['course_id' => $data->course->id, 'event_type' => 'material_changed']);
        fwrite(STDERR, sprintf(
            "\n[CL-48] %d students x %d modules: (c) initial synchronization %d reads, %d material_changed events\n",
            $students,
            $modules,
            $reads,
            $events
        ));
        $this->assertSame($modules, $events, 'One material_changed event per material');
        $this->assertSame($modules, $sync->last_report()['material']);
    }
}
