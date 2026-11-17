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

namespace local_assistente\domain;

/**
 * Value of local_assistente_analysis.analysis_type (plans 010 to 016; char(20)).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum analysis_type: string {
    /** Initial analysis of a published questionnaire (spec-010). */
    case QUIZ_INITIAL = 'quiz_initial';
    /** Answer metrics of a questionnaire (spec-011). */
    case QUIZ_RESULTS = 'quiz_results';
    /** Grades and performance (spec-012). */
    case GRADE_PERFORMANCE = 'grade_performance';
    /** Forum analysis (spec-013). */
    case FORUM_ANALYSIS = 'forum_analysis';
    /** Attendance and participation (spec-014). */
    case FREQUENCY = 'frequency';
    /** Criteria application (spec-015). */
    case CRITERIA_CHECK = 'criteria_check';
    /** Pedagogical synthesis of a section (spec-016). */
    case SECTION_SYNTHESIS = 'section_synthesis';

    /**
     * All allowed raw values.
     *
     * @return string[]
     */
    public static function values(): array {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}
