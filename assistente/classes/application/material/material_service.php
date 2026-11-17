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

use local_assistente\application\llm\instruction_builder;
use local_assistente\application\llm\llm_result_adapter;
use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\handler_result;
use local_assistente\application\synthesis\synthesis_trigger;
use local_assistente\domain\llm\llm_context;
use local_assistente\domain\llm\llm_use_case;
use local_assistente\domain\material\change_detector;
use local_assistente\domain\material\material_availability;
use local_assistente\domain\material\material_state;
use local_assistente\domain\material\summary_status;
use local_assistente\domain\material_type;
use local_assistente\infrastructure\llm\adapter_factory;
use local_assistente\infrastructure\llm\llm_port;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\material_reader;
use local_assistente\infrastructure\moodle\student_reader;
use local_assistente\infrastructure\persistence\material_repository;
use local_assistente\infrastructure\persistence\version_not_newer_exception;

/**
 * Keeps the versions and the summaries of the materials of a course (spec-008; T063; DEC-33, DEC-50, DEC-51).
 *
 * For one module, in this order:
 * 1. Detection: what Moodle holds now is compared with what was recorded for the current version (change_detector). Only a
 *    change of the processable content creates a version; a new name or section only updates the material.
 * 2. A new version is persisted `pending`. It is not current, and a new version does not imply a completed summary.
 * 3. The summary is requested from the LLM port, outside any transaction.
 * 4. Success: the summary is persisted and the version marked `completed` (one transaction), and only then does it become the
 *    current version. Failure: the version is marked `failed`; the previous version, with its valid summary, stays current.
 *
 * Detection, a successful processing and eligibility are separate states: a version can exist and be pending or failed without
 * being eligible.
 *
 * Availability (T058; DEC-51). Each call also brings up to date `material.availability`, the latest known availability of the
 * material: `available` when at least one student of the course reaches the module (partial availability is `available`),
 * `unavailable` when none does, evaluated with course_module_reader::is_available_to_any_user(), the per-user rule of the snapshot. With no
 * students, or when the evaluation fails, nothing is determined and the value held stays (a new material stays `pending`). A new
 * version records the value observed at that moment (`pending` when nothing was determined) and keeps it forever. A change of
 * availability alone creates no version, and a failed evaluation never stops the version or the summary. This value does not
 * decide eligibility: the context uses the availability evaluated in the snapshot, for the students of each analysis (DEC-26,
 * T064). Availability is independent of the summary status and of the pending extraction of PDF and YouTube content.
 *
 * Monotonicity (DEC-62; T144): a version refused as current because its number is not greater than the current one is a business
 * outcome. The handler ends in success, with the reason; it does not call the port again, mark the version failed or ask for another
 * attempt. Technical failures (database, lock) keep following the failure path.
 *
 * Synthesis (T145; DEC-41 D3, DEC-21): when a new version ends completed and current, the synthesis of the section of the material is requested
 * (synthesis_trigger::request_section()); a pending request for the same course and section is reused. Nothing is requested when only the
 * availability, the name or the section changed (no new version), when the version failed, or when it did not become current because a newer
 * one is (DEC-62). A version that is already current (a retry after a failed request) asks again. The synthesis handler is not changed: it
 * decides whether a synthesis is created.
 *
 * Retry: the queue calls again for the same event. A version of the same content that is pending or failed is taken up again
 * instead of creating a duplicate; one that is already completed is only made current.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class material_service {
    /** @var string what the LLM is asked to do; the language of the course is appended (T055; Q-009-05 is open) */
    public const SUMMARY_TASK = 'Summarize the teaching material for a course teacher. Return only the summary text.';

    /** @var material_repository */
    private material_repository $repository;

    /** @var material_reader */
    private material_reader $reader;

    /** @var llm_port */
    private llm_port $port;

    /** @var instruction_builder */
    private instruction_builder $instructions;

    /** @var change_detector */
    private change_detector $detector;

    /** @var bool[] failures of the availability already written to the log by this service, by operation, material and exception */
    private array $reported = [];

    /** @var course_module_reader */
    private course_module_reader $modules;

    /** @var student_reader */
    private student_reader $students;

    /** @var content_extractor[] by material type */
    private array $extractors = [];

    /** @var synthesis_trigger */
    private synthesis_trigger $synthesis;

    /**
     * Constructor.
     *
     * @param material_repository|null $repository
     * @param material_reader|null $reader
     * @param llm_port|null $port the port; the simulated one behind the privacy guard by default
     * @param instruction_builder|null $instructions
     * @param content_extractor[]|null $extractors
     * @param course_module_reader|null $modules reads the availability of the modules for the students
     * @param student_reader|null $students
     * @param synthesis_trigger|null $synthesis asks for the synthesis of the section when a new version becomes current (T145)
     */
    public function __construct(
        ?material_repository $repository = null,
        ?material_reader $reader = null,
        ?llm_port $port = null,
        ?instruction_builder $instructions = null,
        ?array $extractors = null,
        ?course_module_reader $modules = null,
        ?student_reader $students = null,
        ?synthesis_trigger $synthesis = null
    ) {
        $this->repository = $repository ?? new material_repository();
        $this->reader = $reader ?? new material_reader();
        $this->port = $port ?? adapter_factory::create();
        $this->instructions = $instructions ?? new instruction_builder();
        $this->detector = new change_detector();
        $this->modules = $modules ?? new course_module_reader();
        $this->students = $students ?? new student_reader();
        $this->synthesis = $synthesis ?? new synthesis_trigger();
        foreach ($extractors ?? [
            new page_extractor($this->reader),
            new pdf_extractor($this->reader),
            new youtube_extractor($this->reader),
        ] as $extractor) {
            $this->extractors[$extractor->type()->value] = $extractor;
        }
    }

    /**
     * Process the current state of a module.
     *
     * @param int $courseid
     * @param int $cmid
     * @return handler_result success when nothing is left to do, failure when the summary could not be produced or persisted
     */
    public function process(int $courseid, int $cmid): handler_result {
        $moodle = $this->reader->get_material($courseid, $cmid);
        if ($moodle === null) {
            return handler_result::success('not a functional material (or removed): nothing to do');
        }
        $type = material_type::from($moodle->material_type);
        $now = new material_state($type, $moodle->content_hash, (int) $moodle->section_id, (string) $moodle->name);

        $material = $this->repository->find_by_cmid($courseid, $cmid);
        if ($material === null) {
            $materialid = $this->repository->register(
                $courseid,
                $now->sectionid,
                $cmid,
                $type,
                $now->name,
                material_availability::PENDING
            );
        } else {
            $materialid = (int) $material->id;
            if ((int) $material->section_id !== $now->sectionid || $material->name !== $now->name) {
                $this->repository->update_details($materialid, $now->sectionid, $now->name);
            }
        }

        $availability = $this->refresh_availability($courseid, $cmid, $materialid);

        $change = $this->detector->detect($this->repository->find_state($courseid, $cmid), $now);
        if (!$change->creates_version()) {
            return handler_result::success('no change in the processable content');
        }

        $versionid = $this->version_for($materialid, $now->contenthash, $availability);
        return $this->produce_summary($courseid, $cmid, $materialid, $versionid, $type, $now->name, $now->sectionid);
    }

    /**
     * Bring `material.availability` up to date and tell what was observed (T058; DEC-51).
     *
     * At least one student of the course reaching the module is `available`; none is `unavailable`. With no students nothing can be
     * said (their absence is not unavailability), and if anything fails the value held stays as it is: the failure is written to
     * the error log (report_failure()) and never reaches the caller, so it cannot stop a version or a summary. When only the writing
     * of the material fails, what was observed is still what a version created now records: the version is a record of the
     * observation, the material only holds the latest one that could be written.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $materialid
     * @return material_availability the value to record in a version created now: the one observed, `pending` when none
     */
    private function refresh_availability(int $courseid, int $cmid, int $materialid): material_availability {
        try {
            $studentids = $this->students->get_students($courseid);
            if (!$studentids) {
                return material_availability::PENDING;
            }
            $observed = $this->modules->is_available_to_any_user($courseid, $cmid, $studentids)
                ? material_availability::AVAILABLE
                : material_availability::UNAVAILABLE;
        } catch (\Throwable $e) {
            $this->report_failure('evaluation', $courseid, $materialid, $cmid, $e);
            return material_availability::PENDING;
        }
        try {
            $this->record_availability($materialid, $observed);
        } catch (\Throwable $e) {
            $this->report_failure('persistence', $courseid, $materialid, $cmid, $e);
        }
        return $observed;
    }

    /**
     * Write the latest known availability of the material. A separate step so that a test can make the writing fail.
     *
     * @param int $materialid
     * @param material_availability $availability
     */
    protected function record_availability(int $materialid, material_availability $availability): void {
        $this->repository->update_availability($materialid, $availability);
    }

    /**
     * Write to the error log that the availability of a material could not be evaluated or recorded (T058).
     *
     * One line with the operation, the course, the material, the module, the kind of exception, its code, where it was raised and its
     * message (cut, one line). Never the content of the material and never anything about a student: only identifiers of the course
     * and the material are written. The same failure of the same material (operation and kind of exception) is written once by
     * this service, so a course that keeps failing does not fill the log at each event. A failure of the log itself is swallowed:
     * it must not change the outcome of the processing.
     *
     * @param string $operation `evaluation` or `persistence`
     * @param int $courseid
     * @param int $materialid
     * @param int $cmid
     * @param \Throwable $e
     */
    private function report_failure(string $operation, int $courseid, int $materialid, int $cmid, \Throwable $e): void {
        try {
            $key = $operation . ':' . $materialid . ':' . get_class($e);
            if (isset($this->reported[$key])) {
                return;
            }
            $this->reported[$key] = true;
            $message = trim((string) preg_replace('/\s+/', ' ', $e->getMessage()));
            if (strlen($message) > 200) {
                $message = substr($message, 0, 200) . '...';
            }
            error_log(sprintf(
                'local_assistente: availability of a material: %s failed (course %d, material %d, cmid %d): %s (code %s) at %s:%d: %s',
                $operation,
                $courseid,
                $materialid,
                $cmid,
                get_class($e),
                (string) $e->getCode(),
                basename($e->getFile()),
                $e->getLine(),
                $message
            ));
        } catch (\Throwable) {
            // Nothing else can be done: the log must not change the outcome.
            return;
        }
    }

    /**
     * The version to process for a content: the latest one when it is of the same content (retry), otherwise a new one.
     *
     * @param int $materialid
     * @param string $contenthash
     * @param material_availability $availability observed now; recorded only in a new version, never in one taken up again
     * @return int version id
     */
    private function version_for(int $materialid, string $contenthash, material_availability $availability): int {
        $versions = array_values($this->repository->find_versions($materialid));
        $latest = $versions ? end($versions) : null;
        if ($latest !== null && $latest->content_hash === $contenthash) {
            return (int) $latest->id;
        }
        return $this->repository->add_version($materialid, $contenthash, $availability);
    }

    /**
     * Ask for the summary of a version and persist the outcome.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $materialid
     * @param int $versionid
     * @param material_type $type
     * @param string $name
     * @param int $sectionid section of the material, for the synthesis request (T145)
     * @return handler_result
     */
    private function produce_summary(
        int $courseid,
        int $cmid,
        int $materialid,
        int $versionid,
        material_type $type,
        string $name,
        int $sectionid
    ): handler_result {
        $version = $this->repository->find_version_by_id($versionid);
        $status = summary_status::from($version->summary_status);
        if ($status === summary_status::COMPLETED) {
            // Completed earlier but not made current (the switch failed): only the switch is left.
            try {
                $this->repository->switch_current($materialid, $versionid);
            } catch (version_not_newer_exception $e) {
                $outcome = $this->not_newer($version, $e);
                return $e->is_current() ? $this->synthesis_requested($courseid, $sectionid, $outcome) : $outcome;
            }
            return $this->synthesis_requested($courseid, $sectionid, handler_result::success("version {$version->version} is current"));
        }
        if ($status === summary_status::FAILED) {
            $this->repository->retry_summary($versionid);
        }

        $content = ($this->extractors[$type->value] ?? null)?->extract($cmid);
        if ($content === null || !$content->has_content()) {
            $this->repository->fail_summary($versionid);
            return handler_result::failure(attempt_failure::TECHNICAL, 'the material has no content to summarize');
        }

        // The port is called with no transaction open (plan 003); nothing of a student goes in the context (DEC-34).
        try {
            $result = $this->port->evaluate(
                new llm_context(llm_use_case::MATERIAL_SUMMARY, [
                    'material' => ['name' => $name, 'type' => $type->value],
                    'content' => $content->to_context_data(),
                ]),
                $this->instructions->for_course($courseid, self::SUMMARY_TASK)
            );
        } catch (\Throwable $e) {
            $this->repository->fail_summary($versionid);
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }
        if (!$result->is_valid()) {
            $this->repository->fail_summary($versionid);
            return llm_result_adapter::failure_of($result);
        }

        try {
            $this->persist_summary($versionid, (string) $result->data['summary']);
            try {
                $this->repository->switch_current($materialid, $versionid);
            } catch (version_not_newer_exception $e) {
                // The summary is persisted and the version stays completed, as history: nothing to retry (DEC-62).
                $outcome = $this->not_newer($version, $e);
                return $e->is_current() ? $this->synthesis_requested($courseid, $sectionid, $outcome) : $outcome;
            }
        } catch (\Throwable $e) {
            // Persisting failed: a failed write does not change the status, and the previous version stays current.
            $this->mark_failed_if_pending($versionid);
            return handler_result::failure(attempt_failure::TECHNICAL, 'could not persist the summary: ' . $e->getMessage());
        }
        return $this->synthesis_requested($courseid, $sectionid, handler_result::success("version {$version->version} completed and current"));
    }

    /**
     * Ask for the synthesis of the section of a material whose version is current (T145; DEC-41 D3, DEC-21).
     *
     * Called only when a new version of the material is current after a successful summary. The event service reuses a pending event of the
     * same (course, section), so several materials of a section are one synthesis. A failure to queue it fails the attempt: the retry takes the
     * version up again (already current) and asks again. The synthesis handler decides whether a synthesis is created.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param handler_result $ok the outcome to return when the request is queued
     * @return handler_result
     */
    private function synthesis_requested(int $courseid, int $sectionid, handler_result $ok): handler_result {
        try {
            $this->synthesis->request_section($courseid, $sectionid);
        } catch (\Throwable $e) {
            return handler_result::failure(attempt_failure::TECHNICAL, get_class($e) . ': ' . $e->getMessage());
        }
        return $ok;
    }

    /**
     * Persist the summary of a version this attempt read as pending.
     *
     * The lock of the material is not held while the LLM answers, so the reconciliation of the versions (CL-51) can mark the version failed
     * after this attempt read it. The version is then taken up again with the transitions that exist (failed to pending to completed) and the
     * summary this attempt already has: the event is not lost, the attempt is not spent and the LLM is not called again. Any other failure
     * is raised as it was.
     *
     * @param int $versionid
     * @param string $summary
     * @throws \coding_exception when the version is neither pending nor failed
     */
    private function persist_summary(int $versionid, string $summary): void {
        try {
            $this->repository->complete_summary($versionid, $summary);
        } catch (\coding_exception $e) {
            $version = $this->repository->find_version_by_id($versionid);
            if ($version === null || $version->summary_status !== summary_status::FAILED->value) {
                throw $e;
            }
            $this->repository->retry_summary($versionid);
            $this->repository->complete_summary($versionid, $summary);
        }
    }

    /**
     * The outcome of a version refused as current by the monotonicity rule (DEC-62; T144): a valid race, not a failure.
     *
     * The version is left as it is (completed, historical), no summary is requested again and the attempt does not fail.
     *
     * @param \stdClass $version
     * @param version_not_newer_exception $e
     * @return handler_result
     */
    private function not_newer(\stdClass $version, version_not_newer_exception $e): handler_result {
        return handler_result::success($e->is_current()
            ? "version {$version->version} is already current"
            : "version {$version->version} is kept as history: a newer version ({$e->currentnumber}) is current");
    }

    /**
     * Mark a version failed after a persistence failure, only when it is still pending (a completed one is never changed).
     *
     * @param int $versionid
     */
    private function mark_failed_if_pending(int $versionid): void {
        try {
            $version = $this->repository->find_version_by_id($versionid);
            if ($version !== null && $version->summary_status === summary_status::PENDING->value) {
                $this->repository->fail_summary($versionid);
            }
        } catch (\Throwable $e) {
            // Nothing else can be done here; the version stays as the database holds it and the next attempt resumes it.
            debugging('Could not mark the material version as failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
