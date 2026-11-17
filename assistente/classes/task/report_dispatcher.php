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

use local_assistente\application\report\report_generator;

/**
 * Scheduled task that generates the due versions of the reports (T116; spec-019; P-05). It is not part of the event queue: it has its own schedule
 * and takes the Lock API of each report.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_dispatcher extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_report_dispatcher', 'local_assistente');
    }

    #[\Override]
    public function execute(): void {
        $result = (new report_generator())->generate_due(time());
        mtrace(sprintf(
            'report dispatcher: %d versions generated, %d failed, %d left for the next run',
            count($result['generated']),
            count($result['failed']),
            count($result['skipped'])
        ));
        foreach ($result['failed'] as $courseid => $message) {
            mtrace("report dispatcher: course {$courseid} failed: {$message}");
        }
    }
}
