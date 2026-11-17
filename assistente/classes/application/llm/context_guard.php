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

namespace local_assistente\application\llm;

use local_assistente\domain\llm\llm_context;

/**
 * Refuses a request that would send an identifier of a student to the LLM (DEC-34 D3; spec-009 §Regras; quickstart 009 Q9).
 *
 * No author, user id, name or e-mail of a student may be in the `context` or in the `instruction`. The guard checks what can be
 * checked mechanically:
 * - any key of the context, at any depth, whose normalised name (lower case, only letters and digits) is a known identifier
 *   key: userid, authorid, studentid, username, firstname, lastname, fullname, email, idnumber and the like;
 * - any e-mail address in a text of the context or in the instruction;
 * - any literal the caller says is forbidden (for example, the names of the students of the course), found as a whole text,
 *   ignoring case.
 *
 * LIMIT: free text cannot be understood here. A name inside a message that is not in the forbidden list passes. The services
 * that build the context must not copy author data into the text and should pass the names of the course participants as
 * forbidden literals. The exception names only the path or the kind of the finding, never the value, so that the log does not
 * become a new leak.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class context_guard {
    /** @var string[] normalised key names that identify a person */
    private const IDENTIFIER_KEYS = [
        'userid', 'userids', 'user', 'users', 'authorid', 'author', 'authors', 'authorname', 'studentid', 'studentids',
        'student', 'studentname', 'username', 'firstname', 'lastname', 'fullname', 'displayname', 'email', 'emailaddress',
        'useremail', 'idnumber', 'mail',
    ];

    /**
     * Check a request.
     *
     * @param llm_context $context
     * @param string $instruction
     * @param string[] $forbiddenliterals texts that must not appear (names of participants), case-insensitive
     * @throws student_identifier_exception when something that identifies a student is found
     */
    public function assert_clean(llm_context $context, string $instruction, array $forbiddenliterals = []): void {
        $findings = [];
        $this->scan($context->data, 'context', $forbiddenliterals, $findings);
        $this->scan_text($instruction, 'instruction', $forbiddenliterals, $findings);

        if ($findings) {
            throw new student_identifier_exception(
                'The request to the LLM identifies a student (' . implode('; ', $findings) . ').'
            );
        }
    }

    /**
     * Scan an array recursively.
     *
     * @param array $data
     * @param string $path
     * @param string[] $literals
     * @param string[] $findings
     */
    private function scan(array $data, string $path, array $literals, array &$findings): void {
        foreach ($data as $key => $value) {
            $here = $path . '.' . $key;
            if (is_string($key) && in_array(self::normalise($key), self::IDENTIFIER_KEYS, true)) {
                $findings[] = "key '{$here}'";
            }
            if (is_array($value)) {
                $this->scan($value, $here, $literals, $findings);
            } else if (is_string($value)) {
                $this->scan_text($value, $here, $literals, $findings);
            }
        }
    }

    /**
     * Scan a text.
     *
     * @param string $text
     * @param string $path
     * @param string[] $literals
     * @param string[] $findings
     */
    private function scan_text(string $text, string $path, array $literals, array &$findings): void {
        if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text)) {
            $findings[] = "e-mail address in '{$path}'";
        }
        foreach ($literals as $literal) {
            if (is_string($literal) && trim($literal) !== '' && mb_stripos($text, trim($literal)) !== false) {
                $findings[] = "forbidden text in '{$path}'";
            }
        }
    }

    /**
     * Normalise a key: lower case, letters and digits only.
     *
     * @param string $key
     * @return string
     */
    private static function normalise(string $key): string {
        return preg_replace('/[^a-z0-9]/', '', strtolower($key));
    }
}
