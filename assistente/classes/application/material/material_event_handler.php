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

namespace local_assistente\application\material;

use local_assistente\application\queue\attempt_failure;
use local_assistente\application\queue\event_handler;
use local_assistente\application\queue\handler_result;

/**
 * Handler of the event `material_changed` (a material was included or its content changed; DEC-33): delegates to the material
 * service. Registered in handler_registry::DEFAULT_HANDLERS (T063).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class material_event_handler implements event_handler {
    /** @var material_service|null */
    private ?material_service $service;

    /**
     * Constructor.
     *
     * @param material_service|null $service the default service (simulated LLM) when null
     */
    public function __construct(?material_service $service = null) {
        $this->service = $service;
    }

    #[\Override]
    public function handle(\stdClass $event, \stdClass $processing): handler_result {
        $payload = json_decode((string) $event->payload, true);
        $cmid = is_array($payload) ? ($payload['cmid'] ?? null) : null;
        if (!is_int($cmid) || $cmid <= 0) {
            return handler_result::failure(attempt_failure::TECHNICAL, 'the event has no valid cmid');
        }
        $this->service ??= new material_service();
        return $this->service->process((int) $event->course_id, $cmid);
    }
}
