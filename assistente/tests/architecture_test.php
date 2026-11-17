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

/**
 * Architecture rules of the persistence layer (gate G-003; quickstart 003 Q1, Q2, Q3 and Q9).
 *
 * The source is inspected with the PHP tokenizer, ignoring comments. Rules for later deliveries
 * (forms, no LLM in deterministic calculations, no scoring constants) are added by their own tasks.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class architecture_test extends \advanced_testcase {
    /** @var string[] directories allowed directly under classes/ (plan 003, "Layout das classes") */
    private const CLASS_DIRECTORIES = [
        'domain', 'application', 'infrastructure', 'observer', 'task', 'privacy', 'form', 'output', 'event',
    ];

    /** @var string[] subdirectories allowed under classes/infrastructure/ */
    private const INFRASTRUCTURE_DIRECTORIES = ['persistence', 'moodle', 'llm'];

    /** @var string[] files allowed directly under classes/ */
    private const CLASS_ROOT_FILES = ['hook_callbacks.php'];

    /** @var string[] functions lib.php may define: only callbacks with no modern alternative (plan 003, "Callbacks") */
    private const ALLOWED_LIB_FUNCTIONS = ['local_assistente_extend_navigation_course'];

    /**
     * Root of the plugin source.
     *
     * @return string
     */
    private static function plugin_root(): string {
        global $CFG;
        return $CFG->dirroot . '/local/assistente';
    }

    /**
     * PHP files under classes/, relative to the plugin root with forward slashes.
     *
     * @return string[]
     */
    private static function class_files(): array {
        $base = self::plugin_root() . '/classes';
        if (!is_dir($base)) {
            return [];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(self::plugin_root()) + 1));
            }
        }
        sort($files);
        return $files;
    }

    /**
     * The code of a file without comments, as one string (string literals are kept).
     *
     * @param string $relative path relative to the plugin root
     * @return string
     */
    private static function code_without_comments(string $relative): string {
        $code = '';
        foreach (token_get_all(file_get_contents(self::plugin_root() . '/' . $relative)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }
        return $code;
    }

    /**
     * Whether a source mentions $DB or contains SQL.
     *
     * @param string $code
     * @return string|null description of the first violation
     */
    private static function find_dml_or_sql(string $code): ?string {
        if (preg_match('/\$DB\b/', $code)) {
            return '$DB';
        }
        $sql = '/\b(select\s[^;]*\sfrom\s|insert\s+into\s|update\s+\S+\s+set\s|delete\s+from\s)/i';
        if (preg_match($sql, $code, $match)) {
            return 'SQL: ' . trim($match[1]);
        }
        return null;
    }

    /**
     * Q1: no $DB or SQL outside the infrastructure (domain, application, observers, tasks, forms, output, privacy).
     */
    public function test_no_dml_or_sql_outside_the_infrastructure(): void {
        $violations = [];
        foreach (self::class_files() as $file) {
            if (str_starts_with($file, 'classes/infrastructure/')) {
                continue;
            }
            $found = self::find_dml_or_sql(self::code_without_comments($file));
            if ($found !== null) {
                $violations[] = "{$file}: {$found}";
            }
        }
        $this->assertSame([], $violations, 'DML belongs to the infrastructure layer only');
    }

    /**
     * The domain layer is pure: no database, no Moodle API, no global state.
     */
    public function test_domain_does_not_use_the_moodle_api(): void {
        $violations = [];
        foreach (self::class_files() as $file) {
            if (!str_starts_with($file, 'classes/domain/')) {
                continue;
            }
            $code = self::code_without_comments($file);
            if (preg_match('/\bglobal\s+\$|\\\\core\\\\|\b(get_string|get_config|set_config|require_login)\s*\(|\bMOODLE_INTERNAL\b/', $code)) {
                $violations[] = $file;
            }
        }
        $this->assertSame([], $violations, 'classes/domain must not depend on the Moodle API');
    }

    /**
     * Q2: no physical table prefix in any class or in lib.php.
     */
    public function test_no_physical_table_prefix(): void {
        $files = self::class_files();
        if (file_exists(self::plugin_root() . '/lib.php')) {
            $files[] = 'lib.php';
        }
        $violations = [];
        foreach ($files as $file) {
            if (preg_match('/\$CFG\s*->\s*prefix|get_prefix\s*\(|\bmdl_/', self::code_without_comments($file))) {
                $violations[] = $file;
            }
        }
        $this->assertSame([], $violations, 'Use logical table names, never the physical prefix');
    }

    /**
     * Q3: lib.php, when it exists, defines only the allowed callbacks and has no DML or SQL.
     */
    public function test_lib_php_has_only_the_necessary_callbacks(): void {
        $path = self::plugin_root() . '/lib.php';
        if (!file_exists($path)) {
            $this->assertFileDoesNotExist($path, 'lib.php is only created when a callback is needed (T123)');
            return;
        }

        $defined = [];
        $tokens = token_get_all(file_get_contents($path));
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_FUNCTION) {
                for ($next = $index + 1; $next < count($tokens); $next++) {
                    if (is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                        $defined[] = $tokens[$next][1];
                        break;
                    }
                    if ($tokens[$next] === '(') {
                        break; // Closure, not a named function.
                    }
                }
            }
            $this->assertFalse(
                is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true),
                'lib.php must not declare classes'
            );
        }
        $this->assertSame([], array_values(array_diff($defined, self::ALLOWED_LIB_FUNCTIONS)), 'Callbacks outside the plan');
        $this->assertNull(self::find_dml_or_sql(self::code_without_comments('lib.php')), 'lib.php has no business logic');
    }

    /**
     * Q9: classes live in the namespace of their directory, in the approved layout, with the file
     * named after the class (Moodle autoloading).
     */
    public function test_layout_and_namespaces(): void {
        $violations = [];
        foreach (self::class_files() as $file) {
            $parts = explode('/', $file); // Starts with "classes".
            array_shift($parts);
            $filename = array_pop($parts);

            if (!$parts) {
                if (!in_array($filename, self::CLASS_ROOT_FILES, true)) {
                    $violations[] = "{$file}: file not allowed directly under classes/";
                }
            } else if (!in_array($parts[0], self::CLASS_DIRECTORIES, true)) {
                $violations[] = "{$file}: directory '{$parts[0]}' is not in the approved layout";
            } else if (
                $parts[0] === 'infrastructure' && isset($parts[1])
                && !in_array($parts[1], self::INFRASTRUCTURE_DIRECTORIES, true)
            ) {
                $violations[] = "{$file}: infrastructure/{$parts[1]} is not in the approved layout";
            }

            $expectednamespace = implode('\\', array_merge(['local_assistente'], $parts));
            $code = file_get_contents(self::plugin_root() . '/' . $file);
            if (!preg_match('/^namespace\s+([^;]+);/m', $code, $namespace) || trim($namespace[1]) !== $expectednamespace) {
                $violations[] = "{$file}: namespace must be {$expectednamespace}";
            }
            if (!self::declares_name(token_get_all($code), basename($filename, '.php'))) {
                $violations[] = "{$file}: declared class/interface/enum name must match the file name";
            }
        }
        $this->assertSame([], $violations);
    }

    /**
     * Identifiers and variables of a source that name a weighting.
     *
     * @param string $code source with the opening tag, comments already removed
     * @return string[] the names found
     */
    private static function find_weighting(string $code): array {
        $found = [];
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_STRING, T_VARIABLE], true) && preg_match('/weight|ponder/i', $token[1])) {
                $found[] = $token[1];
            }
        }
        return $found;
    }

    /**
     * Threshold constants and comparisons against numbers that look like a threshold.
     *
     * A constant is a threshold when its name says so. A comparison (`>=`, `<=`, `>`, `<`) is read as one when it has a number other than
     * 0, 1, 2 and 100 at one side: those are the status codes, the neutral value and the scale of a percentage.
     *
     * @param string $code source with the opening tag, comments already removed
     * @param bool $comparisons whether to look at the comparisons too
     * @return string[] what was found
     */
    private static function find_thresholds(string $code, bool $comparisons): array {
        $found = [];
        $tokens = array_values(array_filter(
            token_get_all($code),
            static fn($t) => !(is_array($t) && $t[0] === T_WHITESPACE)
        ));
        $operators = ['>=', '<=', '>', '<'];
        foreach ($tokens as $i => $token) {
            if (is_array($token) && $token[0] === T_CONST && isset($tokens[$i + 1]) && is_array($tokens[$i + 1])
                    && preg_match('/THRESHOLD|CUTOFF|PASS|APPROV|MINIMUM|LIMIAR|MIN_?GRADE|MIN_?SCORE/i', $tokens[$i + 1][1])) {
                $found[] = 'constant ' . $tokens[$i + 1][1];
            }
            if (!$comparisons) {
                continue;
            }
            $operator = is_array($token) ? ($token[0] === T_IS_GREATER_OR_EQUAL ? '>=' : ($token[0] === T_IS_SMALLER_OR_EQUAL ? '<=' : null)) : $token;
            if (!in_array($operator, $operators, true)) {
                continue;
            }
            foreach ([$tokens[$i - 1] ?? null, $tokens[$i + 1] ?? null] as $side) {
                if (is_array($side) && in_array($side[0], [T_LNUMBER, T_DNUMBER], true) && !in_array((float) $side[1], [0.0, 1.0, 2.0, 100.0], true)) {
                    $found[] = "comparison {$operator} {$side[1]}";
                }
            }
        }
        return $found;
    }

    /**
     * The repository of the data of one student (the neutralization of the payload of an event and the deletion of individual results) is
     * reached only by the erasure of the student, in application/privacy (T104; DEC-57). No other layer may use it.
     */
    public function test_the_student_data_repository_is_used_only_by_the_privacy_erasure(): void {
        $users = [];
        foreach (self::class_files() as $file) {
            if ($file === 'classes/infrastructure/persistence/student_data_repository.php'
                    || $file === 'classes/infrastructure/persistence/history_redaction_writer.php') {
                continue;
            }
            if (preg_match('/\bstudent_data_repository\b/', self::code_without_comments($file))
                    && !str_starts_with($file, 'classes/application/privacy/') && !str_starts_with($file, 'classes/privacy/')) {
                $users[] = $file;
            }
        }
        $this->assertSame([], $users);
    }

    /**
     * The only path that writes into the historical tables (redaction of names, entries of snapshots, identifiers in messages) is the
     * restricted writer, and only the application of the privacy erasure may reach it (T104; DEC-56). No repository of history, no handler,
     * no observer and not even the provider may use it.
     */
    public function test_the_history_redaction_writer_is_used_only_by_the_privacy_application(): void {
        $users = [];
        foreach (self::class_files() as $file) {
            if ($file === 'classes/infrastructure/persistence/history_redaction_writer.php') {
                continue;
            }
            if (preg_match('/\bhistory_redaction_writer\b/', self::code_without_comments($file))
                    && !str_starts_with($file, 'classes/application/privacy/')) {
                $users[] = $file;
            }
        }
        $this->assertSame([], $users);
    }

    /**
     * The audit events of the erasures are created by one class only, application/privacy/erasure_audit (T142; DEC-58): nothing else triggers
     * an event of the plugin, and no other class (but the reader of the log, which only names them) refers to them.
     */
    public function test_only_the_erasure_audit_triggers_the_audit_events(): void {
        $violations = [];
        foreach (self::class_files() as $file) {
            $code = self::code_without_comments($file);
            $writer = $file === 'classes/application/privacy/erasure_audit.php';
            if (!$writer && preg_match('/->\s*trigger\s*\(/', $code)) {
                $violations[] = "{$file}: triggers an event";
            }
            $allowed = $writer || str_starts_with($file, 'classes/event/') || $file === 'classes/infrastructure/moodle/erasure_audit_reader.php';
            if (!$allowed && preg_match('/\bstudent_erasure_(deferred|completed|stopped)\b/', $code)) {
                $violations[] = "{$file}: refers to an audit event";
            }
        }
        $this->assertSame([], $violations);
    }

    /**
     * Q2 of spec-012: the plugin applies no weighting of its own to the grades; the total of the course is the one Moodle computes.
     */
    public function test_no_own_weighting_of_grades(): void {
        $violations = [];
        foreach (self::class_files() as $file) {
            $scoped = str_starts_with($file, 'classes/domain/grade/') || str_starts_with($file, 'classes/application/grade/')
                || str_starts_with($file, 'classes/domain/criteria/') || str_starts_with($file, 'classes/application/criteria/')
                || $file === 'classes/infrastructure/persistence/performance_repository.php';
            if ($scoped && ($found = self::find_weighting(self::code_without_comments($file))) !== []) {
                $violations[] = $file . ': ' . implode(', ', $found);
            }
        }
        $this->assertSame([], $violations);

        // The rule can fail: a source that weights is found, and a comment that mentions weights is not code.
        $this->assertSame(['$weight', '$weight'], self::find_weighting('<?php $weight = 0.3; $total += $grade * $weight;'));
        $this->assertSame(['ponderar'], self::find_weighting('<?php function ponderar() {}'));
        $this->assertSame([], self::find_weighting('<?php $total = $total + $grade; // the weights are those of Moodle'));
    }

    /**
     * Q4 of spec-015: no threshold constant in the code; the only limits are the values that come from `criteria`.
     */
    public function test_no_threshold_constant_outside_criteria(): void {
        $violations = [];
        $criteriapath = [
            'classes/domain/criteria/criteria_evaluator.php',
            'classes/domain/criteria/criteria_evaluation.php',
            'classes/application/criteria/criteria_handler.php',
            'classes/application/criteria/criteria_request_service.php',
            'classes/infrastructure/persistence/criteria_result_repository.php',
        ];
        foreach (self::class_files() as $file) {
            $found = self::find_thresholds(self::code_without_comments($file), in_array($file, $criteriapath, true));
            if ($found !== []) {
                $violations[] = $file . ': ' . implode(', ', $found);
            }
        }
        $this->assertSame([], $violations);

        // The rule can fail: a threshold constant and a comparison with a fixed limit are found; the status codes and the scale are not.
        $this->assertSame(['constant PASSING_GRADE'], self::find_thresholds('<?php class A { const PASSING_GRADE = 60; }', false));
        $this->assertSame(['comparison >= 60'], self::find_thresholds('<?php $ok = $value >= 60;', true));
        $this->assertSame(['comparison < 50'], self::find_thresholds('<?php $ok = 50 < $value;', true));
        $this->assertSame([], self::find_thresholds('<?php $ok = $value >= $limit && $value <= 100 && $n > 0 && $s < 2;', true));
    }
    /**
     * Quickstart 022 Q2 and quickstart 006 Q4 (T131): the observers only delegate to the services of the queue; they do not call the LLM
     * port, do not touch the database and do not hold a business rule of the analyses.
     */
    public function test_observers_only_delegate(): void {
        $offenders = [];
        foreach (self::class_files() as $relative) {
            if (!str_starts_with($relative, 'classes/observer/')) {
                continue;
            }
            $code = self::code_without_comments($relative);
            if (preg_match('/\$DB\b|\\\\infrastructure\\\\llm\\\\|llm_port|adapter_factory|\bevaluate\s*\(|\\\\infrastructure\\\\persistence\\\\/i', $code, $match)) {
                $offenders[] = $relative . ' (' . $match[0] . ')';
            }
        }
        $this->assertSame([], $offenders);
    }

    /**
     * Quickstart 020 Q4 (T119): forms are moodleform; no manual HTML form and no direct read of the POST in the plugin code.
     */
    public function test_no_manual_form_or_post_where_there_is_a_moodleform(): void {
        $files = array_merge(self::class_files(), array_map(
            static fn(string $path) => basename($path),
            glob(self::plugin_root() . '/*.php') ?: []
        ));
        $offenders = [];
        foreach ($files as $relative) {
            if ($relative === 'version.php') {
                continue;
            }
            if (preg_match('/\$_(POST|REQUEST)\b|<form\b/i', self::code_without_comments($relative), $match)) {
                $offenders[] = $relative . ' (' . $match[0] . ')';
            }
        }
        foreach (glob(self::plugin_root() . '/templates/*.mustache') ?: [] as $template) {
            if (preg_match('/<form\b/i', file_get_contents($template))) {
                $offenders[] = 'templates/' . basename($template) . ' (<form)';
            }
        }
        $this->assertSame([], $offenders);
    }

    /**
     * Whether the tokens declare a class, interface, trait or enum with the given name.
     *
     * @param array $tokens
     * @param string $name
     * @return bool
     */
    private static function declares_name(array $tokens, string $name): bool {
        $kinds = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], $kinds, true)) {
                continue;
            }
            for ($next = $index + 1; $next < count($tokens); $next++) {
                if (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($tokens[$next]) && $tokens[$next][0] === T_STRING && $tokens[$next][1] === $name) {
                    return true;
                }
                break;
            }
        }
        return false;
    }
}
