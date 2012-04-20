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
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_url('/enrol/lmb/tools/extractprocess.php');
require_once('../lib.php');



$config = enrol_lmb_get_config();

$term = null;
$matches = array();

$nav[0] = array('name' => 'Admin', 'link' => '../../../'.$CFG->admin.'/index.php', 'type' => '');
$nav[1] = array('name' => 'LMB', 'link' => '../../../'.$CFG->admin.'/settings.php?section=enrolsettingslmb', 'type' => '');
$nav[2] = array('name' => 'Tools', 'link' => './index.php', 'type' => '');
$nav[3] = array('name' => 'XML Folder Process', 'link' => '', 'type' => 'title');

print_header("$SITE->shortname: ".get_string('enrolments', 'enrol'), $SITE->fullname,
              build_navigation($nav));

echo $OUTPUT->box_start();

// Check to see if a folder is set in config.
if (!isset($config->bannerxmlfolder) || !$config->bannerxmlfolder) {
    die();
}

$enrol = new enrol_lmb_plugin();

print("<pre>");

$enrol->process_folder(null);

print("</pre>");

echo $OUTPUT->box_end();

echo $OUTPUT->footer();

