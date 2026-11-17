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

require_once(__DIR__ . '/../../fixtures/minimal_pdf.php');

use local_assistente\application\queue\attempt_failure;
use local_assistente\fixtures\minimal_pdf;
use local_assistente\infrastructure\llm\fake_adapter;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\moodle\ytdlp_client;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\config_repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests of the material service and of its event handler (T063; quickstart 008 Q1, Q2, Q5, Q8, Q9, Q10; DEC-50, DEC-51).
 *
 * The LLM is always the simulated adapter (DEC-48). Written against the Moodle test framework; not executed outside a
 * Moodle 5.2 with PHPUnit.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(material_service::class)]
#[CoversClass(material_event_handler::class)]
final class material_service_test extends \advanced_testcase {
    /** @var material_repository */
    private material_repository $repository;

    /** @var fake_adapter */
    private fake_adapter $llm;

    /** @var material_service */
    private material_service $service;

    /**
     * Real transactions and locks are used, so the data cannot be reset by rollback.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->repository = new material_repository();
        $this->llm = new fake_adapter();
        $this->service = new material_service($this->repository, null, $this->llm);
    }

    /**
     * A course with a page.
     *
     * @param string $content
     * @return \stdClass course and cmid
     */
    private function page(string $content = '<p>Texto da aula</p>'): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => $content]);
        return (object) ['course' => $course, 'cmid' => (int) $page->cmid, 'instance' => (int) $page->id];
    }

    /**
     * Replace the file of a file resource.
     *
     * @param int $cmid
     * @param string $filename
     * @param string $content
     */
    private function put_file(int $cmid, string $filename, string $content): void {
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_resource', 'content');
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
        ], $content);
    }

    /**
     * Q1: a PDF becomes version 1, with a summary, completed and current (its text reaches the port, DEC-67).
     */
    public function test_pdf_creates_version_one_with_a_summary(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser(); // The resource generator needs a current user.
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $this->put_file((int) $resource->cmid, 'apostila.pdf', minimal_pdf::make('Texto do PDF'));

        $result = $this->service->process((int) $course->id, (int) $resource->cmid);

        $this->assertTrue($result->success);
        $material = $this->repository->find_by_cmid((int) $course->id, (int) $resource->cmid);
        $this->assertSame('pdf', $material->material_type);
        $this->assertSame('pending', $material->availability, 'No student is enrolled in this course, so nothing could be evaluated and the availability stays pending');
        $current = $this->repository->find_current_version($material);
        $this->assertSame(1, (int) $current->version);
        $this->assertSame('completed', $current->summary_status);
        $this->assertStringContainsString('[SIMULADO]', $current->summary);
        $this->assertSame(1, $this->llm->calls());
        $data = $this->llm->requests[0]['context']->data;
        $this->assertFalse($data['content']['extraction_pending'], 'The text of the PDF is extracted (DEC-67)');
        $this->assertStringContainsString('Texto do PDF', $data['content']['text']);
    }

    /**
     * Q1: a page becomes version 1; its text reaches the port.
     */
    public function test_page_creates_version_one(): void {
        $page = $this->page('<p>Fotossintese</p>');

        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);

        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertSame('text', $material->material_type);
        $this->assertSame(1, (int) $this->repository->find_current_version($material)->version);
        $data = $this->llm->requests[0]['context']->data;
        $this->assertStringContainsString('Fotossintese', $data['content']['text']);
        $this->assertFalse($data['content']['extraction_pending']);
        $this->assertSame('material_summary', $this->llm->requests[0]['context']->usecase->value);
    }

    /**
     * No change: processing again calls nothing and creates nothing.
     */
    public function test_same_content_does_nothing(): void {
        $page = $this->page();
        $this->service->process((int) $page->course->id, $page->cmid);

        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertTrue($result->success);
        $this->assertSame(1, $this->llm->calls());
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertCount(1, $this->repository->find_versions((int) $material->id));
    }

    /**
     * Q2 and Q10: replacing the text creates version 2, which becomes current while version 1 is preserved.
     */
    public function test_a_changed_text_creates_version_two_and_preserves_one(): void {
        global $DB;
        $page = $this->page('<p>Texto 1</p>');
        $this->llm->respond_valid(['summary' => 'resumo 1'])->respond_valid(['summary' => 'resumo 2']);
        $this->service->process((int) $page->course->id, $page->cmid);

        $DB->set_field('page', 'content', '<p>Texto 2</p>', ['id' => $page->instance]);
        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);

        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $versions = array_values($this->repository->find_versions((int) $material->id));
        $this->assertCount(2, $versions);
        $this->assertSame((int) $versions[1]->id, (int) $material->current_version);
        $this->assertSame('resumo 2', $this->repository->find_current_version($material)->summary);
        $this->assertSame('resumo 1', $versions[0]->summary, 'Version 1 is preserved, unchanged (Q5)');
        $this->assertSame('completed', $versions[0]->summary_status);
        $this->assertNotSame($versions[0]->content_hash, $versions[1]->content_hash);
    }

    /**
     * Q10: replacing the file, and changing the URL, each create a new version.
     */
    public function test_a_replaced_file_and_a_changed_url_create_versions(): void {
        global $DB;
        $client = new class extends ytdlp_client {
            #[\Override]
            public function available(): bool {
                return true;
            }

            #[\Override]
            public function metadata(string $url): ?array {
                return ['title' => 'Video', 'description' => '', 'subtitles' => [], 'automatic' => []];
            }
        };
        $service = new material_service(
            $this->repository,
            null,
            $this->llm,
            null,
            [new page_extractor(), new pdf_extractor(), new youtube_extractor(null, $client)]
        );
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser(); // The resource generator needs a current user.
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $this->put_file((int) $resource->cmid, 'a.pdf', minimal_pdf::make('Versao um'));
        $url = $this->getDataGenerator()->create_module('url', [
            'course' => $course->id, 'externalurl' => 'https://www.youtube.com/watch?v=aaa',
        ]);

        foreach ([(int) $resource->cmid, (int) $url->cmid] as $cmid) {
            $service->process((int) $course->id, $cmid);
        }
        $this->put_file((int) $resource->cmid, 'a.pdf', minimal_pdf::make('Versao dois'));
        $DB->set_field('url', 'externalurl', 'https://www.youtube.com/watch?v=bbb', ['id' => $url->id]);
        foreach ([(int) $resource->cmid, (int) $url->cmid] as $cmid) {
            $this->assertTrue($service->process((int) $course->id, $cmid)->success);
            $material = $this->repository->find_by_cmid((int) $course->id, $cmid);
            $this->assertCount(2, $this->repository->find_versions((int) $material->id), "cmid {$cmid}");
            $this->assertSame(2, (int) $this->repository->find_current_version($material)->version);
        }
    }

    /**
     * Q8: renaming creates no version, and the registered name is brought up to date.
     */
    public function test_rename_creates_no_version(): void {
        global $DB;
        $page = $this->page();
        $this->service->process((int) $page->course->id, $page->cmid);

        $DB->set_field('page', 'name', 'Novo nome', ['id' => $page->instance]);
        rebuild_course_cache((int) $page->course->id, true);
        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);

        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertSame('Novo nome', $material->name);
        $this->assertCount(1, $this->repository->find_versions((int) $material->id));
        $this->assertSame(1, $this->llm->calls());
    }

    /**
     * Q9: moving to another section updates the section and creates no version.
     */
    public function test_moving_to_another_section_creates_no_version(): void {
        $page = $this->page();
        $this->service->process((int) $page->course->id, $page->cmid);
        $section = $this->getDataGenerator()->create_course_section(['course' => $page->course->id, 'section' => 2]);

        (new \core_courseformat\local\cmactions(get_course((int) $page->course->id)))->move_end_section($page->cmid, (int) $section->id);
        rebuild_course_cache((int) $page->course->id, true);
        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);

        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertSame((int) $section->id, (int) $material->section_id);
        $this->assertCount(1, $this->repository->find_versions((int) $material->id));
        $this->assertSame(1, $this->llm->calls());
    }

    /**
     * Failure of the LLM on a new version: it is marked failed, the previous version and its summary stay current (DEC-50).
     */
    public function test_failure_keeps_the_previous_valid_summary(): void {
        global $DB;
        $page = $this->page('<p>Texto 1</p>');
        $this->llm->respond_valid(['summary' => 'resumo valido'])->fail_with('timeout');
        $this->service->process((int) $page->course->id, $page->cmid);

        $DB->set_field('page', 'content', '<p>Texto 2</p>', ['id' => $page->instance]);
        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TIMEOUT, $result->failure);
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $versions = array_values($this->repository->find_versions((int) $material->id));
        $this->assertSame('failed', $versions[1]->summary_status);
        $this->assertNull($versions[1]->summary);
        $this->assertSame((int) $versions[0]->id, (int) $material->current_version);
        $this->assertSame('resumo valido', $this->repository->find_current_version($material)->summary);
    }

    /**
     * Retry: the failed version of the same content is taken up again, with no duplicate, and then completes.
     */
    public function test_retry_resumes_the_failed_version(): void {
        $page = $this->page();
        $this->llm->fail_with('transport')->respond_valid(['summary' => 'resumo']);

        $first = $this->service->process((int) $page->course->id, $page->cmid);
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertFalse($first->success);
        $this->assertNull($material->current_version, 'No version is eligible after a failure of the first one');

        $second = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertTrue($second->success);
        $material = $this->repository->find($material->id);
        $versions = $this->repository->find_versions((int) $material->id);
        $this->assertCount(1, $versions, 'The same version was retried');
        $this->assertSame('completed', $this->repository->find_current_version($material)->summary_status);
    }

    /**
     * A page with no text has no content to summarize: the attempt fails and the port is not called.
     */
    public function test_empty_page_fails_without_calling_the_port(): void {
        $page = $this->page('<p>   </p>');

        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertFalse($result->success);
        $this->assertSame(0, $this->llm->calls());
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertSame('failed', array_values($this->repository->find_versions((int) $material->id))[0]->summary_status);
    }

    /**
     * An invalid answer of the LLM (no summary) fails the attempt and completes nothing.
     */
    public function test_invalid_answer_completes_nothing(): void {
        $page = $this->page();
        $this->llm->respond_with(['status' => 'valid', 'use_case' => 'material_summary', 'data' => ['summary' => '']]);

        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertFalse($result->success);
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertNull($material->current_version);
        $this->assertSame('failed', array_values($this->repository->find_versions((int) $material->id))[0]->summary_status);
    }

    /**
     * An exception of the port is a technical failure of the attempt, not a crash, and marks the version failed.
     */
    public function test_exception_of_the_port_is_a_technical_failure(): void {
        $page = $this->page();
        $this->llm->crash_with(new \RuntimeException('boom'));

        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertFalse($result->success);
        $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertSame('failed', array_values($this->repository->find_versions((int) $material->id))[0]->summary_status);
    }

    /**
     * The port is called with no transaction open.
     */
    public function test_port_is_called_outside_a_transaction(): void {
        global $DB;
        $page = $this->page();
        $open = null;
        $this->llm->respond_with_callback(function ($context) use (&$open, $DB) {
            $open = $DB->is_transaction_started();
            return ['status' => 'valid', 'use_case' => 'material_summary', 'data' => ['summary' => 'resumo']];
        });

        $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertFalse($open);
    }

    /**
     * A module that is not a functional material, or does not exist, is ignored with nothing registered.
     */
    public function test_a_module_that_is_not_a_material_is_ignored(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);

        $this->assertTrue($this->service->process((int) $course->id, (int) $label->cmid)->success);
        $this->assertTrue($this->service->process((int) $course->id, 99999999)->success);

        $this->assertSame(0, $DB->count_records('local_assistente_material'));
        $this->assertSame(0, $this->llm->calls());
    }

    /**
     * A course with a page and two students.
     *
     * @param string $content
     * @return \stdClass course, cmid, instance, s1 and s2
     */
    private function page_with_students(string $content = '<p>Texto da aula</p>'): \stdClass {
        $page = $this->page($content);
        $page->s1 = $this->getDataGenerator()->create_and_enrol($page->course, 'student');
        $page->s2 = $this->getDataGenerator()->create_and_enrol($page->course, 'student');
        return $page;
    }

    /**
     * Restrict a module to a group, so that only its member keeps access.
     *
     * @param \stdClass $page
     * @param \stdClass $member
     */
    private function restrict_to(\stdClass $page, \stdClass $member): void {
        global $DB;
        $group = $this->getDataGenerator()->create_group(['courseid' => $page->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&', 'showc' => [false], 'c' => [['type' => 'group', 'id' => (int) $group->id]],
        ]), ['id' => $page->cmid]);
        rebuild_course_cache((int) $page->course->id, true);
    }

    /**
     * Latest known availability of the material, and the availability recorded by each version, oldest first.
     *
     * @param \stdClass $page
     * @return array{0: string, 1: string[]}
     */
    private function availabilities(\stdClass $page): array {
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $versions = array_map(fn($v) => $v->availability, array_values($this->repository->find_versions((int) $material->id)));
        return [$material->availability, $versions];
    }

    /**
     * T058: available to every student, and available to only part of them, are `available`; the version records it.
     */
    public function test_availability_all_and_partial_are_available(): void {
        $page = $this->page_with_students();
        $this->service->process((int) $page->course->id, $page->cmid);
        $this->assertSame(['available', ['available']], $this->availabilities($page));

        $partial = $this->page_with_students();
        $this->restrict_to($partial, $partial->s1);
        $this->service->process((int) $partial->course->id, $partial->cmid);
        $this->assertSame(['available', ['available']], $this->availabilities($partial), 'Partial availability is available');
    }

    /**
     * T058: available to nobody is `unavailable`; with no students, or not yet evaluated, it stays `pending`.
     */
    public function test_availability_none_is_unavailable_and_no_students_is_pending(): void {
        $hidden = $this->page_with_students();
        set_coursemodule_visible($hidden->cmid, 0);
        $this->service->process((int) $hidden->course->id, $hidden->cmid);
        $this->assertSame(['unavailable', ['unavailable']], $this->availabilities($hidden));

        $nostudents = $this->page();
        $this->service->process((int) $nostudents->course->id, $nostudents->cmid);
        $this->assertSame(['pending', ['pending']], $this->availabilities($nostudents), 'No students: nothing is determined');
    }

    /**
     * T058: with no students the value held stays; the absence of students is not unavailability.
     */
    public function test_no_students_keeps_the_value_held(): void {
        $page = $this->page_with_students();
        $this->service->process((int) $page->course->id, $page->cmid);
        $this->assertSame(['available', ['available']], $this->availabilities($page));

        $service = new material_service($this->repository, null, $this->llm, null, null, null, new class extends student_reader {
            #[\Override]
            public function get_students(int $courseid, ?int $groupid = null): array {
                return [];
            }
        });
        $this->assertTrue($service->process((int) $page->course->id, $page->cmid)->success);

        $this->assertSame(['available', ['available']], $this->availabilities($page));
    }

    /**
     * T058: both transitions, with no content change: no version is created, no summary requested, and the history is intact.
     */
    public function test_reevaluation_moves_the_material_both_ways_without_a_version(): void {
        $page = $this->page_with_students();
        $this->service->process((int) $page->course->id, $page->cmid);
        $this->assertSame(['available', ['available']], $this->availabilities($page));

        set_coursemodule_visible($page->cmid, 0);
        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);
        $this->assertSame(['unavailable', ['available']], $this->availabilities($page), 'available to unavailable');

        set_coursemodule_visible($page->cmid, 1);
        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);
        $this->assertSame(['available', ['available']], $this->availabilities($page), 'unavailable to available');
        $this->assertSame(1, $this->llm->calls(), 'A change of availability alone creates no version and asks for no summary');
    }

    /**
     * T058: a version records what was observed when it was created and never changes afterwards, while the material follows.
     */
    public function test_each_version_keeps_the_availability_observed_at_its_creation(): void {
        global $DB;
        $page = $this->page_with_students('<p>Texto 1</p>');
        $this->service->process((int) $page->course->id, $page->cmid);

        set_coursemodule_visible($page->cmid, 0);
        $DB->set_field('page', 'content', '<p>Texto 2</p>', ['id' => $page->instance]);
        $this->service->process((int) $page->course->id, $page->cmid);
        $this->assertSame(['unavailable', ['available', 'unavailable']], $this->availabilities($page));

        set_coursemodule_visible($page->cmid, 1);
        $this->service->process((int) $page->course->id, $page->cmid);
        $this->assertSame(['available', ['available', 'unavailable']], $this->availabilities($page));
    }

    /**
     * T058: a retry takes up the version of the same content again and does not rewrite what it recorded.
     */
    public function test_retry_does_not_rewrite_the_availability_of_the_version(): void {
        $page = $this->page_with_students();
        $this->llm->fail_with('transport')->respond_valid(['summary' => 'resumo']);
        $this->assertFalse($this->service->process((int) $page->course->id, $page->cmid)->success);
        $this->assertSame(['available', ['available']], $this->availabilities($page));

        set_coursemodule_visible($page->cmid, 0);
        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);

        $this->assertSame(['unavailable', ['available']], $this->availabilities($page));
    }

    /**
     * T058: a failure of the evaluation changes nothing that is persisted and does not stop the version or the summary.
     */
    public function test_failed_evaluation_neither_changes_the_state_nor_stops_the_processing(): void {
        global $DB;
        $page = $this->page_with_students('<p>Texto 1</p>');
        $failing = new material_service($this->repository, null, $this->llm, null, null, new class extends course_module_reader {
            #[\Override]
            public function is_available_to_any_user(int $courseid, int $cmid, array $userids): bool {
                throw new \RuntimeException('cannot read the course');
            }
        });

        [$log, $result] = $this->capture_log(fn() => $failing->process((int) $page->course->id, $page->cmid));

        $this->assertTrue($result->success);
        $this->assertStringContainsString('availability of a material: evaluation failed', $log);
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertSame(['pending', ['pending']], $this->availabilities($page), 'Not determined: pending, in both');
        $this->assertSame('completed', $this->repository->find_current_version($material)->summary_status);

        $this->service->process((int) $page->course->id, $page->cmid);
        $this->assertSame(['available', ['pending']], $this->availabilities($page), 'The material is evaluated later; the version keeps pending');

        $DB->set_field('page', 'content', '<p>Texto 2</p>', ['id' => $page->instance]);
        [$again, $result] = $this->capture_log(fn() => $failing->process((int) $page->course->id, $page->cmid));
        $this->assertTrue($result->success);
        $this->assertSame('', $again, 'The same failure of the same material is written once by the service');
        $this->assertSame(['available', ['pending', 'pending']], $this->availabilities($page), 'The value held stays; the new version is pending');
    }

    /**
     * Run something and return what it wrote to the error log, with its result.
     *
     * @param callable $work
     * @return array{0: string, 1: mixed} the log and the result of the work
     */
    private function capture_log(callable $work): array {
        $logfile = tempnam(sys_get_temp_dir(), 't058');
        $previous = ini_set('error_log', $logfile);
        try {
            $result = $work();
        } finally {
            ini_set('error_log', (string) $previous);
        }
        $log = (string) file_get_contents($logfile);
        unlink($logfile);
        return [$log, $result];
    }

    /**
     * A service whose writing of the availability of the material fails, after a successful evaluation.
     *
     * @return material_service
     */
    private function service_that_cannot_write_availability(): material_service {
        return new class ($this->repository, null, $this->llm) extends material_service {
            #[\Override]
            protected function record_availability(int $materialid, \local_assistente\domain\material\material_availability $availability): void {
                throw new \RuntimeException('the database refused the write');
            }
        };
    }

    /**
     * T058: the evaluation succeeds and the writing of the material fails. The persisted state stays as it was, the version and the
     * summary go on as usual, the version records what was observed, no earlier version changes, the failure is logged, and a later
     * run brings the material up to date.
     *
     * Simulated failure: a subclass of the service throws a RuntimeException in the write of the availability. It is a test double, not a
     * failure of the database, and it is not evidence of CR-07 (atomicity under a real failure of the write) nor of CR-12 (lock timeout).
     * Those are proved by I-03 to I-05 and M-07 (CL-07, correction 3).
     */
    #[TestDox('Simulated failure (subclass that throws) in the write of the material: keeps the state, processing goes on')]
    public function test_failed_persistence_keeps_the_state_and_does_not_stop_the_processing(): void {
        global $DB;
        $page = $this->page_with_students('<p>Texto 1</p>');
        $this->service->process((int) $page->course->id, $page->cmid);
        $this->assertSame(['available', ['available']], $this->availabilities($page));
        $failing = $this->service_that_cannot_write_availability();

        set_coursemodule_visible($page->cmid, 0);
        $DB->set_field('page', 'content', '<p>Texto 2</p>', ['id' => $page->instance]);
        [$log, $result] = $this->capture_log(fn() => $failing->process((int) $page->course->id, $page->cmid));

        $this->assertTrue($result->success, 'The version and the summary go on');
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $versions = array_values($this->repository->find_versions((int) $material->id));
        $this->assertSame('available', $material->availability, 'The persisted state is intact');
        $this->assertSame(['available', 'unavailable'], array_map(fn($v) => $v->availability, $versions), 'Version 1 untouched; version 2 records the observation');
        $this->assertSame('completed', $versions[1]->summary_status);
        $this->assertSame((int) $versions[1]->id, (int) $material->current_version, 'The new version became current');
        $this->assertSame(2, $this->llm->calls());
        $this->assertSame(1, substr_count($log, 'persistence failed'), $log);
        $this->assertStringContainsString("course {$page->course->id}, material {$material->id}, cmid {$page->cmid}", $log);
        $this->assertStringContainsString('RuntimeException', $log);
        $this->assertStringContainsString('the database refused the write', $log);

        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);
        $this->assertSame(['unavailable', ['available', 'unavailable']], $this->availabilities($page), 'Brought up to date by a later run');
    }

    /**
     * T058: a real failure of the writing, with the lock of the material held by someone else: the persisted state stays, nothing else
     * changes, and the failure is logged with the kind of exception.
     *
     * Lock held in the SAME process: the refusal comes from the internal map of the lock factory and not from GET_LOCK on the server, so
     * despite the name this is not a lock held by another process. It tests how the service handles lock_unavailable_exception and is not
     * evidence of CR-07 nor of CR-12; the real exclusion is M-07 (unit_of_work_concurrency_test) (CL-07, correction 3).
     */
    #[TestDox('Lock held in the same process: the service keeps the state and logs the failure (not evidence of CR-07 or CR-12)')]
    public function test_real_lock_failure_on_the_material_keeps_the_state(): void {
        $repository = new \local_assistente\infrastructure\persistence\material_repository(
            new \local_assistente\infrastructure\persistence\unit_of_work(1)
        );
        $service = new material_service($repository, null, $this->llm);
        $page = $this->page_with_students();
        $service->process((int) $page->course->id, $page->cmid);
        $material = $repository->find_by_cmid((int) $page->course->id, $page->cmid);
        set_coursemodule_visible($page->cmid, 0);

        $lock = \local_assistente\infrastructure\persistence\unit_of_work::lock_factory()->get_lock(
            \local_assistente\infrastructure\persistence\unit_of_work::material_resource((int) $material->id),
            1
        );
        $this->assertNotFalse($lock);
        try {
            [$log, $result] = $this->capture_log(fn() => $service->process((int) $page->course->id, $page->cmid));
        } finally {
            $lock->release();
        }

        $this->assertTrue($result->success);
        $this->assertSame(['available', ['available']], $this->availabilities($page));
        $this->assertSame(1, $this->llm->calls());
        $this->assertStringContainsString('persistence failed', $log);
        $this->assertStringContainsString('lock_unavailable_exception', $log);
    }

    /**
     * T058: the line written for a failure identifies it and says nothing about the material content or about a student.
     */
    public function test_failure_log_has_no_content_and_no_personal_data(): void {
        $page = $this->page_with_students('<p>Conteudo reservado da aula</p>');
        $failing = $this->service_that_cannot_write_availability();

        [$log, ] = $this->capture_log(fn() => $failing->process((int) $page->course->id, $page->cmid));

        $this->assertSame(1, substr_count(trim($log), "\n") + 1, 'One line');
        $this->assertStringContainsString('local_assistente: availability of a material: persistence failed', $log);
        $this->assertStringContainsString('material_service_test.php:', $log, 'Where it was raised');
        $this->assertStringNotContainsString('Conteudo reservado', $log);
        foreach ([$page->s1, $page->s2] as $student) {
            foreach ([$student->username, $student->email, $student->firstname, $student->lastname, fullname($student)] as $personal) {
                $this->assertStringNotContainsString((string) $personal, $log);
            }
        }
    }

    /**
     * T058: a failure of the same kind in another material, or of another operation in the same one, is written again: only the
     * repetition of the same failure is held back.
     */
    public function test_failure_log_is_written_again_for_another_material_or_operation(): void {
        $first = $this->page_with_students();
        $second = $this->page_with_students();
        $failing = $this->service_that_cannot_write_availability();

        [$log, ] = $this->capture_log(function () use ($failing, $first, $second) {
            $failing->process((int) $first->course->id, $first->cmid);
            $failing->process((int) $first->course->id, $first->cmid);
            $failing->process((int) $second->course->id, $second->cmid);
        });

        $this->assertSame(2, substr_count($log, 'persistence failed'), $log);
    }

    /**
     * T058: the snapshot does not depend on this value (DEC-26): evaluated here as available, a restriction applied later and not
     * yet reevaluated leaves the persisted value stale, and the snapshot still sees the restriction.
     */
    public function test_snapshot_is_independent_of_the_persisted_availability(): void {
        $page = $this->page_with_students();
        $this->service->process((int) $page->course->id, $page->cmid);
        $this->restrict_to($page, $page->s1);
        $this->assertSame('available', $this->availabilities($page)[0], 'No event, no reevaluation: the persisted value is stale');

        $builder = new \local_assistente\application\context\context_snapshot_builder($this->repository);
        $section = (int) get_fast_modinfo((int) $page->course->id)->get_cm($page->cmid)->sectionid;
        $snapshot = $builder->build((int) $page->course->id, $section, [(int) $page->s1->id, (int) $page->s2->id]);

        $this->assertFalse($snapshot['materials'][0]['eligible']);
        $this->assertSame('partial', $snapshot['materials'][0]['availability']['coverage']);
        $this->assertSame('available', $this->availabilities($page)[0], 'The snapshot writes nothing');
    }

    /**
     * The event handler reads the cmid of the payload and the course of the event; an invalid payload fails the attempt.
     */
    public function test_event_handler(): void {
        $page = $this->page();
        $handler = new material_event_handler($this->service);
        $event = (object) [
            'course_id' => $page->course->id,
            'payload' => json_encode(['cmid' => $page->cmid, 'moodle_event' => 'course_module_created', 'occurred_at' => 5]),
        ];

        $this->assertTrue($handler->handle($event, (object) [])->success);
        $this->assertNotNull($this->repository->find_by_cmid((int) $page->course->id, $page->cmid));

        foreach (['', 'not json', json_encode(['cmid' => 'x']), json_encode(['cmid' => 0]), json_encode([])] as $payload) {
            $result = $handler->handle((object) ['course_id' => $page->course->id, 'payload' => $payload], (object) []);
            $this->assertFalse($result->success, $payload);
            $this->assertSame(attempt_failure::TECHNICAL, $result->failure);
        }
    }

    /**
     * The default registry processes the material event.
     */
    public function test_default_registry_has_the_material_handler(): void {
        $registry = \local_assistente\application\queue\handler_registry::default();

        $this->assertInstanceOf(
            material_event_handler::class,
            $registry->handler_for(\local_assistente\domain\event\event_type::MATERIAL_CHANGED)
        );
    }

    /**
     * The versions of a material and its pointer, read from the database.
     *
     * @param int $materialid
     * @return array
     */
    private function persisted(int $materialid): array {
        global $DB;
        return [
            (array) $DB->get_record('local_assistente_material', ['id' => $materialid]),
            array_map(fn($v) => (array) $v, $DB->get_records('local_assistente_material_version', ['material_id' => $materialid], 'id ASC')),
        ];
    }

    /**
     * Q18, branch "completed and not current" (DEC-62; T144): the version is already the current one, so the switch is refused by the
     * rule. The handler ends in success, the port is not called again, the version is not marked failed or pending, and nothing changes.
     *
     * The material is made to look like a new version by recording another type for it (the detector compares the type too): the version of
     * the same content is taken up again, it is completed, and only the switch is left, which the rule refuses.
     */
    public function test_q18_rejection_of_a_completed_version_is_a_success_without_a_new_call(): void {
        global $DB;
        $page = $this->page();
        $this->llm->respond_valid(['summary' => 'resumo']);
        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertSame(1, $this->llm->calls());
        $DB->set_field('local_assistente_material', 'material_type', 'pdf', ['id' => $material->id]);
        $before = $this->persisted((int) $material->id);

        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertTrue($result->success, 'A refusal by the rule is not a failure of the attempt: ' . var_export($result->message, true));
        $this->assertNull($result->failure);
        $this->assertStringContainsString('already current', (string) $result->message);
        $this->assertSame(1, $this->llm->calls(), 'The port must not be called again');
        $this->assertSame($before, $this->persisted((int) $material->id), 'Nothing changes: no failed, no pending, no new version, same pointer');
        $this->assertSame('completed', $this->repository->find_current_version($this->repository->find((int) $material->id))->summary_status);
    }

    /**
     * CL-51: the reconciliation marks the version failed while the LLM answers (the version was read as pending, the lock is not held
     * meanwhile). The same attempt takes it up again with the summary it has: failed, pending, completed, current; one call to the LLM, no
     * new version, and the attempt succeeds.
     */
    public function test_a_version_failed_while_the_llm_answers_is_finished_by_the_same_attempt(): void {
        $page = $this->page();
        $this->llm->respond_with_callback(function ($context) use ($page) {
            $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
            $version = array_values($this->repository->find_versions((int) $material->id))[0];
            $this->assertTrue($this->repository->fail_pending_version((int) $version->id));
            return ['status' => 'valid', 'use_case' => 'material_summary', 'data' => ['summary' => 'resumo salvo']];
        });

        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertTrue($result->success, 'The attempt must succeed: ' . var_export($result->message, true));
        $this->assertSame(1, $this->llm->calls());
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $versions = array_values($this->repository->find_versions((int) $material->id));
        $this->assertCount(1, $versions);
        $this->assertSame('completed', $versions[0]->summary_status);
        $this->assertSame('resumo salvo', $versions[0]->summary);
        $this->assertSame((int) $versions[0]->id, (int) $material->current_version);
    }

    /**
     * Q18, branch "summary completed, then the switch": while the summary is being produced a newer version becomes current (a valid race).
     * The summary of the version is persisted and it stays completed, as history; the handler ends in success; the port is called only
     * once (the call that produced the summary); no version is failed; the pointer that won the race stays.
     */
    public function test_q18_rejection_after_the_summary_is_a_success_and_keeps_the_winner(): void {
        $page = $this->page();
        $rival = 0;
        $this->llm->respond_with_callback(function ($context) use (&$rival, $page) {
            // Another process finishes a newer version of the same material first.
            $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
            $rival = $this->repository->add_version((int) $material->id, 'rival-hash', \local_assistente\domain\material\material_availability::AVAILABLE);
            $this->repository->complete_summary($rival, 'resumo do rival');
            $this->repository->switch_current((int) $material->id, $rival);
            return ['status' => 'valid', 'use_case' => 'material_summary', 'data' => ['summary' => 'resumo da versao 1']];
        });

        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertTrue($result->success, 'A refusal by the rule is not a failure of the attempt: ' . var_export($result->message, true));
        $this->assertNull($result->failure);
        $this->assertStringContainsString('newer version (2) is current', (string) $result->message);
        $this->assertSame(1, $this->llm->calls(), 'Only the call that produced the summary');
        $this->assertSame($rival, (int) $material->current_version, 'The pointer that won the race stays');
        $versions = array_values($this->repository->find_versions((int) $material->id));
        $this->assertSame(['completed', 'completed'], array_map(fn($v) => $v->summary_status, $versions), 'No version is failed or pending');
        $this->assertSame('resumo da versao 1', $versions[0]->summary, 'The refused version keeps its summary, as history');
        $this->assertSame('resumo do rival', $versions[1]->summary);
    }

    /**
     * Q18 (complement), branch "completed and not current", refusal because a NEWER version became current: the version is completed and
     * not current when the service decides to only switch the pointer, and before it asks for the lock of the material another process
     * makes version 3 current. The repository itself refuses the switch (version_not_newer_exception, not a double). The handler ends in
     * success with the reason, the port is not called, version 2 stays completed with its summary (neither failed nor pending, no retry),
     * and version 3 stays current.
     *
     * The interleaving is SIMULATED IN A SINGLE PROCESS: there is no second process here. It is placed at the exact point where it can
     * happen, between the decision of the branch and the switch, by a lock factory that runs the "other process" (plain calls of the
     * repository, in this same process and connection) when the service asks for the lock of the material (the same injection as
     * record_locks() of the lock observer). Nothing of production is replaced: the lock, the transaction and the rule are the real ones.
     * This test shows how the service reacts to the refusal; that the rule holds between independent processes is shown by Q17
     * (material_monotonic_concurrency_test: M-12 and M-13), not by this test.
     */
    public function test_q18_rejection_of_a_completed_version_by_a_newer_current_is_a_success_without_a_new_call(): void {
        global $DB;
        $page = $this->page('<p>Texto 1</p>');
        $this->llm->respond_valid(['summary' => 'resumo 1']);
        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $materialid = (int) $material->id;
        $first = (int) $material->current_version;

        // Version 2: the content of the page now, completed but not current (the switch of an earlier attempt did not happen).
        $DB->set_field('page', 'content', '<p>Texto 2</p>', ['id' => $page->instance]);
        $hash = (new \local_assistente\infrastructure\moodle\material_reader())->get_material((int) $page->course->id, $page->cmid)->content_hash;
        $second = $this->repository->add_version($materialid, $hash, \local_assistente\domain\material\material_availability::AVAILABLE);
        $this->repository->complete_summary($second, 'resumo 2');
        $this->assertSame($first, (int) $this->repository->find($materialid)->current_version);
        $this->assertSame('completed', $this->repository->find_version_by_id($second)->summary_status);

        // The "other process" (simulated, same process): a newer version, completed, made current when the lock of the material is asked for.
        $real = \local_assistente\infrastructure\persistence\unit_of_work::lock_factory();
        $resource = \local_assistente\infrastructure\persistence\unit_of_work::material_resource($materialid);
        $repository = $this->repository;
        $newer = 0;
        $runs = 0;
        $factory = new class (\local_assistente\infrastructure\persistence\unit_of_work::LOCK_TYPE, $real, $resource, function () use ($repository, $materialid, &$newer, &$runs) {
            $runs++;
            $newer = $repository->add_version($materialid, 'hash-newer', \local_assistente\domain\material\material_availability::AVAILABLE);
            $repository->complete_summary($newer, 'resumo 3');
            $repository->switch_current($materialid, $newer);
        }) implements \core\lock\lock_factory {
            /** @var \Closure|null the simulated other process, run once, before the first request of the lock */
            private ?\Closure $before;

            /**
             * Constructor (the type is the one the interface asks for; the rest is optional, as in recording_lock_factory).
             *
             * @param string $type
             * @param \core\lock\lock_factory|null $real the factory of the plugin
             * @param string $resource the lock that starts the other process
             * @param \Closure|null $before the other process
             */
            public function __construct($type, private ?\core\lock\lock_factory $real = null, private string $resource = '', ?\Closure $before = null) {
                $this->before = $before;
            }

            #[\Override]
            public function supports_timeout() {
                return $this->real->supports_timeout();
            }

            #[\Override]
            public function supports_auto_release() {
                return $this->real->supports_auto_release();
            }

            #[\Override]
            public function is_available() {
                return $this->real->is_available();
            }

            #[\Override]
            public function get_lock($resource, $timeout, $maxlifetime = 86400) {
                if ($resource === $this->resource && $this->before !== null) {
                    $before = $this->before;
                    $this->before = null; // Disarmed first: the other process takes this same lock through this factory.
                    $before();
                }
                return $this->real->get_lock($resource, $timeout, $maxlifetime);
            }

            #[\Override]
            public function release_lock(\core\lock\lock $lock) {
                return $this->real->release_lock($lock);
            }
        };
        $calls = $this->llm->calls();
        (new \ReflectionProperty(\local_assistente\infrastructure\persistence\unit_of_work::class, 'factory'))->setValue(null, $factory);
        try {
            $result = $this->service->process((int) $page->course->id, $page->cmid);
        } finally {
            \local_assistente\infrastructure\persistence\unit_of_work::reset_lock_factory();
        }

        $this->assertSame(1, $runs, 'The other process ran once, at the request of the lock of the material');
        $this->assertTrue($result->success, 'A refusal by the rule is not a failure of the attempt: ' . var_export($result->message, true));
        $this->assertNull($result->failure);
        $this->assertStringContainsString('version 2 is kept as history: a newer version (3) is current', (string) $result->message);
        $this->assertSame($calls, $this->llm->calls(), 'The port must not be called again');
        $this->assertSame($newer, (int) $this->repository->find($materialid)->current_version, 'The newer version stays current');
        $versions = array_values($this->repository->find_versions($materialid));
        $this->assertSame([1, 2, 3], array_map(fn($v) => (int) $v->version, $versions), 'No version was created or lost (no retry)');
        $this->assertSame(['completed', 'completed', 'completed'], array_map(fn($v) => $v->summary_status, $versions), 'None failed or pending');
        $this->assertSame(['resumo 1', 'resumo 2', 'resumo 3'], array_map(fn($v) => $v->summary, $versions), 'The summaries are intact');
        $this->assertFalse($DB->is_transaction_started());
    }

    /**
     * Synthesis requests of a course (T145): the `synthesis_requested` events and the section each one asks for.
     *
     * @param int $courseid
     * @return int[] section ids, in the order of the events
     */
    private function synthesis_requests(int $courseid): array {
        global $DB;
        $events = $DB->get_records('local_assistente_event', ['course_id' => $courseid, 'event_type' => 'synthesis_requested'], 'id ASC');
        return array_values(array_map(fn($e) => (int) json_decode($e->payload, true)['section_id'], $events));
    }

    /**
     * A course with the assistant enabled and a page, so that a request of synthesis is queued (T145).
     *
     * @param int|null $courseid an existing course to add another page to
     * @return \stdClass course, cmid, instance and section (id of the course section of the page)
     */
    private function enabled_page(?int $courseid = null): \stdClass {
        global $DB;
        if ($courseid === null) {
            $page = $this->page();
            (new config_repository())->create((int) $page->course->id, true);
        } else {
            $course = get_course($courseid);
            $created = $this->getDataGenerator()->create_module('page', ['course' => $courseid, 'content' => '<p>Outra aula</p>']);
            $page = (object) ['course' => $course, 'cmid' => (int) $created->cmid, 'instance' => (int) $created->id];
        }
        $page->section = (int) $DB->get_field('course_modules', 'section', ['id' => $page->cmid], MUST_EXIST);
        return $page;
    }

    /**
     * T145 (a): a new version that ends completed and current asks for one synthesis of the (course, section) of the material.
     */
    public function test_new_completed_version_requests_the_synthesis_of_its_section(): void {
        $page = $this->enabled_page();

        $this->assertTrue($this->service->process((int) $page->course->id, $page->cmid)->success);

        $this->assertSame([$page->section], $this->synthesis_requests((int) $page->course->id));
    }

    /**
     * T145 (b): two materials of the same section completed before the cycle are one pending event (DEC-21).
     */
    public function test_two_materials_of_a_section_are_one_pending_request(): void {
        $first = $this->enabled_page();
        $second = $this->enabled_page((int) $first->course->id);
        $this->assertSame($first->section, $second->section, 'Both pages are in the same section');

        $this->assertTrue($this->service->process((int) $first->course->id, $first->cmid)->success);
        $this->assertTrue($this->service->process((int) $first->course->id, $second->cmid)->success);

        $this->assertSame([$first->section], $this->synthesis_requests((int) $first->course->id));
    }

    /**
     * T145 (c): a change of availability alone creates no version, so no synthesis is requested.
     */
    public function test_availability_change_alone_requests_no_synthesis(): void {
        global $DB;
        $page = $this->enabled_page();
        $page->s1 = $this->getDataGenerator()->create_and_enrol($page->course, 'student');
        $this->service->process((int) $page->course->id, $page->cmid);
        $DB->delete_records('local_assistente_event', ['event_type' => 'synthesis_requested']);

        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&', 'showc' => [false], 'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]],
        ]), ['id' => $page->cmid]);
        rebuild_course_cache((int) $page->course->id, true);
        $result = $this->service->process((int) $page->course->id, $page->cmid);
        $material = $this->repository->find_by_cmid((int) $page->course->id, $page->cmid);
        $this->assertSame('unavailable', $material->availability, 'The availability did change');

        $this->assertTrue($result->success);
        $this->assertSame(1, $this->llm->calls(), 'No new version: the summary was asked for only once');
        $this->assertSame([], $this->synthesis_requests((int) $page->course->id));
    }

    /**
     * T145 (d): a failed summary leaves the version failed and requests no synthesis.
     */
    public function test_failed_summary_requests_no_synthesis(): void {
        $page = $this->enabled_page();
        $this->llm->fail_with('timeout');

        $result = $this->service->process((int) $page->course->id, $page->cmid);

        $this->assertFalse($result->success);
        $this->assertSame([], $this->synthesis_requests((int) $page->course->id));
    }
}

