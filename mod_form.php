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
 * The main groupalloc configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_groupalloc
 * @copyright  2017 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_groupalloc
 * @copyright  2017 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_groupalloc_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $COURSE;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('groupallocname', 'groupalloc'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Select grouping.
        $groupings = groups_get_all_groupings($COURSE->id);
        $choices = array_map(function ($grouping) {
            return format_string($grouping->name);
        }, $groupings);
        $context = context_course::instance($COURSE->id);
        $canmanagegroups = has_capability('moodle/course:managegroups', $context);
        if (empty($this->_cm) && $canmanagegroups) {
            $choices = [-1 => 'Create new grouping'] + $choices; // TODO lang
        }
        $choices = [0 => 'No grouping'] + $choices; // TODO lang

        $mform->addElement('select', 'usegroupingid', get_string('grouping', 'group'), $choices);

        if (empty($this->_cm) && $canmanagegroups) {
            $mform->addElement('text', 'newgroupingname', 'New grouping name', $choices); // TODO lang
            $mform->disabledIf('newgroupingname', 'usegroupingid', 'ne', -1);
            $mform->setType('newgroupingname', PARAM_TEXT);
        }

        if ($canmanagegroups) {
            $groupingslink = html_writer::link(new moodle_url('/group/groupings.php', ['id' => $COURSE->id]), 'Manage groupings'); // TODO lang
            $mform->addElement('static', 'managegroupings', '', $groupingslink);
        }

        // Different options
        $choices = [0 => 'Unlimited'] + array_combine([1,2,3,4,5], [1,2,3,4,5]); // TODO other choices?
        $mform->addElement('select', 'config_maxgroups', 'Maximum groups per person', $choices); // TODO lang
        $mform->setDefault('config_maxgroups', 1);

        $mform->addElement('text', 'config_minmembers', 'Minimum members in group'); // TODO lang
        $mform->setDefault('config_minmembers', 0);
        $mform->addRule('config_minmembers', null, 'numeric', null, 'client');
        $mform->setType('config_minmembers', PARAM_INT);
        // TODO: add help that members will not be allowed to leave if the number of members is less than minimum.

        $mform->addElement('advcheckbox', 'config_autoremove', '', 'Automatically remove empty groups'); // TODO lang
        // TODO: add help that only groups created from this module will be removed.
        $mform->setDefault('config_autoremove', 1);

        $mform->addElement('text', 'config_maxmembers', 'Maximum members in group'); // TODO lang
        // TODO: add help that 0 or empty value means no limit.
        $mform->addRule('config_maxmembers', null, 'numeric', null, 'client');
        $mform->setType('config_maxmembers', PARAM_INT);

        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Only available on moodleform_mod.
     *
     * @param array $default_values passed by reference
     */
    function data_preprocessing(&$default_values){
        parent::data_preprocessing($default_values);
        if (!empty($default_values['config'])) {
            $values = json_decode($default_values['config'], true);
            foreach ($values as $key => $value) {
                $default_values['config_' . $key] = $value;
            }
            unset($default_values['config']);
        }
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data !== null) {
            $config = [];
            foreach ($data as $key => $value) {
                if (preg_match('/^config_(.*)$/', $key, $matches)) {
                    $config[$matches[1]] = $value;
                    unset($data->$key);
                }
            }
            $data->config = json_encode($config);
        }
        return $data;
    }

    // form verification
    function validation($data, $files) {
        global $COURSE, $DB, $CFG;
        $errors = parent::validation($data, $files);
        $mform = $this->_form;

        if ($data['config_minmembers'] > $data['config_maxmembers']) {
            $errors['config_maxmembers'] = 'Can not be less than minmembers'; // TODO lang
        }

        // TODO validate newgroupingname is specified and valid when usegroupingid == -1

        return $errors;
    }
}
