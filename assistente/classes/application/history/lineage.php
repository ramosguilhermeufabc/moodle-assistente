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


namespace local_assistente\application\history;

/**
 * The chain behind an analysis or one of its results, as it is persisted (T101; spec-017, DEC-42): Moodle source, event, processing,
 * attempts, context snapshot, analysis and result. Read only; built by lineage_reader.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lineage {
    /**
     * Constructor.
     *
     * @param \stdClass $analysis the analysis row
     * @param \stdClass $processing the processing that made it
     * @param \stdClass $event the event of the processing
     * @param array $payload the decoded payload of the event
     * @param \stdClass[] $attempts the attempts of the processing, in order
     * @param array|null $context the decoded context snapshot; null when the processing wrote none
     * @param array $materials the materials of the snapshot, each with material_id, cmid, name, material_type, version_id, version, eligible
     *        and reason; [] when the snapshot has none or there is no snapshot
     * @param array $sources the Moodle records the chain starts from, each ['type' => string, 'id' => int]; the references only, whether the
     *        record still exists is not asked here
     * @param array|null $result the result row that was traced, ['table' => string, 'row' => \stdClass]; null when an analysis was traced
     */
    public function __construct(
        /** @var \stdClass analysis */
        public readonly \stdClass $analysis,
        /** @var \stdClass processing */
        public readonly \stdClass $processing,
        /** @var \stdClass event */
        public readonly \stdClass $event,
        /** @var array decoded payload */
        public readonly array $payload,
        /** @var \stdClass[] attempts */
        public readonly array $attempts,
        /** @var array|null decoded context */
        public readonly ?array $context,
        /** @var array materials of the snapshot */
        public readonly array $materials,
        /** @var array Moodle sources */
        public readonly array $sources,
        /** @var array|null the traced result */
        public readonly ?array $result = null
    ) {
    }
}
