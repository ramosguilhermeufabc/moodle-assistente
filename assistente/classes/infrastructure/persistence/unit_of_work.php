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

/**
 * Transactional unit of work with the Lock API (plan 003, "Unidades transacionais").
 *
 * Rules (contracts/persistence-ports.md):
 * - locks are acquired before the transaction is opened and released after the commit or the rollback;
 * - the transaction is short and only wraps the writes;
 * - the LLM port is never called while a transaction is open (see {@see self::assert_no_active_transaction()}).
 *
 * Which lock backend the site uses, and its behaviour under real concurrency, is validated
 * in the technical validation CL-07 (task T020) and CL-02, not by this class.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unit_of_work {
    /** Lock factory type used for every resource of the plugin. */
    public const LOCK_TYPE = 'local_assistente';

    /** @var \core\lock\lock_factory|null the factory shared by the process */
    private static ?\core\lock\lock_factory $factory = null;

    /** @var int[] resources this process holds through this class, with the nesting depth */
    private static array $held = [];

    /** Seconds to wait for each lock when the caller does not choose another value. */
    public const DEFAULT_LOCK_TIMEOUT = 30;

    /**
     * Constructor.
     *
     * @param int $locktimeout seconds to wait for each lock
     */
    public function __construct(
        /** @var int seconds to wait for each lock */
        private readonly int $locktimeout = self::DEFAULT_LOCK_TIMEOUT
    ) {
    }

    /**
     * The lock factory of the plugin, one for the whole process.
     *
     * Moodle's factory remembers the locks it handed out and refuses to stack a second acquisition of the same resource, but
     * lock_config::get_lock_factory() returns a new factory each time, and with the MySQL/MariaDB backend (GET_LOCK, which stacks
     * for one connection) a second factory would acquire a lock that the first one holds. One shared factory makes a second
     * acquisition of the same resource in a process fail fast, as a second process would (DEC-22). Tests that hold a lock to
     * simulate another run must take it from here.
     *
     * @return \core\lock\lock_factory
     */
    public static function lock_factory(): \core\lock\lock_factory {
        return self::$factory ??= \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE);
    }

    /**
     * Forget the shared factory (for tests that change the lock configuration).
     */
    public static function reset_lock_factory(): void {
        self::$factory = null;
        self::$held = [];
    }

    /**
     * Lock resource of an event/processing transition (DEC-16, DEC-22, DEC-32).
     *
     * @param int $eventid
     * @return string
     */
    public static function event_resource(int $eventid): string {
        return "event_{$eventid}";
    }

    /**
     * Lock resource of a material and its versions (DEC-15, DEC-20).
     *
     * @param int $materialid
     * @return string
     */
    public static function material_resource(int $materialid): string {
        return "material_{$materialid}";
    }

    /**
     * Lock resource of the synthesis versions of a course section (DEC-41).
     *
     * @param int $courseid
     * @param int $sectionid
     * @return string
     */
    public static function section_resource(int $courseid, int $sectionid): string {
        return "synthesis_{$courseid}_{$sectionid}";
    }

    /**
     * Lock resource of the events of a student (T104; DEC-57). The erasure of the data of a student holds it for the whole request, and
     * the creation of any event whose payload has a `userid` takes it, so that no event of that student is born while the erasure runs.
     *
     * Global order of the resources. run() sorts the resources of each call as strings, and the locks that cross calls are the one of the
     * event held by the queue and the one of the erasure of a course held by the retry task around its whole delivery, so every path that
     * holds one lock and asks for another goes upward in this order:
     * `course_erasure_<c>` < `event_<n>` < `event_identity_<hash>` < `material_<n>` < `privacy_user_<n>` < `report_<n>` < `synthesis_<c>_<s>`
     * (the digit of `event_<n>` sorts before the `i` of `event_identity`; the `c` of `course_erasure` sorts before every other first letter).
     * A new resource must be placed in this order, and a path must not ask, while it holds a lock, for one that comes before it.
     * unit_of_work_test keeps the order of the names.
     *
     * @param int $userid
     * @return string
     */
    public static function privacy_user_resource(int $userid): string {
        return "privacy_user_{$userid}";
    }

    /**
     * Lock resource of the erasure of the data of a course (redelivery of an event; exception to DEC-32 D2). The erasure of a course holds it
     * from before its transaction until after its commit, and the retry task of a delivery holds it around its whole delivery, so that the two
     * never overlap: a task that comes after the erasure finds the course gone, and an erasure that comes after a task removes the event it made.
     * It is the first in the global order (see privacy_user_resource()), so a path that takes it takes it before any other lock.
     *
     * @param int $courseid
     * @return string
     */
    public static function course_erasure_resource(int $courseid): string {
        return "course_erasure_{$courseid}";
    }

    /**
     * Lock resource of a logical report and its versions (DEC-23, DEC-44).
     *
     * @param int $reportid
     * @return string
     */
    public static function report_resource(int $reportid): string {
        return "report_{$reportid}";
    }

    /**
     * Run the work inside a short delegated transaction, under the given locks.
     *
     * Resources are acquired in sorted order, to avoid deadlocks between units that need several.
     * If the work throws, the transaction is rolled back and the exception is rethrown; the locks
     * are always released.
     *
     * @param callable $work the writes; its return value is returned
     * @param string[] $resources lock resources, empty for units without lock
     * @return mixed the return value of $work
     * @throws lock_unavailable_exception when a lock cannot be acquired within the timeout
     * @throws \coding_exception when called with locks while a transaction is already open
     */
    public function run(callable $work, array $resources = []) {
        global $DB;

        $resources = array_values(array_unique($resources));
        sort($resources, SORT_STRING);
        // A resource this process already holds (the handler of an event runs under the lock of its event and its repositories lock it
        // again) is the same owner: it is not acquired a second time.
        $toacquire = array_values(array_filter($resources, static fn(string $r) => !isset(self::$held[$r])));

        if ($toacquire && $DB->is_transaction_started()) {
            throw new \coding_exception('Locks must be acquired before the transaction is opened.');
        }

        $locks = [];
        try {
            if ($toacquire) {
                $factory = self::lock_factory();
                foreach ($toacquire as $resource) {
                    $lock = $factory->get_lock($resource, $this->locktimeout);
                    if (!$lock) {
                        throw new lock_unavailable_exception("Could not acquire the lock '{$resource}'.", $resource);
                    }
                    $locks[$resource] = $lock;
                    self::$held[$resource] = 1;
                }
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                $result = $work();
            } catch (\Throwable $e) {
                $transaction->rollback($e); // Rethrows $e.
            }
            $transaction->allow_commit();

            return $result;
        } finally {
            foreach (array_reverse($locks, true) as $resource => $lock) {
                unset(self::$held[$resource]);
                $lock->release();
            }
        }
    }

    /**
     * Run work under a lock, without opening a transaction.
     *
     * For sections that include slow or external work (the handler of an event, which may call the LLM), where the lock must
     * be held for the whole processing but a transaction must not (DEC-22, P-04, spec-007). The work opens its own short
     * transactions, through run() without resources, for the writes.
     *
     * @param string $resource lock resource
     * @param callable $work
     * @return mixed the return value of $work
     * @throws lock_unavailable_exception when the lock cannot be acquired within the timeout
     * @throws \coding_exception when a transaction is already open
     */
    public function run_locked(string $resource, callable $work) {
        global $DB;
        // A second run_locked() on a resource this process already holds is refused by the shared factory: it is another run, not the
        // same owner (only run(), the short transactions of the work under the lock, shares the lock of its caller).
        if ($DB->is_transaction_started()) {
            throw new \coding_exception('Locks must be acquired before the transaction is opened.');
        }

        $lock = self::lock_factory()->get_lock($resource, $this->locktimeout);
        if (!$lock) {
            throw new lock_unavailable_exception("Could not acquire the lock '{$resource}'.", $resource);
        }
        self::$held[$resource] = 1;
        try {
            return $work();
        } finally {
            unset(self::$held[$resource]);
            $lock->release();
        }
    }

    /**
     * Guard for callers of the LLM port: no call is allowed while a transaction is open (spec-007).
     *
     * @throws \coding_exception
     */
    public static function assert_no_active_transaction(): void {
        global $DB;
        if ($DB->is_transaction_started()) {
            throw new \coding_exception('The LLM port must not be called while a database transaction is open.');
        }
    }
}
