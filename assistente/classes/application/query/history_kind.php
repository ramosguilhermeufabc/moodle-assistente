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

namespace local_assistente\application\query;

/**
 * What the history query can be asked about (T110; spec-018 Q-018-04, DEC-42): a fixed list, so no query takes free text.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum history_kind: string {
    /** Executions of a questionnaire: initial analyses and results; the id is the module. */
    case QUIZ = 'quiz';
    /** Snapshots of a forum; the id is the module. */
    case FORUM = 'forum';
    /** Versions of a material; the id is the module. */
    case MATERIAL = 'material';
    /** Versions of the synthesis of a section; the id is the section. */
    case SYNTHESIS = 'synthesis';
    /** Versions of the report of the course; there is no id. */
    case REPORT = 'report';
}
