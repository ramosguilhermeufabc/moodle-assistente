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

namespace local_assistente\domain\llm;

/**
 * The five uses allowed for the LLM (spec-009 §Regras; contracts/llm-port.md).
 *
 * Counts, averages, standard deviation, minimum and maximum, attendance and the application of criteria are NOT uses of the
 * LLM and never go through the port.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum llm_use_case: string {
    /** Summary of a material (spec-008). */
    case MATERIAL_SUMMARY = 'material_summary';
    /** Coverage of a questionnaire by the materials of its section (spec-010). */
    case QUIZ_COVERAGE = 'quiz_coverage';
    /** Complexity of one question (spec-010). */
    case QUESTION_COMPLEXITY = 'question_complexity';
    /** Summary and difficulties of a forum (spec-013). */
    case FORUM_ANALYSIS = 'forum_analysis';
    /** Pedagogical synthesis of a course section (spec-016). */
    case PEDAGOGICAL_SYNTHESIS = 'pedagogical_synthesis';
}
