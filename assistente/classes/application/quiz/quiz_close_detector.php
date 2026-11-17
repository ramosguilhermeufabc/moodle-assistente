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

namespace local_assistente\application\quiz;

use local_assistente\application\enqueue_result;
use local_assistente\application\event_service;
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\moodle\questionnaire_reader;
use local_assistente\infrastructure\persistence\config_repository;

/**
 * Detects the questionnaires whose closing time has passed and queues the final recalculation of their results (T074; spec-011,
 * DEC-36 D4).
 *
 * Closing is not an event of Moodle, so a scheduled task asks for it. For each questionnaire of a course where the assistant is enabled
 * whose `timeclose` is in the past, one `quiz_closed` event is queued. Its identity is the questionnaire and the closing time, so the
 * same closing queues once however many times the task runs, and a questionnaire whose closing time is moved later (or earlier and then
 * passed) is a new closing. A questionnaire without `timeclose` never closes: its results stay partial and follow the attempt events.
 *
 * It reads and queues; the recalculation is results_handler (T072). Attempts that Moodle closes after `timeclose` (overdue to
 * abandoned) are not waited for: the final recalculation reads the attempts finished when it runs.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_close_detector {
    /** @var event_service */
    private event_service $events;

    /** @var questionnaire_reader */
    private questionnaire_reader $questionnaires;

    /** @var config_repository */
    private config_repository $configs;

    /**
     * Constructor.
     *
     * @param event_service|null $events
     * @param questionnaire_reader|null $questionnaires
     * @param config_repository|null $configs
     */
    public function __construct(
        ?event_service $events = null,
        ?questionnaire_reader $questionnaires = null,
        ?config_repository $configs = null
    ) {
        $this->events = $events ?? new event_service();
        $this->questionnaires = $questionnaires ?? new questionnaire_reader();
        $this->configs = $configs ?? new config_repository();
    }

    /**
     * Queue the final recalculation of every questionnaire closed by now.
     *
     * @param int|null $now unix time; the current time when null
     * @return enqueue_result[] one for each closed questionnaire found
     */
    public function detect(?int $now = null): array {
        $now ??= time();
        $results = [];
        $closed = $this->questionnaires->get_closed_questionnaires($now, $this->configs->find_enabled_course_ids());
        foreach ($closed as $questionnaire) {
            $results[] = $this->events->enqueue($questionnaire->course_id, event_type::QUIZ_CLOSED, [
                'cmid' => $questionnaire->cmid,
                'timeclose' => $questionnaire->time_close,
            ]);
        }
        return $results;
    }
}
