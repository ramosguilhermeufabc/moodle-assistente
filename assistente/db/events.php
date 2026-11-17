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
 * Observers of Moodle events (plan 022, "Catálogo de gatilhos").
 *
 * The events are candidates verified by the technical validations (CL-02 and the others of the catalog): the names below are
 * those of the plan, not a finding. Each observer only turns the Moodle event into an assistant event through the queue
 * service; it runs no business rule and no LLM. Observers of the other triggers are added by the task of each delivery.
 *
 * 'internal' => false: the observer runs after the commit of the transaction in which Moodle saves the module. The queue takes
 * locks, and a lock must be acquired before a transaction is opened (unit_of_work); with the default (internal) the observer would
 * run inside the transaction of the teacher's action and always fail.
 *
 * No observer for enrolment or role changes: DEC-47.
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Quiz published: created visible, or made visible later (DEC-31).
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_assistente\observer\course_module_observer::course_module_created',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_assistente\observer\course_module_observer::course_module_updated',
        'internal' => false,
    ],
    // Results of a questionnaire: attempt submitted, question graded manually, attempt regraded (T073; DEC-36 D4).
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_assistente\observer\quiz_observer::attempt_submitted',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\question_manually_graded',
        'callback' => '\local_assistente\observer\quiz_observer::question_manually_graded',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_regraded',
        'callback' => '\local_assistente\observer\quiz_observer::attempt_regraded',
        'internal' => false,
    ],
    // Performance of a student: a grade given, changed, recalculated or removed (T079; DEC-37 D1).
    [
        'eventname' => '\core\event\user_graded',
        'callback' => '\local_assistente\observer\grade_observer::user_graded',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\grade_deleted',
        'callback' => '\local_assistente\observer\grade_observer::grade_deleted',
        'internal' => false,
    ],
    // Analysis of a forum: a message created, edited or deleted, a discussion deleted (T083; DEC-03, DEC-38).
    [
        'eventname' => '\mod_forum\event\discussion_created',
        'callback' => '\local_assistente\observer\forum_observer::discussion_created',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_forum\event\post_created',
        'callback' => '\local_assistente\observer\forum_observer::post_created',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_forum\event\post_updated',
        'callback' => '\local_assistente\observer\forum_observer::post_updated',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_forum\event\post_deleted',
        'callback' => '\local_assistente\observer\forum_observer::post_deleted',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_forum\event\discussion_deleted',
        'callback' => '\local_assistente\observer\forum_observer::discussion_deleted',
        'internal' => false,
    ],
    // Participation for the frequency: an assignment sent, the completion of a module (T089; DEC-39 D2). Grades and posts: the observers above.
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => '\local_assistente\observer\participation_observer::assessable_submitted',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_assistente\observer\participation_observer::completion_updated',
        'internal' => false,
    ],
    // A course was deleted: the data of the plugin of that course go (T103; DEC-42 D2). The event is the one triggered after Moodle finished.
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_assistente\observer\course_observer::course_deleted',
        'internal' => false,
    ],
];
