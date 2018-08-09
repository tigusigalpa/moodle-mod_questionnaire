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
 * Print the form to manage feedback settings.
 *
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author Joseph Rezeau (based on Quiz by Tim Hunt)
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot.'/mod/questionnaire/lib.php');

class feedback_form extends \moodleform {

    protected $_feedbacks;

    public function definition() {
        global $questionnaire, $DB;

        $defaultsections = get_config('questionnaire', 'maxsections');

        $mform =& $this->_form;

        $feedbacksections = $this->_customdata->feedbacksections;
        $validquestions = $this->_customdata->validquestions;

        // Questionnaire Feedback Sections and Messages.
        if (!empty($validquestions)) {
            $maxsections = min(count($validquestions), $defaultsections);
            $feedbackoptions = array();
            $feedbackoptions[0] = get_string('feedbacknone', 'questionnaire');
            $mform->addElement('header', 'submithdr', get_string('feedbackoptions', 'questionnaire'));
            $feedbackoptions[1] = get_string('feedbackglobal', 'questionnaire');
            for ($i = 2; $i <= $maxsections; ++$i) {
                $feedbackoptions[$i] = get_string('feedbacksections', 'questionnaire', $i);
            }
            $mform->addElement('select', 'feedbacksections', get_string('feedbackoptions', 'questionnaire'), $feedbackoptions);
            $mform->setDefault('feedbacksections', $questionnaire->survey->feedbacksections);
            $mform->addHelpButton('feedbacksections', 'feedbackoptions', 'questionnaire');

            $options = array('0' => get_string('no'), '1' => get_string('yes'));
            $mform->addElement('select', 'feedbackscores', get_string('feedbackscores', 'questionnaire'), $options);
            $mform->addHelpButton('feedbackscores', 'feedbackscores', 'questionnaire');

            // Is the RGraph library enabled at level site?
            $usergraph = get_config('questionnaire', 'usergraph');
            if ($usergraph) {
                $chartgroup = array();
                $charttypes = array(null => get_string('none'),
                    'bipolar' => get_string('chart:bipolar', 'questionnaire'),
                    'vprogress' => get_string('chart:vprogress', 'questionnaire'));
                $chartgroup[] = $mform->createElement('select', 'chart_type_global',
                    get_string('chart:type', 'questionnaire') . ' (' .
                    get_string('feedbackglobal', 'questionnaire') . ')', $charttypes);
                if ($questionnaire->survey->feedbacksections == 1) {
                    $mform->setDefault('chart_type_global', $questionnaire->survey->chart_type);
                }
                $mform->disabledIf('chart_type_global', 'feedbacksections', 'eq', 0);
                $mform->disabledIf('chart_type_global', 'feedbacksections', 'neq', 1);

                $charttypes = array(null => get_string('none'),
                    'bipolar' => get_string('chart:bipolar', 'questionnaire'),
                    'hbar' => get_string('chart:hbar', 'questionnaire'),
                    'rose' => get_string('chart:rose', 'questionnaire'));
                $chartgroup[] = $mform->createElement('select', 'chart_type_two_sections',
                    get_string('chart:type', 'questionnaire') . ' (' .
                    get_string('feedbackbysection', 'questionnaire') . ')', $charttypes);
                if ($questionnaire->survey->feedbacksections > 1) {
                    $mform->setDefault('chart_type_two_sections', $questionnaire->survey->chart_type);
                }
                $mform->disabledIf('chart_type_two_sections', 'feedbacksections', 'neq', 2);

                $charttypes = array(null => get_string('none'),
                    'bipolar' => get_string('chart:bipolar', 'questionnaire'),
                    'hbar' => get_string('chart:hbar', 'questionnaire'),
                    'radar' => get_string('chart:radar', 'questionnaire'),
                    'rose' => get_string('chart:rose', 'questionnaire'));
                $chartgroup[] = $mform->createElement('select', 'chart_type_sections',
                    get_string('chart:type', 'questionnaire') . ' (' .
                    get_string('feedbackbysection', 'questionnaire') . ')', $charttypes);
                if ($questionnaire->survey->feedbacksections > 1) {
                    $mform->setDefault('chart_type_sections', $questionnaire->survey->chart_type);
                }
                $mform->disabledIf('chart_type_sections', 'feedbacksections', 'eq', 0);
                $mform->disabledIf('chart_type_sections', 'feedbacksections', 'eq', 1);
                $mform->disabledIf('chart_type_sections', 'feedbacksections', 'eq', 2);

                $mform->addGroup($chartgroup, 'chartgroup',
                    get_string('chart:type', 'questionnaire'), null, false);
                $mform->addHelpButton('chartgroup', 'chart:type', 'questionnaire');
            }
            $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext' => true);
            $mform->addElement('editor', 'feedbacknotes', get_string('feedbacknotes', 'questionnaire'), null, $editoroptions);
            $mform->setType('feedbacknotes', PARAM_RAW);
            $mform->setDefault('feedbacknotes', $questionnaire->survey->feedbacknotes);
            $mform->addHelpButton('feedbacknotes', 'feedbacknotes', 'questionnaire');

            $mform->addElement('hidden', 'id', 0);
            $mform->setType('id', PARAM_INT);
            $mform->addElement('hidden', 'sid', 0);
            $mform->setType('sid', PARAM_INT);
            $mform->addElement('hidden', 'courseid', '');
            $mform->setType('courseid', PARAM_RAW);

            $mform->addElement('submit', 'feedbacksettingsbutton', get_string('savesettings', 'questionnaire'));

            // Add new section.
            $mform->addElement('header', 'sections', get_string('feedbacksectionsselect', 'questionnaire'));
            $addnewsectionarray = [];
            $addnewsectionarray[] = $mform->createElement('text', 'sectionlabel',
                get_string('feedbacksectionlabel', 'questionnaire'));
            $mform->setType('sectionlabel', PARAM_TEXT);
            $addnewsectionarray[] = $mform->createElement('submit', 'addnewsection', get_string('addnewsection', 'questionnaire'));
            $mform->addGroup($addnewsectionarray);

            // Sections.
            $esrc = $questionnaire->renderer->image_url('t/edit');
            $rsrc = $questionnaire->renderer->image_url('t/delete');
            $stredit = get_string('edit', 'questionnaire');
            $strremove = get_string('remove', 'questionnaire');
            foreach ($feedbacksections as $feedbacksection) {
                $eextra = ['value' => $feedbacksection->id, 'alt' => $stredit, 'title' => $stredit];
                $eextra['style'] = 'margin-top:-5em;';
                $rextra = ['value' => $feedbacksection->id, 'alt' => $strremove, 'title' => $strremove];
                $rextra['style'] = 'margin-top:-5em;';
                $mform->addElement('header', 'fbsection_' . $feedbacksection->id, $feedbacksection->sectionlabel);
                $sectionactions = [];
                $sectionactions[] = $mform->createElement('image', 'editsection['.$feedbacksection->id.']', $esrc, $eextra);
                $sectionactions[] = $mform->createElement('image', 'deletesection['.$feedbacksection->id.']', $rsrc, $rextra);
                $mform->addGroup($sectionactions, '', get_string('questionsinsection', 'questionnaire'));
                if (!empty($feedbacksection->scorecalculation)) {
                    $scorecalculation = unserialize($feedbacksection->scorecalculation);
                    // Merge arrays maintaining keys.
                    $qvalid = $validquestions;
                    foreach ($scorecalculation as $qid => $score) {
                        unset($qvalid[$qid]);
                        $questionactions = [];
                        $weight = '<input type="number" style="width: 4em;" id="weight' . $qid . "_" . $feedbacksection->id . '" ' .
                                'name="weight|' . $qid . '|' . $feedbacksection->id . '" min="0.0" max="1.0" step="0.01" ' .
                                'value="'. $score .'">';
                        $questionactions[] = $mform->createElement('html', $weight);
                        $rextra['value'] = $feedbacksection->id . '_' . $qid;
                        unset($rextra['style']);
                        $questionactions[] = $mform->createElement('image', 'removequestion_' . $feedbacksection->id . '_' . $qid,
                            $rsrc, $rextra);

                        $mform->addGroup($questionactions, '', $questionnaire->questions[$qid]->name);
                    }
                    if (!empty($qvalid)) {
                        // Merge arrays maintaining keys.
                        $qvalid = [0 => get_string('addquestion', 'questionnaire')] + $validquestions;
                        $qselect = [];
                        $qselect[] = $mform->createElement('select', 'addquestion_' . $feedbacksection->id,
                            get_string('addquestiontosection', 'questionnaire'), $qvalid);
                        $qselect[] = $mform->createElement('submit', 'savesection' . $feedbacksection->id,
                            get_string('savesettings', 'questionnaire'));
                        $mform->addGroup($qselect, '', get_string('addquestiontosection', 'questionnaire'));
                    } else {
                        $mform->addElement('submit', 'savesection' . $feedbacksection->id,
                            get_string('savesettings', 'questionnaire'));
                    }
                }
            }
        } else {
            $mform->addElement('html', get_string('feedbackoptions_help', 'questionnaire'));
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}