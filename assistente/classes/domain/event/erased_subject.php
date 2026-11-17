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


namespace local_assistente\domain\event;

/**
 * What an event carries after the data of its student were erased (T104; DEC-57, DEC-42 D1).
 *
 * The payload keeps its shape: the `userid` becomes 0, which is an integer for the schema of the event and never a user, and the `identity`
 * of a type that derives it from the `userid` becomes forty zeros, which keeps the format of a hash and never equals the identity of a new
 * event. The producers refuse a `userid` that is not positive, so the 0 cannot come from a bug of theirs; the handlers read it as "nothing to do".
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erased_subject {
    /** @var int the `userid` of an event whose student was erased */
    public const USERID = 0;

    /** @var string the `identity` of such an event, in the format of the identities (40 hexadecimal characters) */
    public const IDENTITY = '0000000000000000000000000000000000000000';

    /** @var string response of the attempt that finishes such an event without doing anything */
    public const RESPONSE = 'the subject of the event was erased: nothing to do';
}
