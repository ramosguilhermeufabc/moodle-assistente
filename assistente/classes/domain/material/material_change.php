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

namespace local_assistente\domain\material;

/**
 * Result of comparing a material with what the plugin recorded (DEC-33 D3).
 *
 * `kind` says whether a version must be created:
 * - NEW_MATERIAL: the material is not known yet; register it and create version 1.
 * - NEW_VERSION: the processable content changed (the file, the text of the page or the URL); create the next version.
 * - NONE: the content is the same; no version is created.
 *
 * Whatever the kind, `sectionchanged` and `renamed` say that the section or the name of the registered material must be
 * brought up to date. Neither of them creates a version: the context uses the section in force when the snapshot is built.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class material_change {
    /** @var string the material is not registered */
    public const NEW_MATERIAL = 'new_material';

    /** @var string the processable content changed */
    public const NEW_VERSION = 'new_version';

    /** @var string no version must be created */
    public const NONE = 'none';

    /**
     * Constructor.
     *
     * @param string $kind one of the constants of this class
     * @param bool $sectionchanged the material moved to another section
     * @param bool $renamed the name changed
     */
    public function __construct(
        /** @var string what to do about versions */
        public readonly string $kind,
        /** @var bool the section changed */
        public readonly bool $sectionchanged = false,
        /** @var bool the name changed */
        public readonly bool $renamed = false
    ) {
        if (!in_array($kind, [self::NEW_MATERIAL, self::NEW_VERSION, self::NONE], true)) {
            throw new \InvalidArgumentException("Unknown kind of material change '{$kind}'.");
        }
    }

    /**
     * Whether a new version must be created (also for a material that is new).
     *
     * @return bool
     */
    public function creates_version(): bool {
        return $this->kind !== self::NONE;
    }

    /**
     * Whether the registered material (not its versions) must be updated.
     *
     * @return bool
     */
    public function updates_material(): bool {
        return $this->kind !== self::NEW_MATERIAL && ($this->sectionchanged || $this->renamed);
    }
}
