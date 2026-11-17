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
 * Repository of one table of an aggregate that has no behaviour of its own besides the base repository.
 *
 * Used inside an aggregate repository for its dependent tables (for example, the attempts of a processing), so that the
 * insert, update and the validation of the approved value domains are the same everywhere (schema_domains).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class table_repository extends repository {
    /**
     * Constructor.
     *
     * @param string $table logical table name, without the physical prefix
     */
    public function __construct(
        /** @var string logical table name */
        private readonly string $tablename
    ) {
    }

    #[\Override]
    protected function table(): string {
        return $this->tablename;
    }
}
