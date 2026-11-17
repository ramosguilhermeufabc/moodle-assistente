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

use local_assistente\domain\analysis_type;

/**
 * Repository of the analysis of a forum: the aggregate `analysis` (type `forum_analysis`, `cmid` of the forum, the summary of the forum in
 * `summary`) with its `forum_difficulty` rows (plan 003 "Agregados"; spec-013, data-model; DEC-38; T081).
 *
 * Rules kept here:
 * - Each analysis is a complete snapshot of the forum: the summary and all the difficulties of the snapshot, including those copied
 *   unchanged from the discussions that were not affected (DEC-38). It is written whole, in one transaction, under the lock of the event of
 *   the processing; a failed or invalid execution writes nothing, so the current snapshot of a forum, the latest analysis, stays the last
 *   successful one. No method changes or deletes an analysis: the history is preserved, and the difficulties removed from the current
 *   snapshot stay in the old ones.
 * - One analysis for each processing: a second save for the same processing is rejected (duplicate_record_exception) and leaves the first
 *   untouched.
 * - A discussion may have no difficulty, one or several (0..N): the rows are not unique by discussion. A difficulty has a non-empty
 *   description, and its discussion must belong to the forum of the analysis.
 * - The forum must be a forum module of the course. Reads are scoped by course and forum (`cmid`).
 *
 * It holds no reading of the forum, no choice of mode (incremental or rebuild) and no LLM: that is the handler (T082).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class forum_analysis_repository extends repository {
    /** @var table_repository the forum_difficulty table */
    private table_repository $difficulties;

    /** @var table_repository the processing table, to find the event to lock */
    private table_repository $processings;

    /**
     * Constructor.
     *
     * @param unit_of_work $uow
     */
    public function __construct(
        private readonly unit_of_work $uow = new unit_of_work()
    ) {
        $this->difficulties = new table_repository('local_assistente_forum_difficulty');
        $this->processings = new table_repository('local_assistente_processing');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_analysis';
    }

    /**
     * Save the snapshot of a forum, all or nothing.
     *
     * @param int $courseid
     * @param int $cmid the forum
     * @param int $processingid
     * @param string|null $summary summary of the forum
     * @param array $difficulties list of ['discussion_id' => int, 'description' => string]; the whole snapshot, possibly empty
     * @param int|null $now
     * @return int the id of the analysis
     * @throws invalid_domain_value_exception when a value or a reference is invalid (nothing is written)
     * @throws duplicate_record_exception when the processing already has an analysis of a forum
     * @throws \dml_missing_record_exception when the processing does not exist
     */
    public function save(
        int $courseid,
        int $cmid,
        int $processingid,
        ?string $summary,
        array $difficulties,
        ?int $now = null
    ): int {
        global $DB;
        $forum = $DB->get_record_sql(
            "SELECT cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
              WHERE cm.id = :cmid AND cm.course = :courseid",
            ['cmid' => $cmid, 'courseid' => $courseid]
        );
        if (!$forum) {
            throw new invalid_domain_value_exception("The course module {$cmid} is not a forum of course {$courseid}.");
        }
        $known = [];
        foreach ($difficulties as $difficulty) {
            $discussion = (int) ($difficulty['discussion_id'] ?? 0);
            $description = $difficulty['description'] ?? null;
            if ($discussion <= 0 || !is_string($description) || trim($description) === '') {
                throw new invalid_domain_value_exception('A difficulty needs a discussion and a description that is not empty.');
            }
            if (!isset($known[$discussion])) {
                $known[$discussion] = $DB->record_exists('forum_discussions', ['id' => $discussion, 'forum' => (int) $forum->instance]);
            }
            if (!$known[$discussion]) {
                throw new invalid_domain_value_exception("The discussion {$discussion} does not belong to the forum {$cmid}.");
            }
        }

        $processing = $this->processings->find($processingid)
            ?? throw new \dml_missing_record_exception('local_assistente_processing', 'id = ?', [$processingid]);
        $now ??= time();

        return $this->uow->run(function () use ($courseid, $cmid, $processingid, $summary, $difficulties, $now): int {
            if ($this->find_by_processing($processingid) !== null) {
                throw new duplicate_record_exception("Processing {$processingid} already has a forum analysis.");
            }
            $analysisid = $this->insert((object) [
                'course_id' => $courseid,
                'processing_id' => $processingid,
                'analysis_type' => analysis_type::FORUM_ANALYSIS->value,
                'cmid' => $cmid,
                'summary' => $summary,
                'created_at' => $now,
            ]);
            foreach ($difficulties as $difficulty) {
                $this->difficulties->insert((object) [
                    'analysis_id' => $analysisid,
                    'discussion_id' => (int) $difficulty['discussion_id'],
                    'description' => $difficulty['description'],
                ]);
            }
            return $analysisid;
        }, [unit_of_work::event_resource((int) $processing->event_id)]);
    }

    /**
     * The forum analysis of a processing.
     *
     * @param int $processingid
     * @return \stdClass|null
     */
    public function find_by_processing(int $processingid): ?\stdClass {
        return $this->find_by(['processing_id' => $processingid, 'analysis_type' => analysis_type::FORUM_ANALYSIS->value]);
    }

    /**
     * The current snapshot of a forum: the latest analysis, which is the last successful one.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass|null
     */
    public function find_current(int $courseid, int $cmid): ?\stdClass {
        $history = $this->find_history($courseid, $cmid);
        return $history ? reset($history) : null;
    }

    /**
     * All the snapshots of a forum, newest first.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass[]
     */
    public function find_history(int $courseid, int $cmid): array {
        return $this->find_all_by([
            'course_id' => $courseid,
            'cmid' => $cmid,
            'analysis_type' => analysis_type::FORUM_ANALYSIS->value,
        ], 'created_at DESC, id DESC');
    }

    /**
     * The difficulties of a snapshot, by discussion.
     *
     * @param int $analysisid
     * @param int|null $discussionid restrict to one discussion
     * @return \stdClass[] indexed by id; [] when there are none (or no such analysis)
     */
    public function find_difficulties(int $analysisid, ?int $discussionid = null): array {
        $conditions = ['analysis_id' => $analysisid];
        if ($discussionid !== null) {
            $conditions['discussion_id'] = $discussionid;
        }
        return $this->difficulties->find_all_by($conditions, 'discussion_id ASC, id ASC');
    }
}
