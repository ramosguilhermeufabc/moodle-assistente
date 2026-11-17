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
 * Configuration of the assistant in a course: enabling and the criteria, for who has `local/assistente:manage` (T126; spec-020, spec-021).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// In the development environment the plugin directory is a link into the Moodle tree (DEC-27), so __DIR__ is the real directory and
// the relative path does not reach config.php: the path of the request is tried first.
$moodleconfig = dirname($_SERVER['SCRIPT_FILENAME'] ?? '', 3) . '/config.php';
require_once(is_readable($moodleconfig) ? $moodleconfig : __DIR__ . '/../../config.php');

use local_assistente\form\course_config_form;
use local_assistente\output\configure_page;

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

$page = new configure_page();
$custom = $page->custom_data($courseid); // Requires local/assistente:manage before anything else.

$url = new moodle_url('/local/assistente/configure.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('tab:config', 'local_assistente'));
$PAGE->set_heading($course->fullname);

$form = new course_config_form($url, $custom);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/assistente/index.php', ['id' => $courseid]));
}
if ($data = $form->get_data()) {
    $problem = $page->save_or_explain($data);
    if ($problem !== null) {
        redirect($url, $problem, null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tab:config', 'local_assistente'));
echo html_writer::link(
    new moodle_url('/local/assistente/index.php', ['id' => $courseid]),
    get_string('pluginname', 'local_assistente')
);
$form->display();
echo $OUTPUT->footer();
