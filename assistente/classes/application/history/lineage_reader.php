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


namespace local_assistente\application\history;

use local_assistente\domain\analysis_type;
use local_assistente\domain\event\erased_subject;
use local_assistente\infrastructure\persistence\event_repository;
use local_assistente\infrastructure\persistence\performance_repository;
use local_assistente\infrastructure\persistence\table_repository;

/**
 * Reconstructs the chain behind a result from what is persisted (T101; spec-017 contract `traceResult` and `history`, DEC-42, quickstart 017
 * Q1, Q2 and Q4): Moodle source, event, processing, attempts, context snapshot, analysis, result.
 *
 * Only real links are followed, none is inferred:
 * - result row -> `analysis_id` -> analysis -> `processing_id` -> processing -> `event_id` -> event (its `course_id` and payload);
 * - processing -> attempts (`processing_id`) and the context snapshot (`processing_id`, one at most), whose `materials` list the material and
 *   the version used with the availability evaluated (DEC-05, DEC-26);
 * - the Moodle sources are the references the event payload carries (module, discussion, message, attempt, grade item, user, section) and
 *   the module of the analysis; the result adds its own (the discussion of a difficulty, the section of a synthesis).
 *
 * What the chain does not hold is not reconstructed and is said in the lineage as absent: a synthesis keeps the fingerprint of its inputs, not
 * the analyses that fed it; a version of a material has no link to the event that made it, only the snapshots that used it list its id.
 *
 * A legitimate absence (no snapshot, no materials in it) is null or empty. A broken link (an analysis without processing, a processing without
 * event, an event of another course than the analysis, a payload or snapshot that is not valid, a result of another course than its analysis)
 * throws lineage_exception. An id that does not exist is null. Whether the Moodle record still exists is not asked here (source_status_resolver).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lineage_reader {
    /** @var string[] result tables that have an `analysis_id`, the results that can be traced */
    public const RESULT_TABLES = [
        'local_assistente_forum_difficulty',
        'local_assistente_pedagogical_synthesis',
        'local_assistente_questionnaire_analysis',
        'local_assistente_questionnaire_metric',
        'local_assistente_question_result',
        'local_assistente_performance',
        'local_assistente_frequency',
        'local_assistente_criteria_result',
    ];

    /** @var array<string, string> payload field => type of the Moodle record it names */
    private const PAYLOAD_SOURCES = [
        'cmid' => 'course_module',
        'discussionid' => 'forum_discussion',
        'postid' => 'forum_post',
        'attemptid' => 'quiz_attempt',
        'itemid' => 'grade_item',
        'userid' => 'user',
        'section_id' => 'course_section',
    ];

    /** @var table_repository */
    private table_repository $analyses;

    /** @var table_repository */
    private table_repository $processings;

    /** @var table_repository */
    private table_repository $syntheses;

    /** @var event_repository */
    private event_repository $events;

    /** @var performance_repository */
    private performance_repository $performance;

    /**
     * Constructor.
     *
     * @param event_repository|null $events
     * @param performance_repository|null $performance
     */
    public function __construct(?event_repository $events = null, ?performance_repository $performance = null) {
        $this->analyses = new table_repository('local_assistente_analysis');
        $this->processings = new table_repository('local_assistente_processing');
        $this->syntheses = new table_repository('local_assistente_pedagogical_synthesis');
        $this->events = $events ?? new event_repository();
        $this->performance = $performance ?? new performance_repository();
    }

    /**
     * The chain of an analysis.
     *
     * @param int $analysisid
     * @return lineage|null null when the analysis does not exist
     * @throws lineage_exception when a link of the chain is missing or contradicts the others
     */
    public function trace_analysis(int $analysisid): ?lineage {
        $analysis = $this->analyses->find($analysisid);
        return $analysis === null ? null : $this->build($analysis, null);
    }

    /**
     * The chain of a result row of one of the RESULT_TABLES.
     *
     * @param string $table logical table name
     * @param int $id id of the row
     * @return lineage|null null when the row does not exist
     * @throws \InvalidArgumentException when the table is not a result that can be traced
     * @throws lineage_exception when a link of the chain is missing or contradicts the others
     */
    public function trace_result(string $table, int $id): ?lineage {
        if (!in_array($table, self::RESULT_TABLES, true)) {
            throw new \InvalidArgumentException("'{$table}' is not a result table that can be traced.");
        }
        $row = (new table_repository($table))->find($id);
        if ($row === null) {
            return null;
        }
        $analysis = $this->analyses->find((int) $row->analysis_id)
            ?? throw new lineage_exception("The result {$table} #{$id} points to the analysis {$row->analysis_id}, which does not exist.");
        if ($table === 'local_assistente_pedagogical_synthesis' && (int) $row->course_id !== (int) $analysis->course_id) {
            throw new lineage_exception("The synthesis #{$id} is of course {$row->course_id} and its analysis of course {$analysis->course_id}.");
        }
        return $this->build($analysis, ['table' => $table, 'row' => $row]);
    }

    /**
     * The earlier states of what an analysis updated: the analyses of the same scope, newest first, the given one among them. Each one stays
     * as it was (Q-017-01): an update is a new analysis, never a change.
     *
     * The scope is the course and the type, narrowed by what identifies the thing analysed: the module for the questionnaire and forum
     * analyses, the section for a synthesis, the student of the event for a performance (an analysis of an erased student has only itself).
     * The frequency and the criteria are of the whole course.
     *
     * @param int $analysisid
     * @return \stdClass[] analysis rows; [] when the analysis does not exist
     * @throws lineage_exception when the scope of the analysis cannot be read
     */
    public function history(int $analysisid): array {
        $analysis = $this->analyses->find($analysisid);
        if ($analysis === null) {
            return [];
        }
        $courseid = (int) $analysis->course_id;
        $type = analysis_type::tryFrom((string) $analysis->analysis_type)
            ?? throw new lineage_exception("The analysis {$analysisid} has an unknown type '{$analysis->analysis_type}'.");

        if ($type === analysis_type::GRADE_PERFORMANCE) {
            $payload = $this->payload($this->event_of($analysis));
            $userid = $payload['userid'] ?? null;
            if (!is_int($userid)) {
                throw new lineage_exception("The event of the analysis {$analysisid} has no student.");
            }
            if ($userid === erased_subject::USERID) {
                // The student was erased (DEC-57): the analysis has no owner left and its history is itself, never that of other erased students.
                return [$analysis];
            }
            $same = $this->performance->find_history_for_student($courseid, $userid);
        } else if ($type === analysis_type::SECTION_SYNTHESIS) {
            $own = $this->syntheses->find_by(['analysis_id' => $analysisid])
                ?? throw new lineage_exception("The synthesis analysis {$analysisid} has no synthesis.");
            $same = [];
            foreach ($this->syntheses->find_all_by(['course_id' => $courseid, 'section_id' => (int) $own->section_id], 'version DESC') as $row) {
                $same[] = $this->analyses->find((int) $row->analysis_id);
            }
        } else {
            $conditions = ['course_id' => $courseid, 'analysis_type' => $type->value];
            if ($analysis->cmid !== null) {
                $conditions['cmid'] = (int) $analysis->cmid;
            }
            $same = array_values($this->analyses->find_all_by($conditions, 'created_at DESC, id DESC'));
        }

        $mine = [(int) $analysis->created_at, (int) $analysis->id];
        $earlier = array_filter(
            $same,
            static fn($row) => $row !== null && [(int) $row->created_at, (int) $row->id] <= $mine
        );
        usort($earlier, static fn($a, $b) => [(int) $b->created_at, (int) $b->id] <=> [(int) $a->created_at, (int) $a->id]);
        return array_values($earlier);
    }

    /**
     * Build the chain of an analysis.
     *
     * @param \stdClass $analysis
     * @param array|null $result the traced result
     * @return lineage
     */
    private function build(\stdClass $analysis, ?array $result): lineage {
        $processing = $this->processings->find((int) $analysis->processing_id)
            ?? throw new lineage_exception("The analysis {$analysis->id} points to the processing {$analysis->processing_id}, which does not exist.");
        $event = $this->events->find((int) $processing->event_id)
            ?? throw new lineage_exception("The processing {$processing->id} points to the event {$processing->event_id}, which does not exist.");
        if ((int) $event->course_id !== (int) $analysis->course_id) {
            throw new lineage_exception(
                "The analysis {$analysis->id} is of course {$analysis->course_id} and its event of course {$event->course_id}."
            );
        }
        $payload = $this->payload($event);
        $context = $this->context($processing, (int) $analysis->course_id);

        return new lineage(
            $analysis,
            $processing,
            $event,
            $payload,
            $this->events->find_attempts((int) $processing->id),
            $context,
            $this->materials($context),
            $this->sources($analysis, $payload, $result),
            $result
        );
    }

    /**
     * The event of an analysis.
     *
     * @param \stdClass $analysis
     * @return \stdClass
     */
    private function event_of(\stdClass $analysis): \stdClass {
        $processing = $this->processings->find((int) $analysis->processing_id)
            ?? throw new lineage_exception("The analysis {$analysis->id} points to the processing {$analysis->processing_id}, which does not exist.");
        return $this->events->find((int) $processing->event_id)
            ?? throw new lineage_exception("The processing {$processing->id} points to the event {$processing->event_id}, which does not exist.");
    }

    /**
     * The decoded payload of an event.
     *
     * @param \stdClass $event
     * @return array
     */
    private function payload(\stdClass $event): array {
        $payload = json_decode((string) $event->payload, true);
        if (!is_array($payload)) {
            throw new lineage_exception("The payload of the event {$event->id} is not valid.");
        }
        return $payload;
    }

    /**
     * The decoded context snapshot of a processing; null when it has none.
     *
     * @param \stdClass $processing
     * @param int $courseid course of the analysis
     * @return array|null
     */
    private function context(\stdClass $processing, int $courseid): ?array {
        $stored = $this->events->find_context((int) $processing->id);
        if ($stored === null) {
            return null;
        }
        $context = json_decode((string) $stored->context_json, true);
        if (!is_array($context)) {
            throw new lineage_exception("The context snapshot of the processing {$processing->id} is not valid.");
        }
        if (isset($context['course_id']) && (int) $context['course_id'] !== $courseid) {
            throw new lineage_exception(
                "The context snapshot of the processing {$processing->id} is of course {$context['course_id']}, not of course {$courseid}."
            );
        }
        return $context;
    }

    /**
     * The materials of a snapshot, with the version used.
     *
     * @param array|null $context
     * @return array
     */
    private function materials(?array $context): array {
        if ($context === null || !array_key_exists('materials', $context)) {
            return [];
        }
        if (!is_array($context['materials'])) {
            throw new lineage_exception('The materials of the context snapshot are not a list.');
        }
        $materials = [];
        foreach ($context['materials'] as $item) {
            $materialid = is_array($item) ? ($item['material_id'] ?? null) : null;
            $versionid = is_array($item) ? ($item['version_id'] ?? null) : null;
            if (!is_int($materialid) || ($versionid !== null && !is_int($versionid))) {
                throw new lineage_exception('A material of the context snapshot has no valid material or version.');
            }
            $materials[] = [
                'material_id' => $materialid,
                'cmid' => $item['cmid'] ?? null,
                'name' => $item['name'] ?? null,
                'material_type' => $item['material_type'] ?? null,
                'version_id' => $versionid,
                'version' => $item['version'] ?? null,
                'eligible' => (bool) ($item['eligible'] ?? false),
                'reason' => $item['reason'] ?? null,
            ];
        }
        return $materials;
    }

    /**
     * The Moodle records the chain starts from.
     *
     * @param \stdClass $analysis
     * @param array $payload
     * @param array|null $result
     * @return array
     */
    private function sources(\stdClass $analysis, array $payload, ?array $result): array {
        $sources = [];
        $add = static function (string $type, mixed $id) use (&$sources): void {
            if (is_int($id) && $id > 0) {
                $sources["{$type}:{$id}"] = ['type' => $type, 'id' => $id];
            }
        };
        foreach (self::PAYLOAD_SOURCES as $field => $type) {
            $add($type, $payload[$field] ?? null);
        }
        $add('course_module', $analysis->cmid === null ? null : (int) $analysis->cmid);
        if ($result !== null) {
            $row = $result['row'];
            $add('forum_discussion', isset($row->discussion_id) ? (int) $row->discussion_id : null);
            $add('course_section', isset($row->section_id) ? (int) $row->section_id : null);
            $add('user', isset($row->student_id) ? (int) $row->student_id : null);
            $add('course_module', isset($row->activity_id) ? (int) $row->activity_id : null);
        }
        return array_values($sources);
    }
}
