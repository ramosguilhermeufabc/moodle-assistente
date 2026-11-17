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
 * The `context` of a call to the LLM port: the use that is asked and the data the model may use.
 *
 * The use travels with the context, so that the answer can be checked against the use that was requested and the port keeps the
 * two-argument form of the contract, `evaluate(context, instruction)`.
 *
 * The data must hold no identifier of a student (author, user id, name, e-mail): application\llm\context_guard checks it before
 * any call (DEC-34 D3). For FORUM_ANALYSIS the data must carry `discussion_ids`, the list of discussions the answer may refer to;
 * the validator rejects a difficulty of a discussion that is not in it (spec-013, quickstart 013 Q8).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class llm_context {
    /**
     * Constructor.
     *
     * @param llm_use_case $usecase
     * @param array $data
     */
    public function __construct(
        /** @var llm_use_case the use that is asked */
        public readonly llm_use_case $usecase,
        /** @var array data of the use */
        public readonly array $data = []
    ) {
    }

    /**
     * Discussions that a forum answer may refer to.
     *
     * @return int[]
     */
    public function discussion_ids(): array {
        $ids = $this->data['discussion_ids'] ?? [];
        return is_array($ids) ? array_values(array_filter($ids, 'is_int')) : [];
    }
}
