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
 * Entry page of the assistant in the context of a course: one tab for each query of the spec-018, the report and its history, and the
 * configuration for who can manage (T124; spec-021).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// In the development environment the plugin directory is a link into the Moodle tree (DEC-27), so __DIR__ is the real directory and
// the relative path does not reach config.php: the path of the request is tried first.
$moodleconfig = dirname($_SERVER['SCRIPT_FILENAME'] ?? '', 3) . '/config.php';
require_once(is_readable($moodleconfig) ? $moodleconfig : __DIR__ . '/../../config.php');

use local_assistente\domain\report\version_filter;
use local_assistente\form\query_filter_form;
use local_assistente\output\page_controller;

$courseid = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'grades', PARAM_ALPHANUMEXT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

$controller = new page_controller();
$tabs = $controller->tabs($courseid); // Requires local/assistente:view before anything else.
if (!in_array($tab, $tabs, true)) {
    $tab = $tabs[0]; // Includes a request for the configuration by someone who may only view.
}
if ($tab === page_controller::TAB_CONFIG) {
    redirect(new moodle_url('/local/assistente/configure.php', ['id' => $courseid]));
}

$url = new moodle_url('/local/assistente/index.php', ['id' => $courseid, 'tab' => $tab]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_assistente'));
$PAGE->set_heading($course->fullname);

$tabobjects = [];
foreach ($tabs as $key) {
    $tabobjects[] = new tabobject(
        $key,
        new moodle_url('/local/assistente/index.php', ['id' => $courseid, 'tab' => $key]),
        get_string('tab:' . $key, 'local_assistente')
    );
}

$custom = $controller->filter_data($courseid, $tab);
$filters = new query_filter_form($url, $custom, 'get');
$submitted = $filters->get_data();
$selected = query_filter_form::selected($submitted);
$period = query_filter_form::selected_period($submitted);
// The days of the period are those of the site time zone (DEC-44 D2); a first day after the last one was refused by the form.
$filter = page_controller::has_version_filter($tab)
    ? version_filter::of($selected['version'], $period['from'], $period['to'], core_date::get_server_timezone())
    : version_filter::none();

$output = $PAGE->get_renderer('local_assistente');
echo $OUTPUT->header();
echo $OUTPUT->tabtree($tabobjects, $tab);
if ($custom['groups'] || $custom['sections'] || $custom['versions'] || $custom['dates']) {
    $filters->display();
}
// A submitted filter that the form refused (for instance a first day after the last one) shows its error and no result: the default view
// next to the error would answer a question that was not asked.
if (!($filters->is_submitted() && $submitted === null)) {
    echo $output->render($controller->renderable($courseid, $tab, null, $selected['groupid'], $selected['sectionid'], $filter));
}
echo $OUTPUT->footer();
