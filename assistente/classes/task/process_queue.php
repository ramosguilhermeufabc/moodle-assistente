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

use local_assistente\application\queue\queue_processor;

/**
 * Scheduled task that runs one cycle of the logical queue (spec-007; DEC-32 D1 and D2).
 *
 * The interval is the one of the native schedule of the Moodle scheduled tasks (15 minutes by default, see db/tasks.php),
 * changed by the administrator in the Moodle administration; the plugin has no form or setting for it. The cycle stops
 * when the time of the next scheduled run arrives, computed from the schedule configured for this task (DEC-32 D5 b).
 *
 * Thin by contract: all the logic is in queue_processor.
 *
 * Moodle API: core\task\scheduled_task::get_next_scheduled_time() and NEVER_RUN_TIME (for a task that is set never to
 * run, the deadline is so far away that it does not limit the cycle). Behaviour with the real cron in Moodle 5.2 is
 * validated in CL-02 (T051).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_queue extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_process_queue', 'local_assistente');
    }

    #[\Override]
    public function execute(): void {
        $start = time();
        $report = (new queue_processor())->run_cycle($this->get_next_scheduled_time($start));
        mtrace($report->summary());
    }
}
