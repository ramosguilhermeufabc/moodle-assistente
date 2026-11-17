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

namespace local_assistente\application\privacy;

use local_assistente\domain\privacy\erasure_facts;
use local_assistente\domain\privacy\erasure_key;
use local_assistente\domain\privacy\erasure_result;
use local_assistente\domain\privacy\erasure_state_calculator;
use local_assistente\domain\privacy\name_redactor;
use local_assistente\infrastructure\moodle\erasure_audit_reader;
use local_assistente\infrastructure\persistence\history_redaction_writer;
use local_assistente\infrastructure\persistence\student_data_repository;

/**
 * What is known about the erasure of a student in some courses (T142; DEC-58). It reads and decides; it changes nothing.
 *
 * It gathers the facts the calculator needs: the residue by identifier, the name and whether a preserved text still cites it, the retry
 * tasks and the audit events. The state follows from the data now; the tasks and the events only say what happened to the residue.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_state_resolver {
    /**
     * Constructor.
     *
     * @param student_data_repository|null $data
     * @param history_redaction_writer|null $texts used only to count the occurrences of the name in the preserved texts
     * @param erasure_retry_scheduler|null $scheduler
     * @param erasure_audit_reader|null $reader
     * @param erasure_state_calculator|null $calculator
     */
    public function __construct(
        private ?student_data_repository $data = null,
        private ?history_redaction_writer $texts = null,
        private ?erasure_retry_scheduler $scheduler = null,
        private ?erasure_audit_reader $reader = null,
        private ?erasure_state_calculator $calculator = null
    ) {
        $this->data ??= new student_data_repository();
        $this->texts ??= new history_redaction_writer();
        $this->scheduler ??= new erasure_retry_scheduler();
        $this->reader ??= new erasure_audit_reader();
        $this->calculator ??= new erasure_state_calculator();
    }

    /**
     * The state of an erasure.
     *
     * @param erasure_key $key
     * @return erasure_result
     */
    public function result(erasure_key $key): erasure_result {
        $userid = $key->userid();
        $idresidue = array_values(array_intersect($this->data->course_ids_with_data($userid), $key->courses()));
        $available = $this->data->names_available($userid);
        $textresidue = [];
        if ($available) {
            [$first, $last] = $this->data->names($userid);
            $redactor = new name_redactor([$first], [$last]);
            foreach ($key->courses() as $courseid) {
                if ($this->texts->count_name_occurrences($courseid, $redactor) > 0) {
                    $textresidue[] = $courseid;
                }
            }
        }
        return $this->calculator->calculate(new erasure_facts(
            $key,
            $idresidue,
            $available,
            $textresidue,
            $this->scheduler->tasks_of($userid),
            $this->reader->events_of($userid)
        ));
    }
}
