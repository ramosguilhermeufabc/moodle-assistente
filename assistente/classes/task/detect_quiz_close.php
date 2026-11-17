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

namespace local_assistente\task;

use local_assistente\application\enqueue_result;
use local_assistente\application\enqueue_status;
use local_assistente\application\quiz\quiz_close_detector;

/**
 * Scheduled task that detects questionnaires whose closing time passed and queues their final recalculation (T074; DEC-36 D4).
 *
 * Thin by contract: all the logic is in quiz_close_detector. Running it again queues nothing new for a closing already queued. How the
 * real cron runs it in Moodle 5.2 is validated with the other tasks in CL-02 (T051) and CL-33 (T075).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detect_quiz_close extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_detect_quiz_close', 'local_assistente');
    }

    #[\Override]
    public function execute(): void {
        $results = (new quiz_close_detector())->detect();
        $created = count(array_filter($results, static fn(enqueue_result $r) => $r->status === enqueue_status::CREATED));
        mtrace(sprintf('quiz close detection: %d closed questionnaires, %d queued', count($results), $created));
    }
}
