<?php
// This file is part of the Banner/LMB plugin for Moodle - http://moodle.org/
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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());



$PAGE->set_context(context_system::instance());
$PAGE->set_url('/enrol/lmb/tools/');

$nav = array();
$nav[0] = array('name' => 'Admin', 'link' => '../../../'.$CFG->admin.'/index.php', 'type' => '');
$nav[1] = array('name' => 'LMB', 'link' => '../../../'.$CFG->admin.'/settings.php?section=enrolsettingslmb', 'type' => '');
$nav[2] = array('name' => 'Tools', 'link' => './index.php', 'type' => 'title');


$PAGE->set_title("$SITE->shortname: ".get_string('enrolments', 'enrol'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(false);
$PAGE->navbar->add('Admin', null, null, navigation_node::TYPE_CUSTOM, new moodle_url('../../../'.$CFG->admin.'/index.php'));
$PAGE->navbar->add('LMB', null, null, navigation_node::TYPE_CUSTOM,
        new moodle_url($CFG->admin.'/settings.php?section=enrolsettingslmb'));
$PAGE->navbar->add('Tools', null, null, navigation_node::TYPE_CUSTOM, new moodle_url('/index.php'));

echo $OUTPUT->header();

echo $OUTPUT->box_start();

print '<a href="../importnow.php">Import XML File Now</a> - Import the contents of the XML File now<br>';
print '<a href="extractprocess.php">Import XML Folder Now</a> - Import the contents of the XML Folder now<br>';
print '<a href="lmbstatus.php">LMB Status</a> - Information about the last time the LMB interface has received a message<br>';
print '<a href="reprocessenrolments.php">Reprocess Enrolments</a> - Reprocess enrolments that failed to process<br>';
print '<a href="prunelmbtables.php">Prune LMB Tables</a> - Remove records for a semester from Banner/LMB Module tables.<br>';

echo $OUTPUT->box_end();


echo $OUTPUT->footer();


