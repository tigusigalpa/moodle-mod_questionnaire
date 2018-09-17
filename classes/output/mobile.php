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
 * Contains the mobile output class for the custom certificate.
 *
 * @package   mod_questionnaire
 * @copyright 2018 Igor Sazonov <sovletig@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Mobile output class for the questionnaire.
 *
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Returns the initial page when viewing the activity for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and other data
     */
    public static function mobile_view_activity($args) {
        global $OUTPUT, $USER, $CFG;

        $args = (object) $args;
        $cmid = $args->cmid;
        $pagenum = (isset($args->pagenum) && !empty($args->pagenum)) ? intval($args->pagenum) : 1;

        // Capabilities check.
        $cm = get_coursemodule_from_id('questionnaire', $cmid);
        $context = \context_module::instance($cmid);
        self::require_capability($cm, $context, 'mod/questionnaire:view');

        // Set some variables we are going to be using.
        $questionnaire = get_questionnaire_data($cmid, $USER->id);
        $jsfilepath = $CFG->wwwroot . '/mod/questionnaire/javascript/mobile.js';

        $data = [
            'questionnaire' => $questionnaire,
            'cmid' => $cmid,
            'courseid' => intval($cm->course),
            'pagenum' => $pagenum,
            'userid' => $USER->id,
            'nextpage' => 0,
            'prevpage' => 0
        ];
        $data['completed'] = (isset($questionnaire['response']['complete']) && $questionnaire['response']['complete'] == 'y') ? 1 : 0;
        $data['complete_userdate'] = (isset($questionnaire['response']['complete']) && $questionnaire['response']['complete'] == 'y') ? userdate($questionnaire['response']['submitted']) : '';
        if (isset($questionnaire['questions'][$pagenum])) {
            $i = 0;
            foreach ($questionnaire['questions'][$pagenum] as $questionid => $choices) {
                if (isset($questionnaire['questionsinfo'][$pagenum][$questionid]) && !empty($questionnaire['questionsinfo'][$pagenum][$questionid])) {
                    $data['questions'][$pagenum][$i]['info'] = $questionnaire['questionsinfo'][$pagenum][$questionid];
                    if ($data['questions'][$pagenum][$i]['info']['required'] == 'n') {
                        unset($data['questions'][$pagenum][$i]['info']['required']);
                    }
                    $ii = 0;
                    foreach ($choices as $k => $v) {
                        $data['questions'][$pagenum][$i]['choices'][$ii] = (array) $v;
                        $ii++;
                    }
                    if (count($choices) == 1) {
                        $data['questions'][$pagenum][$i]['value'] = $data['questions'][$pagenum][$i]['choices'][0]['value'];
                    }
                    $i++;
                }
            }
            if (isset($data['questions'][$pagenum]) && !empty($data['questions'][$pagenum])) {
                $i = 0;
                foreach ($data['questions'][$pagenum] as $arr) {
                    $data['pagequestions'][$i] = $arr;
                    $i++;
                }
            }
            if (isset($questionnaire['questions'][$pagenum+1]) && !empty($questionnaire['questions'][$pagenum+1])) {
                $data['nextpage'] = $pagenum+1;
            }
            if (isset($questionnaire['questions'][$pagenum-1]) && !empty($questionnaire['questions'][$pagenum-1])) {
                $data['prevpage'] = $pagenum-1;
            }
        }

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_questionnaire/mobile_view_activity_page', $data)
                ],
            ],
            'javascript' => file_get_contents($jsfilepath),
            'otherdata' => [
                'fields' => json_encode($questionnaire['fields']),
                'questionsinfo' => json_encode($questionnaire['questionsinfo']),
                'questions' => json_encode($questionnaire['questions']),
                'pagequestions' => json_encode($data['pagequestions']),
                'responses' => json_encode($questionnaire['responses']),
                'pagenum' => $pagenum,
                'nextpage' => $data['nextpage'],
                'prevpage' => $data['prevpage'],
                'completed' => $data['completed'],
                'intro' => $questionnaire['questionnaire']['intro'],
                'string_required' => get_string('required')
            ],
            'files' => null
        ];
    }

    /**
     * Confirms the user is logged in and has the specified capability.
     *
     * @param \stdClass $cm
     * @param \context $context
     * @param string $cap
     */
    protected static function require_capability(\stdClass $cm, \context $context, string $cap) {
        require_login($cm->course, false, $cm, true, true);
        require_capability($cap, $context);
    }
}