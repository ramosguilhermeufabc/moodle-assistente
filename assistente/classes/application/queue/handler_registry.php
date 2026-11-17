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

namespace local_assistente\application\queue;

use local_assistente\domain\event\event_type;

/**
 * Handlers by event type.
 *
 * The queue only processes the types that have a handler: an event of a type without one is not selected, not attempted and
 * not failed, so that it waits (pending) indefinitely until the delivery that implements the type adds its handler. That is
 * the approved contract of DEC-63 (CL-43, closed), not a mere implementation choice.
 *
 * The scheduled task uses default(). Each delivery adds its type to DEFAULT_HANDLERS; a handler class needs a constructor
 * without required arguments.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class handler_registry {
    /**
     * Handlers of the plugin: event type value => class implementing event_handler. Filled by the delivery tasks
     * (materials, quiz analyses, metrics, grades, forums, frequency, synthesis).
     */
    public const DEFAULT_HANDLERS = [
        'material_changed' => \local_assistente\application\material\material_event_handler::class,
        'quiz_published' => \local_assistente\application\quiz\initial_analysis_handler::class,
        'quiz_attempt_submitted' => \local_assistente\application\quiz\results_handler::class,
        'quiz_attempt_manually_graded' => \local_assistente\application\quiz\results_handler::class,
        'quiz_attempt_regraded' => \local_assistente\application\quiz\results_handler::class,
        'quiz_closed' => \local_assistente\application\quiz\results_handler::class,
        'grade_changed' => \local_assistente\application\grade\performance_handler::class,
        'forum_post_created' => \local_assistente\application\forum\forum_analysis_handler::class,
        'forum_post_updated' => \local_assistente\application\forum\forum_analysis_handler::class,
        'forum_post_deleted' => \local_assistente\application\forum\forum_analysis_handler::class,
        'forum_discussion_deleted' => \local_assistente\application\forum\forum_analysis_handler::class,
        'forum_rebuild_requested' => \local_assistente\application\forum\forum_analysis_handler::class,
        'participation_changed' => \local_assistente\application\frequency\frequency_handler::class,
        'reference_date_reached' => \local_assistente\application\frequency\frequency_handler::class,
        'criteria_check_requested' => \local_assistente\application\criteria\criteria_handler::class,
        'course_criteria_check_requested' => \local_assistente\application\criteria\criteria_handler::class,
        'synthesis_requested' => \local_assistente\application\synthesis\synthesis_handler::class,
    ];

    /** @var array<string, event_handler> handlers by event type value */
    private array $handlers = [];

    /**
     * Registry with the handlers of the plugin.
     *
     * @return self
     */
    public static function default(): self {
        $registry = new self();
        foreach (self::DEFAULT_HANDLERS as $type => $class) {
            $registry->register(event_type::from($type), new $class());
        }
        return $registry;
    }

    /**
     * Register the handler of a type.
     *
     * @param event_type $type
     * @param event_handler $handler
     * @throws \LogicException when the type already has a handler
     */
    public function register(event_type $type, event_handler $handler): void {
        if (isset($this->handlers[$type->value])) {
            throw new \LogicException("The event type {$type->value} already has a handler.");
        }
        $this->handlers[$type->value] = $handler;
    }

    /**
     * Handler of a type.
     *
     * @param event_type $type
     * @return event_handler|null
     */
    public function handler_for(event_type $type): ?event_handler {
        return $this->handlers[$type->value] ?? null;
    }

    /**
     * Types that have a handler.
     *
     * @return event_type[]
     */
    public function registered_types(): array {
        return array_map(static fn(string $value) => event_type::from($value), array_keys($this->handlers));
    }
}
