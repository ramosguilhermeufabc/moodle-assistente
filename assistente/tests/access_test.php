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

namespace local_assistente;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Definition of the capabilities and their default archetypes (DEC-30, DEC-45 D1 and D2; D2's manager closed by
 * CL-42).
 *
 * `view` and `manage` share the same default archetypes (editing teacher and manager). `correctcriteria` is a
 * different permission (DEC-45 D2): only `manager` has it by default, the editing teacher included, even though the
 * editing teacher has `manage`.
 *
 * The behaviour of the access check itself is tested in application/access_service_test.php and in
 * application/course_config_service_test.php (the correction policy). The installation of the capabilities in a
 * running Moodle 5.2 is validated by CL-26 (task T041).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_test extends \advanced_testcase {
    /** @var string[] every capability of the plugin */
    private const CAPABILITIES = ['local/assistente:view', 'local/assistente:manage', 'local/assistente:correctcriteria'];

    /** @var string[] capabilities with the shared default archetypes (editing teacher and manager) */
    private const SHARED_CAPABILITIES = ['local/assistente:view', 'local/assistente:manage'];

    /** Capability whose only default archetype is the manager (DEC-45 D2, CL-42). */
    private const CORRECTION_CAPABILITY = 'local/assistente:correctcriteria';

    /**
     * Capabilities as declared in db/access.php.
     *
     * @return array
     */
    private function declared(): array {
        global $CFG;
        $capabilities = [];
        include($CFG->dirroot . '/local/assistente/db/access.php');
        return $capabilities;
    }

    /**
     * Exactly the three capabilities are declared, in the course context. `view` and `manage` default to
     * editingteacher and manager; `correctcriteria` defaults to manager only (DEC-45 D2, CL-42).
     */
    public function test_declaration_in_access_php(): void {
        $declared = $this->declared();

        $this->assertEqualsCanonicalizing(self::CAPABILITIES, array_keys($declared));
        foreach ($declared as $name => $definition) {
            $this->assertSame(CONTEXT_COURSE, $definition['contextlevel'], $name);
        }
        foreach (self::SHARED_CAPABILITIES as $name) {
            $this->assertEquals(
                ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
                $declared[$name]['archetypes'],
                "{$name}: only editingteacher and manager receive it by default (DEC-30, DEC-45 D1)"
            );
        }
        $this->assertEquals(
            ['manager' => CAP_ALLOW],
            $declared[self::CORRECTION_CAPABILITY]['archetypes'],
            self::CORRECTION_CAPABILITY . ': only manager receives it by default (DEC-45 D2, CL-42)'
        );
        $this->assertSame('read', $declared['local/assistente:view']['captype']);
        $this->assertSame('write', $declared['local/assistente:manage']['captype']);
        $this->assertSame('write', $declared[self::CORRECTION_CAPABILITY]['captype']);
    }

    /**
     * The capabilities are registered for the plugin in the course context.
     */
    public function test_capabilities_are_installed(): void {
        global $DB;
        foreach (self::CAPABILITIES as $name) {
            $record = $DB->get_record('capabilities', ['name' => $name]);
            $this->assertNotFalse($record, "{$name} must be installed");
            $this->assertSame('local_assistente', $record->component);
            $this->assertEquals(CONTEXT_COURSE, $record->contextlevel);
        }
    }

    /**
     * Archetypes that receive the shared capabilities (`view`, `manage`) by default.
     *
     * @return array
     */
    public static function allowed_archetypes(): array {
        return ['editingteacher' => ['editingteacher'], 'manager' => ['manager']];
    }

    /**
     * Archetypes that do not receive the shared capabilities (`view`, `manage`) by default.
     *
     * @return array
     */
    public static function denied_archetypes(): array {
        return [
            'non-editing teacher' => ['teacher'],
            'student' => ['student'],
            'guest' => ['guest'],
            'authenticated user' => ['user'],
            'authenticated user on the front page' => ['frontpage'],
            'course creator' => ['coursecreator'],
        ];
    }

    /**
     * Archetypes that do not receive `correctcriteria` by default: every archetype except manager, the editing
     * teacher included. `manage` and `correctcriteria` are different permissions (DEC-45 D2, CL-42): having one
     * does not imply the other.
     *
     * @return array
     */
    public static function archetypes_without_correction(): array {
        return [
            'editing teacher' => ['editingteacher'],
            'non-editing teacher' => ['teacher'],
            'student' => ['student'],
            'guest' => ['guest'],
            'authenticated user' => ['user'],
            'authenticated user on the front page' => ['frontpage'],
            'course creator' => ['coursecreator'],
        ];
    }

    /**
     * The default archetypes allow both shared capabilities.
     *
     * @param string $archetype
     */
    #[DataProvider('allowed_archetypes')]
    public function test_default_archetypes_allow(string $archetype): void {
        $defaults = get_default_capabilities($archetype);
        foreach (self::SHARED_CAPABILITIES as $name) {
            $this->assertArrayHasKey($name, $defaults);
            $this->assertEquals(CAP_ALLOW, $defaults[$name]);
        }
    }

    /**
     * No other archetype receives the shared capabilities by default (the non-editing teacher included).
     *
     * @param string $archetype
     */
    #[DataProvider('denied_archetypes')]
    public function test_other_archetypes_do_not_receive_by_default(string $archetype): void {
        $defaults = get_default_capabilities($archetype);
        foreach (self::SHARED_CAPABILITIES as $name) {
            $this->assertArrayNotHasKey($name, $defaults, "{$archetype} must not receive {$name}");
        }
    }

    /**
     * CL-42: the manager, and only the manager, receives `correctcriteria` by default.
     */
    public function test_manager_receives_the_correction_capability_by_default(): void {
        $defaults = get_default_capabilities('manager');
        $this->assertArrayHasKey(self::CORRECTION_CAPABILITY, $defaults);
        $this->assertEquals(CAP_ALLOW, $defaults[self::CORRECTION_CAPABILITY]);
    }

    /**
     * CL-42: no other archetype receives `correctcriteria` by default, including the editing teacher, who has
     * `manage` but not this one.
     *
     * @param string $archetype
     */
    #[DataProvider('archetypes_without_correction')]
    public function test_other_archetypes_do_not_receive_the_correction_capability(string $archetype): void {
        $defaults = get_default_capabilities($archetype);
        $this->assertArrayNotHasKey(
            self::CORRECTION_CAPABILITY,
            $defaults,
            "{$archetype} must not receive " . self::CORRECTION_CAPABILITY
        );
    }

    /**
     * The installed roles follow the archetypes: the editing teacher and the manager have the shared capabilities in
     * the system context (inherited by every course); only the manager also has `correctcriteria` (DEC-45 D2,
     * CL-42); the non-editing teacher and the student have neither.
     */
    public function test_installed_roles_follow_the_archetypes(): void {
        global $DB;
        $systemid = \context_system::instance()->id;
        $expected = [
            'editingteacher' => ['shared' => true, 'correction' => false],
            'manager' => ['shared' => true, 'correction' => true],
            'teacher' => ['shared' => false, 'correction' => false],
            'student' => ['shared' => false, 'correction' => false],
        ];
        foreach ($expected as $shortname => $permissions) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
            foreach (self::SHARED_CAPABILITIES as $name) {
                $permission = $DB->get_field('role_capabilities', 'permission', [
                    'roleid' => $roleid, 'capability' => $name, 'contextid' => $systemid,
                ], IGNORE_MISSING);
                if ($permissions['shared']) {
                    $this->assertEquals(CAP_ALLOW, $permission, "{$shortname} must have {$name}");
                } else {
                    $this->assertFalse($permission, "{$shortname} must not have {$name} by default");
                }
            }
            $permission = $DB->get_field('role_capabilities', 'permission', [
                'roleid' => $roleid, 'capability' => self::CORRECTION_CAPABILITY, 'contextid' => $systemid,
            ], IGNORE_MISSING);
            if ($permissions['correction']) {
                $this->assertEquals(CAP_ALLOW, $permission, "{$shortname} must have " . self::CORRECTION_CAPABILITY);
            } else {
                $this->assertFalse(
                    $permission,
                    "{$shortname} must not have " . self::CORRECTION_CAPABILITY . ' by default'
                );
            }
        }
    }
}
