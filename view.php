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
 * Prints a particular instance of groupalloc
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_groupalloc
 * @copyright  2017 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace groupalloc with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$groupallocid = optional_param('g', 0, PARAM_INT);  // Groupalloc instance ID - it should be named as the first character of the module.
if ($groupallocid) {
    list($course, $cm) = get_course_and_cm_from_instance($groupallocid, 'groupalloc');
} else {
    $cmid = required_param('id', PARAM_INT); // Course_module ID.
    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'groupalloc');
}

require_login($course, true, $cm);

$groupalloc = $PAGE->activityrecord;

\mod_groupalloc\event\course_module_viewed::create_from_cm($cm, $course, $groupalloc)->trigger();

// Print the page header.

$PAGE->set_url('/mod/groupalloc/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($groupalloc->name));
$PAGE->set_heading(format_string($course->fullname));

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('groupalloc-'.$somevar);
 */

$main = new mod_groupalloc_main($cm, $groupalloc);
$main->process_nonjs_actions();

// Output starts here.
echo $OUTPUT->header();

// Conditions to show the intro can change to look for own settings or whatever.
if ($groupalloc->intro) {
    echo $OUTPUT->box(format_module_intro('groupalloc', $groupalloc, $cm->id), 'generalbox mod_introbox', 'groupallocintro');
}

// Replace the following lines with you own code.
echo $OUTPUT->heading(format_string($groupalloc->name));

// Create new group form.
$cancreateempty = $main->can_create_empty_group();
$cancreateandjoin = $main->can_create_and_join_group();
if ($cancreateempty || $cancreateandjoin) {
    $sesskey = sesskey();
    $buttons = '';
    if ($cancreateempty) {
        $buttons .= "<input type=submit name=createempty value=\"Create empty group\">";
    }
    if ($cancreateandjoin) {
        $buttons .= "<input type=submit name=createjoin value=\"Create and join\">";
    }
    $url = $PAGE->url;
    echo <<<EOT
<form method=POST action="$url">
<input type=hidden name=sesskey value="$sesskey">
Create new group:
<input type=text name=newgroupname>
$buttons
</form>
EOT;

}

$groups = $main->get_groups();
if ($groups) {
    foreach ($groups as $group) {
        echo "<h4>".$group->get_formatted_name()."</h4>";
        foreach ($group->get_members() as $user) {
            echo '<p>'.fullname($user);
            if ($user->id == $USER->id && $main->can_leave_group($group)) {
                $leaveurl = new moodle_url($PAGE->url, ['sesskey' => sesskey(), 'leavegroup' => $group->get_id()]);
                echo ' '.html_writer::link($leaveurl, 'Leave');
            }
            echo '</p>';
        }
        if ($main->can_join_group($group)) {
            $joinurl = new moodle_url($PAGE->url, ['sesskey' => sesskey(), 'joingroup' => $group->get_id()]);
            echo '<p>'.html_writer::link($joinurl, 'Join group').'</p>';
        }
    }
}

// Finish the page.
echo $OUTPUT->footer();
