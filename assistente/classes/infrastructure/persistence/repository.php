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
 * Base of the repositories of the own aggregates (spec-003, contracts/persistence-ports.md).
 *
 * DML only lives in the infrastructure layer. Table names are always the logical Moodle name
 * ("local_assistente_x"), never the physical one with the site prefix. Values of fields whose
 * domain the XMLDB cannot restrict are validated here before writing (data-model of spec-002).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class repository {
    /**
     * Logical name of the main table of the aggregate, without the physical prefix.
     *
     * @return string
     */
    abstract protected function table(): string;

    /**
     * Allowed values by field. Defaults to the approved domains of the table.
     *
     * @return array<string, array> field => allowed values
     */
    protected function domain_rules(): array {
        return schema_domains::for_table($this->table());
    }

    /**
     * Read a record by its id.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public function find(int $id): ?\stdClass {
        global $DB;
        $record = $DB->get_record($this->table(), ['id' => $id], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Read the record that matches a natural key. At most one record is expected.
     *
     * @param array $conditions field => value
     * @return \stdClass|null
     */
    public function find_by(array $conditions): ?\stdClass {
        global $DB;
        $record = $DB->get_record($this->table(), $conditions, '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Read all records that match the conditions.
     *
     * @param array $conditions field => value
     * @param string $sort
     * @return \stdClass[] indexed by id
     */
    public function find_all_by(array $conditions = [], string $sort = 'id ASC'): array {
        global $DB;
        return $DB->get_records($this->table(), $conditions, $sort);
    }

    /**
     * Insert a record after validating the value domains.
     *
     * @param \stdClass $record
     * @return int the new id
     * @throws invalid_domain_value_exception when a value is outside its domain
     * @throws duplicate_record_exception when a unique index is violated (nothing is written)
     */
    public function insert(\stdClass $record): int {
        global $DB;
        $this->validate($record);
        try {
            return (int) $DB->insert_record($this->table(), $record);
        } catch (\dml_write_exception $e) {
            throw $this->translate_write_exception($e);
        }
    }

    /**
     * Update a record (identified by its id) after validating the value domains.
     *
     * @param \stdClass $record must have the id
     * @throws invalid_domain_value_exception when a value is outside its domain
     * @throws duplicate_record_exception when a unique index is violated
     */
    public function update(\stdClass $record): void {
        global $DB;
        $this->validate($record);
        try {
            $DB->update_record($this->table(), $record);
        } catch (\dml_write_exception $e) {
            throw $this->translate_write_exception($e);
        }
    }

    /**
     * Check that every present field with a rule holds an allowed value.
     *
     * Numeric strings coming from the database are accepted for integer domains; null is not
     * accepted unless the rule lists it.
     *
     * @param \stdClass $record
     * @throws invalid_domain_value_exception
     */
    protected function validate(\stdClass $record): void {
        foreach ($this->domain_rules() as $field => $allowed) {
            if (!property_exists($record, $field)) {
                continue;
            }
            $value = $record->$field;
            if (is_string($value) && $allowed && is_int($allowed[0]) && is_numeric($value)) {
                $value = (int) $value;
            }
            if (!in_array($value, $allowed, true)) {
                $shown = is_scalar($value) ? (string) $value : gettype($value);
                throw new invalid_domain_value_exception(
                    "Value '{$shown}' is outside the domain of {$this->table()}.{$field}."
                );
            }
        }
    }

    /**
     * Convert a write failure caused by a unique index into a specific exception.
     *
     * The classification reads the database error text, which differs between database types;
     * what is not recognised is rethrown unchanged, never swallowed. Behaviour on the target
     * database is validated in CL-07.
     *
     * @param \dml_write_exception $e
     * @return \Throwable
     */
    private function translate_write_exception(\dml_write_exception $e): \Throwable {
        $text = strtolower(($e->error ?? '') . ' ' . ($e->debuginfo ?? '') . ' ' . $e->getMessage());
        if (str_contains($text, 'duplicate') || str_contains($text, 'unique constraint')) {
            return new duplicate_record_exception(
                "Unique index of {$this->table()} violated; nothing was duplicated.",
                0,
                $e
            );
        }
        return $e;
    }
}
