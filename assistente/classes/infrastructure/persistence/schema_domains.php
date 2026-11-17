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
use local_assistente\domain\attempt_status;
use local_assistente\domain\criteria_status;
use local_assistente\domain\event_status;
use local_assistente\domain\material\material_availability;
use local_assistente\domain\material\summary_status;
use local_assistente\domain\material_type;

/**
 * Approved value domains of the char and int(1) fields that XMLDB does not restrict.
 *
 * Only domains already decided are listed (summary_status: DEC-50; availability: DEC-51).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schema_domains {
    /**
     * Allowed values by field of a table (table names without the physical prefix).
     *
     * @param string $table
     * @return array<string, array> field => allowed values
     */
    public static function for_table(string $table): array {
        $flag = [0, 1];
        $criteria = criteria_status::values();

        return match ($table) {
            'local_assistente_config' => ['enabled' => $flag],
            'local_assistente_criteria' => ['forum_criterion' => $flag],
            'local_assistente_event' => ['status' => event_status::values()],
            'local_assistente_processing' => ['status' => event_status::values()],
            'local_assistente_processing_attempt' => ['status' => attempt_status::values()],
            'local_assistente_material' => [
                'material_type' => material_type::values(),
                'availability' => material_availability::values(),
            ],
            'local_assistente_material_version' => [
                'summary_status' => summary_status::values(),
                'availability' => material_availability::values(),
            ],
            'local_assistente_analysis' => ['analysis_type' => analysis_type::values()],
            'local_assistente_frequency_activity' => ['participated' => $flag],
            'local_assistente_criteria_result' => [
                'activity_status' => $criteria,
                'forum_status' => $criteria,
                'frequency_status' => $criteria,
            ],
            default => [],
        };
    }
}
