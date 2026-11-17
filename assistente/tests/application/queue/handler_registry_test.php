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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the handler contract: registry, results and failure classes (plan 009 Q-009-03; DEC-32 D3 and D4).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(handler_registry::class)]
#[CoversClass(handler_result::class)]
#[CoversClass(attempt_failure::class)]
final class handler_registry_test extends \basic_testcase {
    /**
     * A handler that always succeeds.
     *
     * @return event_handler
     */
    private function handler(): event_handler {
        return new class implements event_handler {
            #[\Override]
            public function handle(\stdClass $event, \stdClass $processing): handler_result {
                return handler_result::success();
            }
        };
    }

    /**
     * A registered handler is found by its type; other types have none.
     */
    public function test_registered_handler_is_found_by_type(): void {
        $registry = new handler_registry();
        $handler = $this->handler();

        $registry->register(event_type::QUIZ_PUBLISHED, $handler);

        $this->assertSame($handler, $registry->handler_for(event_type::QUIZ_PUBLISHED));
        $this->assertNull($registry->handler_for(event_type::QUIZ_CLOSED));
        $this->assertSame([event_type::QUIZ_PUBLISHED], $registry->registered_types());
    }

    /**
     * Two handlers for one type are a programming error, not a silent replacement.
     */
    public function test_a_type_cannot_have_two_handlers(): void {
        $registry = new handler_registry();
        $registry->register(event_type::QUIZ_PUBLISHED, $this->handler());

        $this->expectException(\LogicException::class);
        $registry->register(event_type::QUIZ_PUBLISHED, $this->handler());
    }

    /**
     * An empty registry has no types: nothing is processed until a delivery registers its handler.
     */
    public function test_empty_registry_and_default(): void {
        $this->assertSame([], (new handler_registry())->registered_types());
        $default = handler_registry::default();
        $this->assertSame(
            array_keys(handler_registry::DEFAULT_HANDLERS),
            array_map(static fn(event_type $type) => $type->value, $default->registered_types())
        );
        foreach (handler_registry::DEFAULT_HANDLERS as $class) {
            $this->assertTrue(is_subclass_of($class, event_handler::class), "{$class} must implement event_handler");
        }
    }

    /**
     * Every type of the catalog has a default handler (DEC-63): a type added to event_type without its handler would
     * otherwise wait pending forever (CL-43), silently.
     */
    public function test_every_event_type_has_a_default_handler(): void {
        $registered = array_keys(handler_registry::DEFAULT_HANDLERS);
        foreach (event_type::cases() as $type) {
            $this->assertContains(
                $type->value,
                $registered,
                "{$type->value} has no entry in handler_registry::DEFAULT_HANDLERS (DEC-63, CL-43)."
            );
        }
    }

    /**
     * The six failure classes of the contract, and the way they are written in the response.
     */
    public function test_failure_classes_and_their_text(): void {
        $this->assertSame(
            ['timeout', 'transport', 'auth', 'rate_limit', 'invalid_response', 'technical'],
            array_map(static fn(attempt_failure $failure) => $failure->value, attempt_failure::cases())
        );
        $this->assertSame('rate_limit: too many requests', attempt_failure::RATE_LIMIT->format('too many requests'));
        $this->assertSame(attempt_failure::RATE_LIMIT, attempt_failure::from_response('rate_limit: too many requests'));
        $this->assertSame(attempt_failure::TECHNICAL, attempt_failure::from_response('technical: a: b'));
        $this->assertNull(attempt_failure::from_response('interrupted'));
        $this->assertNull(attempt_failure::from_response('unknown: x'));
        $this->assertNull(attempt_failure::from_response(null));
    }

    /**
     * A success carries its result; a failure carries a class and a reason, written with the class as the prefix.
     */
    public function test_handler_results(): void {
        $ok = handler_result::success('done');
        $this->assertTrue($ok->success);
        $this->assertSame('done', $ok->response());
        $this->assertNull(handler_result::success()->response());

        $failed = handler_result::failure(attempt_failure::TIMEOUT, 'no answer');
        $this->assertFalse($failed->success);
        $this->assertSame(attempt_failure::TIMEOUT, $failed->failure);
        $this->assertSame('timeout: no answer', $failed->response());
    }
}
