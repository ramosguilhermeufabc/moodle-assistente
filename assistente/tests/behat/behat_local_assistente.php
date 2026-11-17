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
 * Behat steps of local_assistente (T128).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test, this file is required by Behat.

// The plugin is a link inside the Moodle tree, so the relative path does not resolve from the real directory of this file: the
// Moodle root is also tried from the directory where Behat runs.
if (!class_exists('behat_base')) {
    foreach ([__DIR__ . '/../../../../lib/behat/behat_base.php', getcwd() . '/lib/behat/behat_base.php', getcwd() . '/public/lib/behat/behat_base.php'] as $candidate) {
        if (is_readable($candidate)) {
            require_once($candidate);
            break;
        }
    }
}

/**
 * Steps to open the pages of the assistant directly, as a request by URL (no link of the interface).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_assistente extends behat_base {
    /**
     * Open a tab of the assistant of a course by its URL.
     *
     * @Given /^I open the "(?P<tab_string>[^"]*)" tab of the assistant in course "(?P<course_string>[^"]*)"$/
     * @param string $tab key of the tab (grades, frequency, report, history...)
     * @param string $shortname short name of the course
     */
    public function i_open_the_tab_of_the_assistant_in_course(string $tab, string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $this->getSession()->visit($this->locate_path('/local/assistente/index.php?id=' . $courseid . '&tab=' . urlencode($tab)));
    }

    /**
     * Open the configuration page of the assistant of a course by its URL.
     *
     * @Given /^I open the configuration of the assistant in course "(?P<course_string>[^"]*)"$/
     * @param string $shortname short name of the course
     */
    public function i_open_the_configuration_of_the_assistant_in_course(string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $this->getSession()->visit($this->locate_path('/local/assistente/configure.php?id=' . $courseid));
    }

    /**
     * Open a tab of the assistant of a course by its URL and check that the access is denied. The Moodle test framework fails any step
     * that ends on an error page, so after the check the step goes back to the course page.
     *
     * @Then /^the "(?P<tab_string>[^"]*)" tab of the assistant in course "(?P<course_string>[^"]*)" is denied$/
     * @param string $tab key of the tab
     * @param string $shortname short name of the course
     */
    public function the_tab_of_the_assistant_is_denied(string $tab, string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $this->assert_denied('/local/assistente/index.php?id=' . $courseid . '&tab=' . urlencode($tab), (int) $courseid);
    }

    /**
     * Open the configuration page of the assistant by its URL and check that the access is denied (see the step of the tabs).
     *
     * @Then /^the configuration of the assistant in course "(?P<course_string>[^"]*)" is denied$/
     * @param string $shortname short name of the course
     */
    public function the_configuration_of_the_assistant_is_denied(string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $this->assert_denied('/local/assistente/configure.php?id=' . $courseid, (int) $courseid);
    }

    /**
     * Visit a path, check the refusal for lack of permission and leave the error page.
     *
     * @param string $path
     * @param int $courseid
     * @throws \Behat\Mink\Exception\ExpectationException when the page is not the refusal
     */
    private function assert_denied(string $path, int $courseid): void {
        $this->getSession()->visit($this->locate_path($path));
        $text = $this->getSession()->getPage()->getText();
        $this->getSession()->visit($this->locate_path('/course/view.php?id=' . $courseid));
        if (!str_contains($text, 'you do not currently have permissions to do that')) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The page ' . $path . ' was not refused for lack of permission.',
                $this->getSession()->getDriver()
            );
        }
    }
}
