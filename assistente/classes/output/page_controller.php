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

namespace local_assistente\output;

use local_assistente\application\access_service;
use local_assistente\application\course_config_service;
use local_assistente\application\query\below_criteria_query;
use local_assistente\application\query\forums_query;
use local_assistente\application\query\frequency_query;
use local_assistente\application\query\grades_query;
use local_assistente\application\query\history_kind;
use local_assistente\application\query\history_query;
use local_assistente\application\query\material_summaries_query;
use local_assistente\application\query\quiz_metrics_query;
use local_assistente\application\query\student_scope;
use local_assistente\application\query\synthesis_query;
use local_assistente\application\report\report_view_model;
use local_assistente\domain\report\version_filter;
use local_assistente\infrastructure\moodle\course_module_reader;
use local_assistente\infrastructure\moodle\course_section_reader;

/**
 * What the entry page of the assistant shows: the tabs a user may open and the renderable of each (T124; spec-021).
 *
 * The page (index.php) is thin: it logs in, asks this class and prints. Here the authorization is the first step: no tab is built for a
 * user without `local/assistente:view`, and the "Configuração" tab exists only with `local/assistente:manage` (DEC-45 D1). The query
 * services check the capability again and answer "assistente não habilitado" for a course that is not enabled (DEC-45 D3), in every tab.
 * There is no chat and no free question: each tab is a fixed query of the spec-018.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_controller {
    /** @var string[] tabs of the individual queries, which accept the group filter */
    public const INDIVIDUAL_TABS = ['grades', 'frequency', 'below_criteria'];

    /** @var string[] tabs of the aggregate queries */
    public const AGGREGATE_TABS = ['quiz_metrics', 'forums', 'materials', 'synthesis'];

    /** @var string tab of the report */
    public const TAB_REPORT = 'report';

    /** @var string tab of the history of the report */
    public const TAB_HISTORY = 'history';

    /** @var string tab of the configuration */
    public const TAB_CONFIG = 'config';

    /**
     * Constructor.
     *
     * @param access_service|null $access
     * @param student_scope|null $scope
     * @param course_section_reader|null $sections
     * @param course_module_reader|null $modules
     * @param report_view_model|null $report
     */
    public function __construct(
        private ?access_service $access = null,
        private ?student_scope $scope = null,
        private ?course_section_reader $sections = null,
        private ?course_module_reader $modules = null,
        private ?report_view_model $report = null
    ) {
        $this->access ??= new access_service();
        $this->scope ??= new student_scope();
        $this->sections ??= new course_section_reader();
        $this->modules ??= new course_module_reader();
        $this->report ??= new report_view_model();
    }

    /**
     * Tabs the user may open, in order.
     *
     * @param int $courseid
     * @param int|null $userid null for the current user
     * @return string[] tab keys
     * @throws \core\exception\required_capability_exception without `local/assistente:view`
     */
    public function tabs(int $courseid, ?int $userid = null): array {
        $this->access->require_view($courseid, $userid);
        $tabs = [...self::INDIVIDUAL_TABS, ...self::AGGREGATE_TABS, self::TAB_REPORT, self::TAB_HISTORY];
        if ($this->access->can_manage($courseid, $userid)) {
            $tabs[] = self::TAB_CONFIG;
        }
        return $tabs;
    }

    /**
     * Whether a tab accepts the group filter.
     *
     * @param string $tab
     * @return bool
     */
    public static function is_individual(string $tab): bool {
        return in_array($tab, self::INDIVIDUAL_TABS, true);
    }

    /**
     * Whether a tab accepts the filters by version and by execution date: the report and its history (Q-020-02, DEC-44).
     *
     * @param string $tab
     * @return bool
     */
    public static function has_version_filter(string $tab): bool {
        return in_array($tab, [self::TAB_REPORT, self::TAB_HISTORY], true);
    }

    /**
     * Whether a tab accepts the group filter: the individual queries and the individual blocks of the report (DEC-43 D1). The aggregate
     * queries and the history have none.
     *
     * @param string $tab
     * @return bool
     */
    public static function has_group_filter(string $tab): bool {
        return self::is_individual($tab) || $tab === self::TAB_REPORT;
    }

    /**
     * Custom data of the filter form of a tab, limited to what the user may see.
     *
     * @param int $courseid
     * @param string $tab
     * @param int|null $userid
     * @return array
     */
    public function filter_data(int $courseid, string $tab, ?int $userid = null): array {
        $this->access->require_view($courseid, $userid);
        $userid ??= $GLOBALS['USER']->id;
        $groups = self::has_group_filter($tab) ? $this->scope->groups($courseid, (int) $userid) : [];
        $versions = [];
        if (self::has_version_filter($tab)) {
            foreach ($this->report->versions($courseid, (int) $userid) as $number => $executedat) {
                $versions[$number] = $number . ' (' . userdate($executedat, get_string('strftimedatetimeshort')) . ')';
            }
        }
        $sections = [];
        foreach ($this->sections->get_sections($courseid) as $section) {
            if ((int) $section->number > 0 && !$section->delegated) {
                $sections[(int) $section->id] = (string) ($section->name ?: get_string('section') . ' ' . $section->number);
            }
        }
        return [
            'courseid' => $courseid, 'tab' => $tab, 'individual' => self::has_group_filter($tab),
            'groups' => $groups, 'sections' => $this->has_section_filter($tab) ? $sections : [], 'versions' => $versions,
            'dates' => self::has_version_filter($tab),
        ];
    }

    /**
     * The renderable of a tab.
     *
     * @param int $courseid
     * @param string $tab one of tabs()
     * @param int|null $userid null for the current user
     * @param int|null $groupid group filter, only for the individual tabs
     * @param int|null $sectionid section filter, for the tabs whose rows belong to a section
     * @param version_filter|null $filter version and execution dates, for the report and its history only (Q-020-02)
     * @return \renderable
     * @throws \core\exception\required_capability_exception without `local/assistente:view`
     * @throws \InvalidArgumentException for a tab that does not exist or is not a query (the configuration)
     */
    public function renderable(
        int $courseid,
        string $tab,
        ?int $userid = null,
        ?int $groupid = null,
        ?int $sectionid = null,
        ?version_filter $filter = null
    ): \renderable {
        $userid ??= (int) $GLOBALS['USER']->id;
        $this->access->require_view($courseid, $userid);
        $title = get_string('tab:' . $tab, 'local_assistente');
        $group = self::is_individual($tab) ? $groupid : null;
        $filter = self::has_version_filter($tab) ? $filter : null;
        $filtered = $filter !== null && !$filter->is_empty();

        $result = match ($tab) {
            'grades' => (new grades_query())->query($courseid, $userid, $group),
            'frequency' => (new frequency_query())->query($courseid, $userid, $group),
            'below_criteria' => (new below_criteria_query())->query($courseid, $userid, $group),
            'quiz_metrics' => (new quiz_metrics_query())->query($courseid, $userid),
            'forums' => (new forums_query())->query($courseid, $userid),
            'materials' => (new material_summaries_query())->query($courseid, $userid),
            'synthesis' => (new synthesis_query())->query($courseid, $userid),
            self::TAB_REPORT => $this->report->current($courseid, $userid, $groupid, $filter),
            self::TAB_HISTORY => (new history_query())->query($courseid, $userid, history_kind::REPORT, 0, null, $filter),
            default => throw new \InvalidArgumentException("Unknown tab '{$tab}'."),
        };

        if ($sectionid !== null && $this->has_section_filter($tab) && !empty($result['available'])) {
            $result['rows'] = $this->in_section($courseid, $result['rows'], $sectionid);
        }
        return match ($tab) {
            self::TAB_REPORT => new report_page(
                $result,
                new \moodle_url('/local/assistente/index.php', ['id' => $courseid, 'tab' => self::TAB_HISTORY]),
                $filtered
            ),
            self::TAB_HISTORY => new history_page($title, $result, $filtered),
            default => new query_page($title, $result),
        };
    }

    /**
     * Whether the rows of a tab belong to a section and can be filtered by it.
     *
     * @param string $tab
     * @return bool
     */
    private function has_section_filter(string $tab): bool {
        return in_array($tab, ['quiz_metrics', 'forums', 'materials', 'synthesis'], true);
    }

    /**
     * Rows that belong to a section: by their `section_id`, or by the section of their module.
     *
     * @param int $courseid
     * @param array $rows
     * @param int $sectionid
     * @return array
     */
    private function in_section(int $courseid, array $rows, int $sectionid): array {
        $modules = $this->modules->get_modules($courseid);
        return array_values(array_filter($rows, static function (array $row) use ($modules, $sectionid): bool {
            if (isset($row['section_id'])) {
                return (int) $row['section_id'] === $sectionid;
            }
            return isset($row['cmid'], $modules[$row['cmid']]) && (int) $modules[$row['cmid']]->section_id === $sectionid;
        }));
    }
}
