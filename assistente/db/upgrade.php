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

/**
 * Upgrade steps of local_assistente (spec-002, Q-002-02, DEC-29).
 *
 * There are no steps yet: the schema was changed directly in install.xml before the first installation. Every
 * later schema change adds a block here, with its own savepoint, together with a version bump in version.php:
 *
 *     if ($oldversion < 2026XXXXXX) {
 *         // XMLDB change (add field, add index, ...).
 *         upgrade_plugin_savepoint(true, 2026XXXXXX, 'local', 'assistente');
 *     }
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrades the schema of the plugin.
 *
 * @param int $oldversion The version installed before the upgrade.
 * @return bool Always true.
 */
function xmldb_local_assistente_upgrade($oldversion) {
    return true;
}
