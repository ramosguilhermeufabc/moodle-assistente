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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the memory of one run in the participation reader (A2, alternative A): the message counts of a forum and the finished attempts of
 * a quiz are read once for all students asked inside the run, with the same answers as reading them for each student, and nothing is kept
 * after the run.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(participation_reader::class)]
final class participation_reader_run_memo_test extends \advanced_testcase {
    /** @var int reads of the message counts of a forum */
    private int $forumreads = 0;

    /** @var int reads of the attempts of a quiz */
    private int $quizreads = 0;

    /**
     * A reader whose forum and quiz readers count their reads and give the real answers.
     *
     * @return participation_reader
     */
    private function reader(): participation_reader {
        $this->forumreads = 0;
        $this->quizreads = 0;
        $forums = new class ($this->forumreads) extends forum_reader {
            /** @var int */
            private int $reads;

            public function __construct(int &$reads) {
                $this->reads = &$reads;
            }

            #[\Override]
            public function get_post_counts(int $cmid): array {
                $this->reads++;
                return parent::get_post_counts($cmid);
            }
        };
        $quizzes = new class ($this->quizreads) extends questionnaire_reader {
            /** @var int */
            private int $reads;

            public function __construct(int &$reads) {
                $this->reads = &$reads;
            }

            #[\Override]
            public function get_attempts(int $cmid): array {
                $this->reads++;
                return parent::get_attempts($cmid);
            }
        };
        return new participation_reader(null, $forums, $quizzes);
    }

    /**
     * A forum with the given number of messages by each of the given users.
     *
     * @param \stdClass $course
     * @param int[] $messages user id => number of messages
     * @return int cmid
     */
    private function forum(\stdClass $course, array $messages): int {
        global $DB;
        $generator = $this->getDataGenerator();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => get_admin()->id,
        ]);
        $first = (int) $DB->get_field('forum_discussions', 'firstpost', ['id' => $discussion->id]);
        foreach ($messages as $userid => $count) {
            for ($i = 0; $i < $count; $i++) {
                $generator->get_plugin_generator('mod_forum')->create_post([
                    'discussion' => $discussion->id, 'parent' => $first, 'userid' => $userid, 'subject' => 'r', 'message' => 'm',
                ]);
            }
        }
        return (int) $forum->cmid;
    }

    /**
     * A quiz with attempts: user id => list of states ('finished', 'inprogress') or 'preview' for a finished preview.
     *
     * @param \stdClass $course
     * @param array<int, string[]> $attempts
     * @return int cmid
     */
    private function quiz(\stdClass $course, array $attempts): int {
        global $DB;
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $unique = (int) $DB->get_field_sql('SELECT COALESCE(MAX(uniqueid), 0) FROM {quiz_attempts}') + 1;
        foreach ($attempts as $userid => $states) {
            foreach (array_values($states) as $number => $state) {
                $DB->insert_record('quiz_attempts', (object) [
                    'quiz' => $quiz->id, 'userid' => $userid, 'attempt' => $number + 1, 'uniqueid' => $unique++, 'layout' => '',
                    'currentpage' => 0, 'preview' => $state === 'preview' ? 1 : 0, 'state' => $state === 'inprogress' ? 'inprogress' : 'finished',
                    'timestart' => time() - 100, 'timefinish' => $state === 'inprogress' ? 0 : time() - 50, 'timemodified' => time() - 50,
                    'timemodifiedoffline' => 0, 'sumgrades' => 1.0,
                ]);
            }
        }
        return (int) $quiz->cmid;
    }

    /**
     * Ask the participation of every user in a module.
     *
     * @param participation_reader $reader
     * @param int $courseid
     * @param int[] $userids
     * @param int $cmid
     * @return array[] user id => has_post, has_finished_attempt, participated, signals
     */
    private function ask(participation_reader $reader, int $courseid, array $userids, int $cmid): array {
        $answers = [];
        foreach ($userids as $userid) {
            $p = $reader->get_participation($courseid, $userid, $cmid);
            $answers[$userid] = [$p->has_post, $p->has_finished_attempt, $p->participated, $p->signals];
        }
        return $answers;
    }

    /**
     * Students of a course.
     *
     * @param \stdClass $course
     * @param int $count
     * @return int[]
     */
    private function students(\stdClass $course, int $count): array {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = (int) $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        }
        return $ids;
    }

    /**
     * 1. The answers inside the run are the ones given outside it, for a forum and a quiz with every kind of student: with messages, without,
     * with a finished attempt, with one in progress only, with a preview only.
     */
    public function test_answers_inside_the_run_equal_the_answers_outside(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$a, $b, $c, $d] = $this->students($course, 4);
        $forum = $this->forum($course, [$a => 2, $b => 1]);
        $quiz = $this->quiz($course, [$a => ['finished', 'inprogress'], $b => ['inprogress'], $c => ['preview']]);
        $users = [$a, $b, $c, $d];
        $reader = $this->reader();

        $outside = [$this->ask($reader, $course->id, $users, $forum), $this->ask($reader, $course->id, $users, $quiz)];
        $inside = $reader->with_run_memo(fn() => [
            $this->ask($reader, $course->id, $users, $forum), $this->ask($reader, $course->id, $users, $quiz),
        ]);

        $this->assertSame($outside, $inside);
        $this->assertTrue($inside[0][$a][0]);
        $this->assertFalse($inside[0][$d][0]);
        $this->assertTrue($inside[1][$a][1]);
        $this->assertFalse($inside[1][$b][1], 'An attempt in progress is not a finished attempt');
        $this->assertFalse($inside[1][$c][1], 'A preview is not a finished attempt');
    }

    /**
     * 2. A forum is read once for all the students inside the run, and once for each student outside it.
     */
    public function test_a_forum_is_read_once_for_many_students(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->students($course, 6);
        $forum = $this->forum($course, [$users[0] => 1]);
        $reader = $this->reader();

        $this->ask($reader, $course->id, $users, $forum);
        $this->assertSame(6, $this->forumreads, 'Outside a run: one read for each student');

        $this->forumreads = 0;
        $reader->with_run_memo(fn() => $this->ask($reader, $course->id, $users, $forum));
        $this->assertSame(1, $this->forumreads, 'Inside a run: one read');
    }

    /**
     * 3. A quiz is read once for all the students inside the run.
     */
    public function test_a_quiz_is_read_once_for_many_students(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->students($course, 6);
        $quiz = $this->quiz($course, [$users[0] => ['finished']]);
        $reader = $this->reader();

        $this->ask($reader, $course->id, $users, $quiz);
        $this->assertSame(6, $this->quizreads, 'Outside a run: one read for each student');

        $this->quizreads = 0;
        $reader->with_run_memo(fn() => $this->ask($reader, $course->id, $users, $quiz));
        $this->assertSame(1, $this->quizreads, 'Inside a run: one read');
    }

    /**
     * 4. A forum without messages and a quiz without attempts are read once too, and nobody participates in them.
     */
    public function test_empty_modules_are_read_once_and_nobody_participates(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->students($course, 4);
        $forum = $this->forum($course, []);
        $quiz = $this->quiz($course, []);
        $reader = $this->reader();

        $answers = $reader->with_run_memo(fn() => [
            $this->ask($reader, $course->id, $users, $forum), $this->ask($reader, $course->id, $users, $quiz),
        ]);

        $this->assertSame([1, 1], [$this->forumreads, $this->quizreads]);
        foreach ($answers as $module) {
            foreach ($module as $answer) {
                $this->assertSame([false, false, false, []], $answer);
            }
        }
    }

    /**
     * 5. Several modules in the course: one read for each module, and each module answers for itself.
     */
    public function test_each_module_is_read_once_and_answers_for_itself(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$a, $b, $c] = $this->students($course, 3);
        $forum1 = $this->forum($course, [$a => 1]);
        $forum2 = $this->forum($course, [$b => 1]);
        $quiz1 = $this->quiz($course, [$a => ['finished']]);
        $quiz2 = $this->quiz($course, [$c => ['finished']]);
        $users = [$a, $b, $c];
        $reader = $this->reader();

        $r = $reader->with_run_memo(fn() => [
            $this->ask($reader, $course->id, $users, $forum1), $this->ask($reader, $course->id, $users, $forum2),
            $this->ask($reader, $course->id, $users, $quiz1), $this->ask($reader, $course->id, $users, $quiz2),
        ]);

        $this->assertSame([2, 2], [$this->forumreads, $this->quizreads]);
        $this->assertSame([true, false, false], array_column($r[0], 0));
        $this->assertSame([false, true, false], array_column($r[1], 0));
        $this->assertSame([true, false, false], array_column($r[2], 1));
        $this->assertSame([false, false, true], array_column($r[3], 1));
    }

    /**
     * 6. Two runs do not share anything: what happens between them is seen by the second, and after a run the reader reads again.
     */
    public function test_two_runs_do_not_share_what_was_read(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$a] = $this->students($course, 1);
        $forum = $this->forum($course, []);
        $reader = $this->reader();

        $first = $reader->with_run_memo(fn() => $this->ask($reader, $course->id, [$a], $forum));
        $this->assertFalse($first[$a][0]);

        $forumrecord = get_coursemodule_from_id('forum', $forum);
        $discussion = (int) $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $forumrecord->instance, 'userid' => $a,
        ])->id;
        $this->assertGreaterThan(0, $discussion);

        $second = $reader->with_run_memo(fn() => $this->ask($reader, $course->id, [$a], $forum));
        $this->assertTrue($second[$a][0], 'The second run sees the message written after the first');
        $this->assertSame(2, $this->forumreads, 'One read for each run');

        $this->ask($reader, $course->id, [$a], $forum);
        $this->assertSame(3, $this->forumreads, 'After the run nothing is kept: the next call reads again');
    }

    /**
     * 7. Nothing leaks between courses: the same user, a message in a forum of one course and none in the forum of the other, in the same run.
     */
    public function test_nothing_leaks_between_courses(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $one = $generator->create_course();
        $two = $generator->create_course();
        $user = (int) $generator->create_and_enrol($one, 'student')->id;
        $generator->enrol_user($user, $two->id, 'student');
        $forumone = $this->forum($one, [$user => 1]);
        $forumtwo = $this->forum($two, []);
        $quizone = $this->quiz($one, [$user => ['finished']]);
        $quiztwo = $this->quiz($two, []);
        $reader = $this->reader();

        $r = $reader->with_run_memo(fn() => [
            $this->ask($reader, $one->id, [$user], $forumone), $this->ask($reader, $two->id, [$user], $forumtwo),
            $this->ask($reader, $one->id, [$user], $quizone), $this->ask($reader, $two->id, [$user], $quiztwo),
        ]);

        $this->assertTrue($r[0][$user][0]);
        $this->assertFalse($r[1][$user][0]);
        $this->assertTrue($r[2][$user][1]);
        $this->assertFalse($r[3][$user][1]);
    }

    /**
     * 8. The memory is dropped also when the work fails, and a call inside another run keeps the memory of the outer one.
     */
    public function test_the_memory_is_dropped_when_the_work_fails_and_nested_runs_share_it(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->students($course, 3);
        $forum = $this->forum($course, [$users[0] => 1]);
        $reader = $this->reader();

        try {
            $reader->with_run_memo(function () use ($reader, $course, $users, $forum) {
                $this->ask($reader, $course->id, $users, $forum);
                throw new \RuntimeException('fails in the middle');
            });
            $this->fail('The exception must come out');
        } catch (\RuntimeException $e) {
            $this->assertSame(1, $this->forumreads);
        }
        $this->ask($reader, $course->id, $users, $forum);
        $this->assertSame(4, $this->forumreads, 'The memory of the failed run is gone: one read for each student again');

        $this->forumreads = 0;
        $reader->with_run_memo(fn() => $reader->with_run_memo(fn() => $this->ask($reader, $course->id, $users, $forum)));
        $this->assertSame(1, $this->forumreads, 'A run inside a run reuses the memory');
    }
}
