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

use local_assistente\domain\material\material_availability;
use local_assistente\domain\material\material_state;
use local_assistente\domain\material\summary_status;
use local_assistente\domain\material_type;

/**
 * Repository of the material aggregate: `material` and its `material_version` rows (plan 003 "Agregados"; spec-008).
 *
 * Rules kept here:
 * - A version is persisted first; the material only points to it (`current_version`) afterwards, in a separate step. A new
 *   version never becomes current by being inserted, and it is always inserted `pending`: a new version does not imply a
 *   completed summary (DEC-50).
 * - `completed` is set only by complete_summary(), that is, together with the persistence of the summary text, in one
 *   transaction. A failure (fail_summary) changes only the status: it never erases a valid summary, and a failed version never
 *   becomes current, because only a completed version can (DEC-50; eligibility, DEC-05, DEC-15). The previous version stays
 *   current.
 * - Allowed transitions of the status: pending to completed or failed, failed back to pending (new attempt); completed is final.
 * - Switching the current version runs under the Lock API, one lock per material (unit_of_work::material_resource), and in a
 *   transaction. If the switch fails, the previous version stays current.
 * - The pointer never goes back (DEC-62): switch_current() refuses, with version_not_newer_exception, a version whose number is not
 *   greater than the current one. A refused version stays in the history, completed.
 * - The history is immutable: no method changes or deletes the identity of a version (its material, number, content hash,
 *   availability and date). Only the summary and its status are written afterwards, because the summary is produced
 *   asynchronously.
 * - `availability` (DEC-51) is independent of `summary_status` and of the pending extraction of the content (T062). Its values
 *   are validated by schema_domains.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class material_repository extends repository {
    /** @var int physical length of material_version.content_hash */
    private const CONTENT_HASH_LENGTH = 64;

    /** @var table_repository the versions table */
    private table_repository $versions;

    /**
     * Constructor.
     *
     * @param unit_of_work $uow
     */
    public function __construct(
        private readonly unit_of_work $uow = new unit_of_work()
    ) {
        $this->versions = new table_repository('local_assistente_material_version');
    }

    #[\Override]
    protected function table(): string {
        return 'local_assistente_material';
    }

    /**
     * Registered material of a course module.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass|null
     */
    public function find_by_cmid(int $courseid, int $cmid): ?\stdClass {
        return $this->find_by(['course_id' => $courseid, 'cmid' => $cmid]);
    }

    /**
     * A version of a material by its number.
     *
     * @param int $materialid
     * @param int $version
     * @return \stdClass|null
     */
    public function find_version(int $materialid, int $version): ?\stdClass {
        return $this->versions->find_by(['material_id' => $materialid, 'version' => $version]);
    }

    /**
     * A version by its id.
     *
     * @param int $versionid
     * @return \stdClass|null
     */
    public function find_version_by_id(int $versionid): ?\stdClass {
        return $this->versions->find($versionid);
    }

    /**
     * The current version of a material.
     *
     * @param \stdClass $material
     * @return \stdClass|null null when no version is current yet
     */
    public function find_current_version(\stdClass $material): ?\stdClass {
        return $material->current_version === null ? null : $this->versions->find((int) $material->current_version);
    }

    /**
     * The whole history of a material, oldest first.
     *
     * @param int $materialid
     * @return \stdClass[] indexed by version id
     */
    public function find_versions(int $materialid): array {
        return $this->versions->find_all_by(['material_id' => $materialid], 'version ASC');
    }

    /**
     * What the plugin recorded for the current version of a module, to be compared with what Moodle holds now.
     *
     * @param int $courseid
     * @param int $cmid
     * @return material_state|null null when the material is unknown or has no current version yet
     */
    public function find_state(int $courseid, int $cmid): ?material_state {
        $material = $this->find_by_cmid($courseid, $cmid);
        $version = $material === null ? null : $this->find_current_version($material);
        if ($version === null) {
            return null;
        }
        return new material_state(
            material_type::from($material->material_type),
            $version->content_hash,
            (int) $material->section_id,
            $material->name
        );
    }

    /**
     * Register a material, with no version yet.
     *
     * @param int $courseid
     * @param int $sectionid
     * @param int $cmid
     * @param material_type $type
     * @param string $name
     * @param material_availability $availability
     * @param int|null $now
     * @return int the id of the material
     * @throws duplicate_record_exception when the module is already registered
     */
    public function register(
        int $courseid,
        int $sectionid,
        int $cmid,
        material_type $type,
        string $name,
        material_availability $availability,
        ?int $now = null
    ): int {
        return $this->insert((object) [
            'course_id' => $courseid,
            'section_id' => $sectionid,
            'cmid' => $cmid,
            'material_type' => $type->value,
            'name' => $name,
            'availability' => $availability->value,
            'current_version' => null,
            'created_at' => $now ?? time(),
        ]);
    }

    /**
     * Bring the section and the name of a registered material up to date. Creates no version.
     *
     * Under the lock of the material, reading the row again inside the lock and the transaction (CL-49): the row is written whole, so
     * an unlocked write could restore a `current_version` or an `availability` that changed meanwhile. Only `section_id` and `name`
     * are changed; every other field is written as just read.
     *
     * @param int $materialid
     * @param int $sectionid
     * @param string $name
     * @throws \dml_missing_record_exception when the material does not exist
     * @throws lock_unavailable_exception when the lock of the material cannot be acquired within the timeout
     */
    public function update_details(int $materialid, int $sectionid, string $name): void {
        $this->uow->run(function () use ($materialid, $sectionid, $name) {
            $material = $this->require_material($materialid);
            $material->section_id = $sectionid;
            $material->name = $name;
            $this->update($material);
        }, [unit_of_work::material_resource($materialid)]);
    }

    /**
     * Record the latest known availability of the material itself (independent of the summary status). Never touches a version.
     *
     * Under the lock of the material, reading the row again inside it: the row is written whole, so an unlocked write could
     * overwrite a `current_version` that switch_current() set meanwhile. Nothing is written when the value is the same.
     *
     * @param int $materialid
     * @param material_availability $availability
     * @throws \dml_missing_record_exception when the material does not exist
     */
    public function update_availability(int $materialid, material_availability $availability): void {
        $this->uow->run(function () use ($materialid, $availability) {
            $material = $this->require_material($materialid);
            if ($material->availability === $availability->value) {
                return;
            }
            $material->availability = $availability->value;
            $this->update($material);
        }, [unit_of_work::material_resource($materialid)]);
    }

    /**
     * Persist the next version of a material, `pending`. It does not become current: see switch_current().
     *
     * @param int $materialid
     * @param string $contenthash identifier of the processable content (CL-45)
     * @param material_availability $availability
     * @param int|null $now
     * @return int the id of the version
     * @throws \InvalidArgumentException for an empty or too long hash
     * @throws \dml_missing_record_exception when the material does not exist
     */
    public function add_version(
        int $materialid,
        string $contenthash,
        material_availability $availability,
        ?int $now = null
    ): int {
        if (trim($contenthash) === '' || strlen($contenthash) > self::CONTENT_HASH_LENGTH) {
            throw new \InvalidArgumentException('The content hash must have from 1 to ' . self::CONTENT_HASH_LENGTH . ' characters.');
        }

        return $this->uow->run(function () use ($materialid, $contenthash, $availability, $now) {
            global $DB;
            $this->require_material($materialid);
            $last = (int) $DB->get_field_sql(
                'SELECT MAX(version) FROM {local_assistente_material_version} WHERE material_id = ?',
                [$materialid]
            );
            return $this->versions->insert((object) [
                'material_id' => $materialid,
                'version' => $last + 1,
                'content_hash' => $contenthash,
                'summary' => null,
                'summary_status' => summary_status::PENDING->value,
                'availability' => $availability->value,
                'created_at' => $now ?? time(),
            ]);
        }, [unit_of_work::material_resource($materialid)]);
    }

    /**
     * Persist the summary of a version and mark it `completed`, in one transaction.
     *
     * `completed` exists only together with the persisted summary text: if the write fails, the status is not changed.
     *
     * @param int $versionid
     * @param string $summary the text of the summary; must not be empty
     * @throws \InvalidArgumentException when the summary is empty
     * @throws \dml_missing_record_exception when the version does not exist
     * @throws \coding_exception when the version is not pending
     */
    public function complete_summary(int $versionid, string $summary): void {
        if (trim($summary) === '') {
            throw new \InvalidArgumentException('A completed summary must not be empty.');
        }
        $this->change_status($versionid, summary_status::COMPLETED, $summary);
    }

    /**
     * Mark a version `failed`. Whatever summary it already holds is kept.
     *
     * @param int $versionid
     * @throws \dml_missing_record_exception when the version does not exist
     * @throws \coding_exception when the version is not pending
     */
    public function fail_summary(int $versionid): void {
        $this->change_status($versionid, summary_status::FAILED);
    }

    /**
     * Take a failed version back to `pending` for a new attempt.
     *
     * @param int $versionid
     * @throws \dml_missing_record_exception when the version does not exist
     * @throws \coding_exception when the version is not failed
     */
    public function retry_summary(int $versionid): void {
        $this->change_status($versionid, summary_status::PENDING);
    }

    /**
     * Make a persisted version the current one of its material.
     *
     * Under the lock of the material and in a transaction. The version must already exist, belong to the material and be
     * completed, and its number must be greater than the number of the current version (DEC-62); with no current version yet, the
     * first completed version is accepted. When anything fails the pointer is not changed, so the previous version stays current.
     *
     * @param int $materialid
     * @param int $versionid
     * @throws \InvalidArgumentException when the version does not belong to the material
     * @throws \coding_exception when the version is not completed
     * @throws version_not_newer_exception when the number is equal to or lower than the current one (the current version itself
     *         included); nothing is written, and it is a business outcome, not a technical failure
     * @throws \dml_missing_record_exception when the material or the version does not exist
     */
    public function switch_current(int $materialid, int $versionid): void {
        $this->uow->run(function () use ($materialid, $versionid) {
            $material = $this->require_material($materialid);
            $version = $this->require_version($versionid);
            if ((int) $version->material_id !== $materialid) {
                throw new \InvalidArgumentException("Version {$versionid} does not belong to material {$materialid}.");
            }
            if ($version->summary_status !== summary_status::COMPLETED->value) {
                throw new \coding_exception("Only a completed version can become current (version {$versionid} is {$version->summary_status}).");
            }
            // DEC-62: the pointer never goes back. Read inside the lock and the transaction (the material above is the first read of
            // the transaction), so it sees what the previous switch committed. Compared by the number of the version, never by id.
            if ($material->current_version !== null) {
                $current = $this->require_version((int) $material->current_version);
                if ((int) $version->version <= (int) $current->version) {
                    throw new version_not_newer_exception(
                        $materialid,
                        $versionid,
                        (int) $version->version,
                        (int) $current->id,
                        (int) $current->version
                    );
                }
            }
            $material->current_version = $versionid;
            $this->update($material);
        }, [unit_of_work::material_resource($materialid)]);
    }

    /**
     * The completed versions that should be current and are not (CL-51): for each material of a course with the assistant enabled, the
     * completed version of the greatest number, when the material has no current version or that number is greater than the current one.
     *
     * Only a candidate, read without a lock: nothing is written and nothing is promoted here. switch_current() decides, under the lock of
     * the material and in a transaction, and refuses a version that was overtaken meanwhile (DEC-62). A pending or failed version is never a
     * candidate, whatever its number, and does not hide the completed one below it.
     *
     * @return int[] id of the version to promote, by id of the material, in the order of the materials
     */
    public function find_unpromoted_completed_versions(): array {
        global $DB;
        $sql = "SELECT m.id AS materialid, v.id AS versionid
                  FROM {local_assistente_material} m
                  JOIN {local_assistente_config} c ON c.course_id = m.course_id AND c.enabled = 1
                  JOIN {local_assistente_material_version} v
                    ON v.material_id = m.id
                   AND v.summary_status = :completed
                   AND v.version = (SELECT MAX(l.version)
                                      FROM {local_assistente_material_version} l
                                     WHERE l.material_id = m.id AND l.summary_status = :latest)
             LEFT JOIN {local_assistente_material_version} cur ON cur.id = m.current_version
                 WHERE m.current_version IS NULL OR v.version > cur.version
              ORDER BY m.id ASC";
        $found = $DB->get_records_sql_menu($sql, [
            'completed' => summary_status::COMPLETED->value,
            'latest' => summary_status::COMPLETED->value,
        ]);
        return array_map('intval', $found);
    }

    /**
     * The pending versions that no completed version of a greater number supersedes, in courses with the assistant enabled (CL-51): the
     * candidates to be marked failed when nobody is working on them.
     *
     * Only a candidate, read without a lock: nothing is written. Whether somebody is working on the version is not known here (it is the
     * caller's to find out, from the events of the module), and a superseded pending version is not a candidate (CL-50).
     *
     * @return \stdClass[] by id of the version, each with `materialid`, `courseid` and `cmid`, in the order of the versions
     */
    public function find_pending_without_later_completed(): array {
        global $DB;
        $sql = "SELECT v.id AS versionid, m.id AS materialid, m.course_id AS courseid, m.cmid AS cmid
                  FROM {local_assistente_material_version} v
                  JOIN {local_assistente_material} m ON m.id = v.material_id
                  JOIN {local_assistente_config} c ON c.course_id = m.course_id AND c.enabled = 1
                 WHERE v.summary_status = :pending
                   AND NOT EXISTS (SELECT 1
                                     FROM {local_assistente_material_version} l
                                    WHERE l.material_id = v.material_id AND l.summary_status = :completed AND l.version > v.version)
              ORDER BY v.id ASC";
        return $DB->get_records_sql($sql, [
            'pending' => summary_status::PENDING->value,
            'completed' => summary_status::COMPLETED->value,
        ]);
    }

    /**
     * Mark a pending version failed (CL-51), under the lock of the material and in a short transaction.
     *
     * That nobody is working on the version is established by the caller, who knows the events: the lock of the material is not held while a
     * worker waits for the LLM, so this method cannot tell. The caller says it twice: before the call, to leave the version alone without
     * taking a lock, and with `$abandoned`, which is asked inside the lock and the transaction, on the state read again, so that an event that
     * arrived since the first look is seen. What is decided here is also that the version is still pending and that no completed version of a
     * greater number exists (a superseded version is never changed: CL-50). The summary and `current_version` are not touched, and a version
     * that is failed or completed is left as it is.
     *
     * @param int $versionid
     * @param \Closure|null $abandoned asked inside the lock and the transaction; the version is left as it is when it answers false
     * @return bool true when the version was marked failed, false when it was left as it is
     * @throws \dml_missing_record_exception when the version does not exist
     */
    public function fail_pending_version(int $versionid, ?\Closure $abandoned = null): bool {
        $first = $this->require_version($versionid);
        return $this->uow->run(function () use ($versionid, $abandoned): bool {
            global $DB;
            $version = $this->require_version($versionid);
            if ($version->summary_status !== summary_status::PENDING->value) {
                return false;
            }
            if ($DB->record_exists_select(
                'local_assistente_material_version',
                'material_id = :material AND summary_status = :completed AND version > :number',
                ['material' => $version->material_id, 'completed' => summary_status::COMPLETED->value, 'number' => $version->version]
            )) {
                return false;
            }
            if ($abandoned !== null && !$abandoned()) {
                return false;
            }
            $this->change_status($versionid, summary_status::FAILED);
            return true;
        }, [unit_of_work::material_resource((int) $first->material_id)]);
    }

    /**
     * Change the status of a version, with the summary when it is completed, under the lock of the material.
     *
     * @param int $versionid
     * @param summary_status $to
     * @param string|null $summary written only when given
     */
    private function change_status(int $versionid, summary_status $to, ?string $summary = null): void {
        $version = $this->require_version($versionid);
        $this->uow->run(function () use ($versionid, $to, $summary) {
            // Read again under the lock: the transition is decided on the current state, not on the one read before it.
            $version = $this->require_version($versionid);
            $from = summary_status::from($version->summary_status);
            if (!$from->can_become($to)) {
                throw new \coding_exception("A summary cannot go from {$from->value} to {$to->value}.");
            }
            $version->summary_status = $to->value;
            if ($summary !== null) {
                $version->summary = $summary;
            }
            $this->versions->update($version);
        }, [unit_of_work::material_resource((int) $version->material_id)]);
    }

    /**
     * A material that must exist.
     *
     * @param int $materialid
     * @return \stdClass
     * @throws \dml_missing_record_exception
     */
    private function require_material(int $materialid): \stdClass {
        return $this->find($materialid) ?? throw new \dml_missing_record_exception($this->table(), 'id = ?', [$materialid]);
    }

    /**
     * A version that must exist.
     *
     * @param int $versionid
     * @return \stdClass
     * @throws \dml_missing_record_exception
     */
    private function require_version(int $versionid): \stdClass {
        return $this->versions->find($versionid)
            ?? throw new \dml_missing_record_exception('local_assistente_material_version', 'id = ?', [$versionid]);
    }
}
