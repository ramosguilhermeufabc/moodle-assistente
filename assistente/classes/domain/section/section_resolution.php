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

namespace local_assistente\domain\section;

/**
 * Result of resolving or validating a section: the course_sections.id or an explicit unresolved status.
 *
 * The section id is the same course_sections.id used in material.section_id and frequency.section_id.
 * The resolution never writes anything; recording a processing pendency belongs to the processing mechanism.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class section_resolution {
    /**
     * Constructor; use the named constructors.
     *
     * @param section_resolution_status $status
     * @param int|null $sectionid only when resolved
     */
    private function __construct(
        /** @var section_resolution_status outcome */
        public readonly section_resolution_status $status,
        /** @var int|null course_sections.id, only when resolved */
        public readonly ?int $sectionid
    ) {
    }

    /**
     * A resolved section.
     *
     * @param int $sectionid course_sections.id
     * @return self
     */
    public static function resolved(int $sectionid): self {
        return new self(section_resolution_status::RESOLVED, $sectionid);
    }

    /**
     * An unresolved outcome.
     *
     * @param section_resolution_status $status any status but RESOLVED
     * @return self
     * @throws \InvalidArgumentException when given RESOLVED
     */
    public static function unresolved(section_resolution_status $status): self {
        if ($status === section_resolution_status::RESOLVED) {
            throw new \InvalidArgumentException('A resolved section needs its id.');
        }
        return new self($status, null);
    }

    /**
     * Whether the section was resolved.
     *
     * @return bool
     */
    public function is_resolved(): bool {
        return $this->status === section_resolution_status::RESOLVED;
    }
}
