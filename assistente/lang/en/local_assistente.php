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
 * English language strings for local_assistente (required package, DEC-46).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['assistente:correctcriteria'] = 'Correct criteria of a course already saved by someone else (DEC-45 D2)';
$string['assistente:manage'] = 'Configure the teaching assistant of a course';
$string['assistente:view'] = 'View the teaching assistant of a course';
$string['pluginname'] = 'Teaching assistant';
$string['task_process_queue'] = 'Process the teaching assistant queue';
$string['task_detect_quiz_close'] = 'Detect closed questionnaires';
$string['task_detect_reference_dates'] = 'Detect reference dates reached';
$string['task_report_dispatcher'] = 'Generate the due versions of the reports';
$string['task_reconcile_material_versions'] = 'Reconcile the material versions left behind by a failure (completed to current, abandoned pending to failed)';

// Privacy API (T104; DEC-34 D3, DEC-42, DEC-56).
$string['privacy:metadata:openrouter'] = 'OpenRouter, the external service of the language model used to write summaries and syntheses. By default the plugin asks the provider not to retain or collect the data (Zero Data Retention policy: zdr and data_collection deny in every request), but this has not been validated as zero retention. The site administrator can turn this request off in the plugin settings; when it is off, the provider may retain or use the data sent. The provider keeps operational metadata of each call (model, tokens, latency and cost), without content, even under this policy.';
$string['privacy:metadata:openrouter:model'] = 'The identifier of the model configured by the administrator.';
$string['privacy:metadata:openrouter:context'] = 'Texts of materials, texts of forum messages written by the participants (with the generic role of the author, teacher or student) and aggregated results, with no identifier, user name or e-mail of a student. The text of the messages may contain names written by their own authors, which the plugin does not remove (DEC-34, out of scope).';
$string['privacy:metadata:openrouter:instruction'] = 'The instruction for the task, in the language of the course.';
$string['privacy:path'] = 'Teaching assistant';
$string['privacy:path:performance'] = 'Performance';
$string['privacy:path:frequency'] = 'Frequency';
$string['privacy:path:criteria'] = 'Criteria';
$string['privacy:path:snapshots'] = 'Entries in snapshots';
$string['privacy:metadata:local_assistente_performance'] = 'Grade of a student in an activity, in percent, in each analysis of performance.';
$string['privacy:metadata:local_assistente_performance:student_id'] = 'The student.';
$string['privacy:metadata:local_assistente_performance:activity_id'] = 'The course module of the activity.';
$string['privacy:metadata:local_assistente_performance:grade'] = 'The grade in percent.';
$string['privacy:metadata:local_assistente_frequency'] = 'Frequency of a student in a section of the course, in each analysis of frequency.';
$string['privacy:metadata:local_assistente_frequency:student_id'] = 'The student.';
$string['privacy:metadata:local_assistente_frequency:section_id'] = 'The section.';
$string['privacy:metadata:local_assistente_frequency:total_activities'] = 'Activities that entered the calculation.';
$string['privacy:metadata:local_assistente_frequency:participating_activities'] = 'Activities in which the student took part.';
$string['privacy:metadata:local_assistente_frequency:percentage'] = 'The frequency in percent.';
$string['privacy:metadata:local_assistente_frequency_activity'] = 'Activities of the calculation of the frequency of a student, and whether the student took part.';
$string['privacy:metadata:local_assistente_frequency_activity:activity_id'] = 'The course module of the activity.';
$string['privacy:metadata:local_assistente_frequency_activity:participated'] = 'Whether the student took part.';
$string['privacy:metadata:local_assistente_frequency_activity:reference_date'] = 'The reference date of the activity.';
$string['privacy:metadata:local_assistente_criteria_result'] = 'Result of the criteria of the course for a student: below, meets or no data in activities, forum and frequency.';
$string['privacy:metadata:local_assistente_criteria_result:student_id'] = 'The student.';
$string['privacy:metadata:local_assistente_criteria_result:activity_status'] = 'The result for the activities.';
$string['privacy:metadata:local_assistente_criteria_result:forum_status'] = 'The result for the forum.';
$string['privacy:metadata:local_assistente_criteria_result:frequency_status'] = 'The result for the frequency.';
$string['privacy:metadata:local_assistente_processing_context'] = 'Snapshot of what a processing used. It holds, for the students, their identifier, whether and when they accessed the course and individual values.';
$string['privacy:metadata:local_assistente_processing_context:context_json'] = 'The snapshot, with the entries of the students.';
$string['privacy:metadata:local_assistente_event'] = 'Event of the queue. The payload of the events of a student holds the identifier of the student.';
$string['privacy:metadata:local_assistente_event:payload'] = 'The payload, with the user.';
$string['privacy:metadata:local_assistente_event:response'] = 'The result of the processing, which may name the student in a failure.';
$string['privacy:metadata:local_assistente_processing_attempt'] = 'Attempt to process an event.';
$string['privacy:metadata:local_assistente_processing_attempt:response'] = 'The result of the attempt, which may name the student in a failure.';
$string['privacy:metadata:local_assistente_analysis'] = 'Analysis made by the plugin. The summary of the analysis of a forum is generated from messages and may name a student.';
$string['privacy:metadata:local_assistente_analysis:summary'] = 'The summary, a generated text.';
$string['privacy:metadata:local_assistente_forum_difficulty'] = 'Difficulties of the students found in a forum, a generated text that may name a student.';
$string['privacy:metadata:local_assistente_forum_difficulty:description'] = 'The description of the difficulty.';
$string['privacy:metadata:local_assistente_pedagogical_synthesis'] = 'Pedagogical synthesis of a section, a generated text that may name a student.';
$string['privacy:metadata:local_assistente_pedagogical_synthesis:content'] = 'The text of the synthesis.';
$string['privacy:metadata:local_assistente_material_version'] = 'Version of a teaching material with its generated summary.';
$string['privacy:metadata:local_assistente_material_version:summary'] = 'The summary of the material.';
$string['privacy:metadata:local_assistente_report_version'] = 'Version of the report of the course, as a snapshot.';
$string['privacy:metadata:local_assistente_report_version:snapshot_json'] = 'The snapshot of the report.';
$string['query:no_data'] = 'no data';
$string['query:error'] = 'technical error';
$string['query:no_materials'] = 'no materials';
$string['query:source_removed'] = 'source removed';
$string['query:not_enabled'] = 'assistant not enabled';
$string['query:criteria_below'] = 'below';
$string['query:criteria_meets'] = 'meets';
$string['query:access_yes'] = 'accessed the course';
$string['query:access_no'] = 'did not access the course';
$string['query:access_last'] = 'last access: {$a}';
$string['task:retry_student_erasure'] = 'Retry the erasure of the personal data of a student';
$string['task:redeliver_event'] = 'Deliver again an event that could not be created because a lock was busy';
$string['event:student_erasure_deferred'] = 'Erasure of a student deferred';
$string['event:student_erasure_completed'] = 'Erasure of a student completed';
$string['event:student_erasure_stopped'] = 'Erasure of a student stopped';
$string['form:enabled'] = 'Enable the assistant in this course';
$string['form:activity_criterion'] = 'Activity criterion (%)';
$string['form:forum_criterion'] = 'Require participation in the forums';
$string['form:frequency_criterion'] = 'Attendance criterion (%)';
$string['form:criteria_readonly'] = 'The criteria are saved. Only an administrator can correct them, and a correction applies to new evaluations only.';
$string['form:error_percentage'] = 'Enter a number from 0 to 100 with at most three decimals.';
$string['form:filter_all'] = 'All';
$string['form:filter_group'] = 'Group';
$string['form:filter_section'] = 'Section';
$string['form:filter_version'] = 'Report version';
$string['form:filter_apply'] = 'Apply filters';
$string['form:filter_error'] = 'Choose one of the options of the list.';
$string['nav:assistant'] = 'Assistant';
$string['col:name'] = 'Student';
$string['col:course_total'] = 'Course total';
$string['col:activities'] = 'Activities';
$string['col:grade'] = 'Grade';
$string['col:status'] = 'Status';
$string['col:course_access'] = 'Course access';
$string['col:sections'] = 'Sections';
$string['col:criteria'] = 'Criteria';
$string['col:activity'] = 'Activities';
$string['col:forum'] = 'Forum';
$string['col:frequency'] = 'Attendance';
$string['col:quiz'] = 'Questionnaire';
$string['col:coverage'] = 'Coverage';
$string['col:metrics'] = 'Metrics';
$string['col:questions'] = 'Questions';
$string['col:summary'] = 'Summary';
$string['col:difficulties'] = 'Difficulties';
$string['col:description'] = 'Description';
$string['col:material'] = 'Material';
$string['col:version'] = 'Version';
$string['col:section'] = 'Section';
$string['col:content'] = 'Content';
$string['col:type'] = 'Type';
$string['col:created_at'] = 'Created';
$string['col:generated_at'] = 'Generated';
$string['col:current'] = 'Current';
$string['col:source'] = 'Source';
$string['col:complexity'] = 'Complexity';
$string['col:text'] = 'Text';
$string['tab:grades'] = 'Grades';
$string['tab:frequency'] = 'Attendance';
$string['tab:below_criteria'] = 'Below the criteria';
$string['tab:quiz_metrics'] = 'Questionnaires';
$string['tab:forums'] = 'Forums';
$string['tab:materials'] = 'Materials';
$string['tab:synthesis'] = 'Synthesis';
$string['tab:report'] = 'Report';
$string['tab:history'] = 'History';
$string['tab:config'] = 'Configuration';
$string['report:current_version'] = 'Current version: {$a}';
$string['report:history_link'] = 'See the history of the report';
$string['report:none'] = 'No report has been generated yet.';
$string['block:grades'] = 'Grades';
$string['block:frequency'] = 'Attendance';
$string['block:below_criteria'] = 'Below the criteria';
$string['block:forums'] = 'Forums';
$string['block:materials'] = 'Materials';
$string['block:synthesis'] = 'Synthesis';
$string['block:quiz_metrics'] = 'Questionnaires';
$string['block:material_summaries'] = 'Materials';
$string['block:section_synthesis'] = 'Synthesis';
$string['form:filter_from'] = 'Executed from';
$string['form:filter_to'] = 'Executed until';
$string['form:filter_period_error'] = 'The last day must not be before the first day.';
$string['filter:no_results'] = 'No version matches the filters.';
$string['form:criteria_immutable'] = 'The criteria are saved and only an administrator can correct them. Nothing was changed.';
$string['settings:ytdlppath'] = 'Path of the yt-dlp program';
$string['settings:ytdlppath_desc'] = 'Full path of the yt-dlp executable (default /usr/local/bin/yt-dlp), used to read the title, description and subtitles of YouTube videos. No YouTube API or key is used. Leave it empty, or point it to a missing file, and YouTube materials have no content. yt-dlp depends on the YouTube site and may stop working until it is updated.';
$string['settings:llmurl'] = 'LLM API URL';
$string['settings:llmurl_desc'] = 'Complete endpoint of the Chat Completions API, used exactly as typed (for OpenRouter: https://openrouter.ai/api/v1/chat/completions). It must use https and have no user or password. There is no default value. Destinations are checked by the Moodle block list (curlsecurityblockedhosts); the default list does not cover the whole 169.254.0.0/16 range, private and link-local IPv6 (fc00::/7, fe80::/10) or other cloud metadata addresses (for example 100.100.100.200); optionally add them to that list.';
$string['settings:llmurl_invalid'] = 'The URL must use https and must not contain a user or a password.';
$string['settings:llmmodel'] = 'LLM model';
$string['settings:llmmodel_desc'] = 'Identifier of the model, as the provider names it. There is no default value.';
$string['settings:llmzdr'] = 'Require Zero Data Retention (ZDR)';
$string['settings:llmzdr_desc'] = 'When checked (the default), every request asks the provider to route only to endpoints with Zero Data Retention that do not collect data (provider.zdr = true and provider.data_collection = deny), and is never repeated without them; if no endpoint qualifies, the call fails. When unchecked, those parameters are not sent: the provider and the model endpoint may keep or use the course content that is sent (texts of materials, texts of forum messages written by the participants, and aggregated results; never identifiers, user names or e-mails of students, although the text of the messages may contain names written by their own authors). This is a privacy decision of the site administrator.';
$string['settings:llmapikey'] = 'LLM API key';
$string['settings:llmapikey_desc'] = 'Key of the provider API. It is saved encrypted and is never shown again after saving; leave it as is to keep the saved key. There is no default value.';
