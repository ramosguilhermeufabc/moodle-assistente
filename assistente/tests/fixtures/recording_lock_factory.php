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

namespace local_assistente\tests\fixtures;

/**
 * A lock factory that records the order in which the locks are taken, for tests of the global order of the locks (redelivery of an event).
 *
 * It wraps the factory of the plugin and gives every lock back unchanged. Each acquisition is recorded with the locks the process held at
 * that moment, so a test can say not only the sequence but also that a lock was taken first, with nothing else held.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recording_lock_factory implements \core\lock\lock_factory {
    /** @var array[] each acquisition: the resource and the resources held when it was asked for */
    public array $log = [];

    /** @var string[] the resources held now, by key */
    private array $held = [];

    /** @var \core\lock\lock[] the real locks, by resource */
    private array $locks = [];

    /** @var \core\lock\lock_factory */
    private \core\lock\lock_factory $real;

    /**
     * Constructor.
     *
     * @param string $type
     * @param \core\lock\lock_factory|null $real
     */
    public function __construct($type, ?\core\lock\lock_factory $real = null) {
        $this->real = $real ?? \core\lock\lock_config::get_lock_factory($type);
    }

    #[\Override]
    public function supports_timeout() {
        return $this->real->supports_timeout();
    }

    #[\Override]
    public function supports_auto_release() {
        return $this->real->supports_auto_release();
    }

    #[\Override]
    public function is_available() {
        return $this->real->is_available();
    }

    #[\Override]
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        $heldbefore = array_values($this->held);
        $lock = $this->real->get_lock($resource, $timeout, $maxlifetime);
        if (!$lock) {
            return false;
        }
        $this->log[] = ['resource' => $resource, 'held_before' => $heldbefore];
        $this->held[$resource] = $resource;
        $this->locks[$resource] = $lock;
        return new \core\lock\lock($resource, $this);
    }

    #[\Override]
    public function release_lock(\core\lock\lock $lock) {
        $key = $lock->get_key();
        unset($this->held[$key]);
        $real = $this->locks[$key] ?? null;
        unset($this->locks[$key]);
        return $real ? $real->release() : false;
    }

    /**
     * The resources in the order they were acquired.
     *
     * @return string[]
     */
    public function sequence(): array {
        return array_column($this->log, 'resource');
    }
}
