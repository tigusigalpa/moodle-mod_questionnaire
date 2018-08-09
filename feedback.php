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

// This page prints a particular instance of questionnaire.

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

$id = required_param('id', PARAM_INT);    // Course module ID.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.

if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", ["id" => $cm->course])) {
    print_error('coursemisconf');
}

if (! $questionnaire = $DB->get_record("questionnaire", ["id" => $cm->instance])) {
    print_error('invalidcoursemodule');
}

// Needed here for forced language courses.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/feedback.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}
$questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

// Add renderer and page objects to the questionnaire object for display use.
$questionnaire->add_renderer($PAGE->get_renderer('mod_questionnaire'));
$questionnaire->add_page(new \mod_questionnaire\output\feedbackpage());

$SESSION->questionnaire->current_tab = 'feedback';

if (!$questionnaire->capabilities->editquestions) {
    print_error('nopermissions', 'error', 'mod:questionnaire:editquestions');
}

$feedbacksections = $DB->get_records('questionnaire_fb_sections', ['survey_id' => $questionnaire->sid]);
// Get all questions that are valid feedback questions.
$validquestions = [];
foreach ($questionnaire->questions as $question) {
    if ($question->valid_feedback()) {
        $validquestions[$question->id] = $question->name;
    }
}
$customdata = new stdClass();
$customdata->feedbacksections = $feedbacksections;
$customdata->validquestions = $validquestions;

$feedbackform = new \mod_questionnaire\feedback_form('feedback.php', $customdata);
$sdata = clone($questionnaire->survey);
$sdata->sid = $questionnaire->survey->id;
$sdata->id = $cm->id;

$draftideditor = file_get_submitted_draft_itemid('feedbacknotes');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_questionnaire', 'feedbacknotes',
    $sdata->sid, ['subdirs' => true], $questionnaire->survey->feedbacknotes);
$sdata->feedbacknotes = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$feedbackform->set_data($sdata);

if ($feedbackform->is_cancelled()) {
    redirect ($CFG->wwwroot.'/mod/questionnaire/view.php?id='.$questionnaire->cm->id, '');
}

if ($settings = $feedbackform->get_data()) {
    // Because formslib doesn't support 'numeric' or 'image' inputs, the results won't show up in the $feedbackform object.
    $fullform = data_submitted();
    if (isset($settings->feedbacksettingsbutton)) {
        if (isset ($settings->feedbackscores)) {
            $sdata->feedbackscores = $settings->feedbackscores;
        } else {
            $sdata->feedbackscores = 0;
        }

        if (isset ($settings->feedbacknotes)) {
            $sdata->fbnotesitemid = $settings->feedbacknotes['itemid'];
            $sdata->fbnotesformat = $settings->feedbacknotes['format'];
            $sdata->feedbacknotes  = $settings->feedbacknotes['text'];
            $sdata->feedbacknotes  = file_save_draft_area_files($sdata->fbnotesitemid, $context->id, 'mod_questionnaire',
                'feedbacknotes', $sdata->id, ['subdirs' => true], $sdata->feedbacknotes);
        } else {
            $sdata->feedbacknotes = '';
        }

        if (isset ($settings->feedbacksections)) {
            $sdata->feedbacksections = $settings->feedbacksections;
            $usergraph = get_config('questionnaire', 'usergraph');
            if ($usergraph) {
                if ($settings->feedbacksections == 1) {
                    $sdata->chart_type = $settings->chart_type_global;
                } else if ($settings->feedbacksections == 2) {
                    $sdata->chart_type = $settings->chart_type_two_sections;
                } else if ($settings->feedbacksections > 2) {
                    $sdata->chart_type = $settings->chart_type_sections;
                }
            }
        } else {
            $sdata->feedbacksections = '';
        }
        $sdata->courseid = $settings->courseid;
        if (!($sid = $questionnaire->survey_update($sdata))) {
            print_error('couldnotcreatenewsurvey', 'questionnaire');
        } else {
            $redirecturl = $CFG->wwwroot . '/mod/questionnaire/feedback.php?id=' . $questionnaire->cm->id;
            redirect($redirecturl, get_string('settingssaved', 'questionnaire'));

            // Delete existing section and feedback records for this questionnaire if any were previously set and None are wanted now
            // or Global feedback is now wanted.
            if ($sdata->feedbacksections == 0 || ($questionnaire->survey->feedbacksections > 1 && $sdata->feedbacksections == 1)) {
                if ($feedbacksections = $DB->get_records('questionnaire_fb_sections', ['survey_id' => $sid], '', 'id')) {
                    foreach ($feedbacksections as $key => $feedbacksection) {
                        $DB->delete_records('questionnaire_feedback', ['section_id' => $key]);
                    }
                    $DB->delete_records('questionnaire_fb_sections', ['survey_id' => $sid]);
                }
            }
        }
    } else if (isset($settings->addnewsection)) {

    } else if (isset($fullform->editsection)) {
        $url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/fbsettings.php',
            ['id' => $cm->id, 'sectionid' => key($fullform->editsection)]);
        redirect($url);

    } else if (isset($settings->deletesection)) {

    } else {
        foreach ($feedbacksections as $feedbacksection) {
            if (isset($settings->{'savesection' . $feedbacksection->id})) {
                $scorecalculation = [];
                // Check for added question.
                $addquestion = 'addquestion_' . $feedbacksection->id;
                if (isset($settings->$addquestion) && ($settings->$addquestion != 0)) {
                    $scorecalculation[$settings->$addquestion] = 0;
                }
                // Get all current asigned questions.
                foreach ($validquestions as $qid => $question) {
                    if (isset($fullform->{'weight|' . $qid . '|' . $feedbacksection->id})) {
                        $scorecalculation[$qid] = $fullform->{'weight|' . $qid . '|' . $feedbacksection->id};
                    }
                }
                // Update the section with question weights.
                $newscore = serialize($scorecalculation);
                $DB->set_field('questionnaire_fb_sections', 'scorecalculation', $newscore, ['id' => $feedbacksection->id]);
                $feedbacksections[$feedbacksection->id]->scorecalculation = $newscore;
                break;
            }
        }
    }
    $feedbackform = new \mod_questionnaire\feedback_form('feedback.php', $customdata);
}

// Print the page header.
$PAGE->set_title(get_string('editingfeedback', 'questionnaire'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'questionnaire'));
echo $questionnaire->renderer->header();
require('tabs.php');
$questionnaire->page->add_to_page('formarea', $feedbackform->render());
echo $questionnaire->renderer->render($questionnaire->page);
echo $questionnaire->renderer->footer($course);
